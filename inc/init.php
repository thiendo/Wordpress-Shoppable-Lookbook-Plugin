<?php
/**
 * Enqueue scripts and styles
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

/*
    Load Backend Scripts
*/
function shoppablelookbook_admin_scripts( $hook ) {
    // Read-only check of the current admin screen; no nonce required.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    // Freemius Account screen: only the dark re-skin (the SDK renders the
    // page itself; its templates must not be edited).
    if ( 'shoppable-lookbook-account' === $page ) {
        wp_enqueue_style( 'lookbook-account-style', plugin_dir_url( __DIR__ ) . 'assets/css/lookbook-account.css', array(), LA_LOOKBOOK_VERSION );
        return;
    }

    if ( 'shoppable-lookbook' != $page && 'new-shoppable-lookbook' != $page ) {
        return;
    }

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_style( 'material-icons-style', plugin_dir_url( __DIR__ ) . 'assets/css/material-icons.css', array(), LA_LOOKBOOK_VERSION );
    wp_enqueue_style( 'lookbook-init-style', plugin_dir_url( __DIR__ ) . 'assets/css/lookbook-init.css', array(), LA_LOOKBOOK_VERSION );
    wp_enqueue_style( 'lookbook-admin-style', plugin_dir_url( __DIR__ ) . 'assets/css/lookbook-admin.css', array(), LA_LOOKBOOK_VERSION );

    // Use the jQuery + jQuery UI shipped with WordPress core instead of
    // bundling our own copy (which would break other plugins/themes).
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'jquery-ui-draggable' );
    wp_enqueue_script( 'wp-color-picker' );
    //Enqueue media.
    wp_enqueue_media();
    wp_enqueue_script(
        'lookbook-admin-script',
        plugin_dir_url( __DIR__ ) . 'assets/js/lookbook-admin.js',
        array( 'jquery', 'jquery-ui-draggable', 'wp-color-picker' ),
        LA_LOOKBOOK_VERSION,
        true
    );

    // Front-end assets so the in-editor "Preview" overlay is styled and interactive.
    wp_enqueue_style( 'shoppable-lookbook', plugin_dir_url( __DIR__ ) . 'assets/css/shoppable-lookbook.css', array(), LA_LOOKBOOK_VERSION );
    wp_enqueue_script( 'shoppable-lookbook', plugin_dir_url( __DIR__ ) . 'assets/js/shoppable-lookbook.js', array( 'jquery' ), LA_LOOKBOOK_VERSION, true );
    wp_localize_script(
        'shoppable-lookbook',
        'shoppableLookbookFront',
        array(
            'i18n' => array(
                'adding' => __( 'Adding…', 'shoppable-lookbook' ),
                'added'  => __( 'Added ✓', 'shoppable-lookbook' ),
            ),
        )
    );

    // Let add-ons (Pro) style the in-editor Preview too. The $is_preview flag lets
    // modules load their visual CSS while skipping front-only behaviour such as
    // analytics impression tracking.
    do_action( 'shoppablelookbook_enqueue_assets', true );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation param.
    $return_lb = isset( $_GET['return_lookbook'] ) ? absint( wp_unslash( $_GET['return_lookbook'] ) ) : 0;

    wp_localize_script(
        'lookbook-admin-script',
        'litLookbook',
        array(
            'ajaxurl'           => admin_url( 'admin-ajax.php' ),
            'uploadUrl'         => admin_url( 'async-upload.php' ),
            'nonce'             => wp_create_nonce( 'shoppablelookbook_admin' ),
            'isPro'             => shoppablelookbook_is_pro() ? 1 : 0,
            'upgradeUrl'        => shoppablelookbook_upgrade_url(),
            'returnLookbookUrl' => $return_lb ? admin_url( 'admin.php?page=shoppable-lookbook-galleries&action=edit&id=' . $return_lb ) : '',
            'returnLookbookId'  => $return_lb,
            'i18n'    => array(
                'selectProduct'    => __( 'Select a product', 'shoppable-lookbook' ),
                'searchProduct'    => __( 'Search a product...', 'shoppable-lookbook' ),
                'selectImage'      => __( 'Select Image', 'shoppable-lookbook' ),
                'addLink'          => __( 'Add a link', 'shoppable-lookbook' ),
                'productTab'       => __( 'Product', 'shoppable-lookbook' ),
                'customTab'        => __( 'Custom Link', 'shoppable-lookbook' ),
                'urlPlaceholder'   => __( 'Link URL (https://...)', 'shoppable-lookbook' ),
                'titlePlaceholder' => __( 'Title', 'shoppable-lookbook' ),
                'pricePlaceholder' => __( 'Price (optional)', 'shoppable-lookbook' ),
                'selectImageBtn'   => __( 'Select image', 'shoppable-lookbook' ),
                'openNewTab'       => __( 'Open in new tab', 'shoppable-lookbook' ),
                'applyLink'        => __( 'Apply link', 'shoppable-lookbook' ),
                'urlRequired'      => __( 'Please enter a link URL.', 'shoppable-lookbook' ),
                'previewTitle'     => __( 'Preview', 'shoppable-lookbook' ),
                'duplicateFailed'  => __( 'Could not duplicate the lookbook.', 'shoppable-lookbook' ),
                'importFailed'     => __( 'Could not import the lookbook.', 'shoppable-lookbook' ),
                'noMarkers'        => __( 'No markers yet. Click on the image to add one.', 'shoppable-lookbook' ),
                'unlinkedMarker'   => __( '(no link yet)', 'shoppable-lookbook' ),
            ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'shoppablelookbook_admin_scripts' );

/*
    Load Frontend Scripts
*/
function shoppablelookbook_front_scripts() {
    wp_enqueue_style( 'shoppable-lookbook', plugin_dir_url( __DIR__ ) . 'assets/css/shoppable-lookbook.css', array(), LA_LOOKBOOK_VERSION );

    wp_enqueue_script( 'shoppable-lookbook', plugin_dir_url( __DIR__ ) . 'assets/js/shoppable-lookbook.js', array( 'jquery' ), LA_LOOKBOOK_VERSION, true );

    wp_localize_script(
        'shoppable-lookbook',
        'shoppableLookbookFront',
        array(
            'i18n' => array(
                'adding' => __( 'Adding…', 'shoppable-lookbook' ),
                'added'  => __( 'Added ✓', 'shoppable-lookbook' ),
            ),
        )
    );

    // Enable WooCommerce AJAX add-to-cart on lookbook markers when available.
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
    }

    /**
     * Let add-ons (Pro) enqueue their own front-end assets (quick-view, etc.).
     *
     * @param bool $is_preview Whether this is the in-editor admin Preview (false on the real front-end).
     */
    do_action( 'shoppablelookbook_enqueue_assets', false );
}
add_action( 'wp_enqueue_scripts', 'shoppablelookbook_front_scripts' );
