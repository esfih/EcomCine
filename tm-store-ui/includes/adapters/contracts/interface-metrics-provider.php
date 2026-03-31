<?php
/**
 * Contract: social metrics, completeness, and map modules (tho-005).
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface THO_Metrics_Provider {
	/**
	 * Compute social platform links for a vendor.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array{platforms: array<array{id: string, url: string, label: string}>}
	 */
	public function compute_social_metrics( int $vendor_id ): array;

	/**
	 * Compute profile completeness.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array{score: int, sections: array, missing_fields: string[]}
	 */
	public function compute_completeness( int $vendor_id ): array;

	/**
	 * Render a map embed for the vendor's location.
	 *
	 * @param int   $vendor_id WP user ID.
	 * @param array $options   width, height, zoom.
	 * @return string HTML string, or '' if no location or feature disabled.
	 */
	public function render_map_embed( int $vendor_id, array $options = [] ): string;
}
