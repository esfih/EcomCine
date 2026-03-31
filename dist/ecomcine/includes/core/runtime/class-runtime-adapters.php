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

		$mode = class_exists( 'EcomCine_Admin_Settings', false )
			? EcomCine_Admin_Settings::get_runtime_mode()
			: 'wp_woo_dokan_booking';

		switch ( $mode ) {
			case 'wp_woo_dokan':
			case 'wp_woo_dokan_booking':
				self::$theme_adapter = new EcomCine_Theme_Adapter_Dokan_Astra();
				break;
			default: // wp_cpt, wp_woo, wp_woo_booking
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

		$mode = class_exists( 'EcomCine_Admin_Settings', false )
			? EcomCine_Admin_Settings::get_runtime_mode()
			: 'wp_woo_dokan_booking';

		switch ( $mode ) {
			case 'wp_woo_dokan':
			case 'wp_woo_dokan_booking':
				self::$commerce_adapter = new EcomCine_Commerce_Adapter_WooDokan();
				break;
			case 'wp_fluentcart':
				self::$commerce_adapter = new EcomCine_Commerce_Adapter_FluentCart();
				break;
			default: // wp_cpt, wp_woo, wp_woo_booking
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
