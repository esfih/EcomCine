<?php
/**
 * Runtime adapter resolver for stack-aware behavior.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Runtime_Adapters {
	private static $theme_adapter = null;
	private static $commerce_adapter = null;

	/**
	 * Get active theme/runtime adapter.
	 */
	public static function theme() {
		if ( null !== self::$theme_adapter ) {
			return self::$theme_adapter;
		}

		$runtime_mode = function_exists( 'ecomcine_get_settings_snapshot' )
			? EcomCine_Admin_Settings::get_runtime_mode()
			: 'preferred_stack';
		if ( 'baseline_wp' === $runtime_mode ) {
			self::$theme_adapter = new EcomCine_Theme_Adapter_WP_Baseline();
			return self::$theme_adapter;
		}

		if ( function_exists( 'dokan_is_store_page' ) && function_exists( 'dokan_is_store_listing' ) ) {
			self::$theme_adapter = new EcomCine_Theme_Adapter_Dokan_Astra();
		} else {
			self::$theme_adapter = new EcomCine_Theme_Adapter_WP_Baseline();
		}

		return self::$theme_adapter;
	}

	/**
	 * Get active commerce adapter.
	 */
	public static function commerce() {
		if ( null !== self::$commerce_adapter ) {
			return self::$commerce_adapter;
		}

		$runtime_mode = function_exists( 'ecomcine_get_settings_snapshot' )
			? EcomCine_Admin_Settings::get_runtime_mode()
			: 'preferred_stack';
		if ( 'baseline_wp' === $runtime_mode ) {
			self::$commerce_adapter = new EcomCine_Commerce_Adapter_WP_Baseline();
			return self::$commerce_adapter;
		}

		if ( class_exists( 'WooCommerce' ) && function_exists( 'dokan_get_store_url' ) ) {
			self::$commerce_adapter = new EcomCine_Commerce_Adapter_WooDokan();
		} else {
			self::$commerce_adapter = new EcomCine_Commerce_Adapter_WP_Baseline();
		}

		return self::$commerce_adapter;
	}

	/**
	 * Introspect runtime adapter selection for diagnostics.
	 */
	public static function snapshot() {
		$theme = self::theme();
		$commerce = self::commerce();

		return array(
			'theme_adapter' => $theme->id(),
			'commerce_adapter' => $commerce->id(),
			'commerce_available' => (bool) $commerce->is_available(),
		);
	}
}
