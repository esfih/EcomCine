<?php
/**
 * Default WP Profile Projector — CPT-based scaffold (Phase 1 stub).
 *
 * Phase 1: Returns an empty array.  Phase 2 will query CPT post meta for
 * attribute sets and user meta for vendor values.
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * TODO(phase-2): Query 'dca_attribute_set' CPT and user meta; return same
 * AttributeSetProjection[] shape defined in DCA_Compat_Profile_Projector.
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

	/**
	 * @inheritdoc
	 *
	 * Phase 1: stub — returns empty array.
	 *
	 * @param int $vendor_id
	 * @return array
	 */
	public function project_vendor_attributes( $vendor_id ) {
		// TODO(phase-2): Build projection from CPT-based attribute sets.
		_doing_it_wrong(
			__METHOD__,
			'Default WP profile projector not yet implemented.',
			'1.1.0'
		);

		return array();
	}
}
