<?php
/**
 * Default WP Attribute Repository — CPT-based implementation (Phase 2).
 *
 * Stores attribute sets and fields in the private 'dca_attribute_set' and
 * 'dca_attribute_field' CPTs via DCA_WP_CPT_Storage.  Vendor values remain
 * in standard user meta under the same key names as the compat adapter,
 * so no data migration is required when switching between adapters.
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 2 — Default WP Pilot Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_Attribute_Repository
 *
 * @implements DCA_Attribute_Repository
 */
class DCA_WP_Attribute_Repository implements DCA_Attribute_Repository {

	/** @var DCA_WP_CPT_Storage */
	private $storage;

	public function __construct() {
		$this->storage = new DCA_WP_CPT_Storage();
	}

	// -------------------------------------------------------------------------
	// Attribute Set CRUD
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_attribute_sets( array $args = array() ): array {
		return $this->storage->get_sets( $args );
	}

	/**
	 * @inheritdoc
	 */
	public function get_attribute_set( int $set_id ): ?object {
		return $this->storage->get_set( (int) $set_id );
	}

	/**
	 * @inheritdoc
	 */
	public function create_attribute_set( array $set_data, array $fields = array() ) {
		if ( empty( $set_data['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Attribute set name is required', 'dokan-category-attributes' ) );
		}

		if ( empty( $set_data['slug'] ) ) {
			$set_data['slug'] = sanitize_title( $set_data['name'] );
		}

		$set_id = $this->storage->insert_set( $set_data );
		if ( ! $set_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create attribute set', 'dokan-category-attributes' ) );
		}

		if ( ! empty( $fields ) ) {
			foreach ( array_values( $fields ) as $order => $field_data ) {
				$field_data['attribute_set_id'] = $set_id;
				$field_data['display_order']    = $order;
				$this->create_field( $field_data );
			}
		}

		return $set_id;
	}

	/**
	 * @inheritdoc
	 */
	public function update_attribute_set( $set_id, array $set_data ) {
		if ( ! $this->storage->get_set( (int) $set_id ) ) {
			return new WP_Error( 'not_found', __( 'Attribute set not found', 'dokan-category-attributes' ) );
		}
		if ( ! $this->storage->update_set( (int) $set_id, $set_data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update attribute set', 'dokan-category-attributes' ) );
		}
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function delete_attribute_set( $set_id ) {
		if ( ! $this->storage->delete_set( (int) $set_id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete attribute set', 'dokan-category-attributes' ) );
		}
		return true;
	}

	/**
	 * @inheritdoc
	 *
	 * Returns JSON in the same format as the compat adapter so files are
	 * portable between both adapters.
	 */
	public function export_attribute_set( $set_id ) {
		$set = $this->storage->get_set( (int) $set_id );
		if ( ! $set ) {
			return new WP_Error( 'not_found', __( 'Attribute set not found', 'dokan-category-attributes' ) );
		}

		$fields      = $this->storage->get_fields_by_set( (int) $set_id );
		$field_data  = array();
		foreach ( $fields as $field ) {
			$field_data[] = array(
				'field_name'        => $field->field_name,
				'field_label'       => $field->field_label,
				'field_icon'        => $field->field_icon,
				'field_type'        => $field->field_type,
				'field_options'     => $field->field_options,
				'required'          => $field->required,
				'display_order'     => $field->display_order,
				'show_in_dashboard' => $field->show_in_dashboard,
				'show_in_public'    => $field->show_in_public,
				'show_in_filters'   => $field->show_in_filters,
			);
		}

		$export = array(
			'set'    => array(
				'name'       => $set->name,
				'slug'       => $set->slug,
				'icon'       => $set->icon,
				'categories' => $set->categories,
				'priority'   => $set->priority,
			),
			'fields' => $field_data,
		);

		return wp_json_encode( $export );
	}

	/**
	 * @inheritdoc
	 *
	 * Accepts the same JSON format produced by export_attribute_set().
	 */
	public function import_attribute_set( $json ) {
		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON data', 'dokan-category-attributes' ) );
		}

		if ( empty( $data['set'] ) || ! isset( $data['fields'] ) ) {
			return new WP_Error( 'invalid_structure', __( 'Invalid data structure', 'dokan-category-attributes' ) );
		}

		return $this->create_attribute_set( $data['set'], (array) $data['fields'] );
	}

	// -------------------------------------------------------------------------
	// Field CRUD
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_fields( int $set_id, array $args = array() ): array {
		return $this->storage->get_fields_by_set( (int) $set_id, $args );
	}

	/**
	 * @inheritdoc
	 */
	public function create_field( array $field_data ) {
		if ( empty( $field_data['attribute_set_id'] ) ) {
			return new WP_Error( 'missing_set_id', __( 'Attribute set ID is required', 'dokan-category-attributes' ) );
		}
		if ( empty( $field_data['field_name'] ) ) {
			return new WP_Error( 'missing_field_name', __( 'Field name is required', 'dokan-category-attributes' ) );
		}

		// Apply same defaults as compat adapter.
		$defaults = array(
			'field_label'       => ucwords( str_replace( '_', ' ', $field_data['field_name'] ) ),
			'field_type'        => 'select',
			'required'          => 0,
			'display_order'     => 0,
			'show_in_dashboard' => 1,
			'show_in_public'    => 1,
			'show_in_filters'   => 1,
		);
		$field_data = wp_parse_args( $field_data, $defaults );

		$field_id = $this->storage->insert_field( $field_data );
		if ( ! $field_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create field', 'dokan-category-attributes' ) );
		}

		return $field_id;
	}

	/**
	 * @inheritdoc
	 */
	public function update_field( $field_id, array $field_data ) {
		if ( ! $this->storage->update_field( (int) $field_id, $field_data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update field', 'dokan-category-attributes' ) );
		}
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function delete_field( $field_id ) {
		if ( ! $this->storage->delete_field( (int) $field_id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete field', 'dokan-category-attributes' ) );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Vendor Values (shared user meta — same key names as compat adapter)
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public function get_vendor_value( $vendor_id, $field_name ) {
		return get_user_meta( (int) $vendor_id, sanitize_key( $field_name ), true );
	}

	/**
	 * @inheritdoc
	 */
	public function save_vendor_value( int $vendor_id, string $field_name, $value ): bool {
		$result = update_user_meta( (int) $vendor_id, sanitize_key( $field_name ), $value );
		return false !== $result;
	}

	/**
	 * @inheritdoc
	 *
	 * Returns all fields from CPT sets whose categories intersect the
	 * vendor's store categories.
	 */
	public function get_fields_for_vendor( int $vendor_id ): array {
		$vendor_categories = wp_get_object_terms(
			(int) $vendor_id,
			'store_category',
			array( 'fields' => 'slugs' )
		);
		if ( is_wp_error( $vendor_categories ) ) {
			return array();
		}

		$sets   = $this->storage->get_sets( array( 'status' => 'active' ) );
		$result = array();

		foreach ( $sets as $set ) {
			if ( empty( array_intersect( $set->categories, $vendor_categories ) ) ) {
				continue;
			}
			$fields = $this->storage->get_fields_by_set( $set->id );
			foreach ( $fields as $field ) {
				$result[] = $field;
			}
		}

		return $result;
	}
}
