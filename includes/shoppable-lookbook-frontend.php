<?php
/**
 * Defines the frontend plugin class
 *
 * @link       https://douple.net
 * @since      1.1.4
 * @package    Douple
 * @subpackage Douple/inc
 * @author     Douple <support@douple.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the AJAX add-to-cart button markup for a product marker.
 *
 * Mirrors WooCommerce's loop add-to-cart markup so the core "wc-add-to-cart"
 * script can handle the AJAX add for supported (simple, purchasable) products.
 * Other product types fall back to a link to the product page.
 *
 * @param WC_Product $product The product object.
 * @param array      $options Lookbook options.
 * @return string HTML for the button, or empty string when disabled.
 */
function shoppablelookbook_add_to_cart_button( $product, $options ) {

    if ( empty( $options['cart'] ) || 1 != $options['cart'] || ! $product ) {
        return '';
    }

    // Variable products: JS will enable this button once a variation is chosen.
    if ( $product->get_type() === 'variable' ) {
        return sprintf(
            '<div class="lookbook-cart"><a href="#" class="button lookbook-add-to-cart lookbook-variation-btn" data-parent_id="%d" data-quantity="1" rel="nofollow" aria-disabled="true">%s</a></div>',
            esc_attr( $product->get_id() ),
            esc_html__( 'Add to cart', 'shoppable-lookbook' )
        );
    }

    $can_ajax = $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock();

    $classes = array( 'button', 'lookbook-add-to-cart', 'product_type_' . $product->get_type() );
    if ( $product->is_purchasable() && $product->is_in_stock() ) {
        $classes[] = 'add_to_cart_button';
    }
    if ( $can_ajax ) {
        $classes[] = 'ajax_add_to_cart';
    }

    return sprintf(
        '<div class="lookbook-cart"><a href="%s" data-quantity="1" class="%s" data-product_id="%d" data-product_sku="%s" rel="nofollow" aria-label="%s">%s</a></div>',
        esc_url( $product->add_to_cart_url() ),
        esc_attr( implode( ' ', array_filter( $classes ) ) ),
        esc_attr( $product->get_id() ),
        esc_attr( $product->get_sku() ),
        esc_attr( $product->add_to_cart_text() ),
        esc_attr( $product->add_to_cart_text() ),
        esc_html( $product->add_to_cart_text() )
    );
}

/**
 * Build variation attribute selects for variable products (when Add to Cart is enabled).
 * Embeds minimal variation data as JSON for client-side matching.
 *
 * @param WC_Product $product
 * @param array      $options Lookbook options.
 * @return string
 */
