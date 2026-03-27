<?php
/**
 * Attribute Repository Contract
 *
 * Core contract for all attribute schema CRUD and vendor value
 * storage operations (feature dca-001). Implementations must not
 * leak vendor-stack details (Dokan/Woo/custom-table internals) into
 * callers — callers depend only on this interface.
 *
 * @package Dokan_Category_Attributes
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DCA_Attribute_Repository {

	// -------------------------------------------------------------------------
	// Attribute Sets
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a list of attribute sets.
	 *
	 * @param array $args {
	 *   Optional query arguments.
	 *   @type string $status  'active' | 'inactive' | '' (all). Default ''.
	 *   @type string $orderby Column to order by. Default 'priority'.
	 *   @type string $order   'ASC' | 'DESC'. Default 'ASC'.
	 * }
	 * @return object[] stdClass rows with id, name, slug, icon, categories (array), priority, status.
	 */
	public function get_attribute_sets( array $args = array() ): array;

	/**
	 * Retrieve a single attribute set by ID.
	 *
	 * @param int $set_id Attribute set ID.
	 * @return object|null stdClass row or null if not found.
	 */
	public function get_attribute_set( int $set_id ): ?object;

	/**
	 * Create a new attribute set, optionally with initial fields.
	 *
	 * @param array  $set_data  Attribute set data (name required).
	 * @param array  $fields    Optional array of field data arrays.
	 * @return int|\WP_Error    New set ID or WP_Error on failure.
	 */
	public function create_attribute_set( array $set_data, array $fields = array() );

	/**
	 * Update an existing attribute set.
	 *
	 * @param int   $set_id   Attribute set ID.
	 * @param array $set_data Partial data to update.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function update_attribute_set( int $set_id, array $set_data );

	/**
	 * Delete an attribute set and cascade-delete all its fields.
	 *
	 * @param int $set_id Attribute set ID.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function delete_attribute_set( int $set_id );

	/**
	 * Export an attribute set (and its fields) as a JSON string.
	 *
	 * @param int $set_id Attribute set ID.
	 * @return string|\WP_Error JSON string or WP_Error.
	 */
	public function export_attribute_set( int $set_id );

	/**
	 * Import an attribute set from a JSON string.
	 *
	 * @param string $json JSON-encoded attribute set data.
	 * @return int|\WP_Error New set ID or WP_Error.
	 */
	public function import_attribute_set( string $json );

	// -------------------------------------------------------------------------
	// Attribute Fields
	// -------------------------------------------------------------------------

	/**
	 * Retrieve fields for a given attribute set.
	 *
	 * @param int   $set_id Attribute set ID.
	 * @param array $args {
	 *   Optional filter arguments.
	 *   @type string $location  'dashboard' | 'public' | 'filters' — filters by the
	 *                           corresponding show_in_* flag. Omit to return all fields.
	 * }
	 * @return object[] stdClass field rows ordered by display_order ASC.
	 */
	public function get_fields( int $set_id, array $args = array() ): array;

	/**
	 * Create a new attribute field.
	 *
	 * @param array $field_data Field data (attribute_set_id and field_name required).
	 * @return int|\WP_Error New field ID or WP_Error.
	 */
	public function create_field( array $field_data );

	/**
	 * Update an existing attribute field.
	 *
	 * @param int   $field_id  Field ID.
	 * @param array $field_data Partial data to update.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function update_field( int $field_id, array $field_data );

	/**
	 * Delete an attribute field.
	 *
	 * @param int $field_id Field ID.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function delete_field( int $field_id );

	// -------------------------------------------------------------------------
	// Vendor Values
	// -------------------------------------------------------------------------

	/**
	 * Read a vendor's stored value for a given field name.
	 *
	 * @param int    $vendor_id  WP user ID.
	 * @param string $field_name Attribute field machine name.
	 * @return mixed Stored value, or empty string if not set.
	 */
	public function get_vendor_value( int $vendor_id, string $field_name );

	/**
	 * Persist a vendor's value for a given field.
	 *
	 * @param int    $vendor_id  WP user ID.
	 * @param string $field_name Attribute field machine name.
	 * @param mixed  $value      Value to persist.
	 * @return bool True on success.
	 */
	public function save_vendor_value( int $vendor_id, string $field_name, $value ): bool;

	/**
	 * Retrieve all attribute fields that apply to a vendor (based on category
	 * membership), together with the vendor's current values.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array[] Each element: ['field' => object, 'value' => mixed].
	 */
	public function get_fields_for_vendor( int $vendor_id ): array;
}
