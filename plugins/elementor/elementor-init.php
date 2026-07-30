<?php
/**
 * Elementor Init
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
    Elementor Init
*/
class ShoppableLookbookElementor {
	
	public static function init() {

		$min_elementor_version = '2.0.0';
		$min_php_version = '7.0';

		// Check for required Elementor version
		if(!version_compare(ELEMENTOR_VERSION, $min_elementor_version, '>=' )) return;
		
		// Check for required PHP version
		if(version_compare(PHP_VERSION, $min_php_version, '<')) return;
		
		// Register the widget. "elementor/widgets/register" replaced the
		// deprecated "elementor/widgets/widgets_registered" hook in Elementor 3.5.
		if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			add_action( 'elementor/widgets/register', array( 'ShoppableLookbookElementor', 'register_elementor_widget' ) );
		} else {
			add_action( 'elementor/widgets/widgets_registered', array( 'ShoppableLookbookElementor', 'init_elementor_widgets' ) );
		}

	}

	public static function register_elementor_widget( $widgets_manager ) {

		// Include Widget files
		require_once( plugin_dir_path( __FILE__ ) . 'elementor-widget.php' );

		// Register widget (Elementor 3.5+)
		$widgets_manager->register( new ShoppableLookbookElementorWidget() );

	}

	public static function init_elementor_widgets() {

		// Include Widget files
		require_once(plugin_dir_path( __FILE__) . 'elementor-widget.php');

		// Register widget (legacy Elementor < 3.5)
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->register_widget_type( new ShoppableLookbookElementorWidget() );

	}
	
}

// Run once Elementor is loaded so ELEMENTOR_VERSION is available and the
// widget registration hooks are guaranteed to fire.
add_action( 'elementor/loaded', array( 'ShoppableLookbookElementor', 'init' ) );
