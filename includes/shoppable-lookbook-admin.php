<?php
/**
 * Defines the core plugin class
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
 * Open a collapsible settings section.
 *
 * @param string $title Section title.
 * @param string $icon  Material icon name.
 * @param bool   $open  Whether the section is expanded by default.
 * @return string
 */
function shoppablelookbook_section_open( $title, $icon, $open = false ) {
    return '<div class="lb-section' . ( $open ? ' open' : '' ) . '">'
        . '<button type="button" class="lb-section-head">'
        . '<i class="material-icons-round lb-section-icon">' . esc_html( $icon ) . '</i>'
        . '<span class="lb-section-title">' . esc_html( $title ) . '</span>'
        . '<i class="material-icons-round lb-section-arrow">expand_more</i>'
        . '</button>'
        . '<div class="lb-section-body">';
}

/**
 * Close a collapsible settings section.
 */
function shoppablelookbook_section_close() {
    return '</div></div>';
}

/**
 * Inline "!" hint icon for a form-heading — hover (desktop) or tap/click
 * (touch, keyboard) reveals the explanation instead of it always sitting
 * below the field as a permanent paragraph.
 *
 * @param string $text Explanation shown in the tooltip.
 * @return string
 */
function shoppablelookbook_hint( $text ) {
    return ' <span class="lb-hint" tabindex="0" role="button" aria-label="' . esc_attr__( 'More info', 'shoppable-lookbook' ) . '">'
        . '<i class="material-icons-round">info</i>'
        . '<span class="lb-hint-tip">' . esc_html( $text ) . '</span>'
        . '</span>';
}

function shoppablelookbook_page_init() {

    // Read-only admin page routing; no state change, so no nonce required.
    $action = isset($_GET['action']) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $id = isset($_GET['id']) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( !empty($action) && $action == 'edit' && !empty($id) && $id > 0 )
    {
        shoppablelookbook_page_edit($id);
    }
    else {
        shoppablelookbook_page_list();
    }

}

/**
 * Toolbar back link — returns to the Lookbook (gallery) editor when the editor
 * was opened with ?return_lookbook=ID, otherwise back to Image Hotspots list.
 *
 * @return string Escaped HTML for the anchor tag.
 */
function shoppablelookbook_back_link() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation param.
    $return_lb = isset( $_GET['return_lookbook'] ) ? absint( wp_unslash( $_GET['return_lookbook'] ) ) : 0;
    if ( $return_lb ) {
        $url   = admin_url( 'admin.php?page=shoppable-lookbook-galleries&action=edit&id=' . $return_lb );
        $icon  = 'photo_library';
        $label = __( 'Back to Lookbook', 'shoppable-lookbook' );
    } else {
        $url   = admin_url( '/admin.php?page=shoppable-lookbook' );
        $icon  = 'view_module';
        $label = __( 'Back', 'shoppable-lookbook' );
    }
    return '<a href="' . esc_url( $url ) . '" class="lookbook-back"><i class="material-icons-round">' . esc_html( $icon ) . '</i> ' . esc_html( $label ) . '</a>';
}

