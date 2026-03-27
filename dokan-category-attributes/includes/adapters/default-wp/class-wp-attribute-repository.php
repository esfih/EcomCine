<?php
/**
 * Default WP Attribute Repository — CPT-based scaffold (Phase 1 stub).
 *
 * Phase 1: All methods are stubbed.  Each logs _doing_it_wrong() and returns a
 * safe empty/false value so the plugin loads cleanly when dca_active_adapter is
 * set to 'default_wp' in a staging or test environment.
 *
 * Phase 2 implementation plan:
 *  - Attribute sets  → CPT 'dca_attribute_set'
 *  - Attribute fields → post meta on the set CPT
 *  - Vendor values   → user meta (same key names as compat layer for migration ease)
 *  - Categories      → ACF relationship or standard taxonomy term assignment
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 1 — Core Contract Scaffolding
 * TODO(phase-2): Implement CPT-based persistence.
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

	// -------------------------------------------------------------------------
	// Attribute Set CRUD — TODO(phase-2)
	// -------------------------------------------------------------------------

	public function get_attribute_sets( array $args = array() ) {
		$this->not_implemented( __METHOD__ );
		return array();
	}

	public function get_attribute_set( $set_id ) {
		$this->not_implemented( __METHOD__ );
		return null;
	}

	public function create_attribute_set( array $set_data, array $fields = array() ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function update_attribute_set( $set_id, array $set_data ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function delete_attribute_set( $set_id ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function export_attribute_set( $set_id ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function import_attribute_set( $json ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	// -------------------------------------------------------------------------
	// Field CRUD — TODO(phase-2)
	// -------------------------------------------------------------------------

	public function get_fields( $set_id, array $args = array() ) {
		$this->not_implemented( __METHOD__ );
		return array();
	}

	public function create_field( array $field_data ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function update_field( $field_id, array $field_data ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	public function delete_field( $field_id ) {
		$this->not_implemented( __METHOD__ );
		return new WP_Error( 'dca_not_implemented', 'Default WP adapter not yet implemented.' );
	}

	// -------------------------------------------------------------------------
	// Vendor Values — TODO(phase-2): reuse user-meta keys for zero-migration cost
	// -------------------------------------------------------------------------

	public function get_vendor_value( $vendor_id, $field_name ) {
		// Phase 2 note: user-meta storage is identical to compat layer; this stub
		// can be promoted to the real implementation with a one-line change.
		$this->not_implemented( __METHOD__ );
		return null;
	}

	public function save_vendor_value( $vendor_id, $field_name, $value ) {
		$this->not_implemented( __METHOD__ );
		return false;
	}

	public function get_fields_for_vendor( $vendor_id ) {
		$this->not_implemented( __METHOD__ );
		return array();
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Log a wp notice when a stub method is called.
	 *
	 * @param string $method
	 */
	private function not_implemented( $method ) {
		_doing_it_wrong(
			esc_html( $method ),
			'Default WP adapter not yet implemented. Switch dca_active_adapter to "compatibility".',
			'1.1.0'
		);
	}
}
