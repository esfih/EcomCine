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
		$theme_dir = get_stylesheet_directory();

		if ( ! empty( $context['is_showcase'] ) ) {
			// Full-width showcase.
			$full = $theme_dir . '/template-talent-showcase-full.php';
			$std  = $theme_dir . '/template-talent-showcase.php';
			return file_exists( $full ) ? $full : ( file_exists( $std ) ? $std : '' );
		}

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $theme_dir . '/page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		if ( ! empty( $context['is_store'] ) ) {
			$tpl = $theme_dir . '/dokan/store.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		return '';
	}

	public function get_listing_page_template( array $context ): string {
		$theme_dir = get_stylesheet_directory();

		if ( ! empty( $context['is_platform'] ) ) {
			$tpl = $theme_dir . '/page-platform.php';
			return file_exists( $tpl ) ? $tpl : '';
		}

		$tpl = $theme_dir . '/dokan/store-lists.php';
		return file_exists( $tpl ) ? $tpl : '';
	}
}