function shoppablelookbook_page_list() {

    global $wpdb;

    $current_user = wp_get_current_user();

    /* translators: %s: current user display name */
    $hello = sprintf( __( 'Hello %s!', 'shoppable-lookbook' ), $current_user->display_name );
    /* translators: 1: plugin name, 2: plugin version */
    $running = sprintf( __( 'You are running %1$s %2$s', 'shoppable-lookbook' ), LA_LOOKBOOK_NAME, LA_LOOKBOOK_VERSION );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbooks = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id DESC" );

    $new_url   = admin_url( 'admin.php?page=new-shoppable-lookbook' );

    echo '<div class="wrap lookbook-wrap">
        <div class="action-opacity">
            <div class="action-text">
                <div class="action-content">
                    <h5>'.esc_html__( 'Are you sure you want to delete', 'shoppable-lookbook' ).' <strong></strong> ?</h5>
                    <input type="hidden" name="lookbook-id" value="" />
                    <div class="action-buttons">
                        <button id="action-cancel" type="button"><span class="dashicons dashicons-dismiss"></span> '.esc_html__( 'Cancel', 'shoppable-lookbook' ).'</button>
                        <button id="action-delete" type="button"><span class="dashicons dashicons-trash"></span> '.esc_html__( 'Yes, delete lookbook', 'shoppable-lookbook' ).'</button>
                    </div>
                </div>
                <div class="action-deleting"><span class="material-icons-round">auto_delete</span> '.esc_html__( 'Deleting...', 'shoppable-lookbook' ).'</div>
                <div class="action-deleted"><span class="material-icons-round">delete</span> '.esc_html__( 'Deleted...', 'shoppable-lookbook' ).'</div>
                <div class="action-loading"><span class="material-icons-round">blur_on</span> '.esc_html__( 'Loading...', 'shoppable-lookbook' ).'</div>
                <div class="action-fail"><span class="material-icons-round">cancel</span> '.esc_html__( 'Failed...', 'shoppable-lookbook' ).'</div>
            </div>
        </div>
        <h1 class="wp-heading-inline">'.esc_html( get_admin_page_title() ).'</h1>
        <a href="'.esc_url( admin_url('/admin.php?page=new-shoppable-lookbook') ).'" class="page-title-action"><span class="lookbook-dashicon dashicons-before dashicons-plus"></span> '.esc_html__( 'Add New', 'shoppable-lookbook' ).'</a>';
    if ( shoppablelookbook_is_pro() ) {
        echo '<a href="#" id="lookbook-import" class="page-title-action"><span class="lookbook-dashicon dashicons-before dashicons-upload"></span> '.esc_html__( 'Import', 'shoppable-lookbook' ).'</a>
        <input type="file" id="lookbook-import-file" accept=".json,application/json" style="display:none" />';
    }
    echo '<hr class="wp-header-end">';
    
    if ( empty( $lookbooks ) ) {
		echo '<div class="lookbook-body nopost"><p>' . esc_html__( 'No image hotspots yet.', 'shoppable-lookbook' ) . '<br /><br /> <a href="' . esc_url( $new_url ) . '">' . esc_html__( 'Create your first image hotspots', 'shoppable-lookbook' ) . '</a></p></div>';
		echo '</div>';
		return;
	}

    echo '<div class="lookbook-body">
            <ul>';
            foreach ( $lookbooks as $lookbook ) {

                $image     = wp_get_attachment_image_src( $lookbook->media_id, 'full' );
                $image_url = $image ? $image[0] : '';
                $options   = maybe_unserialize( $lookbook->lookbook_options );
                $name      = isset( $options['name'] ) ? $options['name'] : '';
                $id        = (int) $lookbook->id;
                $edit_url  = admin_url( 'admin.php?page=shoppable-lookbook&action=edit&id=' . $id );

                $issues      = shoppablelookbook_count_product_issues( maybe_unserialize( $lookbook->lookbook_products ) );
                $issue_badge = '';
                if ( $issues['missing'] || $issues['outofstock'] ) {
                    $issue_parts = array();
                    if ( $issues['missing'] ) {
                        /* translators: %d: number of markers whose product no longer exists */
                        $issue_parts[] = sprintf( _n( '%d marker points to a missing product', '%d markers point to missing products', $issues['missing'], 'shoppable-lookbook' ), $issues['missing'] );
                    }
                    if ( $issues['outofstock'] ) {
                        /* translators: %d: number of markers whose product is out of stock */
                        $issue_parts[] = sprintf( _n( '%d product is out of stock', '%d products are out of stock', $issues['outofstock'], 'shoppable-lookbook' ), $issues['outofstock'] );
                    }
                    $issue_badge = '<span class="lookbook-issue-badge dashicons-before dashicons-warning" title="'.esc_attr( implode( ' — ', $issue_parts ) ).'"></span>';
                }

            echo '<li data-id="'.esc_attr( $id ).'" class="attachment lookbook-'.esc_attr( $id ).'">
                    <div class="attachment-preview landscape">
                        <a class="thumbnail" href="'.esc_url( $edit_url ).'" style="background-image:url('.esc_url( $image_url ).')"></a>
                        '.wp_kses_post( $issue_badge ).'
                    </div>
                    <div class="lookbook-foot">
                        <div class="lookbook-bar">
                            <span class="lookbook-name">'.esc_html( $name ).'</span>
                            <span class="lookbook-card-actions">
                                <span class="lookbook-action lookbook-duplicate lookbook-dashicon dashicons-before dashicons-admin-page" data-id="'.esc_attr( $id ).'" title="'.esc_attr__( 'Duplicate', 'shoppable-lookbook' ).'"></span>
                                '.( shoppablelookbook_is_pro() ? '<span class="lookbook-action lookbook-export lookbook-dashicon dashicons-before dashicons-download" data-id="'.esc_attr( $id ).'" title="'.esc_attr__( 'Export', 'shoppable-lookbook' ).'"></span>' : '' ).'
                                <span class="lookbook-action lookbook-dashicon dashicons-before dashicons-trash" onclick="ondeleteLookbook('.esc_js( $id ).', \''.esc_js( $name ).'\')" title="'.esc_attr__( 'Delete', 'shoppable-lookbook' ).'"></span>
                            </span>
                            <input id="lookbook-'.esc_attr( $id ).'" data-id="'.esc_attr( $id ).'" class="lookbook-shortcode" type="text" value="[shoppablelookbook id='.esc_attr( $id ).']" readonly />
                            <div class="lookbook-copied">'.esc_html__( 'Copied to clipboard', 'shoppable-lookbook' ).'</div>
                        </div>
                    </div>
                </li>';
            }
    echo '  </ul>
        </div>
    </div>';
}

/**
 * Render a single marker inside the editor.
 *
 * Supports both "product" markers (linked to a WooCommerce product) and
 * "custom" markers (linked to any URL with a custom title/image).
 *
 * @param int   $i       Marker index.
 * @param array $marker  Stored marker data.
 * @param array $options Lookbook options (color, boxcolor, marker icon).
 * @param array $markers List of available marker icon names.
 */
