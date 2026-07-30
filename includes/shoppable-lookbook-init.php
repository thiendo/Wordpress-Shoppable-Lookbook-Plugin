<?php
/**
 * Defines the init plugin class
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
 * Verify that the current AJAX request comes from an authorized admin
 * with a valid nonce. Dies with a JSON error otherwise.
 */
function shoppablelookbook_verify_admin_request() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'shoppable-lookbook' ) ), 403 );
    }
    check_ajax_referer( 'shoppablelookbook_admin', '_ajax_nonce' );
}

/**
 * Return the lookbook table name.
 */
function shoppablelookbook_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'shoppable_lookbook';
}

/**
 * Whether the Pro features are available.
 *
 * The free plugin always returns false. The Pro add-on (or the Freemius
 * premium build) hooks "shoppablelookbook_is_pro" to return true.
 *
 * @return bool
 */
function shoppablelookbook_is_pro() {
    return (bool) apply_filters( 'shoppablelookbook_is_pro', false );
}

/**
 * Output any add-on (Pro) markup registered for a settings section.
 *
 * Usage in the settings template:  echo shoppablelookbook_settings_hook( 'marker', $options );
 * Add-ons hook:  add_action( 'shoppablelookbook_settings_marker', function( $options ) { ... } );
 *
 * @param string $section Section slug (box|marker|display|...).
 * @param array  $options Current lookbook options.
 * @return string
 */
function shoppablelookbook_settings_hook( $section, $options = array() ) {
    ob_start();
    do_action( 'shoppablelookbook_settings_' . $section, $options );
    return ob_get_clean();
}

/**
 * Like shoppablelookbook_settings_hook(), but wraps the collected add-on markup
 * in a single inline-group row so each add-on's .form-col block sits side by
 * side (matching the built-in "Show Price / Add to Cart" row). Returns '' when
 * no add-on produced output (e.g. the free build).
 *
 * @param string $section Section slug.
 * @param array  $options Current lookbook options.
 * @return string
 */
function shoppablelookbook_settings_inline_hook( $section, $options = array() ) {
    $html = shoppablelookbook_settings_hook( $section, $options );
    if ( '' === trim( $html ) ) {
        return '';
    }
    return '<div class="form-row form-inline-group">' . $html . '</div>';
}

/**
 * Check update database
 */
function shoppablelookbook_update_db() {

    if ( get_option( 'douple_lookbook_version' ) != LA_LOOKBOOK_VERSION ) {
        shoppablelookbook_create_db();
    }

}
add_action( 'plugins_loaded', 'shoppablelookbook_update_db' );

/**
 * Create admin menu
 */
function shoppablelookbook_menu() {
    $page_title = __( 'Shoppable Lookbook', 'shoppable-lookbook' );
    $menu_title = __( 'Lookbook', 'shoppable-lookbook' );
    $capability = 'manage_options';
    $menu_slug  = 'shoppable-lookbook';
    $function   = 'shoppablelookbook_page_init';
    $icon_url   = 'dashicons-location';
    $position   = 58; // Below WooCommerce (55.5), above Appearance (60).

    add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );

    // Rename the auto-generated first submenu (which otherwise repeats the
    // top-level menu title) to "Image Hotspots". Same slug, so it points at
    // the same list page.
    add_submenu_page( $menu_slug, __( 'Image Hotspots', 'shoppable-lookbook' ), __( 'Image Hotspots', 'shoppable-lookbook' ), $capability, $menu_slug, $function );

    // Registered under the real parent so WP knows the page belongs here —
    // this gives correct menu highlighting and a non-null $title (fixes PHP
    // 8.1 strip_tags deprecation). The nav item is hidden via CSS below.
    add_submenu_page( $menu_slug, __( 'New Image Hotspot', 'shoppable-lookbook' ), __( 'New Image Hotspot', 'shoppable-lookbook' ), $capability, 'new-shoppable-lookbook', 'shoppablelookbook_page_new' );
}
add_action( 'admin_menu', 'shoppablelookbook_menu' );

