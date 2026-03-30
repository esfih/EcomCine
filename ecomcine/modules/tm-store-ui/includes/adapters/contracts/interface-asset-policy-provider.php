<?php
/**
 * Contract: asset governance policy for vendor pages (tho-002).
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface THO_Asset_Policy_Provider {
	/**
	 * Return asset policy arrays for the current page context.
	 *
	 * @param array $context ['is_vendor_page' => bool, 'is_listing' => bool]
	 * @return array{
	 *   dequeue_scripts: string[],
	 *   dequeue_styles: string[],
	 *   keep_scripts: string[]
	 * }
	 */
	public function get_asset_policy( array $context ): array;
}
