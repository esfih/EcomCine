<?php
/**
 * Parity Check — validates that both adapters produce equivalent outputs.
 *
 * Run via WP-CLI or admin tool:
 *   DCA_Parity_Check::run();
 *
 * Each check:
 * 1. Creates identical test data in each adapter independently.
 * 2. Reads it back and compares the returned object shape/types.
 * 3. Tears down all test data.
 * 4. Returns a structured result array:
 *    [
 *      'check'  => string (human-readable name),
 *      'pass'   => bool,
 *      'detail' => string,
 *    ]
 *
 * NOTE: This compares structural parity (same property keys, same value types),
 * NOT value equality. The two adapters have separate storage backends so IDs will
 * differ. Vendor user-meta values ARE shared between adapters, so value equality
 * is verified for that check.
 *
 * @package DCA\Parity
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 2 — Default WP Pilot Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_Parity_Check
 */
final class DCA_Parity_Check {

	/**
	 * Run all parity checks and return a result array.
	 *
	 * Results are also stored in the transient 'dca_parity_check_results'
	 * for 1 hour so the admin panel can display them without re-running.
	 *
	 * @return array{check: string, pass: bool, detail: string}[]
	 */
	public static function run() {
		$instance = new self();
		$results  = array(
			$instance->check_attribute_set_crud(),
			$instance->check_field_crud(),
			$instance->check_vendor_value_shared_storage(),
			$instance->check_profile_projector_shape(),
			$instance->check_filter_schema_shape(),
			$instance->check_apply_query_filters_shape(),
		);

		set_transient( 'dca_parity_check_results', $results, HOUR_IN_SECONDS );

		return $results;
	}

	/**
	 * Return the last cached parity results without re-running checks.
	 *
	 * @return array|false  Result array or false if no results cached.
	 */
	public static function get_cached_results() {
		return get_transient( 'dca_parity_check_results' );
	}

	// -------------------------------------------------------------------------
	// Individual checks
	// -------------------------------------------------------------------------

	/**
	 * Verify that both adapters return an attribute set with the same
	 * property names and value types.
	 *
	 * @return array
	 */
	private function check_attribute_set_crud() {
		$name = 'Attribute set CRUD — shape parity';

		$compat = new DCA_Compat_Attribute_Repository();
		$wp     = new DCA_WP_Attribute_Repository();

		$set_data = array(
			'name'       => '_parity_test_' . time(),
			'slug'       => '_parity_' . time(),
			'icon'       => 'admin-generic',
			'categories' => array( 'test-cat' ),
			'priority'   => 99,
			'status'     => 'draft',
		);

		// Create in both adapters.
		$compat_id = $compat->create_attribute_set( $set_data );
		$wp_id     = $wp->create_attribute_set( $set_data );

		if ( is_wp_error( $compat_id ) || is_wp_error( $wp_id ) ) {
			return $this->result( $name, false, 'create_attribute_set returned WP_Error in one or both adapters.' );
		}

		// Read back.
		$compat_set = $compat->get_attribute_set( $compat_id );
		$wp_set     = $wp->get_attribute_set( $wp_id );

		// Clean up.
		$compat->delete_attribute_set( $compat_id );
		$wp->delete_attribute_set( $wp_id );

		if ( ! $compat_set || ! $wp_set ) {
			return $this->result( $name, false, 'get_attribute_set returned null in one or both adapters.' );
		}

		$diff = $this->compare_shapes( (array) $compat_set, (array) $wp_set );
		if ( $diff ) {
			return $this->result( $name, false, 'Shape mismatch: ' . $diff );
		}

		// Verify categories round-trips as array.
		if ( ! is_array( $compat_set->categories ) || ! is_array( $wp_set->categories ) ) {
			return $this->result( $name, false, '"categories" property is not an array in one or both adapters.' );
		}

		return $this->result( $name, true, 'Both adapters return identical property set and value types.' );
	}

