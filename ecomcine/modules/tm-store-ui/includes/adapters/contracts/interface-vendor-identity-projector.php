<?php
/**
 * Contract: vendor identity projection for product/listing cards (tho-004).
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface THO_Vendor_Identity_Projector {
	/**
	 * Return structured vendor identity (name, avatar, store URL).
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array{name: string, avatar_url: string, store_url: string}
	 */
	public function project_vendor_identity( int $vendor_id ): array;

	/**
	 * Render the vendor identity HTML block.
	 *
	 * @param int    $vendor_id WP user ID.
	 * @param string $context   'product_loop' or 'store_listing'.
	 * @return string HTML string, or '' if vendor_id is invalid.
	 */
	public function render_vendor_identity_block( int $vendor_id, string $context = 'product_loop' ): string;
}
