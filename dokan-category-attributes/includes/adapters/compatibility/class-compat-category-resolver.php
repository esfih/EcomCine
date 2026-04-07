<?php
/**
 * Compatibility Category Resolver — resolves vendor store categories via Dokan taxonomy.
 *
 * Uses wp_get_object_terms with the 'store_category' taxonomy registered by Dokan.
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
 * Class DCA_Compat_Category_Resolver
 *
 * @implements DCA_Vendor_Category_Resolver
 */
class DCA_Compat_Category_Resolver implements DCA_Vendor_Category_Resolver {

	/**
	 * @inheritdoc
	 */
	public function get_vendor_categories( int $vendor_id ): array {
		$terms = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_values( $terms );
	}
}
