<?php
/**
 * Default WP Filter Provider — CPT-based implementation (Phase 2).
 *
 * Reads filter schema from the 'dca_attribute_set' / 'dca_attribute_field' CPTs
 * and builds WP_Query / WP_User_Query meta_query entries from $active_filters.
 * Accepts explicit filter values (not $_GET) so it is fully testable and
 * REST-compatible.
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
 * Class DCA_WP_Filter_Provider
 *
 * @implements DCA_Filter_Provider
 */
class DCA_WP_Filter_Provider implements DCA_Filter_Provider {

	/** @var DCA_WP_CPT_Storage */
	private $storage;

	public function __construct() {
		$this->storage = new DCA_WP_CPT_Storage();
	}

	/**
	 * @inheritdoc
	 *
	 * Returns FilterSchema[] — same shape as DCA_Compat_Filter_Provider.
	 *
	 * @return array
	 */
	public function get_filter_schema() {
		$attribute_sets = $this->storage->get_sets( array( 'status' => 'active' ) );
		$schema         = array();

		foreach ( $attribute_sets as $set ) {
			$fields = $this->storage->get_fields_by_set( $set->id, array( 'location' => 'filters' ) );
			if ( empty( $fields ) ) {
				continue;
			}

			$field_schemas = array();
			foreach ( $fields as $field ) {
				$field_schemas[] = array(
					'field_name'    => $field->field_name,
					'field_label'   => $field->field_label,
					'field_icon'    => $field->field_icon,
					'field_type'    => $field->field_type,
					'field_options' => is_array( $field->field_options ) ? $field->field_options : array(),
				);
			}

			$schema[] = array(
				'set_id'     => $set->id,
				'set_name'   => $set->name,
				'set_icon'   => $set->icon,
				'categories' => is_array( $set->categories ) ? $set->categories : array(),
				'fields'     => $field_schemas,
			);
		}

		return $schema;
	}

	/**
	 * @inheritdoc
	 *
	 * Builds meta_query entries from $active_filters.  Accepts explicit values
	 * instead of reading from $_GET so the method is fully testable and
	 * REST-compatible.
	 *
	 * $active_filters shape: [ 'field_name' => 'value' | string[], ... ]
	 *
	 * @param array $query_args    WP_User_Query / WP_Query args.
	 * @param array $active_filters Associative array of field_name => value.
	 * @return array
	 */
	public function apply_query_filters( array $query_args, array $active_filters ) {
		if ( empty( $active_filters ) ) {
			return $query_args;
		}

		$meta_query     = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
		$attribute_sets = $this->storage->get_sets( array( 'status' => 'active' ) );

		foreach ( $attribute_sets as $set ) {
			$fields = $this->storage->get_fields_by_set( $set->id );

			foreach ( $fields as $field ) {
				$field_name = $field->field_name;

				if ( ! isset( $active_filters[ $field_name ] ) || '' === $active_filters[ $field_name ] ) {
					continue;
				}

				$value = $active_filters[ $field_name ];

				if ( is_array( $value ) ) {
					$value = array_filter( array_map( 'sanitize_text_field', $value ) );
					if ( empty( $value ) ) {
						continue;
					}
					$meta_query[] = array(
						'key'     => $field_name,
						'value'   => $value,
						'compare' => 'IN',
					);
				} else {
					$meta_query[] = array(
						'key'     => $field_name,
						'value'   => sanitize_text_field( (string) $value ),
						'compare' => '=',
					);
				}
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}
}
