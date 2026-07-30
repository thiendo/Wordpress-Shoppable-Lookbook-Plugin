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
 * Return all lookbooks as an array of { value, label } choices,
 * suitable for a block editor SelectControl.
 */
function shoppablelookbook_get_lookbook_choices() {
    global $wpdb;

    $choices = array();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbooks = $wpdb->get_results( "SELECT id, lookbook_options FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id ASC" );
    foreach ( $lookbooks as $lookbook ) {
        $options  = maybe_unserialize( $lookbook->lookbook_options );
        $name     = isset( $options['name'] ) && '' !== $options['name'] ? $options['name'] : 'Lookbook ' . (int) $lookbook->id;
        $choices[] = array(
            'value' => (string) (int) $lookbook->id,
            'label' => $name,
        );
    }

    return $choices;
}

/**
 * Lookbook List
 */
function shoppablelookbook_list() {

    global $wpdb;

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'shoppable-lookbook' ) ), 403 );
    }
    check_ajax_referer( 'shoppablelookbook_admin', '_ajax_nonce' );

    $selectedvalue = isset( $_POST['selected_value'] ) ? absint( wp_unslash( $_POST['selected_value'] ) ) : 0;
    $html = '';

    if ( empty( $selectedvalue ) ) {
        $html .= '<option value="0">' . esc_html__( 'Select a lookbook', 'shoppable-lookbook' ) . '</option>';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
    $lookbooks = $wpdb->get_results( "SELECT id, lookbook_options FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id ASC" );
    foreach ( $lookbooks as $lookbook ) {
        $options  = maybe_unserialize( $lookbook->lookbook_options );
        $name     = isset( $options['name'] ) ? $options['name'] : '';
        $selected = selected( $selectedvalue, (int) $lookbook->id, false );
        $html    .= '<option value="' . esc_attr( $lookbook->id ) . '" ' . $selected . '>' . esc_html( $name ) . '</option>';
    }

    wp_send_json_success( $html );
}
add_action('wp_ajax_shoppablelookbook_list' , 'shoppablelookbook_list');
?>