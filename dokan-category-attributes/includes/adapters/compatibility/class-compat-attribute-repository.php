<?php
/**
 * Compatibility Attribute Repository — wraps DCA_Attribute_Manager and WP user-meta.
 *
 * Delegates all set/field CRUD to the existing DCA_Attribute_Manager class so that
 * legacy behaviour is preserved without modification.  Vendor values are stored via
 * standard WP user meta (identical to how DCA_Dashboard_Fields saves them).
 *
 * @package DCA\Adapters\Compatibility
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 1 — Core Contract Scaffolding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_Compat_Attribute_Repository
 *
 * @implements DCA_Attribute_Repository
 */
class DCA_Compat_Attribute_Repository implements DCA_Attribute_Repository {

	/** @var DCA_Attribute_Manager */
	private $manager;

	public function __construct() {
		$this->manager = new DCA_Attribute_Manager();
	}

	// -------------------------------------------------------------------------
	// Attribute Set CRUD
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_attribute_sets( array $args = array() ) {
		return $this->manager->get_attribute_sets( $args );
	}

	/**
	 * @inheritdoc
	 */
	public function get_attribute_set( $set_id ) {
		return $this->manager->get_attribute_set( $set_id ) ?: null;
	}

	/**
	 * @inheritdoc
	 */
	public function create_attribute_set( array $set_data, array $fields = array() ) {
		return $this->manager->create_attribute_set( $set_data, $fields );
	}

	/**
	 * @inheritdoc
	 */
	public function update_attribute_set( $set_id, array $set_data ) {
		return $this->manager->update_attribute_set( $set_id, $set_data );
	}

	/**
	 * @inheritdoc
	 */
	public function delete_attribute_set( $set_id ) {
		return $this->manager->delete_attribute_set( $set_id );
	}

	/**
	 * @inheritdoc
	 */
	public function export_attribute_set( $set_id ) {
		return $this->manager->export_to_json( $set_id );
	}

	/**
	 * @inheritdoc
	 */
	public function import_attribute_set( $json ) {
		return $this->manager->import_from_json( $json );
	}

	// -------------------------------------------------------------------------
	// Field CRUD
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_fields( $set_id, array $args = array() ) {
		return $this->manager->get_fields( $set_id, $args );
	}

	/**
	 * @inheritdoc
	 */
	public function create_field( array $field_data ) {
		return $this->manager->create_field( $field_data );
	}

	/**
	 * @inheritdoc
	 */
	public function update_field( $field_id, array $field_data ) {
		return $this->manager->update_field( $field_id, $field_data );
	}

	/**
	 * @inheritdoc
	 */
	public function delete_field( $field_id ) {
		return $this->manager->delete_field( $field_id );
	}

	// -------------------------------------------------------------------------
	// Vendor Values (user meta)
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_vendor_value( $vendor_id, $field_name ) {
		return get_user_meta( $vendor_id, sanitize_key( $field_name ), true );
	}

	/**
	 * @inheritdoc
	 */
	public function save_vendor_value( $vendor_id, $field_name, $value ) {
		$result = update_user_meta( $vendor_id, sanitize_key( $field_name ), $value );
		// update_user_meta returns meta_id (int) on insert, true on update, false on no-op/error.
		return false !== $result;
	}

	/**
	 * @inheritdoc
	 */
	public function get_fields_for_vendor( $vendor_id ) {
		$vendor_categories = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $vendor_categories ) ) {
			return array();
		}

		$sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		$result = array();

		foreach ( $sets as $set ) {
			if ( empty( array_intersect( $set->categories, $vendor_categories ) ) ) {
				continue;
			}
			$fields = $this->manager->get_fields( $set->id );
			foreach ( $fields as $field ) {
				$result[] = $field;
			}
		}

		return $result;
	}
}
