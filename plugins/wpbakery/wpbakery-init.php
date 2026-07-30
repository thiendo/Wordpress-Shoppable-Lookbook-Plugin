<?php
/**
 * Visual Composer
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
    Register Visual Composer / WPBakery Element

    Hooked to "vc_before_init" so it runs after WPBakery has loaded,
    regardless of plugin load order.
*/
function shoppablelookbook_wpbakery_map() {

    if ( ! function_exists( 'vc_map' ) ) {
        return;
    }

    // WPBakery integration is a Pro feature (the shortcode itself stays free —
    // free users can still paste it into a text element).
    if ( ! shoppablelookbook_is_pro() ) {
        return;
    }

    global $wpdb;
    $arr = array();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbooks  = $wpdb->get_results( "SELECT id, lookbook_options FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id ASC" );
    foreach ( $lookbooks as $lookbook ) {
        $options = maybe_unserialize( $lookbook->lookbook_options );
        $name    = isset( $options['name'] ) && '' !== $options['name'] ? $options['name'] : 'Lookbook ' . (int) $lookbook->id;
        $arr[ $name ] = $lookbook->id;
    }

    vc_map(
        array(
            'name'                    => __( 'Shoppable Lookbook', 'shoppable-lookbook' ),
            'base'                    => 'shoppablelookbook',
            'icon'                    => plugin_dir_url( __FILE__ ) . 'img/shoppablelookbook-icon.png',
            'show_settings_on_create' => true,
            'params'                  => array(
                array(
                    'type'        => 'dropdown',
                    'heading'     => __( 'Shoppable Lookbook', 'shoppable-lookbook' ),
                    'description' => __( 'Select a lookbook to display', 'shoppable-lookbook' ),
                    'param_name'  => 'id',
                    'admin_label' => true,
                    'value'       => $arr,
                    'save_always' => true,
                ),
            ),
        )
    );
}
add_action( 'vc_before_init', 'shoppablelookbook_wpbakery_map' );