function shoppablelookbook_variation_select( $product, $options ) {
    // Same gate as the Add to cart button: the selects only accompany the
    // button, so they never render when the cart feature is off.
    if ( empty( $options['cart'] ) || 1 != $options['cart'] || ! $product || $product->get_type() !== 'variable' ) {
        return '';
    }
    if ( ! function_exists( 'wc_attribute_label' ) ) {
        return '';
    }

    $attributes = $product->get_variation_attributes();
    if ( empty( $attributes ) ) {
        return '';
    }

    $variation_data = array();
    foreach ( $product->get_available_variations() as $v ) {
        $variation_data[] = array(
            'id'         => $v['variation_id'],
            'attributes' => $v['attributes'],
            'in_stock'   => $v['is_in_stock'],
        );
    }

    $html = '<div class="lookbook-variation" data-variations=\'' . wp_json_encode( $variation_data ) . '\'>';

    foreach ( $attributes as $attribute_name => $options_list ) {
        $attr_key = 'attribute_' . sanitize_title( $attribute_name );
        $label    = wc_attribute_label( $attribute_name );
        $is_tax   = taxonomy_exists( $attribute_name );

        $html .= '<select class="lookbook-variation-select" data-attr="' . esc_attr( $attr_key ) . '" aria-label="' . esc_attr( $label ) . '">';
        $html .= '<option value="">' . esc_html( $label ) . '</option>';

        foreach ( $options_list as $opt ) {
            $name = $opt;
            if ( $is_tax ) {
                $term = get_term_by( 'slug', $opt, $attribute_name );
                if ( $term ) {
                    $name = $term->name;
                }
            }
            $html .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $name ) . '</option>';
        }

        $html .= '</select>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Whether a hex colour is "light" (so it needs dark text on top).
 *
 * @param string $hex Hex colour, e.g. #ffcc00.
 * @return bool
 */
function shoppablelookbook_is_light_color( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( 3 === strlen( $hex ) ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( 6 !== strlen( $hex ) ) {
        return true;
    }
    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );
    // Perceived luminance (0–1).
    $lum = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
    return $lum > 0.6;
}

/**
 * Resolve the marker image URL from the stored attachment ID (or '' if none).
 *
 * @param array $options Lookbook options.
 * @return string
 */
function shoppablelookbook_marker_image_url( $options ) {
    if ( empty( $options['markerimage'] ) ) {
        return '';
    }
    $url = wp_get_attachment_image_url( (int) $options['markerimage'], 'full' );
    return $url ? $url : '';
}

/**
 * Build the marker "grab" element — a custom image when one is set, otherwise
 * the chosen Material icon. Falls back to the icon if the image is missing.
 *
 * @param array  $options    Lookbook options.
 * @param array  $markers    Available icon names.
 * @param string $grab_class Base class (e.g. "lookbook-grab" or "lit-grab").
 * @param string $attrs      Extra (already-escaped) attributes.
 * @return string
 */
function shoppablelookbook_marker_inner( $options, $markers, $grab_class, $attrs = '' ) {
    // Size is applied via the --lb-marker-size CSS variable on an ancestor so
    // it can be capped responsively on mobile (see CSS).
    $img_url = shoppablelookbook_marker_image_url( $options );

    if ( $img_url ) {
        return '<img class="' . esc_attr( $grab_class ) . ' ' . esc_attr( $grab_class ) . '-img" src="' . esc_url( $img_url ) . '" alt="" ' . $attrs . ' />';
    }

    $icon = isset( $markers[ (int) $options['marker'] ] ) ? $markers[ (int) $options['marker'] ] : $markers[0];
    return '<i class="' . esc_attr( $grab_class ) . ' material-icons-round" style="color:' . esc_attr( $options['color'] ) . '" ' . $attrs . '>' . esc_html( $icon ) . '</i>';
}

function shoppablelookbook_shortcode( $atts ) {

    global $wpdb;

    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'shoppablelookbook' );
    $id   = absint( $atts['id'] );
    if ( ! $id ) {
        return '';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbook = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook WHERE id = %d", $id ) );
    if ( ! $lookbook ) {
        return '';
    }

    $image = wp_get_attachment_image_src( $lookbook->media_id, 'full' );
    if ( ! $image ) {
        return '';
    }

    $options  = maybe_unserialize( $lookbook->lookbook_options );
    $products = maybe_unserialize( $lookbook->lookbook_products );
    $markers  = array( 'add_circle', 'favorite', 'local_offer', 'local_mall', 'place', 'star', 'shopping_bag', 'touch_app' );

    // Normalise options with safe defaults.
    $options = wp_parse_args(
        is_array( $options ) ? $options : array(),
        array(
            'name'     => '',
            'layout'   => 0,
            'boxcolor' => 'light',
            'marker'         => 0,
            'color'          => '#000000',
            'price'          => 0,
            'cart'           => 0,
            'boxcustomcolor' => '',
            'contentcolor'   => 'auto',
            'opacity'        => 100,
            'markerimage'    => 0,
            'markersize'     => 30,
            'animation'      => 'pulse',
            'trigger'        => 'click',
        )
    );

    // Enforce the free feature set at render time too (covers rows saved while
    // a Pro licence was active that has since expired).
    $options = shoppablelookbook_apply_free_limits( $options );

    $layout      = (int) $options['layout'];
    $marker_icon = isset( $markers[ (int) $options['marker'] ] ) ? $markers[ (int) $options['marker'] ] : $markers[0];

    $limittop = $limitleft = 200;
    $left = $right = -15;
    $top = 15;
    $bottom = 5;
    if ( $layout == 0 ) {
        $limittop = 110;
        $limitleft = 310;
        if ( wp_is_mobile() ) {
            $limittop = 70;
            $limitleft = 200;
        }
    } else if ( $layout == 1 ) {
        $limittop = 210;
        $limitleft = 210;
        if ( wp_is_mobile() ) {
            $limittop = 145;
            $limitleft = 164;
        }
    } else if ( $layout == 2 ) {
        $limittop = 80;
        $limitleft = 250;
        $top = -40;
        $bottom = 48;
        if ( wp_is_mobile() ) {
            $limittop = 80;
            $limitleft = 180;
            $top = -30;
            $bottom = 37;
            $left = $right = -21;
        }
    } else if ( $layout == 3 ) {
        // Tag: compact pill, no image.
        $limittop = 70;
        $limitleft = 240;
        if ( wp_is_mobile() ) {
            $limittop = 60;
            $limitleft = 180;
        }
    }

    $count = ! empty( $products ) && is_array( $products ) ? count( $products ) : 0;

    // Box colour class + custom colour / opacity via CSS variables.
    $marker_classes = array( 'lookbook-markers', $options['boxcolor'] );
    $marker_style   = '';

    $opacity = isset( $options['opacity'] ) ? (int) $options['opacity'] : 100;
    if ( $opacity >= 0 && $opacity < 100 ) {
        $marker_style .= '--lb-bg-opacity:' . ( $opacity / 100 ) . ';';
    }

    $markersize = isset( $options['markersize'] ) ? (int) $options['markersize'] : 30;
    $markersize = max( 10, min( 120, $markersize ) );
    $marker_style .= '--lb-marker-size:' . $markersize . 'px;';
    $marker_style .= '--lb-marker-color:' . ( isset( $options['color'] ) ? $options['color'] : '#000000' ) . ';';

    // Box Corner: user-set rounding for the marker box and its thumbnail,
    // shared by every Box Style (the admin just suggests a different default
    // per style — see the image1 handler in lookbook-admin.js).
    $boxradius = isset( $options['boxradius'] ) ? (int) $options['boxradius'] : 0;
    $boxradius = max( 0, min( 60, $boxradius ) );
    $marker_style .= '--lb-radius:' . $boxradius . 'px;';

    if ( 'custom' === $options['boxcolor'] && ! empty( $options['boxcustomcolor'] ) ) {
        $marker_classes[] = shoppablelookbook_is_light_color( $options['boxcustomcolor'] ) ? 'lb-text-dark' : 'lb-text-light';
        $marker_style    .= '--lb-bg:' . $options['boxcustomcolor'] . ';';
    }

    // Explicit content colour (text, price, close icon, preview link, cart
    // button) — overrides the per-box-colour defaults when not "auto".
    if ( in_array( $options['contentcolor'], array( 'light', 'dark' ), true ) ) {
        $marker_classes[] = 'lb-content-' . $options['contentcolor'];
    }

    $marker_style_attr = $marker_style ? ' style="' . esc_attr( $marker_style ) . '"' : '';

    $anim = isset( $options['animation'] ) ? sanitize_html_class( $options['animation'] ) : 'pulse';

    $trigger = isset( $options['trigger'] ) ? $options['trigger'] : 'click';
    if ( 'always' === $trigger ) {
        $trigger = 'openall'; // legacy value from the first release of this option.
    }
    if ( ! in_array( $trigger, array( 'click', 'hover', 'openall', 'openfirst', 'accordion' ), true ) ) {
        $trigger = 'click';
    }

    // Mobile display style: the full-screen bottom sheet (default), or a
    // scaled-down version of the desktop box ("compact"). Read by both the
    // CSS (@media max-width:768px rules key off .lookbook-mobile-compact)
    // and the JS (positionLookbooks / openMarker skip the sheet portal for it).
    $mobilebox = isset( $options['mobilebox'] ) ? $options['mobilebox'] : 'sheet';
    if ( ! in_array( $mobilebox, array( 'sheet', 'compact' ), true ) ) {
        $mobilebox = 'sheet';
    }

    // Photo Max Width: a further, user-chosen ceiling on top of the JS's
    // natural-size cap (positionLookbooks() in shoppable-lookbook.js reads
    // this via data-max-width). 0 = no extra limit.
    $maxwidth = isset( $options['maxwidth'] ) ? (int) $options['maxwidth'] : 0;
    $maxwidth = max( 0, min( 4000, $maxwidth ) );
    // Inside a gallery/lookbook slide the layout (carousel track, book page,
    // grid cell) owns the sizing — a per-hotspot Photo Width would fight it.
    if ( ! empty( $GLOBALS['shoppablelookbook_gallery_ctx'] ) ) {
        $maxwidth = 0;
    }
    $maxwidth_attr = $maxwidth ? ' style="max-width:' . $maxwidth . 'px"' : '';

    $str = '<div id="shoppable-lookbook-' . esc_attr( $id ) . '" data-limit-top="' . esc_attr( $limittop ) . '" data-limit-left="' . esc_attr( $limitleft ) . '" data-top="' . esc_attr( $top ) . '" data-bottom="' . esc_attr( $bottom ) . '" data-left="' . esc_attr( $left ) . '" data-right="' . esc_attr( $right ) . '" data-max-width="' . esc_attr( $maxwidth ) . '" class="shoppable-lookbook lookbook-layout-' . esc_attr( $layout ) . ' lookbook-anim-' . esc_attr( $anim ) . ' lookbook-trigger-' . esc_attr( $trigger ) . ' lookbook-mobile-' . esc_attr( $mobilebox ) . '"' . $maxwidth_attr . '>
            <div class="' . esc_attr( implode( ' ', $marker_classes ) ) . '"' . $marker_style_attr . '>';
            if ( $count > 0 ) {
                $thumb_size = ( $layout == 1 ) ? 'large' : 'medium';

                for ( $i = 0; $i <= $count - 1; $i++ ) {
                    $marker = $products[ $i ];
                    $type   = isset( $marker['type'] ) ? $marker['type'] : 'product';

                    // Resolve the link target, title, image and price for this marker.
                    $cart_html      = '';
                    $variation_html = '';

                    if ( 'custom' === $type ) {
                        $link  = isset( $marker['url'] ) ? $marker['url'] : '';
                        if ( empty( $link ) ) {
                            continue;
                        }
                        $product_id   = 0;
                        $product_name = isset( $marker['title'] ) ? $marker['title'] : '';
                        $thumbnail    = isset( $marker['image'] ) ? $marker['image'] : '';
                        $price_html   = isset( $marker['price'] ) ? $marker['price'] : '';
                        $target       = ( isset( $marker['target'] ) && '_blank' === $marker['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    } else {
                        $product_id = isset( $marker['product_id'] ) ? absint( $marker['product_id'] ) : 0;
                        $product    = ( $product_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;
                        if ( ! $product ) {
                            continue;
                        }
                        $link         = get_permalink( $product_id );
                        $product_name = $product->get_name();
                        $thumbnail    = get_the_post_thumbnail_url( $product_id, $thumb_size );
                        $price_html   = $product->get_price_html();
                        $target       = '';
                        $cart_html      = shoppablelookbook_add_to_cart_button( $product, $options );
                        $variation_html = shoppablelookbook_variation_select( $product, $options );
                    }

                    $product_price = ( $options['price'] == 1 && '' !== $price_html ) ? '<div class="product-price">' . wp_kses_post( $price_html ) . '</div>' : '';
                    $image_html    = $thumbnail ? '<div class="product-image"><img src="' . esc_url( $thumbnail ) . '" class="attachment-thumbnail size-thumbnail wp-post-image" alt="' . esc_attr( $product_name ) . '" /></div>' : '';
                    $pos_left      = isset( $marker['left'] ) ? (float) $marker['left'] : 0;
                    $pos_top       = isset( $marker['top'] ) ? (float) $marker['top'] : 0;
                    /* translators: %s: product or link name */
                    $marker_label = sprintf( __( 'View: %s', 'shoppable-lookbook' ), $product_name );
                    $grab_el      = shoppablelookbook_marker_inner( $options, $markers, 'lookbook-grab', 'role="button" tabindex="0" aria-label="' . esc_attr( $marker_label ) . '" aria-expanded="false"' );

                    $box_classes = array( 'lookbook-box' );
                    if ( ! empty( $options['price'] ) ) {
                        $box_classes[] = 'is-price';
                    }
                    if ( ! empty( $options['cart'] ) ) {
                        $box_classes[] = 'is-cart';
                    }
                    $box_classes = apply_filters( 'shoppablelookbook_box_classes', $box_classes, $options, $product_id );
                    $box_class_attr = esc_attr( implode( ' ', $box_classes ) );

                    $marker_html = '<div id="lookbook-marker' . esc_attr( $i ) . '" class="lookbook-marker" data-product="' . esc_attr( $product_id ) . '" style="left: ' . esc_attr( $pos_left ) . '%; top: ' . esc_attr( $pos_top ) . '%;">' . $grab_el . '<div class="' . $box_class_attr . '">
                    <a href="' . esc_url( $link ) . '" class="lookbook-product"' . $target . '>
                            <div class="product-name">
                                ' . $image_html . '
                                <div class="product-info">
                                    <h3 class="product-name">' . esc_html( $product_name ) . '</h3>
                                    ' . $product_price . '
                                </div>
                            </div>
                        </a>
                        ' . $variation_html . '
                        ' . $cart_html . '
                        <i class="lookbook-close material-icons" role="button" tabindex="0" aria-label="' . esc_attr__( 'Close', 'shoppable-lookbook' ) . '">clear</i>
                        </div>
                    </div>';

                    /**
                     * Filter a single marker\'s HTML (Pro can add quick-view, etc.).
                     *
                     * @param string $marker_html Marker markup.
                     * @param array  $marker      Marker data.
                     * @param array  $options     Lookbook options.
                     * @param int    $product_id  Linked product ID (0 for custom links).
                     */
                    $str .= apply_filters( 'shoppablelookbook_marker_html', $marker_html, $marker, $options, $product_id );
                }
            }
    // Responsive image attributes: srcset/sizes let the browser pick the right
    // size, lazy loading defers offscreen lookbooks (filterable for above-the-
    // fold placements: add_filter( 'shoppablelookbook_lazyload', '__return_false' )).
    $photo_attrs = ' width="' . esc_attr( $image[1] ) . '" height="' . esc_attr( $image[2] ) . '" decoding="async"';
    if ( apply_filters( 'shoppablelookbook_lazyload', true, $id ) ) {
        $photo_attrs .= ' loading="lazy"';
    }
    $srcset = wp_get_attachment_image_srcset( $lookbook->media_id, 'full' );
    if ( $srcset ) {
        $sizes        = wp_get_attachment_image_sizes( $lookbook->media_id, 'full' );
        $photo_attrs .= ' srcset="' . esc_attr( $srcset ) . '"' . ( $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '' );
    }

    $str .= '   </div>
                <img class="lookbook-photo" src="' . esc_url( $image[0] ) . '" alt="' . esc_attr( $options['name'] ) . '"' . $photo_attrs . ' />
            </div>';

    /**
     * Filter the whole lookbook HTML (Pro can append a quick-view modal,
     * a "shop the look" bar, analytics data attributes, etc.).
     *
     * @param string $str      Lookbook markup.
     * @param int    $id       Lookbook ID.
     * @param array  $options  Lookbook options.
     * @param array  $products Marker/product data.
     */
    return apply_filters( 'shoppablelookbook_html', $str, $id, $options, $products );
}
add_shortcode('shoppablelookbook', 'shoppablelookbook_shortcode');
