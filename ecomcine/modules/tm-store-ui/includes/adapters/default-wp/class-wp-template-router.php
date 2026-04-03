<?php
/**
 * Default-WP adapter: template router using block-theme template hierarchy.
 *
 * Uses theme/templates/ for block-based single/archive vendor templates.
 * No Dokan template filter needed.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_WP_Template_Router implements THO_Template_Router {

	public function get_store_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';

		if ( ! empty( $context['is_showcase'] ) ) {
			$tpl = $plugin_tpl . 'template-talent-showcase.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		// In default-WP mode a block theme handles single-tm_vendor routing natively.
		// Return '' to allow WP block template hierarchy to operate.
		return '';
	}

	public function get_listing_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		// Block theme: archive-tm_vendor.html handles listing routing natively.
		return '';
	}
}