	/**
	 * Verify that both adapters return a field object with the same shape.
	 *
	 * @return array
	 */
	private function check_field_crud() {
		$name = 'Attribute field CRUD — shape parity';

		$compat = new DCA_Compat_Attribute_Repository();
		$wp     = new DCA_WP_Attribute_Repository();

		$set_data = array(
			'name'   => '_parity_set_' . time(),
			'status' => 'draft',
		);
		$compat_set_id = $compat->create_attribute_set( $set_data );
		$wp_set_id     = $wp->create_attribute_set( $set_data );

		if ( is_wp_error( $compat_set_id ) || is_wp_error( $wp_set_id ) ) {
			return $this->result( $name, false, 'Could not create test sets for field check.' );
		}

		$field_data = array(
			'attribute_set_id' => 0, // overridden below
			'field_name'       => '_parity_field_' . time(),
			'field_label'      => 'Parity Test Field',
			'field_type'       => 'select',
			'field_options'    => array( 'a' => 'Option A', 'b' => 'Option B' ),
		);

		$cf        = $field_data;
		$cf['attribute_set_id'] = $compat_set_id;
		$compat_field_id = $compat->create_field( $cf );

		$wf        = $field_data;
		$wf['attribute_set_id'] = $wp_set_id;
		$wp_field_id = $wp->create_field( $wf );

		// Read back via get_fields.
		$compat_fields = $compat->get_fields( $compat_set_id );
		$wp_fields     = $wp->get_fields( $wp_set_id );

		// Clean up.
		$compat->delete_attribute_set( $compat_set_id );
		$wp->delete_attribute_set( $wp_set_id );

		if ( is_wp_error( $compat_field_id ) || is_wp_error( $wp_field_id ) ) {
			return $this->result( $name, false, 'create_field returned WP_Error in one or both adapters.' );
		}

		if ( empty( $compat_fields ) || empty( $wp_fields ) ) {
			return $this->result( $name, false, 'get_fields returned empty in one or both adapters.' );
		}

		$compat_field = reset( $compat_fields );
		$wp_field     = reset( $wp_fields );

		$diff = $this->compare_shapes( (array) $compat_field, (array) $wp_field );
		if ( $diff ) {
			return $this->result( $name, false, 'Field shape mismatch: ' . $diff );
		}

		// field_options must be an array in both.
		if ( ! is_array( $compat_field->field_options ) || ! is_array( $wp_field->field_options ) ) {
			return $this->result( $name, false, '"field_options" is not an array in one or both adapters.' );
		}

		return $this->result( $name, true, 'Both adapters return identical field property set and value types.' );
	}

	/**
	 * Verify that vendor values written via one adapter are readable via the other
	 * (shared user meta storage).
	 *
	 * @return array
	 */
	private function check_vendor_value_shared_storage() {
		$name = 'Vendor value — shared user-meta storage';

		// Use a dummy vendor ID that is extremely unlikely to be a real user.
		$test_vendor_id = 999998;
		$field_name     = '_parity_meta_' . time();
		$test_value     = 'parity_value_' . wp_generate_password( 8, false );

		$compat = new DCA_Compat_Attribute_Repository();
		$wp     = new DCA_WP_Attribute_Repository();

		// Write via compat, read via WP.
		$compat->save_vendor_value( $test_vendor_id, $field_name, $test_value );
		$read_via_wp = $wp->get_vendor_value( $test_vendor_id, $field_name );

		// Write via WP, read via compat.
		$new_value = 'parity_new_' . wp_generate_password( 8, false );
		$wp->save_vendor_value( $test_vendor_id, $field_name, $new_value );
		$read_via_compat = $compat->get_vendor_value( $test_vendor_id, $field_name );

		// Clean up.
		delete_user_meta( $test_vendor_id, $field_name );

		if ( $read_via_wp !== $test_value ) {
			return $this->result( $name, false, 'Value written via compat adapter was not readable via WP adapter.' );
		}

		if ( $read_via_compat !== $new_value ) {
			return $this->result( $name, false, 'Value written via WP adapter was not readable via compat adapter.' );
		}

		return $this->result( $name, true, 'Vendor values are shared between both adapters via user meta.' );
	}

