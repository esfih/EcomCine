<?php
/**
 * Vendor Category Resolver Contract
 *
 * Resolves which category slugs are assigned to a vendor. Used by
 * dca-002, dca-003, and dca-004 to determine which attribute sets
 * apply for a given vendor context.
 *
 * @package Dokan_Category_Attributes
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DCA_Vendor_Category_Resolver {

	/**
	 * Return taxonomy category slugs assigned to the given vendor.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 * @return string[] Array of category slugs; empty array if none assigned.
	 */
	public function get_vendor_categories( int $vendor_id ): array;
}
