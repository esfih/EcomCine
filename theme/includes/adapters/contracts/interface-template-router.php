<?php
/**
 * Contract: store/listing template routing (tho-001).
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface THO_Template_Router {
	/**
	 * Resolve the template file path for the current page context.
	 *
	 * @param array $context  ['is_showcase' => bool, 'is_store' => bool, 'is_listing' => bool, 'is_platform' => bool]
	 * @return string  Absolute path to template file, or '' to fall through.
	 */
	public function get_store_page_template( array $context ): string;

	/**
	 * Resolve the listing/archive template for the current page context.
	 *
	 * @param array $context
	 * @return string
	 */
	public function get_listing_page_template( array $context ): string;
}
