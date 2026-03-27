<?php
/**
 * Default WP Profile Projector — CPT-based implementation (Phase 2).
 *
 * Builds the same AttributeSetProjection[] shape as DCA_Compat_Profile_Projector
 * but reads attribute sets and fields from the 'dca_attribute_set' /
 * 'dca_attribute_field' CPTs via DCA_WP_CPT_Storage.  Vendor values come from
 * standard user meta (shared with the compat adapter).
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
 * Class DCA_WP_Profile_Projector
 *
 * @implements DCA_Profile_Projector
 */
class DCA_WP_Profile_Projector implements DCA_Profile_Projector {

	/** @var DCA_WP_CPT_Storage */
	private $storage;

	public function __construct() {
		$this->storage = new DCA_WP_CPT_Storage();
	}

	/**
	 * @inheritdoc
	 *
	 * Returns an array of AttributeSetProjection entries (identical shape to
	 * DCA_Compat_Profile_Projector).  Only sets/fields that have stored values
	 * are included.
	 *
	 * @param int $vendor_id
	 * @return array
	 */
	public function project_vendor_attributes( $vendor_id ) {
		$vendor_id = (int) $vendor_id;

		$vendor_categories = wp_get_object_terms(
			$vendor_id,
			'store_category',
			array( 'fields' => 'slugs' )
		);
		if ( is_wp_error( $vendor_categories ) ) {
			return array();
		}

		$attribute_sets = $this->storage->get_sets( array( 'status' => 'active' ) );
		$projection     = array();

		foreach ( $attribute_sets as $set ) {
			if ( empty( array_intersect( $set->categories, $vendor_categories ) ) ) {
				continue;
			}

			$fields = $this->storage->get_fields_by_set( $set->id, array( 'location' => 'public' ) );
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
				'set_id' => $set->id,
				'name'   => $set->name,
				'icon'   => $set->icon,
				'fields' => $field_projections,
			);
		}

		return $projection;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a raw meta value to a human-readable display string.
	 * Same logic as DCA_Compat_Profile_Projector::format_display_value().
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
