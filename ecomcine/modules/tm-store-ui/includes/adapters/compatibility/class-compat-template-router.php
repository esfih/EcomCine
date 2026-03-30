<?php
/**
 * Compatibility adapter: template router using Dokan template overrides.
 *
 * Uses theme/dokan/ directory shadowing and Dokan context functions.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Template_Router implements THO_Template_Router {

	public function get_store_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';
		$plugin_dokan = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/dokan/' : '';

		if ( ! empty( $context['is_showcase'] ) ) {
			$full = $plugin_tpl . 'template-talent-showcase-full.php';
			$std  = $plugin_tpl . 'template-talent-showcase.php';
			return file_exists( $full ) ? $full : ( file_exists( $std ) ? $std : '' );
		}

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		if ( ! empty( $context['is_store'] ) ) {
			$tpl = $plugin_dokan . 'store.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		return '';
	}

	public function get_listing_page_template( array $context ): string {
		$plugin_tpl = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/page-templates/' : '';
		$plugin_dokan = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/dokan/' : '';

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $plugin_tpl . 'page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		$tpl = $plugin_dokan . 'store-lists.php';
		return file_exists( $tpl ) ? $tpl : '';
	}
}
