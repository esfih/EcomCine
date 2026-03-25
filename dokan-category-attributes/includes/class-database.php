<?php
/**
 * Database Handler Class
 * 
 * Manages database tables for category attributes
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Database {
	
	/**
	 * Table names
	 */
	private $attribute_sets_table;
	private $attribute_fields_table;
	private $vendor_attributes_table;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		
		$this->attribute_sets_table = $wpdb->prefix . 'dokan_attribute_sets';
		$this->attribute_fields_table = $wpdb->prefix . 'dokan_attribute_fields';
		$this->vendor_attributes_table = $wpdb->prefix . 'dokan_vendor_attributes';
	}
	
	/**
	 * Create database tables
	 */
	public function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		// Attribute Sets Table
		$sql_sets = "CREATE TABLE IF NOT EXISTS {$this->attribute_sets_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			icon varchar(50) DEFAULT NULL,
			categories longtext DEFAULT NULL COMMENT 'JSON array of category slugs',
			priority int(11) DEFAULT 10,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) $charset_collate;";
		
		dbDelta( $sql_sets );
		
		// Attribute Fields Table
		$sql_fields = "CREATE TABLE IF NOT EXISTS {$this->attribute_fields_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attribute_set_id bigint(20) UNSIGNED NOT NULL,
			field_name varchar(255) NOT NULL,
			field_label varchar(255) NOT NULL,
			field_icon varchar(50) DEFAULT NULL,
			field_type varchar(50) DEFAULT 'select',
			field_options longtext DEFAULT NULL COMMENT 'JSON array of options',
			required tinyint(1) DEFAULT 0,
			display_order int(11) DEFAULT 0,
			show_in_dashboard tinyint(1) DEFAULT 1,
			show_in_public tinyint(1) DEFAULT 1,
			show_in_filters tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY field_name (field_name),
			KEY attribute_set_id (attribute_set_id),
			KEY display_order (display_order)
		) $charset_collate;";
		
		dbDelta( $sql_fields );
		
		// Vendor Attributes Table (optional - for better querying)
		$sql_vendor = "CREATE TABLE IF NOT EXISTS {$this->vendor_attributes_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			field_id bigint(20) UNSIGNED NOT NULL,
			field_value text DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY vendor_field (vendor_id, field_id),
			KEY vendor_id (vendor_id),
			KEY field_id (field_id)
		) $charset_collate;";
		
		dbDelta( $sql_vendor );
		
		// Update version
		update_option( 'dca_db_version', DCA_VERSION );
	}
	
	/**
	 * Drop database tables (for uninstall)
	 */
	public function drop_tables() {
		global $wpdb;
		
		$wpdb->query( "DROP TABLE IF EXISTS {$this->vendor_attributes_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$this->attribute_fields_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$this->attribute_sets_table}" );
		
		delete_option( 'dca_db_version' );
	}
	
	/**
	 * Get attribute sets table name
	 * 
	 * @return string
	 */
	public function get_sets_table() {
		return $this->attribute_sets_table;
	}
	
	/**
	 * Get attribute fields table name
	 * 
	 * @return string
	 */
	public function get_fields_table() {
		return $this->attribute_fields_table;
	}
	
	/**
	 * Get vendor attributes table name
	 * 
	 * @return string
	 */
	public function get_vendor_table() {
		return $this->vendor_attributes_table;
	}
	
	/**
	 * Insert attribute set
	 * 
	 * @param array $data Attribute set data
	 * @return int|false Insert ID or false on failure
	 */
	public function insert_attribute_set( $data ) {
		global $wpdb;
		
		$defaults = array(
			'name' => '',
			'slug' => '',
			'icon' => '',
			'categories' => array(),
			'priority' => 10,
			'status' => 'active',
		);
		
		$data = wp_parse_args( $data, $defaults );
		
		// Ensure categories is JSON
		if ( is_array( $data['categories'] ) ) {
			$data['categories'] = wp_json_encode( $data['categories'] );
		}
		
		$result = $wpdb->insert(
			$this->attribute_sets_table,
			$data,
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Update attribute set
	 * 
	 * @param int $id Attribute set ID
	 * @param array $data Data to update
	 * @return bool
	 */
	public function update_attribute_set( $id, $data ) {
		global $wpdb;
		
		// Ensure categories is JSON
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$data['categories'] = wp_json_encode( $data['categories'] );
		}
		
		return $wpdb->update(
			$this->attribute_sets_table,
			$data,
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}
	
	/**
	 * Delete attribute set
	 * 
	 * @param int $id Attribute set ID
	 * @return bool
	 */
	public function delete_attribute_set( $id ) {
		global $wpdb;
		
		// Delete all fields in this set
		$wpdb->delete(
			$this->attribute_fields_table,
			array( 'attribute_set_id' => $id ),
			array( '%d' )
		);
		
		// Delete the set
		return $wpdb->delete(
			$this->attribute_sets_table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}
	
	/**
	 * Get attribute set by ID
	 * 
	 * @param int $id Attribute set ID
	 * @return object|null
	 */
	public function get_attribute_set( $id ) {
		global $wpdb;
		
		$set = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->attribute_sets_table} WHERE id = %d",
				$id
			)
		);
		
		if ( $set ) {
			// Decode JSON categories
			$set->categories = json_decode( $set->categories, true );
		}
		
		return $set;
	}
	
	/**
	 * Get all attribute sets
	 * 
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_attribute_sets( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'status' => 'active',
			'orderby' => 'priority',
			'order' => 'ASC',
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = '';
		if ( ! empty( $args['status'] ) ) {
			$where = $wpdb->prepare( "WHERE status = %s", $args['status'] );
		}
		
		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		
		$sets = $wpdb->get_results(
			"SELECT * FROM {$this->attribute_sets_table} {$where} ORDER BY {$orderby}"
		);
		
		// Decode JSON categories for each set
		foreach ( $sets as $set ) {
			$set->categories = json_decode( $set->categories, true );
		}
		
		return $sets;
	}
	
	/**
	 * Insert attribute field
	 * 
	 * @param array $data Field data
	 * @return int|false
	 */
	public function insert_attribute_field( $data ) {
		global $wpdb;
		
		// Ensure field_options is JSON
		if ( isset( $data['field_options'] ) && is_array( $data['field_options'] ) ) {
			$data['field_options'] = wp_json_encode( $data['field_options'] );
		}
		
		$result = $wpdb->insert(
			$this->attribute_fields_table,
			$data
		);
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Update attribute field
	 * 
	 * @param int $id Field ID
	 * @param array $data Data to update
	 * @return bool
	 */
	public function update_attribute_field( $id, $data ) {
		global $wpdb;
		
		// Ensure field_options is JSON
		if ( isset( $data['field_options'] ) && is_array( $data['field_options'] ) ) {
			$data['field_options'] = wp_json_encode( $data['field_options'] );
		}
		
		return $wpdb->update(
			$this->attribute_fields_table,
			$data,
			array( 'id' => $id )
		);
	}
	
	/**
	 * Delete attribute field
	 * 
	 * @param int $id Field ID
	 * @return bool
	 */
	public function delete_attribute_field( $id ) {
		global $wpdb;
		
		return $wpdb->delete(
			$this->attribute_fields_table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}
	
	/**
	 * Get fields by attribute set ID
	 * 
	 * @param int $set_id Attribute set ID
	 * @return array
	 */
	public function get_fields_by_set( $set_id ) {
		global $wpdb;
		
		$fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->attribute_fields_table} 
				WHERE attribute_set_id = %d 
				ORDER BY display_order ASC",
				$set_id
			)
		);
		
		// Decode JSON options for each field
		foreach ( $fields as $field ) {
			$field->field_options = json_decode( $field->field_options, true );
		}
		
		return $fields;
	}
	
	/**
	 * Get vendor attribute value
	 * 
	 * @param int $vendor_id Vendor ID
	 * @param int $field_id Field ID
	 * @return string|null
	 */
	public function get_vendor_attribute( $vendor_id, $field_id ) {
		global $wpdb;
		
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT field_value FROM {$this->vendor_attributes_table} 
				WHERE vendor_id = %d AND field_id = %d",
				$vendor_id,
				$field_id
			)
		);
	}
	
	/**
	 * Save vendor attribute value
	 * 
	 * @param int $vendor_id Vendor ID
	 * @param int $field_id Field ID
	 * @param string $value Field value
	 * @return bool
	 */
	public function save_vendor_attribute( $vendor_id, $field_id, $value ) {
		global $wpdb;
		
		// Check if exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->vendor_attributes_table} 
				WHERE vendor_id = %d AND field_id = %d",
				$vendor_id,
				$field_id
			)
		);
		
		if ( $exists ) {
			// Update
			return $wpdb->update(
				$this->vendor_attributes_table,
				array( 'field_value' => $value ),
				array( 'vendor_id' => $vendor_id, 'field_id' => $field_id ),
				array( '%s' ),
				array( '%d', '%d' )
			);
		} else {
			// Insert
			return $wpdb->insert(
				$this->vendor_attributes_table,
				array(
					'vendor_id' => $vendor_id,
					'field_id' => $field_id,
					'field_value' => $value,
				),
				array( '%d', '%d', '%s' )
			);
		}
	}
}
