<?php
/**
 * Compatibility Filter Provider — wraps DCA_Store_Filters logic.
 *
 * Provides the interface contract for filter schema retrieval and query modification.
 * apply_query_filters() accepts explicit $active_filters instead of reading from
 * $_GET directly, making the provider testable and REST-compatible.
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
 * Class DCA_Compat_Filter_Provider
 *
 * @implements DCA_Filter_Provider
 */
class DCA_Compat_Filter_Provider implements DCA_Filter_Provider {

	/** @var DCA_Attribute_Manager */
	private $manager;

	public function __construct() {
		$this->manager = new DCA_Attribute_Manager();
	}

	/**
	 * @inheritdoc
	 *
	 * Returns an array of FilterSchema entries:
	 * [
	 *   {
	 *     set_id:     int,
	 *     set_name:   string,
	 *     set_icon:   string,
	 *     categories: string[],
	 *     fields: [
	 *       {
	 *         field_name:    string,
	 *         field_label:   string,
	 *         field_icon:    string,
	 *         field_type:    string,
	 *         field_options: array<string,string>,
	 *       },
	 *       ...
	 *     ],
	 *   },
	 *   ...
	 * ]
	 *
	 * @return array
	 */
	public function get_filter_schema() {
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		$schema = array();

		foreach ( $attribute_sets as $set ) {
			$fields = $this->manager->get_fields( $set->id, array( 'location' => 'filters' ) );
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
				'set_id'     => (int) $set->id,
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
	 * Builds meta_query entries from $active_filters (mirrors DCA_Store_Filters::apply_filters
	 * but accepts explicit filter values instead of reading from $_GET).
	 *
	 * $active_filters shape: [ 'field_name' => 'value' | string[], ... ]
	 *
	 * @param array $query_args    WP_User_Query / WP_Query args array.
	 * @param array $active_filters Associative array of field_name => value.
	 * @return array Modified $query_args with meta_query populated.
	 */
	public function apply_query_filters( array $query_args, array $active_filters ) {
		if ( empty( $active_filters ) ) {
			return $query_args;
		}

		$meta_query = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();

		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );

		foreach ( $attribute_sets as $set ) {
			$fields = $this->manager->get_fields( $set->id );

			foreach ( $fields as $field ) {
				$field_name = $field->field_name;

				if ( ! isset( $active_filters[ $field_name ] ) || '' === $active_filters[ $field_name ] ) {
					continue;
				}

				$value = $active_filters[ $field_name ];

				if ( is_array( $value ) ) {
					// Remove empty entries.
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
