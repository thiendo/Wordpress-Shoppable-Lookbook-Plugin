<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 * @link              https://douple.net
 * @since             1.1.4
 * @package           Shoppable_Lookbook
 * @wordpress-plugin
 * Plugin Name:       Shoppable Lookbook
 * Plugin URI:        https://douple.net/shoppable-lookbook/
 * Description:       Tag products on your photos to turn them into shoppable images. Supports drag & drop markers to mark your products.
 * Version:           1.8.2
 * Author:            Douple
 * Author URI:        https://douple.net
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shoppable-lookbook
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * WC requires at least: 3.0
 */

// If this file is called directly, abort.
if ( !defined('ABSPATH') ) {
    die;
}

if ( !defined('LA_LOOKBOOK_NAME') ) {
    define('LA_LOOKBOOK_NAME', 'Shoppable Lookbook');
}

if ( !defined('LA_LOOKBOOK_VERSION') ) {
    define('LA_LOOKBOOK_VERSION', '1.8.2');
}

if ( !defined('LA_LOOKBOOK_TEXT_DOMAIN') ) {
    define('LA_LOOKBOOK_TEXT_DOMAIN', 'shoppable-lookbook');
}

$lookbook_plugin_dir = plugin_dir_path(__FILE__);

if ( !defined('LA_LOOKBOOK_PLUGIN_PATH') ) {
    define('LA_LOOKBOOK_PLUGIN_PATH', $lookbook_plugin_dir);
}

define('LA_LOOKBOOK_PLUGIN_URL', plugins_url() . '/' . basename(plugin_dir_path(__FILE__)));

/**
 * Freemius SDK initialization (premium / Freemius-distributed builds only).
 *
 * The wordpress.org / GitHub free package does NOT ship the freemius/ folder, so
 * this block is skipped there — no remote updater, no opt-in phone-home. Pro is
 * sold as a separate premium zip from douple.net / Freemius checkout.
 */
if ( file_exists( dirname( __FILE__ ) . '/freemius/start.php' ) && ! function_exists( 'sl_fs' ) ) {
    function sl_fs() {
        global $sl_fs;

        if ( ! isset( $sl_fs ) ) {
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            // This working copy is the PREMIUM codebase. Freemius automatically
            // flips is_premium to false (and strips every *__premium_only
            // file/folder) when it generates a free build from premium.
            $sl_fs = fs_dynamic_init( array(
                'id'                  => '32876',
                'slug'                => 'shoppable-lookbook',
                'type'                => 'plugin',
                'public_key'          => 'pk_b90cbbbbcd0f9d075609038bcd7af',
                'is_premium'          => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                // 'wp_org_gatekeeper' => 'PASTE_FROM_FREEMIUS_DASHBOARD',
                'menu'                => array(
                    'slug'    => 'shoppable-lookbook',
                    'account' => true,
                    'pricing' => true,
                    'contact' => false,
                    'support' => false,
                ),
            ) );
        }

        return $sl_fs;
    }

    sl_fs();
    do_action( 'sl_fs_loaded' );
}

/**
 * Uninstall cleanup.
 *
 * When Freemius is present, hook via Freemius `after_uninstall` so its own
 * uninstall survey still runs. When Freemius is absent (wordpress.org free
 * package), use a normal uninstall hook instead.
 */
function shoppablelookbook_uninstall_site() {
    global $wpdb;

    $delete_data = get_option( 'shoppablelookbook_delete_data' );

    // Always remove our bookkeeping options.
    delete_option( 'douple_lookbook_version' );
    delete_option( 'shoppablelookbook_delete_data' );

    // Only drop user content when explicitly opted in.
    if ( 'yes' === $delete_data ) {
        // Table name is built from the trusted DB prefix and cannot be parameterised.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}shoppable_lookbook`" );
    }
}

