<?php
/**
 * AJAX Handler Class
 * 
 * Handles AJAX requests for import/export
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Ajax_Handler {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_dca_export_set', array( $this, 'export_set' ) );
		add_action( 'wp_ajax_dca_import_set', array( $this, 'import_set' ) );
	}
	
	/**
	 * Export attribute set
	 */
	public function export_set() {
		check_ajax_referer( 'dca_export', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'dokan-category-attributes' ) );
		}
		
		$set_id = isset( $_POST['set_id'] ) ? intval( $_POST['set_id'] ) : 0;
		
		if ( ! $set_id ) {
			wp_send_json_error( __( 'Invalid set ID', 'dokan-category-attributes' ) );
		}
		
		$manager = new DCA_Attribute_Manager();
		$json = $manager->export_to_json( $set_id );
		
		if ( is_wp_error( $json ) ) {
			wp_send_json_error( $json->get_error_message() );
		}
		
		wp_send_json_success( $json );
	}
	
	/**
	 * Import attribute set
	 */
	public function import_set() {
		check_ajax_referer( 'dca_import', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'dokan-category-attributes' ) );
		}
		
		$json_data = isset( $_POST['json_data'] ) ? wp_unslash( $_POST['json_data'] ) : '';
		
		if ( empty( $json_data ) ) {
			wp_send_json_error( __( 'No data provided', 'dokan-category-attributes' ) );
		}
		
		$manager = new DCA_Attribute_Manager();
		$result = $manager->import_from_json( $json_data );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		
		wp_send_json_success( __( 'Imported successfully', 'dokan-category-attributes' ) );
	}
}