function shoppablelookbook_render_marker_editor( $i, $marker, $options, $markers ) {

    $marker_icon = isset( $markers[ (int) $options['marker'] ] ) ? $markers[ (int) $options['marker'] ] : $markers[0];
    $type        = isset( $marker['type'] ) ? $marker['type'] : 'product';

    $pos_left     = isset( $marker['left'] ) ? (float) $marker['left'] : 0;
    $pos_top      = isset( $marker['top'] ) ? (float) $marker['top'] : 0;
    // Sanitize on render too: rows saved by older versions may carry a stray
    // "display: none" from a mid-animation save, which would hide the chip.
    $lit_selected = isset( $marker['lit_selected'] ) ? shoppablelookbook_sanitize_position_style( $marker['lit_selected'] ) : '';
    $lit_box      = isset( $marker['lit_box'] ) ? shoppablelookbook_sanitize_position_style( $marker['lit_box'] ) : '';

    $product_id = 0;
    $url        = '';
    $title      = '';
    $image      = '';
    $price      = '';
    $target     = '';

    if ( 'custom' === $type ) {
        $url    = isset( $marker['url'] ) ? $marker['url'] : '';
        $title  = isset( $marker['title'] ) ? $marker['title'] : '';
        $image  = isset( $marker['image'] ) ? $marker['image'] : '';
        $price  = isset( $marker['price'] ) ? $marker['price'] : '';
        $target = ( isset( $marker['target'] ) && '_blank' === $marker['target'] ) ? '_blank' : '';
        if ( empty( $url ) ) {
            return;
        }
        $display_name  = $title ? $title : $url;
        $display_thumb = $image;
    } else {
        $product_id = isset( $marker['product_id'] ) ? absint( $marker['product_id'] ) : 0;
        $product    = ( $product_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            return;
        }
        $display_name  = substrword( $product->get_name(), 40 );
        $display_thumb = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
    }

    $thumb_html  = $display_thumb ? '<img src="' . esc_url( $display_thumb ) . '" alt="">' : '';
    $is_custom   = ( 'custom' === $type );
    $content_class = '';
    if ( isset( $options['contentcolor'] ) && in_array( $options['contentcolor'], array( 'light', 'dark' ), true ) ) {
        $content_class = ' lb-content-' . $options['contentcolor'];
    }
    $tab_product = $is_custom ? '' : ' active';
    $tab_custom  = $is_custom ? ' active' : '';

    echo '<div id="litmarker' . esc_attr( $i ) . '" class="lit-marker draggable ui-draggable ui-draggable-handle active" style="left: ' . esc_attr( $pos_left ) . '%; top: ' . esc_attr( $pos_top ) . '%;">'
        . wp_kses_post( shoppablelookbook_marker_inner( $options, $markers, 'lit-grab' ) )
        . '<div class="lit-selected ' . esc_attr( $options['boxcolor'] . $content_class ) . '" style="' . esc_attr( $lit_selected ) . '"><span class="lit-product">' . wp_kses_post( $thumb_html ) . esc_html( $display_name ) . '</span><i class="lit-cancel material-icons-round">clear</i></div>'
        . '<div class="lit-box" style="' . esc_attr( $lit_box ) . '">'
            . '<div class="lit-header"><h5>' . esc_html__( 'Add a link', 'shoppable-lookbook' ) . '</h5></div>'
            . '<i class="lit-close material-icons-round">clear</i><i class="lit-less material-icons-round">undo</i>'
            . '<div class="lit-tabs"><button type="button" class="lit-tab' . esc_attr( $tab_product ) . '" data-tab="product">' . esc_html__( 'Product', 'shoppable-lookbook' ) . '</button><button type="button" class="lit-tab' . esc_attr( $tab_custom ) . '" data-tab="custom">' . esc_html__( 'Custom Link', 'shoppable-lookbook' ) . '</button></div>'
            . '<div class="lit-form lit-pane lit-pane-product"' . ( $is_custom ? ' style="display:none"' : '' ) . '><input id="litproduct' . esc_attr( $i ) . '" type="text" value="" autocomplete="off" placeholder="' . esc_attr__( 'Search a product...', 'shoppable-lookbook' ) . '" onkeyup="litfetch(' . esc_js( $i ) . ')"><div class="lit-result"></div></div>'
            . '<div class="lit-pane lit-pane-custom"' . ( $is_custom ? '' : ' style="display:none"' ) . '>'
                . '<input class="lit-custom-url" type="url" value="' . esc_attr( $url ) . '" placeholder="' . esc_attr__( 'Link URL (https://...)', 'shoppable-lookbook' ) . '">'
                . '<input class="lit-custom-title" type="text" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr__( 'Title', 'shoppable-lookbook' ) . '">'
                . '<input class="lit-custom-price" type="text" value="' . esc_attr( $price ) . '" placeholder="' . esc_attr__( 'Price (optional)', 'shoppable-lookbook' ) . '">'
                . '<div class="lit-custom-image-row"><button type="button" class="lit-custom-image-btn button">' . esc_html__( 'Select image', 'shoppable-lookbook' ) . '</button><input class="lit-custom-image" type="hidden" value="' . esc_attr( $image ) . '"><span class="lit-custom-image-name">' . esc_html( $image ? basename( $image ) : '' ) . '</span></div>'
                . '<label class="lit-custom-target-label"><input type="checkbox" class="lit-custom-target" ' . checked( $target, '_blank', false ) . '> ' . esc_html__( 'Open in new tab', 'shoppable-lookbook' ) . '</label>'
                . '<button type="button" class="lit-custom-apply button button-primary">' . esc_html__( 'Apply link', 'shoppable-lookbook' ) . '</button>'
            . '</div>'
        . '</div>'
        . '<input type="hidden" name="data-x" value="' . esc_attr( $pos_left ) . '" />'
        . '<input type="hidden" name="data-y" value="' . esc_attr( $pos_top ) . '" />'
        . '<input type="hidden" name="data-product" value="' . esc_attr( $product_id ) . '" />'
        . '<input type="hidden" name="data-type" value="' . esc_attr( $type ) . '" />'
        . '<input type="hidden" name="data-url" value="' . esc_attr( $url ) . '" />'
        . '<input type="hidden" name="data-title" value="' . esc_attr( $title ) . '" />'
        . '<input type="hidden" name="data-image" value="' . esc_attr( $image ) . '" />'
        . '<input type="hidden" name="data-price" value="' . esc_attr( $price ) . '" />'
        . '<input type="hidden" name="data-target" value="' . esc_attr( $target ) . '" />'
        . '</div>';
}

