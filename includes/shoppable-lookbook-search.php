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
 * Search Products
 */
function shoppablelookbook_search() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'shoppable-lookbook' ) ), 403 );
    }
    check_ajax_referer( 'shoppablelookbook_admin', '_ajax_nonce' );

    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

    $html = '';
    $the_query = new WP_Query(
                    array(
                        'posts_per_page' => 5,
                        's'              => $keyword,
                        'post_type'      => 'product',
                    ) );

    if( $the_query->have_posts() ) :
        while( $the_query->have_posts() ): $the_query->the_post();
            $thumbnail = get_the_post_thumbnail_url( get_the_id(), 'thumbnail' );

            $html .= '<a href="#" data-product="' . esc_attr( get_the_id() ) . '"><img src="' . esc_url( $thumbnail ) . '" alt="" />' . esc_html( substrword( get_the_title(), 40 ) ) . '</a>';

        endwhile;
        wp_reset_postdata();
    endif;

    wp_send_json_success( array( 'html' => $html ) );
}
add_action('wp_ajax_shoppablelookbook_search' , 'shoppablelookbook_search');

/**
 * Truncate a string to a maximum number of characters without breaking words.
 *
 * Uses multibyte-safe functions so non-Latin product names (e.g. Vietnamese,
 * Chinese, Arabic) are not corrupted.
 */
if ( ! function_exists( 'substrword' ) ) {
    function substrword( $text, $maxchar, $end = '...' ) {

        $text = (string) $text;

        if ( mb_strlen( $text ) <= $maxchar ) {
            return trim( $text );
        }

        $words = explode( ' ', $text );

        if ( count( $words ) > 1 ) {
            $output = '';
            foreach ( $words as $word ) {
                if ( mb_strlen( $output . ' ' . $word ) > $maxchar ) {
                    break;
                }
                $output .= ' ' . $word;
            }
            // If the very first word already exceeds the limit, hard-cut it.
            if ( '' === trim( $output ) ) {
                $output = mb_substr( $text, 0, max( 0, $maxchar - mb_strlen( $end ) ) );
            }
        } else {
            $output = mb_substr( $text, 0, max( 0, $maxchar - mb_strlen( $end ) ) );
        }

        return trim( $output ) . $end;
    }
}
