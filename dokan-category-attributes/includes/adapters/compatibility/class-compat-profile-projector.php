<?php
/**
 * Compatibility Profile Projector — builds structured attribute projection from user-meta.
 *
 * Mirrors the projection logic in DCA_Frontend_Display::display_attributes() but
 * returns a structured array instead of echoing HTML, enabling server-side rendering,
 * REST responses, and template flexibility.
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
 * Class DCA_Compat_Profile_Projector
 *
 * @implements DCA_Profile_Projector
 */
class DCA_Compat_Profile_Projector implements DCA_Profile_Projector {

	/** @var DCA_Attribute_Manager */
	private $manager;

	public function __construct() {
		$this->manager = new DCA_Attribute_Manager();
	}

	/**
	 * @inheritdoc
	 *
	 * Returns an array of AttributeSetProjection entries:
	 * [
	 *   {
	 *     set_id:  int,
	 *     name:    string,
	 *     icon:    string,
	 *     fields:  [
	 *       {
	 *         field_name:  string,
	 *         field_label: string,
	 *         field_icon:  string,
	 *         field_type:  string,
	 *         value:       mixed,
	 *         display:     string,
	 *       },
	 *       ...
	 *     ],
	 *   },
	 *   ...
	 * ]
	 *
	 * Only sets/fields that have stored values are included.
	 *
	 * @param int $vendor_id
	 * @return array
	 */
	public function project_vendor_attributes( $vendor_id ) {
		$vendor_categories = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $vendor_categories ) ) {
			return array();
		}

		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		$projection = array();

		foreach ( $attribute_sets as $set ) {
			if ( empty( array_intersect( $set->categories, $vendor_categories ) ) ) {
				continue;
			}

			$fields = $this->manager->get_fields( $set->id, array( 'location' => 'public' ) );
			if ( empty( $fields ) ) {
				continue;
			}

			$field_projections = array();

			foreach ( $fields as $field ) {
				$raw_value = get_user_meta( $vendor_id, $field->field_name, true );
				if ( '' === $raw_value || false === $raw_value ) {
					continue;
				}

				$field_projections[] = array(
					'field_name'  => $field->field_name,
					'field_label' => $field->field_label,
					'field_icon'  => $field->field_icon,
					'field_type'  => $field->field_type,
					'value'       => $raw_value,
					'display'     => $this->format_display_value( $field, $raw_value ),
				);
			}

			if ( empty( $field_projections ) ) {
				continue;
			}

			$projection[] = array(
				'set_id' => (int) $set->id,
				'name'   => $set->name,
				'icon'   => $set->icon,
				'fields' => $field_projections,
			);
		}

		return $projection;
	}

	// -------------------------------------------------------------------------
	// Helpers (mirrors DCA_Frontend_Display::format_display_value)
	// -------------------------------------------------------------------------

	/**
	 * Convert a raw meta value to a human-readable display string.
	 *
	 * @param object $field
	 * @param mixed  $value
	 * @return string
	 */
	private function format_display_value( $field, $value ) {
		switch ( $field->field_type ) {
			case 'select':
				if ( is_array( $field->field_options ) && isset( $field->field_options[ $value ] ) ) {
					return $field->field_options[ $value ];
				}
				return (string) $value;

			case 'checkbox':
				if ( is_array( $value ) ) {
					$labels = array();
					foreach ( $value as $key ) {
						if ( is_array( $field->field_options ) && isset( $field->field_options[ $key ] ) ) {
							$labels[] = $field->field_options[ $key ];
						} else {
							$labels[] = $key;
						}
					}
					return implode( ', ', $labels );
				}
				return (string) $value;

			default:
				return (string) $value;
		}
	}
}
