<?php
/**
 * Gutenberg
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
    Register Gutenberg block category
*/
function shoppablelookbook_block_category( $categories ) {
    // Avoid registering the category twice.
    foreach ( $categories as $category ) {
        if ( isset( $category['slug'] ) && 'douple' === $category['slug'] ) {
            return $categories;
        }
    }

    return array_merge(
        $categories,
        array(
            array(
                'slug'  => 'douple',
                'title' => __( 'Douple', 'shoppable-lookbook' ),
            ),
        )
    );
}

// "block_categories" was deprecated in WordPress 5.8 in favour of "block_categories_all".
if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
    add_filter( 'block_categories_all', 'shoppablelookbook_block_category', 10, 1 );
} else {
    add_filter( 'block_categories', 'shoppablelookbook_block_category', 10, 1 );
}

/*
    Register the dynamic block (server-side rendered via the shortcode)
*/
function shoppablelookbook_register_block() {

    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    wp_register_script(
        'shoppable-lookbook-gutenberg',
        plugin_dir_url( __FILE__ ) . 'js/shoppable-lookbook-gutenberg.js',
        array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
        LA_LOOKBOOK_VERSION,
        true
    );

    wp_localize_script(
        'shoppable-lookbook-gutenberg',
        'litLookbookBlock',
        array(
            'lookbooks' => shoppablelookbook_get_lookbook_choices(),
        )
    );

    if ( function_exists( 'wp_set_script_translations' ) ) {
        wp_set_script_translations( 'shoppable-lookbook-gutenberg', 'shoppable-lookbook', LA_LOOKBOOK_PLUGIN_PATH . 'languages' );
    }

    wp_register_style(
        'shoppable-lookbook-gutenberg-style',
        plugin_dir_url( __FILE__ ) . 'css/shoppable-lookbook-gutenberg.css',
        array(),
        LA_LOOKBOOK_VERSION
    );

    register_block_type(
        'douple/shoppable-lookbook',
        array(
            'editor_script'   => 'shoppable-lookbook-gutenberg',
            'editor_style'    => 'shoppable-lookbook-gutenberg-style',
            'attributes'      => array(
                'id' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
            ),
            'render_callback' => 'shoppablelookbook_block_render',
        )
    );
}
add_action( 'init', 'shoppablelookbook_register_block' );

/**
 * Load the front-end stylesheet inside the editor so the ServerSideRender
 * preview is styled the same as the published page.
 */
function shoppablelookbook_block_editor_styles() {
    wp_enqueue_style(
        'shoppable-lookbook',
        LA_LOOKBOOK_PLUGIN_URL . '/assets/css/shoppable-lookbook.css',
        array(),
        LA_LOOKBOOK_VERSION
    );
    // The front-end script too: add-ons below declare it as a dependency
    // (enqueueing against an unregistered handle trips a WP 6.9 notice).
    wp_enqueue_script(
        'shoppable-lookbook',
        LA_LOOKBOOK_PLUGIN_URL . '/assets/js/shoppable-lookbook.js',
        array( 'jquery' ),
        LA_LOOKBOOK_VERSION,
        true
    );

    /**
     * Let add-ons (Pro) style the block-editor preview too — Product List,
     * Shop the Look, Quick View, Gallery. Same hook the front-end and the
     * admin Preview fire; $is_preview = true skips front-only behaviour
     * (analytics impression tracking).
     */
    do_action( 'shoppablelookbook_enqueue_assets', true );
}
add_action( 'enqueue_block_editor_assets', 'shoppablelookbook_block_editor_styles' );

/**
 * Server-side render callback: outputs the lookbook via the shortcode.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function shoppablelookbook_block_render( $attributes ) {
    $id = isset( $attributes['id'] ) ? absint( $attributes['id'] ) : 0;
    if ( ! $id ) {
        return '';
    }
    return shoppablelookbook_shortcode( array( 'id' => $id ) );
}