function shoppablelookbook_page_edit($id) {

    global $wpdb;

    $id       = (int) $id;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbook = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook WHERE id = %d", $id ) );

    if ( ! $lookbook ) {
        echo '<div class="wrap"><h1>' . esc_html__( 'Lookbook not found.', 'shoppable-lookbook' ) . '</h1></div>';
        return;
    }

    $image     = wp_get_attachment_image_src( $lookbook->media_id, 'full' );
    $image_url = $image ? $image[0] : '';
    $options   = maybe_unserialize( $lookbook->lookbook_options );
    $products  = maybe_unserialize( $lookbook->lookbook_products );

    // Normalise options with safe defaults (older rows may use a different key set).
    $options = wp_parse_args(
        is_array( $options ) ? $options : array(),
        array(
            'name'           => '',
            'layout'         => 0,
            'boxcolor'       => 'light',
            'boxcustomcolor' => '',
            'contentcolor'   => 'auto',
            'opacity'        => 100,
            'marker'         => 0,
            'markerimage'    => 0,
            'markersize'     => 30,
            'color'          => '#000000',
            'price'          => 0,
            'cart'           => 0,
            'sidebar'        => 0,
            'mobilebox'      => 'sheet',
            'boxradius'      => 0,
            'maxwidth'       => 0,
        )
    );

    $marker_image_url = ! empty( $options['markerimage'] ) ? wp_get_attachment_image_url( (int) $options['markerimage'], 'full' ) : '';

    $layout = array('','','','','');
    $boxcolor = array('light'=>'','dark'=>'','yellow'=>'','blue'=>'','green'=>'','rose'=>'','custom'=>'');
    $contentcolor = array('auto'=>'','light'=>'','dark'=>'');
    $marker = array('','','','','','','','');
    $markers = array('add_circle', 'favorite', 'local_offer', 'local_mall', 'place', 'star', 'shopping_bag', 'touch_app');
    $price = array('','');
    $cart = array('','');
    $sidebar = array('','','');
    $animation = array('none'=>'','pulse'=>'','beat'=>'','bounce'=>'');
    $trigger = array('click'=>'','hover'=>'','openall'=>'','openfirst'=>'','accordion'=>'');
    $mobilebox = array('sheet'=>'','compact'=>'');
    $layout_key = isset( $layout[ $options['layout'] ] ) ? $options['layout'] : 0;
    $color_key  = isset( $boxcolor[ $options['boxcolor'] ] ) ? $options['boxcolor'] : 'light';
    $content_key = isset( $options['contentcolor'], $contentcolor[ $options['contentcolor'] ] ) ? $options['contentcolor'] : 'auto';
    $contentcolor[ $content_key ] = 'checked';
    $anim_key   = isset( $options['animation'], $animation[ $options['animation'] ] ) ? $options['animation'] : 'pulse';
    $trigger_key = isset( $options['trigger'] ) ? $options['trigger'] : 'click';
    if ( 'always' === $trigger_key ) { $trigger_key = 'openall'; } // legacy value.
    if ( ! isset( $trigger[ $trigger_key ] ) ) { $trigger_key = 'click'; }
    $mobilebox_key = isset( $options['mobilebox'], $mobilebox[ $options['mobilebox'] ] ) ? $options['mobilebox'] : 'sheet';
    if ( ! shoppablelookbook_is_pro() ) {
        $mobilebox_key = 'compact'; // Bottom sheet is Pro; mirror the enforced state.
    }
    $layout[$layout_key] = $boxcolor[$color_key] = $marker[$options['marker']] = $price[$options['price']] = $cart[$options['cart']] = $sidebar[$options['sidebar']] = $animation[$anim_key] = $trigger[$trigger_key] = $mobilebox[$mobilebox_key] = 'checked';

    $count = !empty($products) && is_array($products) ? count($products) : 0;

    // Surface markers whose product was deleted or is out of stock — those
    // markers are silently skipped on the front-end, so warn the editor here.
    $issues       = shoppablelookbook_count_product_issues( $products );
    $issue_notice = '';
    if ( $issues['missing'] || $issues['outofstock'] ) {
        $issue_parts = array();
        if ( $issues['missing'] ) {
            /* translators: %d: number of markers whose product no longer exists */
            $issue_parts[] = sprintf( _n( '%d marker points to a product that no longer exists and will not be shown.', '%d markers point to products that no longer exist and will not be shown.', $issues['missing'], 'shoppable-lookbook' ), $issues['missing'] );
        }
        if ( $issues['outofstock'] ) {
            /* translators: %d: number of markers whose product is out of stock */
            $issue_parts[] = sprintf( _n( '%d linked product is out of stock.', '%d linked products are out of stock.', $issues['outofstock'], 'shoppable-lookbook' ), $issues['outofstock'] );
        }
        $issue_notice = '<div class="notice notice-warning lookbook-issue-notice"><p><strong>'.esc_html__( 'Product check:', 'shoppable-lookbook' ).'</strong> '.esc_html( implode( ' ', $issue_parts ) ).'</p></div>';
    }

    echo '<div class="wrap lookbook-wrap">
        <div class="action-opacity">
            <div class="action-text">
                <div class="action-loading"><span class="material-icons-round">blur_on</span> '.esc_html__( 'Loading...', 'shoppable-lookbook' ).'</div>
                <div class="action-saving"><span class="material-icons-round">system_update_alt</span> '.esc_html__( 'Saving...', 'shoppable-lookbook' ).'</div>
                <div class="action-saved"><span class="material-icons">check_circle</span> '.esc_html__( 'Saved...', 'shoppable-lookbook' ).'</div>
            </div>
        </div>
        <hr class="wp-header-end">'.wp_kses_post( $issue_notice ).'
        <div class="lookbook-toolbar">
            '.wp_kses_post( shoppablelookbook_back_link() ).' <a href="#" class="lookbook-change"><i class="material-icons">photo_size_select_actual</i> '.esc_html__( 'Change Image', 'shoppable-lookbook' ).'</a>
        </div>
        <div class="lookbook-detail">
            <div id="lit-frame">
                <input type="hidden" name="lit_id" id="lit-id" value="'.esc_attr( $id ).'" />
                <input type="hidden" name="lit_temp" id="lit-temp" value="'.esc_attr( $lookbook->media_id ).'" />
                <div id="markers" class="lookbook-anim-'.esc_attr( $anim_key ).'" data-id="'.esc_attr( $count ).'" style="--lb-marker-size:'.esc_attr( $options['markersize'] ).'px;--lb-marker-color:'.esc_attr( $options['color'] ).'">';
                if ( $count > 0 ) {
                    for ($i = 0; $i <= $count - 1; $i++) {
                        shoppablelookbook_render_marker_editor( $i, $products[$i], $options, $markers );
                    }
                }
          echo '</div>
                <img id="lit-photo" src="'.esc_url( $image_url ).'" alt="" />
                <div class="lit-hint"><i class="material-icons-round">touch_app</i> '.esc_html__( 'Click anywhere on the image to add a marker', 'shoppable-lookbook' ).'</div>
            </div>
            <div id="lit-sidebar">
                <div class="lookbook-heading"><span class="lookbook-dashicon dashicons-before dashicons-admin-settings"></span> '.esc_html__( 'Image Hotspot Settings', 'shoppable-lookbook' ).'</div>
                <div class="lookbook-settings">
                    <div class="lb-default">
                    <div class="form-row">
                        <label for="lookbook-name" class="input-label">'.esc_html__( 'Title', 'shoppable-lookbook' ).'</label>
                        <input type="text" id="lookbook-name" value="'.esc_attr( $options['name'] ).'" />
                    </div>
                    <div class="form-row row-shortcode">
                        <label for="lookbook-shortcode" class="input-label">'.esc_html__( 'Shortcode', 'shoppable-lookbook' ).'</label>
                        <input type="text" id="lookbook-shortcode" value="[shoppablelookbook id='.esc_attr( $id ).']" />
                        <div class="lookbook-copied">'.esc_html__( 'Copied to clipboard', 'shoppable-lookbook' ).'</div>
                    </div>
                    </div>'.wp_kses_post( shoppablelookbook_section_open( __( 'Hotspot List', 'shoppable-lookbook' ), 'format_list_bulleted' ) ).'
                    <ul id="lit-marker-list" class="lit-marker-list"></ul>
                    '.wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Box', 'shoppable-lookbook' ), 'palette' ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Box Style', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style0.png' ).'" alt="Style 0" />
                                <input type="radio" name="lookbook_layout" value="0" '.esc_attr( $layout[0] ).' />
                                '.esc_html__( 'Inline', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style1.png' ).'" alt="Style 1" />
                                <input type="radio" name="lookbook_layout" value="1" '.esc_attr( $layout[1] ).' />
                                '.esc_html__( 'Pad', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style2.png' ).'" alt="Style 2" />
                                <input type="radio" name="lookbook_layout" value="2" '.esc_attr( $layout[2] ).' />
                                '.esc_html__( 'Instagram', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Box Color', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-white"></span>
                                <input type="radio" name="lookbook_box_color" value="light" '.esc_attr( $boxcolor['light'] ).' />
                                '.esc_html__( 'Light', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-dark"></span>
                                <input type="radio" name="lookbook_box_color" value="dark" '.esc_attr( $boxcolor['dark'] ).' />
                                '.esc_html__( 'Dark', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-yellow"></span>
                                <input type="radio" name="lookbook_box_color" value="yellow" '.esc_attr( $boxcolor['yellow'] ).' />
                                '.esc_html__( 'Yellow', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-blue"></span>
                                <input type="radio" name="lookbook_box_color" value="blue" '.esc_attr( $boxcolor['blue'] ).' />
                                '.esc_html__( 'Blue', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-green"></span>
                                <input type="radio" name="lookbook_box_color" value="green" '.esc_attr( $boxcolor['green'] ).' />
                                '.esc_html__( 'Green', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-rose"></span>
                                <input type="radio" name="lookbook_box_color" value="rose" '.esc_attr( $boxcolor['rose'] ).' />
                                '.esc_html__( 'Rose', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Content Color', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Color of the product name, price, close icon, preview link and Add to Cart button inside the box. Auto picks a readable color for the chosen box color.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-auto" aria-hidden="true"></span>
                                <input type="radio" name="lookbook_content_color" value="auto" '.esc_attr( $contentcolor['auto'] ).' />
                                '.esc_html__( 'Auto', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-light" aria-hidden="true">Aa</span>
                                <input type="radio" name="lookbook_content_color" value="light" '.esc_attr( $contentcolor['light'] ).' />
                                '.esc_html__( 'Light', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-dark" aria-hidden="true">Aa</span>
                                <input type="radio" name="lookbook_content_color" value="dark" '.esc_attr( $contentcolor['dark'] ).' />
                                '.esc_html__( 'Dark', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>                    </div>'.wp_kses_post( shoppablelookbook_settings_hook( 'box', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Marker', 'shoppable-lookbook' ), 'place', true ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">add_circle</i>
                                <input type="radio" name="lookbook_marker" value="0" '.esc_attr( $marker[0] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">favorite</i>
                                <input type="radio" name="lookbook_marker" value="1" '.esc_attr( $marker[1] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">local_offer</i>
                                <input type="radio" name="lookbook_marker" value="2" '.esc_attr( $marker[2] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">local_mall</i>
                                <input type="radio" name="lookbook_marker" value="3" '.esc_attr( $marker[3] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">place</i>
                                <input type="radio" name="lookbook_marker" value="4" '.esc_attr( $marker[4] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">star</i>
                                <input type="radio" name="lookbook_marker" value="5" '.esc_attr( $marker[5] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">shopping_bag</i>
                                <input type="radio" name="lookbook_marker" value="6" '.esc_attr( $marker[6] ).' />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">touch_app</i>
                                <input type="radio" name="lookbook_marker" value="7" '.esc_attr( $marker[7] ).' />
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker Animation', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio anim1">
                                <i class="material-icons">not_interested</i>
                                <input type="radio" name="lookbook_animation" value="none" '.esc_attr( $animation['none'] ).' />
                                '.esc_html__( 'None', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio anim1">
                                <i class="material-icons">track_changes</i>
                                <input type="radio" name="lookbook_animation" value="pulse" '.esc_attr( $animation['pulse'] ).' />
                                '.esc_html__( 'Pulse', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>

                    <div class="form-row form-inline-group">
                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Marker Color', 'shoppable-lookbook' ).'</div>
                            <input type="text" name="lookbook_marker_color" value="'.esc_attr( $options['color'] ).'" data-default-color="'.esc_attr( $options['color'] ).'" class="marker-color" />
                            <input type="hidden" name="hide_marker_color" id="hide-marker-color" value="'.esc_attr( $options['color'] ).'" />
                        </div>

                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Marker Size', 'shoppable-lookbook' ).' <span class="markersize-value">'.esc_html( $options['markersize'] ).'</span> px</div>
                            <input type="range" name="lookbook_marker_size" min="10" max="120" step="1" value="'.esc_attr( $options['markersize'] ).'" class="markersize-range" />
                        </div>
                    </div>

'.wp_kses_post( shoppablelookbook_settings_hook( 'marker', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Display', 'shoppable-lookbook' ), 'visibility' ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Photo Width', 'shoppable-lookbook' ).' <span class="maxwidth-value">'.( $options['maxwidth'] ? esc_html( $options['maxwidth'] ) . ' px' : esc_html__( 'Full width', 'shoppable-lookbook' ) ).'</span>'.wp_kses_post( shoppablelookbook_hint( __( 'Caps how wide the main photo can grow on the frontend. Drag to 0 for no limit (full width of its container).', 'shoppable-lookbook' ) ) ).'</div>
                        <input type="range" name="lookbook_max_width" min="0" max="2000" step="10" value="'.esc_attr( $options['maxwidth'] ).'" class="maxwidth-range" />
                    </div>

                    <div class="form-row form-inline-group">
                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Show Price', 'shoppable-lookbook' ).'</div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">money_off</i>
                                    <input type="radio" name="lookbook_price" value="0" '.esc_attr( $price[0] ).' />
                                    '.esc_html__( 'No', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">monetization_on</i>
                                    <input type="radio" name="lookbook_price" value="1" '.esc_attr( $price[1] ).' />
                                    '.esc_html__( 'Yes', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Add to Cart', 'shoppable-lookbook' ).'</div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">remove_shopping_cart</i>
                                    <input type="radio" name="lookbook_cart" value="0" '.esc_attr( $cart[0] ).' />
                                    '.esc_html__( 'No', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">add_shopping_cart</i>
                                    <input type="radio" name="lookbook_cart" value="1" '.esc_attr( $cart[1] ).' />
                                    '.esc_html__( 'Yes', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                        </div>
                        '.wp_kses_post( shoppablelookbook_settings_hook( 'display', $options ) ).'
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Open Marker On', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Open All: every box opens on load (visitors can close them). Open First: the first box opens on load. Accordion: one box is always open — opening another closes it.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">touch_app</i>
                                <input type="radio" name="lookbook_trigger" value="click" '.esc_attr( $trigger['click'] ).' />
                                '.esc_html__( 'Click', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">mouse</i>
                                <input type="radio" name="lookbook_trigger" value="hover" '.esc_attr( $trigger['hover'] ).' />
                                '.esc_html__( 'Hover', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Mobile Display', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Bottom sheet: the box slides up from the bottom of the screen, full width. Compact: the box opens next to its marker like on desktop, sized down for mobile.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">crop_free</i>
                                <input type="radio" name="lookbook_mobilebox" value="compact" '.esc_attr( $mobilebox['compact'] ).' />
                                '.esc_html__( 'Compact (desktop-style)', 'shoppable-lookbook' ).'
                            </label>
                        </div>                    </div>

                    <div class="form-row" style="display:none">
                        <div class="form-heading">'.esc_html__( 'Show Sidebar', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">not_interested</i>
                                <input type="radio" name="lookbook_sidebar" value="0" '.esc_attr( $sidebar[0] ).' />
                                '.esc_html__( 'None', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">vertical_split</i>
                                <input type="radio" name="lookbook_sidebar" value="1" '.esc_attr( $sidebar[1] ).' />
                                '.esc_html__( 'Left', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons material-flip">vertical_split</i>
                                <input type="radio" name="lookbook_sidebar" value="2" '.esc_attr( $sidebar[2] ).' />
                                '.esc_html__( 'Right', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>'.wp_kses_post( shoppablelookbook_settings_hook( 'display_full', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).'

                    <div class="form-buttons">
                        <button id="lookbook-submit" type="button" class="is-button"><i class="material-icons dashicons-before">save</i> '.esc_html__( 'Save', 'shoppable-lookbook' ).'</button>
                        <button id="lookbook-preview" type="button" class="is-button"><span class="lookbook-dashicon dashicons-before dashicons-visibility"></span> '.esc_html__( 'Preview', 'shoppable-lookbook' ).'</button>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function shoppablelookbook_page_new() {

    $options = shoppablelookbook_default_options( '' );

    echo '<div class="wrap lookbook-wrap">
        <div class="action-opacity">
            <div class="action-text">
                <div class="action-loading"><span class="material-icons-round">blur_on</span> '.esc_html__( 'Loading...', 'shoppable-lookbook' ).'</div>
                <div class="action-saving"><span class="material-icons-round">system_update_alt</span> '.esc_html__( 'Saving...', 'shoppable-lookbook' ).'</div>
                <div class="action-saved"><span class="material-icons">check_circle</span> '.esc_html__( 'Saved...', 'shoppable-lookbook' ).'</div>
            </div>
        </div>
        <hr class="wp-header-end">
        <div class="lookbook-toolbar">
            '.wp_kses_post( shoppablelookbook_back_link() ).' <a href="#" class="lookbook-change hidden"><i class="material-icons">photo_size_select_actual</i> '.esc_html__( 'Change Image', 'shoppable-lookbook' ).'</a>
        </div>
        <div class="lookbook-detail">
            <div id="lit-frame" class="lookbook-upload">
                <input type="hidden" name="lit_id" id="lit-id" value="" />
                <input type="hidden" name="lit_temp" id="lit-temp" value="" />
                <div id="markers" class="lookbook-anim-pulse" data-id="0" style="--lb-marker-size:30px;--lb-marker-color:#000000"></div>
                <img id="lit-photo" alt="" />
                <div class="lit-hint"><i class="material-icons-round">touch_app</i> '.esc_html__( 'Click anywhere on the image to add a marker', 'shoppable-lookbook' ).'</div>
                <div id="upload-buttons">
                    <button id="lit-upload" type="button"><i class="material-icons">add_photo_alternate</i> '.esc_html__( 'Select Image', 'shoppable-lookbook' ).'</button>
                    <p class="lit-upload-hint">'.esc_html__( 'or drag & drop an image here', 'shoppable-lookbook' ).'</p>
                </div>
            </div>
            <div id="lit-sidebar" class="disabled">
                <div class="lookbook-heading"><span class="lookbook-dashicon dashicons-before dashicons-admin-settings"></span> '.esc_html__( 'Lookbook Settings', 'shoppable-lookbook' ).'</div>
                <div class="lookbook-settings">
                    <div class="lb-default">
                    <div class="form-row row-flex">
                        <label for="lookbook-name" class="input-label">'.esc_html__( 'Title', 'shoppable-lookbook' ).'</label>
                        <input type="text" id="lookbook-name" value="" />
                    </div>
                    <div class="form-row row-shortcode row-flex">
                        <label for="lookbook-shortcode" class="input-label">'.esc_html__( 'Shortcode', 'shoppable-lookbook' ).'</label>
                        <input type="text" id="lookbook-shortcode" value="" />
                        <div class="lookbook-copied">'.esc_html__( 'Copied to clipboard', 'shoppable-lookbook' ).'</div>
                    </div>
                    </div>'.wp_kses_post( shoppablelookbook_section_open( __( 'Hotspot List', 'shoppable-lookbook' ), 'format_list_bulleted' ) ).'
                    <ul id="lit-marker-list" class="lit-marker-list"></ul>
                    '.wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Box', 'shoppable-lookbook' ), 'palette' ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Box Style', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style0.png' ).'" alt="Style 0" />
                                <input type="radio" name="lookbook_layout" value="0" checked />
                                '.esc_html__( 'Inline', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style1.png' ).'" alt="Style 1" />
                                <input type="radio" name="lookbook_layout" value="1" />
                                '.esc_html__( 'Pad', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio image1">
                                <img class="img-responsive" src="'.esc_url( plugin_dir_url( __DIR__ ) . 'assets/img/style2.png' ).'" alt="Style 2" />
                                <input type="radio" name="lookbook_layout" value="2" />
                                '.esc_html__( 'Instagram', 'shoppable-lookbook' ).'
                                <i class="material-icons material-select hidden">check_circle</i>
                            </label>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Box Color', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-white"></span>
                                <input type="radio" name="lookbook_box_color" value="light" checked />
                                '.esc_html__( 'Light', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-dark"></span>
                                <input type="radio" name="lookbook_box_color" value="dark" />
                                '.esc_html__( 'Dark', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-yellow"></span>
                                <input type="radio" name="lookbook_box_color" value="yellow" />
                                '.esc_html__( 'Yellow', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-blue"></span>
                                <input type="radio" name="lookbook_box_color" value="blue" />
                                '.esc_html__( 'Blue', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-green"></span>
                                <input type="radio" name="lookbook_box_color" value="green" />
                                '.esc_html__( 'Green', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio color1">
                                <span class="rectangle-rose"></span>
                                <input type="radio" name="lookbook_box_color" value="rose" />
                                '.esc_html__( 'Rose', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Content Color', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Color of the product name, price, close icon, preview link and Add to Cart button inside the box. Auto picks a readable color for the chosen box color.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-auto" aria-hidden="true"></span>
                                <input type="radio" name="lookbook_content_color" value="auto" checked />
                                '.esc_html__( 'Auto', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-light" aria-hidden="true">Aa</span>
                                <input type="radio" name="lookbook_content_color" value="light" />
                                '.esc_html__( 'Light', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio content1">
                                <span class="rectangle-content-dark" aria-hidden="true">Aa</span>
                                <input type="radio" name="lookbook_content_color" value="dark" />
                                '.esc_html__( 'Dark', 'shoppable-lookbook' ).'
                            </label>
                        </div>                    </div>'.wp_kses_post( shoppablelookbook_settings_hook( 'box', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Marker', 'shoppable-lookbook' ), 'place', true ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">add_circle</i>
                                <input type="radio" name="lookbook_marker" value="0" checked />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">favorite</i>
                                <input type="radio" name="lookbook_marker" value="1" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">local_offer</i>
                                <input type="radio" name="lookbook_marker" value="2" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">local_mall</i>
                                <input type="radio" name="lookbook_marker" value="3" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">place</i>
                                <input type="radio" name="lookbook_marker" value="4" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">star</i>
                                <input type="radio" name="lookbook_marker" value="5" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">shopping_bag</i>
                                <input type="radio" name="lookbook_marker" value="6" />
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio marker1">
                                <i class="material-icons-round">touch_app</i>
                                <input type="radio" name="lookbook_marker" value="7" />
                            </label>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker Size', 'shoppable-lookbook' ).' (<span class="markersize-value">30</span>px)</div>
                        <input type="range" name="lookbook_marker_size" min="10" max="120" step="1" value="30" class="markersize-range" />
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker Color', 'shoppable-lookbook' ).'</div>
                        <input type="text" name="lookbook_marker_color" value="#000000" data-default-color="#000000" class="marker-color" />
                        <input type="hidden" name="hide_marker_color" id="hide-marker-color" value="#000000" />
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Marker Animation', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio anim1">
                                <i class="material-icons">not_interested</i>
                                <input type="radio" name="lookbook_animation" value="none" />
                                '.esc_html__( 'None', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio anim1">
                                <i class="material-icons">track_changes</i>
                                <input type="radio" name="lookbook_animation" value="pulse" checked />
                                '.esc_html__( 'Pulse', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>'.wp_kses_post( shoppablelookbook_settings_hook( 'marker', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).wp_kses_post( shoppablelookbook_section_open( __( 'Display', 'shoppable-lookbook' ), 'visibility' ) ).'

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Photo Width', 'shoppable-lookbook' ).' <span class="maxwidth-value">'.esc_html__( 'Full width', 'shoppable-lookbook' ).'</span>'.wp_kses_post( shoppablelookbook_hint( __( 'Caps how wide the main photo can grow on the frontend. Drag to 0 for no limit (full width of its container).', 'shoppable-lookbook' ) ) ).'</div>
                        <input type="range" name="lookbook_max_width" min="0" max="2000" step="10" value="0" class="maxwidth-range" />
                    </div>

                    <div class="form-row form-inline-group">
                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Show Price', 'shoppable-lookbook' ).'</div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">money_off</i>
                                    <input type="radio" name="lookbook_price" value="0" checked />
                                    '.esc_html__( 'No', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">monetization_on</i>
                                    <input type="radio" name="lookbook_price" value="1" />
                                    '.esc_html__( 'Yes', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-heading">'.esc_html__( 'Add to Cart', 'shoppable-lookbook' ).'</div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">remove_shopping_cart</i>
                                    <input type="radio" name="lookbook_cart" value="0" checked />
                                    '.esc_html__( 'No', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                            <div class="form-option">
                                <label class="image-radio">
                                    <i class="material-icons">add_shopping_cart</i>
                                    <input type="radio" name="lookbook_cart" value="1" />
                                    '.esc_html__( 'Yes', 'shoppable-lookbook' ).'
                                </label>
                            </div>
                        </div>
                        '.wp_kses_post( shoppablelookbook_settings_hook( 'display', $options ) ).'
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Open Marker On', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Open All: every box opens on load (visitors can close them). Open First: the first box opens on load. Accordion: one box is always open — opening another closes it.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">touch_app</i>
                                <input type="radio" name="lookbook_trigger" value="click" checked />
                                '.esc_html__( 'Click', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">mouse</i>
                                <input type="radio" name="lookbook_trigger" value="hover" />
                                '.esc_html__( 'Hover', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-heading">'.esc_html__( 'Mobile Display', 'shoppable-lookbook' ).wp_kses_post( shoppablelookbook_hint( __( 'Bottom sheet: the box slides up from the bottom of the screen, full width. Compact: the box opens next to its marker like on desktop, sized down for mobile.', 'shoppable-lookbook' ) ) ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">crop_free</i>
                                <input type="radio" name="lookbook_mobilebox" value="compact" '.( shoppablelookbook_is_pro() ? '' : 'checked' ).' />
                                '.esc_html__( 'Compact (desktop-style)', 'shoppable-lookbook' ).'
                            </label>
                        </div>                    </div>

                    <div class="form-row" style="display:none">
                        <div class="form-heading">'.esc_html__( 'Show Sidebar', 'shoppable-lookbook' ).'</div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">not_interested</i>
                                <input type="radio" name="lookbook_sidebar" value="0" checked />
                                '.esc_html__( 'None', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons">vertical_split</i>
                                <input type="radio" name="lookbook_sidebar" value="1" />
                                '.esc_html__( 'Left', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                        <div class="form-option">
                            <label class="image-radio">
                                <i class="material-icons material-flip">vertical_split</i>
                                <input type="radio" name="lookbook_sidebar" value="2" />
                                '.esc_html__( 'Right', 'shoppable-lookbook' ).'
                            </label>
                        </div>
                    </div>'.wp_kses_post( shoppablelookbook_settings_hook( 'display_full', $options ) ).wp_kses_post( shoppablelookbook_section_close() ).'

                    <div class="form-buttons">
                        <button id="lookbook-submit" type="button" class="is-button"><i class="material-icons dashicons-before">save</i> '.esc_html__( 'Save', 'shoppable-lookbook' ).'</button>
                        <button id="lookbook-preview" type="button" class="is-button"><span class="lookbook-dashicon dashicons-before dashicons-visibility"></span> '.esc_html__( 'Preview', 'shoppable-lookbook' ).'</button>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}