function shoppablelookbook_after_uninstall() {
    if ( is_multisite() ) {
        $site_ids = get_sites( array( 'fields' => 'ids' ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            shoppablelookbook_uninstall_site();
            restore_current_blog();
        }
    } else {
        shoppablelookbook_uninstall_site();
    }
}

if ( function_exists( 'sl_fs' ) ) {
    sl_fs()->add_action( 'after_uninstall', 'shoppablelookbook_after_uninstall' );
} else {
    register_uninstall_hook( __FILE__, 'shoppablelookbook_after_uninstall' );
}

/**
 * Defines the core plugin class
 */
require plugin_dir_path(__FILE__) . 'includes/shoppable-lookbook-init.php';
require plugin_dir_path(__FILE__) . 'includes/shoppable-lookbook-admin.php';
require plugin_dir_path(__FILE__) . 'includes/shoppable-lookbook-search.php';
require plugin_dir_path(__FILE__) . 'includes/shoppable-lookbook-frontend.php';
require plugin_dir_path(__FILE__) . 'includes/shoppable-lookbook-list.php';
require plugin_dir_path(__FILE__) . 'plugins/wpbakery/wpbakery-init.php';
require plugin_dir_path(__FILE__) . 'plugins/elementor/elementor-init.php';
require plugin_dir_path(__FILE__) . 'plugins/gutenberg/gutenberg-init.php';
require plugin_dir_path(__FILE__) . 'plugins/fusionbuilder/fusionbuilder-init.php';

/**
 * Bridge the plugin's "is Pro" extension hook to the Freemius licence state.
 *
 * Pro modules call shoppablelookbook_is_pro() (filter "shoppablelookbook_is_pro")
 * to decide whether to activate. We answer it from Freemius: a feature is "Pro"
 * only when the premium code is present AND the user may use it (paid / trial).
 */
add_filter( 'shoppablelookbook_is_pro', function ( $is_pro ) {
    // Local dev override: define('DOUPLELOOKBOOK_PRO_DEV', true) in wp-config.php
    // to unlock Pro without a real Freemius licence. Never set on a live site.
    if ( defined( 'DOUPLELOOKBOOK_PRO_DEV' ) && DOUPLELOOKBOOK_PRO_DEV ) {
        return true;
    }
    if ( function_exists( 'sl_fs' ) && method_exists( sl_fs(), 'can_use_premium_code' ) ) {
        return sl_fs()->can_use_premium_code();
    }
    return $is_pro;
} );

/**
 * PRO modules.
 *
 * These files live in includes/pro__premium_only/ — Freemius strips that whole
 * folder from the free wordpress.org build, so the free version ships with NO
 * Pro code at all. They are only loaded in the premium build (is__premium_only),
 * and each module additionally gates its own behaviour by the licence state via
 * shoppablelookbook_is_pro(). The file_exists() guard keeps the free build safe.
 */
$shoppablelookbook_load_pro = function_exists( 'sl_fs' ) && sl_fs()->is__premium_only();
if ( $shoppablelookbook_load_pro ) {
    foreach ( array( 'quick-view', 'shop-the-look', 'analytics', 'multi-image', 'gallery', 'product-list', 'product-page' ) as $shoppablelookbook_pro_module ) {
        $shoppablelookbook_pro_file = plugin_dir_path( __FILE__ ) . 'includes/pro__premium_only/' . $shoppablelookbook_pro_module . '.php';
        if ( file_exists( $shoppablelookbook_pro_file ) ) {
            require $shoppablelookbook_pro_file;
        }
    }
    unset( $shoppablelookbook_pro_module, $shoppablelookbook_pro_file );
}
unset( $shoppablelookbook_load_pro );

/**
 * Enqueue scripts and styles
 */
if ( function_exists( 'register_block_type' ) ) {
    require plugin_dir_path(__FILE__) . 'inc/init.php';
}

/**
 * Load plugin translations.
 */
function shoppablelookbook_load_textdomain() {
    // Kept so translations bundled in /languages also load outside wordpress.org.
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
    load_plugin_textdomain( 'shoppable-lookbook', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'shoppablelookbook_load_textdomain' );

/**
 * Create the database table on activation.
 */
register_activation_hook( __FILE__, 'shoppablelookbook_create_db' );
