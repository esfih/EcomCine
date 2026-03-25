<?php
/**
 * Admin Menu Class
 * 
 * Registers admin menu and pages
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Admin_Menu {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 100 );
	}
	
	/**
	 * Register admin menu
	 */
	public function register_menu() {
		// Add as submenu under Dokan
		add_submenu_page(
			'dokan',
			__( 'Category Attributes', 'dokan-category-attributes' ),
			__( 'Category Attributes', 'dokan-category-attributes' ),
			'manage_options',
			'dokan-category-attributes',
			array( $this, 'render_attribute_sets_page' )
		);
		
		// Add field builder page (hidden from menu)
		add_submenu_page(
			null, // Parent slug null means hidden
			__( 'Edit Attribute Set', 'dokan-category-attributes' ),
			__( 'Edit Attribute Set', 'dokan-category-attributes' ),
			'manage_options',
			'dokan-category-attributes-builder',
			array( $this, 'render_field_builder_page' )
		);
	}
	
	/**
	 * Render attribute sets list page
	 */
	public function render_attribute_sets_page() {
		// Handle actions
		if ( isset( $_GET['action'] ) && isset( $_GET['set_id'] ) && check_admin_referer( 'dca_action' ) ) {
			$set_id = intval( $_GET['set_id'] );
			$manager = new DCA_Attribute_Manager();
			
			switch ( $_GET['action'] ) {
				case 'delete':
					$manager->delete_attribute_set( $set_id );
					echo '<div class="notice notice-success"><p>' . __( 'Attribute set deleted.', 'dokan-category-attributes' ) . '</p></div>';
					break;
					
				case 'duplicate':
					$new_id = $manager->duplicate_attribute_set( $set_id );
					if ( ! is_wp_error( $new_id ) ) {
						echo '<div class="notice notice-success"><p>' . __( 'Attribute set duplicated.', 'dokan-category-attributes' ) . '</p></div>';
					}
					break;
					
				case 'toggle_status':
					$set = $manager->get_attribute_set( $set_id );
					$new_status = ( $set->status === 'active' ) ? 'inactive' : 'active';
					$manager->update_attribute_set( $set_id, array( 'status' => $new_status ) );
					echo '<div class="notice notice-success"><p>' . __( 'Status updated.', 'dokan-category-attributes' ) . '</p></div>';
					break;
			}
		}
		
		require_once DCA_PLUGIN_DIR . 'includes/admin/views/attribute-sets-list.php';
	}
	
	/**
	 * Render field builder page
	 */
	public function render_field_builder_page() {
		$set_id = isset( $_GET['set_id'] ) ? intval( $_GET['set_id'] ) : 0;
		
		// Handle form submission
		if ( isset( $_POST['dca_save_attribute_set'] ) && check_admin_referer( 'dca_save_set' ) ) {
			$this->save_attribute_set( $set_id );
		}
		
		require_once DCA_PLUGIN_DIR . 'includes/admin/views/field-builder.php';
	}
	
	/**
	 * Save attribute set and fields
	 * 
	 * @param int $set_id Set ID (0 for new)
	 */
	private function save_attribute_set( $set_id ) {
		$manager = new DCA_Attribute_Manager();
		
		// Prepare set data
		$set_data = array(
			'name' => sanitize_text_field( $_POST['set_name'] ),
			'slug' => sanitize_title( $_POST['set_slug'] ),
			'icon' => sanitize_text_field( $_POST['set_icon'] ),
			'categories' => isset( $_POST['set_categories'] ) ? array_map( 'sanitize_text_field', $_POST['set_categories'] ) : array(),
			'priority' => intval( $_POST['set_priority'] ),
			'status' => sanitize_text_field( $_POST['set_status'] ),
		);
		
		if ( $set_id > 0 ) {
			// Update existing
			$result = $manager->update_attribute_set( $set_id, $set_data );
			
			// Delete removed fields
			if ( isset( $_POST['deleted_fields'] ) && ! empty( $_POST['deleted_fields'] ) ) {
				$deleted_ids = explode( ',', $_POST['deleted_fields'] );
				foreach ( $deleted_ids as $field_id ) {
					$manager->delete_field( intval( $field_id ) );
				}
			}
		} else {
			// Create new
			$set_id = $manager->create_attribute_set( $set_data );
			$result = ! is_wp_error( $set_id );
		}
		
		// Save fields
		if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
			foreach ( $_POST['fields'] as $field_data ) {
				$field_id = isset( $field_data['id'] ) ? intval( $field_data['id'] ) : 0;
				
				$field_update = array(
					'attribute_set_id' => $set_id,
					'field_name' => sanitize_text_field( $field_data['name'] ),
					'field_label' => sanitize_text_field( $field_data['label'] ),
					'field_icon' => sanitize_text_field( $field_data['icon'] ),
					'field_type' => sanitize_text_field( $field_data['type'] ),
					'field_options' => $this->parse_field_options( $field_data['options'], $field_data['type'] ),
					'required' => isset( $field_data['required'] ) ? 1 : 0,
					'display_order' => intval( $field_data['order'] ),
					'show_in_dashboard' => isset( $field_data['show_dashboard'] ) ? 1 : 0,
					'show_in_public' => isset( $field_data['show_public'] ) ? 1 : 0,
					'show_in_filters' => isset( $field_data['show_filters'] ) ? 1 : 0,
				);
				
				if ( $field_id > 0 ) {
					$manager->update_field( $field_id, $field_update );
				} else {
					$manager->create_field( $field_update );
				}
			}
		}
		
		if ( $result && ! is_wp_error( $result ) ) {
			echo '<div class="notice notice-success"><p>' . __( 'Attribute set saved successfully.', 'dokan-category-attributes' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . __( 'Failed to save attribute set.', 'dokan-category-attributes' ) . '</p></div>';
		}
	}
	
	/**
	 * Parse field options based on field type
	 * 
	 * @param string $options_string Options string
	 * @param string $field_type Field type
	 * @return array
	 */
	private function parse_field_options( $options_string, $field_type ) {
		if ( in_array( $field_type, array( 'select', 'radio', 'checkbox' ) ) ) {
			// Parse line-separated options
			$lines = explode( "\n", $options_string );
			$options = array();
			
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				
				// Support "value:label" format
				if ( strpos( $line, ':' ) !== false ) {
					list( $value, $label ) = explode( ':', $line, 2 );
					$options[ trim( $value ) ] = trim( $label );
				} else {
					$options[ $line ] = $line;
				}
			}
			
			return $options;
		} elseif ( $field_type === 'number' ) {
			// Parse min, max, step
			$config = array();
			$parts = explode( '|', $options_string );
			
			foreach ( $parts as $part ) {
				if ( strpos( $part, '=' ) !== false ) {
					list( $key, $value ) = explode( '=', $part, 2 );
					$config[ trim( $key ) ] = trim( $value );
				}
			}
			
			return $config;
		}
		
		return array();
	}
}