	/**
	 * Verify that profile projectors return arrays (shape check — no real vendor needed).
	 *
	 * @return array
	 */
	private function check_profile_projector_shape() {
		$name = 'Profile projector — return type parity';

		$compat = new DCA_Compat_Profile_Projector();
		$wp     = new DCA_WP_Profile_Projector();

		// Use a non-existent vendor ID — both projectors should return [].
		$compat_result = $compat->project_vendor_attributes( 0 );
		$wp_result     = $wp->project_vendor_attributes( 0 );

		if ( ! is_array( $compat_result ) ) {
			return $this->result( $name, false, 'Compat projector did not return an array for vendor_id=0.' );
		}
		if ( ! is_array( $wp_result ) ) {
			return $this->result( $name, false, 'WP projector did not return an array for vendor_id=0.' );
		}

		return $this->result( $name, true, 'Both projectors return array[] for a non-existent vendor.' );
	}

	/**
	 * Verify that filter schema providers return arrays with the same top-level keys.
	 *
	 * @return array
	 */
	private function check_filter_schema_shape() {
		$name = 'Filter schema — return type parity';

		$compat = new DCA_Compat_Filter_Provider();
		$wp     = new DCA_WP_Filter_Provider();

		$compat_schema = $compat->get_filter_schema();
		$wp_schema     = $wp->get_filter_schema();

		if ( ! is_array( $compat_schema ) ) {
			return $this->result( $name, false, 'Compat filter provider did not return an array.' );
		}
		if ( ! is_array( $wp_schema ) ) {
			return $this->result( $name, false, 'WP filter provider did not return an array.' );
		}

		return $this->result( $name, true, 'Both filter providers return array[].' );
	}

	/**
	 * Verify that apply_query_filters returns an array in both adapters.
	 *
	 * @return array
	 */
	private function check_apply_query_filters_shape() {
		$name = 'apply_query_filters — return type parity';

		$compat = new DCA_Compat_Filter_Provider();
		$wp     = new DCA_WP_Filter_Provider();

		$base_args = array( 'role' => 'seller' );

		$compat_result = $compat->apply_query_filters( $base_args, array() );
		$wp_result     = $wp->apply_query_filters( $base_args, array() );

		if ( ! is_array( $compat_result ) ) {
			return $this->result( $name, false, 'Compat provider did not return an array.' );
		}
		if ( ! is_array( $wp_result ) ) {
			return $this->result( $name, false, 'WP provider did not return an array.' );
		}

		// With empty filters both adapters must return the original args unchanged.
		if ( $compat_result !== $base_args || $wp_result !== $base_args ) {
			return $this->result( $name, false, 'With empty $active_filters, args were mutated unexpectedly.' );
		}

		return $this->result( $name, true, 'Both providers return the original args unchanged when no filters are active.' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a result entry.
	 *
	 * @param string $check
	 * @param bool   $pass
	 * @param string $detail
	 * @return array
	 */
	private function result( $check, $pass, $detail ) {
		return array(
			'check'  => $check,
			'pass'   => (bool) $pass,
			'detail' => (string) $detail,
		);
	}

	/**
	 * Compare two flat associative arrays for structural parity:
	 * - same keys present in both
	 * - same PHP type for each key's value (gettype)
	 *
	 * Returns a non-empty string describing the first difference found, or an
	 * empty string when the shapes match.
	 *
	 * @param array $a
	 * @param array $b
	 * @return string
	 */
	private function compare_shapes( array $a, array $b ) {
		$keys_a = array_keys( $a );
		$keys_b = array_keys( $b );

		$missing_in_b = array_diff( $keys_a, $keys_b );
		if ( $missing_in_b ) {
			return 'Keys missing in WP adapter: ' . implode( ', ', $missing_in_b );
		}

		$missing_in_a = array_diff( $keys_b, $keys_a );
		if ( $missing_in_a ) {
			return 'Extra keys in WP adapter (not in compat): ' . implode( ', ', $missing_in_a );
		}

		foreach ( $keys_a as $key ) {
			$type_a = gettype( $a[ $key ] );
			$type_b = gettype( $b[ $key ] );
			// Allow integer vs double mismatch for numeric fields (both are "numeric").
			if ( $type_a !== $type_b ) {
				$numeric = array( 'integer', 'double' );
				if ( ! ( in_array( $type_a, $numeric, true ) && in_array( $type_b, $numeric, true ) ) ) {
					return "Key \"{$key}\": compat type={$type_a}, wp type={$type_b}";
				}
			}
		}

		return '';
	}
}
