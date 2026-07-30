<?php
/**
 * Fusion Builder
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
    Register Fusion Builder Element
*/
function fusion_shoppable_lookbook() {

    if ( ! function_exists( 'fusion_builder_map' ) ) {
        return;
    }

    // Fusion Builder integration is a Pro feature (the shortcode itself stays
    // free — free users can still paste it into a text element).
    if ( ! shoppablelookbook_is_pro() ) {
        return;
    }

    // Auto-activate the element in Fusion Builder when supported.
    if ( function_exists( 'fusion_builder_auto_activate_element' ) ) {
        fusion_builder_auto_activate_element( 'shoppablelookbook' );
    }

    global $wpdb;
    $arr = array();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbooks = $wpdb->get_results( "SELECT id, lookbook_options FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id DESC" );
    foreach ($lookbooks as $lookbook) {
        $options = maybe_unserialize($lookbook->lookbook_options);
        $arr[$lookbook->id] = isset( $options['name'] ) ? $options['name'] : '';
    }

    fusion_builder_map( 
        array(
            'name'            => esc_attr__( 'Shoppable Lookbook', 'shoppable-lookbook' ),
            'shortcode'       => 'shoppablelookbook',
            'icon'            => 'fusiona-plus',
            'preview_id'      => 'fusion-builder-block-module-text-preview-template',
            'allow_generator' => true,
            'params'          => array(
                array(
                    'type'        => 'select',
                    'heading'     => esc_attr__( 'Shoppable Lookbook', 'shoppable-lookbook' ),
                    'description' => esc_attr__( 'Select a lookbook to display', 'shoppable-lookbook' ),
                    'param_name'  => 'id',
                    'value'       => $arr
                ),
            ),
        ) 
    );
}
add_action( 'fusion_builder_before_init', 'fusion_shoppable_lookbook' );
?>