// Hide the "New Image Hotspot" nav item — it is only reachable via the inline
// "Add New" button, not from the sidebar. Registered with a real parent above
// so WP still resolves the title and highlights the correct menu item.
add_action( 'admin_enqueue_scripts', function () {
    wp_add_inline_style( 'wp-admin', '#adminmenu a[href$="page=new-shoppable-lookbook"]{display:none!important}' );
} );

/**
 * Ajax action to refresh the user image
 */
function shoppablelookbook_get_image() {
    shoppablelookbook_verify_admin_request();

    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $media_id = isset( $_GET['media_id'] ) ? absint( wp_unslash( $_GET['media_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $media_id ) {
        $image = wp_get_attachment_image_src( $media_id, 'full' );
        if ( $image ) {
            wp_send_json_success( array( 'image' => $image[0] ) );
        }
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_get_image', 'shoppablelookbook_get_image' );

/**
 * Create database table
 */
function shoppablelookbook_create_db()
{
    global $wpdb;

    $table_name      = shoppablelookbook_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    // Check to see if the table exists already, if not, then create it
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-off table existence check on activation.
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) != $table_name ) {

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-off CREATE TABLE on activation via dbDelta().
        $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL auto_increment,
                user_id bigint(20) NOT NULL,
                media_id bigint(20) NOT NULL,
                lookbook_options longtext NOT NULL,
                lookbook_products longtext NOT NULL,
                lookbook_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (id)
                ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }

    update_option( 'douple_lookbook_version', LA_LOOKBOOK_VERSION );
}

/**
 * Default option set for a new lookbook.
 */
function shoppablelookbook_default_options( $name ) {
    $defaults = array(
        'name'           => $name,
        'layout'         => 0,
        'boxcolor'       => 'light',
        'boxcustomcolor' => '',
        'contentcolor'   => 'auto',
        'opacity'        => 100,
        'marker'         => 0,
        'markerimage'    => 0,
        'markersize'     => 30,
        'animation'      => 'pulse',
        'color'          => '#000000',
        'price'          => 0,
        'cart'           => 0,
        'sidebar'        => 0,
        'trigger'        => 'click',
        'mobilebox'      => 'sheet',
        'boxradius'      => 0,
        'maxwidth'       => 0,
    );

    /**
     * Allow add-ons (Pro) to register default values for their own options.
     *
     * @param array $defaults Default option set.
     */
    return apply_filters( 'shoppablelookbook_default_options', $defaults );
}

/**
 * URL of the upgrade/pricing page (Freemius checkout when available).
 */
function shoppablelookbook_upgrade_url() {
    if ( function_exists( 'sl_fs' ) && method_exists( sl_fs(), 'get_upgrade_url' ) ) {
        return sl_fs()->get_upgrade_url();
    }
    return 'https://douple.net/shoppable-lookbook/';
}

/**
 * Small "PRO" badge shown next to Pro-only settings in the editor.
 *
 * Always rendered (also when licensed, as a feature label — same as the
 * Product List / Quick View headings); when the licence is missing the admin
 * JS additionally locks the field and turns the badge into an upgrade link.
 */
function shoppablelookbook_pro_badge() {
    return ' <span class="lb-pro-badge">PRO</span>';
}

/**
 * Clamp a lookbook option set to the free feature set.
 *
 * Applied on save AND on render, so a lookbook configured while a Pro licence
 * was active falls back gracefully (instead of keeping paid styling) when the
 * licence expires or the free version is installed over the Pro one.
 */
function shoppablelookbook_apply_free_limits( $options ) {
    if ( shoppablelookbook_is_pro() ) {
        return $options;
    }

    // Pro: Tag (3) and Bottom (4) box styles (free keeps Inline/Pad/Instagram).
    if ( isset( $options['layout'] ) && in_array( (int) $options['layout'], array( 3, 4 ), true ) ) {
        $options['layout'] = 0;
    }

    // Pro: box corner radius.
    $options['boxradius'] = 0;

    // Pro: custom box colour + opacity (free keeps the light/dark presets).
    if ( isset( $options['boxcolor'] ) && 'custom' === $options['boxcolor'] ) {
        $options['boxcolor'] = 'light';
    }
    $options['boxcustomcolor'] = '';
    $options['opacity']        = 100;

    // Pro: custom marker image/SVG (free keeps the icon set).
    $options['markerimage'] = 0;

    // Pro: beat/bounce animations (free keeps none + pulse).
    if ( isset( $options['animation'] ) && ! in_array( $options['animation'], array( 'none', 'pulse' ), true ) ) {
        $options['animation'] = 'pulse';
    }

    // Pro: openall/openfirst/accordion triggers (free keeps click + hover).
    if ( isset( $options['trigger'] ) && ! in_array( $options['trigger'], array( 'click', 'hover' ), true ) ) {
        $options['trigger'] = 'click';
    }

    // Pro: bottom-sheet mobile display (free uses compact desktop-style).
    $options['mobilebox'] = 'compact';

    return $options;
}

/**
 * Sanitize a raw options array coming from the editor.
 *
 * The raw input is expected to be already unslashed by the caller.
 */
function shoppablelookbook_sanitize_options( $raw ) {
    $raw         = (array) $raw;
    $color       = isset( $raw['color'] ) ? sanitize_hex_color( $raw['color'] ) : '';
    $customcolor = isset( $raw['boxcustomcolor'] ) ? sanitize_hex_color( $raw['boxcustomcolor'] ) : '';
    $opacity     = isset( $raw['opacity'] ) ? (int) $raw['opacity'] : 100;
    $opacity     = max( 0, min( 100, $opacity ) );
    $markersize  = isset( $raw['markersize'] ) ? (int) $raw['markersize'] : 30;
    $markersize  = max( 10, min( 120, $markersize ) );
    $animation   = isset( $raw['animation'] ) ? sanitize_key( $raw['animation'] ) : 'pulse';
    if ( ! in_array( $animation, array( 'none', 'pulse', 'beat', 'bounce' ), true ) ) {
        $animation = 'pulse';
    }
    $contentcolor = isset( $raw['contentcolor'] ) ? sanitize_key( $raw['contentcolor'] ) : 'auto';
    if ( ! in_array( $contentcolor, array( 'auto', 'light', 'dark' ), true ) ) {
        $contentcolor = 'auto';
    }
    $trigger = isset( $raw['trigger'] ) ? sanitize_key( $raw['trigger'] ) : 'click';
    if ( 'always' === $trigger ) {
        $trigger = 'openall'; // legacy value from the first release of this option.
    }
    if ( ! in_array( $trigger, array( 'click', 'hover', 'openall', 'openfirst', 'accordion' ), true ) ) {
        $trigger = 'click';
    }
    $mobilebox = isset( $raw['mobilebox'] ) ? sanitize_key( $raw['mobilebox'] ) : 'sheet';
    if ( ! in_array( $mobilebox, array( 'sheet', 'compact' ), true ) ) {
        $mobilebox = 'sheet';
    }
    $boxradius = isset( $raw['boxradius'] ) ? (int) $raw['boxradius'] : 0;
    $boxradius = max( 0, min( 60, $boxradius ) );
    $maxwidth  = isset( $raw['maxwidth'] ) ? (int) $raw['maxwidth'] : 0;
    $maxwidth  = max( 0, min( 4000, $maxwidth ) ); // 0 = no cap (full width).

    $clean = array(
        'name'           => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
        'layout'         => isset( $raw['layout'] ) ? absint( $raw['layout'] ) : 0,
        'boxcolor'       => isset( $raw['boxcolor'] ) ? sanitize_key( $raw['boxcolor'] ) : 'light',
        'boxcustomcolor' => $customcolor ? $customcolor : '',
        'contentcolor'   => $contentcolor,
        'opacity'        => $opacity,
        'marker'         => isset( $raw['marker'] ) ? absint( $raw['marker'] ) : 0,
        'markerimage'    => isset( $raw['markerimage'] ) ? absint( $raw['markerimage'] ) : 0,
        'markersize'     => $markersize,
        'animation'      => $animation,
        'color'          => $color ? $color : '#000000',
        'price'          => isset( $raw['price'] ) ? absint( $raw['price'] ) : 0,
        'cart'           => isset( $raw['cart'] ) ? absint( $raw['cart'] ) : 0,
        'sidebar'        => isset( $raw['sidebar'] ) ? absint( $raw['sidebar'] ) : 0,
        'trigger'        => $trigger,
        'mobilebox'      => $mobilebox,
        'boxradius'      => $boxradius,
        'maxwidth'       => $maxwidth,
    );

    $clean = shoppablelookbook_apply_free_limits( $clean );

    /**
     * Let add-ons (Pro) sanitize and persist their own option fields.
     *
     * @param array $clean Sanitized free options.
     * @param array $raw   Raw input.
     */
    return apply_filters( 'shoppablelookbook_sanitize_options', $clean, $raw );
}

/**
 * Reduce a stored marker inline style to positional declarations only.
 *
 * Older versions saved the raw style attribute, which could include transient
 * jQuery animation state ("display: none", "opacity: 0.3") and would then
 * permanently hide the marker chip in the editor. Whitelisting top/left/right/
 * bottom with simple length values heals those rows on render and keeps any
 * unexpected CSS out of the database.
 *
 * @param string $style Raw inline style string.
 * @return string Sanitized style string.
 */
function shoppablelookbook_sanitize_position_style( $style ) {
    $allowed = array( 'top', 'left', 'right', 'bottom' );
    $clean   = array();

    foreach ( explode( ';', (string) $style ) as $declaration ) {
        $parts = explode( ':', $declaration, 2 );
        if ( 2 !== count( $parts ) ) {
            continue;
        }
        $prop  = strtolower( trim( $parts[0] ) );
        $value = trim( $parts[1] );
        if ( ! in_array( $prop, $allowed, true ) ) {
            continue;
        }
        if ( ! preg_match( '/^(auto|-?\d+(\.\d+)?(px|%|em|rem)?)$/i', $value ) ) {
            continue;
        }
        $clean[] = $prop . ': ' . $value;
    }

    return $clean ? implode( '; ', $clean ) . ';' : '';
}

/**
 * Sanitize the raw product/marker array coming from the editor.
 *
 * A marker can either link to a WooCommerce product ("product" type) or to any
 * custom URL ("custom" type) with its own title and image. Legacy markers that
 * only carry a product_id are treated as "product" type for backward compat.
 */
function shoppablelookbook_sanitize_products( $raw ) {
    $raw      = (array) $raw;
    $products = array();

    foreach ( $raw as $item ) {
        $item = (array) $item;

        $type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'product';
        if ( 'custom' !== $type ) {
            $type = 'product';
        }

        $base = array(
            'type'         => $type,
            'left'         => isset( $item['left'] ) ? (float) $item['left'] : 0,
            'top'          => isset( $item['top'] ) ? (float) $item['top'] : 0,
            'lit_selected' => isset( $item['lit_selected'] ) ? shoppablelookbook_sanitize_position_style( $item['lit_selected'] ) : '',
            'lit_box'      => isset( $item['lit_box'] ) ? shoppablelookbook_sanitize_position_style( $item['lit_box'] ) : '',
        );

        if ( 'custom' === $type ) {
            $url = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
            if ( empty( $url ) ) {
                continue;
            }
            $products[] = array_merge(
                $base,
                array(
                    'product_id' => 0,
                    'url'        => $url,
                    'title'      => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
                    'image'      => isset( $item['image'] ) ? esc_url_raw( $item['image'] ) : '',
                    'price'      => isset( $item['price'] ) ? sanitize_text_field( $item['price'] ) : '',
                    'target'     => ( isset( $item['target'] ) && $item['target'] ) ? '_blank' : '',
                )
            );
        } else {
            if ( empty( $item['product_id'] ) ) {
                continue;
            }
            $products[] = array_merge(
                $base,
                array(
                    'product_id' => absint( $item['product_id'] ),
                )
            );
        }
    }

    return $products;
}

/**
 * Insert database
 */
function shoppablelookbook_insert_db()
{
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    $table_name = shoppablelookbook_table_name();
    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $media_id   = isset( $_GET['media_id'] ) ? absint( wp_unslash( $_GET['media_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $media_id ) {

        $current_user = wp_get_current_user();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, last id lookup.
        $lookbook_query = $wpdb->get_row( "SELECT id FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id DESC LIMIT 1" );
        $last_lookbook  = isset( $lookbook_query->id ) ? (int) $lookbook_query->id : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table insert.
        $wpdb->insert(
            $table_name,
            array(
                'user_id'          => $current_user->ID,
                'media_id'         => $media_id,
                'lookbook_options' => maybe_serialize( shoppablelookbook_default_options( 'Lookbook ' . ( $last_lookbook + 1 ) ) ),
                'lookbook_date'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        if ( $wpdb->insert_id > 0 ) {
            wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
        }
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_insert_db', 'shoppablelookbook_insert_db' );

/**
 * Update media database
 */
function shoppablelookbook_update_media_db()
{
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    $table_name = shoppablelookbook_table_name();
    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $media_id   = isset( $_GET['media_id'] ) ? absint( wp_unslash( $_GET['media_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $id         = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $media_id && $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table update.
        $wpdb->update(
            $table_name,
            array( 'media_id' => $media_id ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
        wp_send_json_success();
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_update_media_db', 'shoppablelookbook_update_media_db' );

/**
 * Update lookbook database
 */
function shoppablelookbook_update_lookbook_db()
{
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    $table_name = shoppablelookbook_table_name();
    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $id         = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( $id && isset( $_POST['lit_options'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

        // Unslash here; each field is sanitized inside the dedicated helpers below.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_options  = isset( $_POST['lit_options'] ) ? wp_unslash( $_POST['lit_options'] ) : array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_products = isset( $_POST['lit_products'] ) ? wp_unslash( $_POST['lit_products'] ) : array();

        $options  = shoppablelookbook_sanitize_options( $raw_options );
        $products = shoppablelookbook_sanitize_products( $raw_products );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table update.
        $wpdb->update(
            $table_name,
            array(
                'lookbook_options'  => maybe_serialize( $options ),
                'lookbook_products' => maybe_serialize( $products ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        /**
         * Fires after an Image Hotspot has been saved from the editor.
         *
         * Add-ons (Pro) can react to the save — e.g. the Lookbooks module
         * attaches the hotspot to the lookbook it was created from (the
         * editor posts that lookbook's id as "return_lookbook").
         *
         * @param int   $id      Image Hotspot ID.
         * @param array $options Sanitized options.
         */
        do_action( 'shoppablelookbook_lookbook_saved', $id, $options );

        wp_send_json_success();
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_update_lookbook_db', 'shoppablelookbook_update_lookbook_db' );


/**
 * Delete lookbook database
 */
function shoppablelookbook_delete_lookbook_db()
{
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    $table_name = shoppablelookbook_table_name();
    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $id         = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table delete.
        $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success();
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_delete_lookbook_db', 'shoppablelookbook_delete_lookbook_db' );

/**
 * Ajax action to duplicate a lookbook (image, options and markers).
 */
function shoppablelookbook_duplicate_db() {
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( ! $id ) {
        wp_send_json_error();
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook WHERE id = %d", $id ) );
    if ( ! $row ) {
        wp_send_json_error();
    }

    $options = maybe_unserialize( $row->lookbook_options );
    if ( is_array( $options ) ) {
        $name = isset( $options['name'] ) && '' !== $options['name'] ? $options['name'] : 'Lookbook ' . $id;
        /* translators: %s: original lookbook name */
        $options['name'] = sprintf( __( '%s (Copy)', 'shoppable-lookbook' ), $name );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table insert.
    $wpdb->insert(
        shoppablelookbook_table_name(),
        array(
            'user_id'           => get_current_user_id(),
            'media_id'          => (int) $row->media_id,
            'lookbook_options'  => maybe_serialize( $options ),
            'lookbook_products' => $row->lookbook_products,
            'lookbook_date'     => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%s', '%s', '%s' )
    );

    if ( $wpdb->insert_id > 0 ) {
        wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_duplicate_db', 'shoppablelookbook_duplicate_db' );

/**
 * Portable export payload for a single lookbook row (options, markers and the
 * image URL — cross-site imports sideload the image from that URL).
 *
 * @param object $row Lookbook table row.
 * @return array
 */
function shoppablelookbook_export_lookbook_payload( $row ) {
    $image = wp_get_attachment_image_src( $row->media_id, 'full' );

    return array(
        'options'   => maybe_unserialize( $row->lookbook_options ),
        'products'  => maybe_unserialize( $row->lookbook_products ),
        'image_url' => $image ? $image[0] : '',
    );
}

/**
 * Create a lookbook from an export payload (see the export counterpart above).
 *
 * The image is re-used when the URL belongs to this site's media library and
 * sideloaded into it otherwise (cross-site import).
 *
 * @param array $data Payload: { options, products, image_url }.
 * @return int|WP_Error New lookbook ID.
 */
function shoppablelookbook_import_lookbook_payload( $data ) {
    global $wpdb;

    $data     = (array) $data;
    $options  = shoppablelookbook_sanitize_options( isset( $data['options'] ) ? (array) $data['options'] : array() );
    $products = shoppablelookbook_sanitize_products( isset( $data['products'] ) ? (array) $data['products'] : array() );

    if ( '' === $options['name'] ) {
        $options['name'] = __( 'Imported lookbook', 'shoppable-lookbook' );
    }

    // Resolve the image: reuse a local attachment when possible, sideload otherwise.
    $media_id  = 0;
    $image_url = isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '';
    if ( $image_url ) {
        $media_id = (int) attachment_url_to_postid( $image_url );
        if ( ! $media_id ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $sideloaded = media_sideload_image( $image_url, 0, $options['name'], 'id' );
            $media_id   = is_wp_error( $sideloaded ) ? 0 : (int) $sideloaded;
        }
    }

    if ( ! $media_id ) {
        return new WP_Error( 'lookbook_no_image', __( 'The lookbook image could not be imported.', 'shoppable-lookbook' ) );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table insert.
    $wpdb->insert(
        shoppablelookbook_table_name(),
        array(
            'user_id'           => get_current_user_id(),
            'media_id'          => $media_id,
            'lookbook_options'  => maybe_serialize( $options ),
            'lookbook_products' => maybe_serialize( $products ),
            'lookbook_date'     => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%s', '%s', '%s' )
    );

    if ( $wpdb->insert_id > 0 ) {
        return (int) $wpdb->insert_id;
    }
    return new WP_Error( 'lookbook_insert_failed', __( 'Could not import the lookbook.', 'shoppable-lookbook' ) );
}

/**
 * Ajax action to export a lookbook as a downloadable JSON file.
 */
function shoppablelookbook_export() {
    global $wpdb;

    shoppablelookbook_verify_admin_request();

    if ( ! shoppablelookbook_is_pro() ) {
        wp_send_json_error( array( 'message' => __( 'Export is a Pro feature.', 'shoppable-lookbook' ) ), 403 );
    }

    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( ! $id ) {
        wp_send_json_error();
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook WHERE id = %d", $id ) );
    if ( ! $row ) {
        wp_send_json_error();
    }

    $data = array_merge(
        array(
            'plugin'   => 'shoppable-lookbook',
            'version'  => LA_LOOKBOOK_VERSION,
            'exported' => current_time( 'mysql' ),
        ),
        shoppablelookbook_export_lookbook_payload( $row )
    );

    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="shoppable-lookbook-' . $id . '.json"' );
    echo wp_json_encode( $data, JSON_PRETTY_PRINT );
    exit;
}
add_action( 'wp_ajax_shoppablelookbook_export', 'shoppablelookbook_export' );

/**
 * Ajax action to import a lookbook from an exported JSON payload.
 */
function shoppablelookbook_import() {
    shoppablelookbook_verify_admin_request();

    if ( ! shoppablelookbook_is_pro() ) {
        wp_send_json_error( array( 'message' => __( 'Import is a Pro feature.', 'shoppable-lookbook' ) ), 403 );
    }

    // Nonce is verified above; the payload is JSON and sanitized field by field below.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
    $data    = json_decode( $payload, true );

    if ( ! is_array( $data ) || ! isset( $data['plugin'] ) || 'shoppable-lookbook' !== $data['plugin'] ) {
        wp_send_json_error( array( 'message' => __( 'This is not a valid lookbook export file.', 'shoppable-lookbook' ) ) );
    }

    $result = shoppablelookbook_import_lookbook_payload( $data );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array( 'id' => $result ) );
}
add_action( 'wp_ajax_shoppablelookbook_import', 'shoppablelookbook_import' );

/**
 * Count marker product issues for a lookbook.
 *
 * @param array $products Marker data.
 * @return array { missing: int, outofstock: int } Markers whose product was
 *               deleted / unpublished, and markers whose product is out of stock.
 */
function shoppablelookbook_count_product_issues( $products ) {
    $issues = array(
        'missing'    => 0,
        'outofstock' => 0,
    );

    if ( empty( $products ) || ! is_array( $products ) || ! function_exists( 'wc_get_product' ) ) {
        return $issues;
    }

    foreach ( $products as $marker ) {
        $type = isset( $marker['type'] ) ? $marker['type'] : 'product';
        if ( 'custom' === $type ) {
            continue;
        }
        $pid = isset( $marker['product_id'] ) ? absint( $marker['product_id'] ) : 0;
        if ( ! $pid ) {
            continue;
        }
        $product = wc_get_product( $pid );
        if ( ! $product || 'publish' !== get_post_status( $pid ) ) {
            $issues['missing']++;
        } elseif ( ! $product->is_in_stock() ) {
            $issues['outofstock']++;
        }
    }

    return $issues;
}

/**
 * Ajax action to render a live preview of a lookbook in the editor.
 */
function shoppablelookbook_preview() {
    shoppablelookbook_verify_admin_request();

    // Nonce is verified above in shoppablelookbook_verify_admin_request().
    $id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $id ) {
        $html = shoppablelookbook_shortcode( array( 'id' => $id ) );
        wp_send_json_success( array( 'html' => $html ) );
    }
    wp_send_json_error();
}
add_action( 'wp_ajax_shoppablelookbook_preview', 'shoppablelookbook_preview' );

/**
 * Allow administrators to upload SVG files (needed for custom markers).
 */
function shoppablelookbook_allow_svg_upload( $mimes ) {
    // SVG markers are a Pro feature — the free version keeps the icon set.
    if ( shoppablelookbook_is_pro() && current_user_can( 'manage_options' ) ) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter( 'upload_mimes', 'shoppablelookbook_allow_svg_upload' );

/**
 * Make WordPress recognise the SVG file type during upload.
 */
function shoppablelookbook_fix_svg_filetype( $data, $file, $filename, $mimes, $real_mime = '' ) {
    if ( ! shoppablelookbook_is_pro() || ! current_user_can( 'manage_options' ) ) {
        return $data;
    }
    $ext = isset( $data['ext'] ) ? $data['ext'] : '';
    if ( '' === $ext ) {
        $parts = explode( '.', $filename );
        $ext   = strtolower( end( $parts ) );
    }
    if ( 'svg' === $ext || 'svgz' === $ext ) {
        $data['ext']  = $ext;
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'shoppablelookbook_fix_svg_filetype', 10, 5 );

/**
 * Sanitize uploaded SVG files (strip scripts, event handlers, etc.).
 */
function shoppablelookbook_sanitize_svg_upload( $file ) {
    if ( ! isset( $file['type'] ) || 'image/svg+xml' !== $file['type'] ) {
        return $file;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        $file['error'] = __( 'You are not allowed to upload SVG files.', 'shoppable-lookbook' );
        return $file;
    }

    $path = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
    if ( ! $path || ! file_exists( $path ) ) {
        return $file;
    }

    $svg   = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    $clean = shoppablelookbook_clean_svg( $svg );

    if ( false === $clean ) {
        $file['error'] = __( 'This SVG could not be sanitized and was rejected.', 'shoppable-lookbook' );
        return $file;
    }

    file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'shoppablelookbook_sanitize_svg_upload' );

/**
 * Basic SVG sanitizer: removes scripts, event handlers, external/JS references
 * and DOCTYPE/entity declarations. Returns the cleaned markup or false.
 *
 * @param string $svg Raw SVG markup.
 * @return string|false
 */
function shoppablelookbook_clean_svg( $svg ) {
    $svg = (string) $svg;

    // Remove PHP tags just in case.
    $svg = preg_replace( '/<\?(php|=).*?\?>/is', '', $svg );

    // Drop everything before the opening <svg> tag — this strips the XML
    // prolog, DOCTYPE and any entity declarations (XXE / billion-laughs) safely.
    $start = stripos( $svg, '<svg' );
    if ( false === $start ) {
        return false;
    }
    $svg = substr( $svg, $start );

    if ( ! class_exists( 'DOMDocument' ) ) {
        // Conservative fallback when DOM is unavailable.
        $svg = preg_replace( '#<script.*?>.*?</script>#is', '', $svg );
        $svg = preg_replace( '#<(script|foreignObject|iframe|embed|object|use)\b[^>]*>#is', '', $svg );
        $svg = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );
        $svg = preg_replace( '/(?:href|xlink:href)\s*=\s*("\s*(?:javascript|data|vbscript):[^"]*"|\'\s*(?:javascript|data|vbscript):[^\']*\')/i', '', $svg );
        return ( false !== stripos( $svg, '<svg' ) ) ? $svg : false;
    }

    $dom                      = new DOMDocument();
    $dom->preserveWhiteSpace  = false;
    $dom->strictErrorChecking = false;
    libxml_use_internal_errors( true );

    $loaded = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
    libxml_clear_errors();

    if ( ! $loaded ) {
        return false;
    }

    // Remove dangerous elements.
    $bad_tags = array( 'script', 'foreignObject', 'iframe', 'embed', 'object', 'audio', 'video', 'animate', 'animateTransform', 'set', 'handler', 'listener' );
    foreach ( $bad_tags as $tag ) {
        $nodes = $dom->getElementsByTagName( $tag );
        for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
            $node = $nodes->item( $i );
            if ( $node && $node->parentNode ) {
                $node->parentNode->removeChild( $node );
            }
        }
    }

    // Strip event handlers and javascript:/data: references.
    $xpath = new DOMXPath( $dom );
    foreach ( $xpath->query( '//*' ) as $el ) {
        if ( ! $el->hasAttributes() ) {
            continue;
        }
        $remove = array();
        foreach ( $el->attributes as $attr ) {
            $name = strtolower( $attr->nodeName );
            $val  = trim( (string) $attr->nodeValue );
            if ( 0 === strpos( $name, 'on' ) ) {
                $remove[] = $attr;
            } elseif ( ( 'href' === $name || 'xlink:href' === $name ) && preg_match( '/^\s*(javascript|data|vbscript):/i', $val ) ) {
                $remove[] = $attr;
            } elseif ( 'style' === $name && preg_match( '/(expression|javascript:|vbscript:)/i', $val ) ) {
                $remove[] = $attr;
            }
        }
        foreach ( $remove as $attr ) {
            $el->removeAttributeNode( $attr );
        }
    }

    $clean = $dom->saveXML( $dom->documentElement, LIBXML_NOEMPTYTAG );
    if ( false === $clean || false === stripos( $clean, '<svg' ) ) {
        return false;
    }
    return $clean;
}

/**
 * Require WooCommerce plugin
 */
function shoppablelookbook_require_plugins(){

    // Only show this notice on the plugin's own admin screens (read-only, no nonce).
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( 'shoppable-lookbook' !== $page && 'new-shoppable-lookbook' !== $page ) {
        return;
    }

    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
        // WooCommerce is optional (custom links still work), so this is just a notice.
        echo '<div class="notice notice-warning is-dismissible notice-shoppablelookbook">
             <p>' . esc_html__( 'WooCommerce is not active. You can still create lookbooks with custom links, but product markers require WooCommerce.', 'shoppable-lookbook' ) . '</p>
             <p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">&laquo; ' . esc_html__( 'Return to Plugins', 'shoppable-lookbook' ) . '</a></p>
         </div>';
    }
}
add_action('admin_notices', 'shoppablelookbook_require_plugins');
