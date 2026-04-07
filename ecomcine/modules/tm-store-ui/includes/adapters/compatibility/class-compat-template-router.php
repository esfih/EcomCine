<?php
/**
 * Compatibility adapter: template router using vendor-store overrides.
 *
 * Keeps Dokan hook contracts, but resolves plugin-owned template files from
 * the vendor-store namespace instead of the legacy vendor template tree.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Template_Router implements THO_Template_Router {

	public function get_store_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';
		$plugin_vendor = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/vendor-store/' : '';

		if ( ! empty( $context['is_showcase'] ) ) {
			$tpl = $plugin_tpl . 'template-talent-showcase.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		if ( ! empty( $context['is_store'] ) ) {
			$tpl = $plugin_vendor . 'store.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		return '';
	}

	public function get_listing_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';
		$plugin_vendor = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/vendor-store/' : '';

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		$tpl = $plugin_vendor . 'store-lists.php';
		return file_exists( $tpl ) ? $tpl : '';
	}
}
