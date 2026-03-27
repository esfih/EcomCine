<?php
/**
 * Dashboard Renderer Contract
 *
 * Core contract for vendor dashboard attribute field rendering and
 * save operations (feature dca-002). Decouples business logic from
 * the Dokan hook system so the same behaviour can be driven from
 * alternative entry points (REST, Gutenberg sidebar, etc.).
 *
 * @package Dokan_Category_Attributes
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DCA_Dashboard_Renderer {

	/**
	 * Output the attribute field sections appropriate for the given vendor.
	 *
	 * Only sections whose category list intersects with the vendor's assigned
	 * categories are rendered; non-matching sections are hidden via CSS.
	 * Fields with show_in_dashboard = 0 are excluded.
	 *
	 * @param int   $vendor_id     WP user ID of the vendor.
	 * @param array $store_settings Current store settings array (may be empty).
	 * @return void Outputs HTML directly.
	 */
	public function render_fields( int $vendor_id, array $store_settings = array() ): void;

	/**
	 * Persist submitted attribute field values for the given vendor.
	 *
	 * Reads values from the provided POST data array. Each field is
	 * sanitized according to its field_type before being stored.
	 *
	 * @param int   $vendor_id WP user ID of the vendor.
	 * @param array $post_data Raw POST payload (e.g. $_POST or equivalent).
	 * @return array {
	 *   @type string[] $saved  Field names that were successfully saved.
	 *   @type string[] $errors Field names that failed validation.
	 * }
	 */
	public function save_submitted_values( int $vendor_id, array $post_data ): array;
}
