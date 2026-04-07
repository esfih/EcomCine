<?php
/**
 * Default WP Category Resolver — Phase 1 scaffold.
 *
 * For Phase 1 this mirrors the compatibility resolver, using the Dokan
 * 'store_category' taxonomy.  Phase 2 will introduce a Dokan-free taxonomy.
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * TODO(phase-2): Replace 'store_category' with a plugin-owned taxonomy so that
 * the resolver works without Dokan being active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_Category_Resolver
 *
 * @implements DCA_Vendor_Category_Resolver
 */
class DCA_WP_Category_Resolver implements DCA_Vendor_Category_Resolver {

	/**
	 * @inheritdoc
	 *
	 * Phase 1: identical to compatibility resolver.  Returns slugs for
	 * terms assigned to the vendor user via the 'store_category' taxonomy.
	 *
	 * @param int $vendor_id
	 * @return string[]
	 */
	public function get_vendor_categories( int $vendor_id ): array {
		// TODO(phase-2): query a plugin-owned taxonomy ('dca_vendor_category')
		// that works without Dokan.
		$terms = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_values( $terms );
	}
}
