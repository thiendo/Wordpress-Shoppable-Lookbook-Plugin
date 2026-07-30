<?php
/**
 * Elementor Widget
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
    Register Elementor Widget
*/
class ShoppableLookbookElementorWidget extends \Elementor\Widget_Shortcode {

	public function get_name() {
		
		return 'shoppablelookbook';
		
	}

	public function get_title() {
		
		return 'Shoppable Lookbook';
		
	}

	public function get_icon() {
		
		return 'eicon-plus-circle-o';
		
	}

	public function get_categories() {
		
		return array('general');
		
	}

	protected function register_controls() {

        global $wpdb;
        $arr = array();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table read.
        $lookbooks = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}shoppable_lookbook ORDER BY id ASC" );
        foreach ($lookbooks as $lookbook) {
            $options = maybe_unserialize($lookbook->lookbook_options);
            $arr[$lookbook->id] = isset( $options['name'] ) ? $options['name'] : '';
        }
        
		$this->start_controls_section(
			'content_section',
			array(
				'label' => 'Shoppable Lookbook',
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'shoppablelookbookselect',
			array(
				'label' => __( 'Select a lookbook', 'shoppable-lookbook' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'solid',
				'options' => $arr,
			)
		);
        
		$this->end_controls_section();	
	}

	protected function render() {
		
		$settings = $this->get_settings_for_display();
        $id       = isset( $settings['shoppablelookbookselect'] ) ? absint( $settings['shoppablelookbookselect'] ) : 0;
        echo do_shortcode( '[' . $this->get_name() . ' id=' . $id . ']' );

	}
}
?>