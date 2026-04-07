<?php
/**
 * Profile Projector Contract
 *
 * Core contract for projecting a vendor's attribute values into a
 * structured public-display representation (feature dca-003).
 * Does not write any state; read-only projection.
 *
 * @package Dokan_Category_Attributes
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DCA_Profile_Projector {

	/**
	 * Build the public attribute projection for a vendor.
	 *
	 * Applies the following rules:
	 * - Only attribute sets whose category list intersects with the
	 *   vendor's assigned categories are included.
	 * - Only fields with show_in_public = 1 are included.
	 * - Only fields whose stored value is non-empty are included.
	 * - Sets with zero populated public fields are excluded.
	 * - Sets are ordered by priority ASC.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 * @return array[] Each element is an attribute set projection:
	 *   [
	 *     'set_id'   => int,
	 *     'name'     => string,
	 *     'icon'     => string|null,
	 *     'fields'   => [
	 *       [ 'label' => string, 'icon' => string|null, 'value' => mixed ],
	 *       ...
	 *     ],
	 *   ]
	 *   Empty array when no applicable sets exist.
	 */
	public function project_vendor_attributes( int $vendor_id ): array;
}
