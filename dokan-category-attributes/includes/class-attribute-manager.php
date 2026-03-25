<?php
/**
 * Attribute Manager Class
 * 
 * Handles CRUD operations for attribute sets and fields
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Attribute_Manager {
	
	/**
	 * Database instance
	 * 
	 * @var DCA_Database
	 */
	private $db;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = new DCA_Database();
	}
	
	/**
	 * Create new attribute set with fields
	 * 
	 * @param array $set_data Attribute set data
	 * @param array $fields Array of field data
	 * @return int|WP_Error Set ID or error
	 */
	public function create_attribute_set( $set_data, $fields = array() ) {
		// Validate required fields
		if ( empty( $set_data['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Attribute set name is required', 'dokan-category-attributes' ) );
		}
		
		// Generate slug if not provided
		if ( empty( $set_data['slug'] ) ) {
			$set_data['slug'] = sanitize_title( $set_data['name'] );
		}
		
		// Insert attribute set
		$set_id = $this->db->insert_attribute_set( $set_data );
		
		if ( ! $set_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create attribute set', 'dokan-category-attributes' ) );
		}
		
		// Add fields if provided
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $order => $field_data ) {
				$field_data['attribute_set_id'] = $set_id;
				$field_data['display_order'] = $order;
				
				$this->create_field( $field_data );
			}
		}
		
		return $set_id;
	}
	
	/**
	 * Update attribute set
	 * 
	 * @param int $set_id Set ID
	 * @param array $set_data Data to update
	 * @return bool|WP_Error
	 */
	public function update_attribute_set( $set_id, $set_data ) {
		// Validate set exists
		$set = $this->db->get_attribute_set( $set_id );
		if ( ! $set ) {
			return new WP_Error( 'not_found', __( 'Attribute set not found', 'dokan-category-attributes' ) );
		}
		
		// Update
		$result = $this->db->update_attribute_set( $set_id, $set_data );
		
		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update attribute set', 'dokan-category-attributes' ) );
		}
		
		return true;
	}
	
	/**
	 * Delete attribute set and all its fields
	 * 
	 * @param int $set_id Set ID
	 * @return bool|WP_Error
	 */
	public function delete_attribute_set( $set_id ) {
		$result = $this->db->delete_attribute_set( $set_id );
		
		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete attribute set', 'dokan-category-attributes' ) );
		}
		
		return true;
	}
	
	/**
	 * Get attribute set by ID
	 * 
	 * @param int $set_id Set ID
	 * @return object|null
	 */
	public function get_attribute_set( $set_id ) {
		return $this->db->get_attribute_set( $set_id );
	}
	
	/**
	 * Get all attribute sets
	 * 
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_attribute_sets( $args = array() ) {
		return $this->db->get_attribute_sets( $args );
	}
	
	/**
	 * Get attribute sets by category
	 * 
	 * @param string $category_slug Category slug
	 * @return array
	 */
	public function get_sets_by_category( $category_slug ) {
		$all_sets = $this->db->get_attribute_sets();
		$matching_sets = array();
		
		foreach ( $all_sets as $set ) {
			if ( is_array( $set->categories ) && in_array( $category_slug, $set->categories ) ) {
				$matching_sets[] = $set;
			}
		}
		
		return $matching_sets;
	}
	
	/**
	 * Create attribute field
	 * 
	 * @param array $field_data Field data
	 * @return int|WP_Error Field ID or error
	 */
	public function create_field( $field_data ) {
		// Validate required fields
		if ( empty( $field_data['attribute_set_id'] ) ) {
			return new WP_Error( 'missing_set_id', __( 'Attribute set ID is required', 'dokan-category-attributes' ) );
		}
		
		if ( empty( $field_data['field_name'] ) ) {
			return new WP_Error( 'missing_field_name', __( 'Field name is required', 'dokan-category-attributes' ) );
		}
		
		// Generate field label if not provided
		if ( empty( $field_data['field_label'] ) ) {
			$field_data['field_label'] = ucwords( str_replace( '_', ' ', $field_data['field_name'] ) );
		}
		
		// Set defaults
		$defaults = array(
			'field_type' => 'select',
			'required' => 0,
			'display_order' => 0,
			'show_in_dashboard' => 1,
			'show_in_public' => 1,
			'show_in_filters' => 1,
		);
		
		$field_data = wp_parse_args( $field_data, $defaults );
		
		// Insert field
		$field_id = $this->db->insert_attribute_field( $field_data );
		
		if ( ! $field_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create field', 'dokan-category-attributes' ) );
		}
		
		return $field_id;
	}
	
	/**
	 * Update field
	 * 
	 * @param int $field_id Field ID
	 * @param array $field_data Data to update
	 * @return bool|WP_Error
	 */
	public function update_field( $field_id, $field_data ) {
		$result = $this->db->update_attribute_field( $field_id, $field_data );
		
		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update field', 'dokan-category-attributes' ) );
		}
		
		return true;
	}
	
	/**
	 * Delete field
	 * 
	 * @param int $field_id Field ID
	 * @return bool|WP_Error
	 */
	public function delete_field( $field_id ) {
		$result = $this->db->delete_attribute_field( $field_id );
		
		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete field', 'dokan-category-attributes' ) );
		}
		
		return true;
	}
	
	/**
	 * Get fields by attribute set
	 * 
	 * @param int $set_id Set ID
	 * @param array $args Additional arguments
	 * @return array
	 */
	public function get_fields( $set_id, $args = array() ) {
		$fields = $this->db->get_fields_by_set( $set_id );
		
		// Filter by location if specified
		if ( ! empty( $args['location'] ) ) {
			$location_key = 'show_in_' . $args['location'];
			$fields = array_filter( $fields, function( $field ) use ( $location_key ) {
				return ! empty( $field->$location_key );
			} );
		}
		
		return $fields;
	}
	
	/**
	 * Reorder fields
	 * 
	 * @param array $field_orders Array of field_id => order pairs
	 * @return bool
	 */
	public function reorder_fields( $field_orders ) {
		foreach ( $field_orders as $field_id => $order ) {
			$this->db->update_attribute_field( $field_id, array( 'display_order' => $order ) );
		}
		
		return true;
	}
	
	/**
	 * Duplicate attribute set
	 * 
	 * @param int $set_id Set ID to duplicate
	 * @return int|WP_Error New set ID or error
	 */
	public function duplicate_attribute_set( $set_id ) {
		// Get original set
		$original_set = $this->db->get_attribute_set( $set_id );
		if ( ! $original_set ) {
			return new WP_Error( 'not_found', __( 'Attribute set not found', 'dokan-category-attributes' ) );
		}
		
		// Prepare new set data
		$new_set_data = array(
			'name' => $original_set->name . ' (Copy)',
			'slug' => $original_set->slug . '_copy_' . time(),
			'icon' => $original_set->icon,
			'categories' => $original_set->categories,
			'priority' => $original_set->priority,
			'status' => 'draft', // Set to draft by default
		);
		
		// Create new set
		$new_set_id = $this->db->insert_attribute_set( $new_set_data );
		if ( ! $new_set_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to duplicate attribute set', 'dokan-category-attributes' ) );
		}
		
		// Get and duplicate fields
		$fields = $this->db->get_fields_by_set( $set_id );
		foreach ( $fields as $field ) {
			$new_field_data = array(
				'attribute_set_id' => $new_set_id,
				'field_name' => $field->field_name . '_copy_' . time(),
				'field_label' => $field->field_label,
				'field_icon' => $field->field_icon,
				'field_type' => $field->field_type,
				'field_options' => $field->field_options,
				'required' => $field->required,
				'display_order' => $field->display_order,
				'show_in_dashboard' => $field->show_in_dashboard,
				'show_in_public' => $field->show_in_public,
				'show_in_filters' => $field->show_in_filters,
			);
			
			$this->db->insert_attribute_field( $new_field_data );
		}
		
		return $new_set_id;
	}
	
	/**
	 * Get available categories from store_category taxonomy
	 * 
	 * @return array
	 */
	public function get_available_categories() {
		$categories = get_terms( array(
			'taxonomy' => 'store_category',
			'hide_empty' => false,
		) );
		
		if ( is_wp_error( $categories ) ) {
			return array();
		}
		
		$options = array();
		foreach ( $categories as $category ) {
			$options[ $category->slug ] = $category->name;
		}
		
		return $options;
	}
	
	/**
	 * Import attribute set from JSON
	 * 
	 * @param string $json JSON data
	 * @return int|WP_Error Set ID or error
	 */
	public function import_from_json( $json ) {
		$data = json_decode( $json, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON data', 'dokan-category-attributes' ) );
		}
		
		if ( empty( $data['set'] ) || empty( $data['fields'] ) ) {
			return new WP_Error( 'invalid_structure', __( 'Invalid data structure', 'dokan-category-attributes' ) );
		}
		
		return $this->create_attribute_set( $data['set'], $data['fields'] );
	}
	
	/**
	 * Export attribute set to JSON
	 * 
	 * @param int $set_id Set ID
	 * @return string|WP_Error JSON or error
	 */
	public function export_to_json( $set_id ) {
		$set = $this->db->get_attribute_set( $set_id );
		if ( ! $set ) {
			return new WP_Error( 'not_found', __( 'Attribute set not found', 'dokan-category-attributes' ) );
		}
		
		$fields = $this->db->get_fields_by_set( $set_id );
		
		$export_data = array(
			'set' => array(
				'name' => $set->name,
				'slug' => $set->slug,
				'icon' => $set->icon,
				'categories' => $set->categories,
				'priority' => $set->priority,
			),
			'fields' => array(),
		);
		
		foreach ( $fields as $field ) {
			$export_data['fields'][] = array(
				'field_name' => $field->field_name,
				'field_label' => $field->field_label,
				'field_icon' => $field->field_icon,
				'field_type' => $field->field_type,
				'field_options' => $field->field_options,
				'required' => $field->required,
				'display_order' => $field->display_order,
				'show_in_dashboard' => $field->show_in_dashboard,
				'show_in_public' => $field->show_in_public,
				'show_in_filters' => $field->show_in_filters,
			);
		}
		
		return wp_json_encode( $export_data, JSON_PRETTY_PRINT );
	}
}
