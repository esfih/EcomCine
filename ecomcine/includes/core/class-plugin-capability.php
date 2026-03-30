<?php
/**
 * EcomCine plugin capability detection.
 *
 * Central registry for detecting which third-party plugins are active.
 * Results are cached per-request (computed once on plugins_loaded).
 *
 * Usage:
 *   EcomCine_Plugin_Capability::has_woocommerce()
 *   EcomCine_Plugin_Capability::has_wc_bookings()
 *   EcomCine_Plugin_Capability::snapshot()   // for settings page / diagnostics
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Plugin_Capability {

	/** @var array<string,bool>|null Per-request capability cache. */
	private static ?array $cache = null;

	/**
	 * Compute and cache all capability flags once.
	 */
	private static function init(): void {
		if ( null !== self::$cache ) {
			return;
		}

		self::$cache = [
			'woocommerce'    => class_exists( 'WooCommerce' ),
			'wc_bookings'    => class_exists( 'WC_Booking' ) || class_exists( 'WC_Bookings' ),
			'dokan'          => function_exists( 'dokan_get_store_url' ),
			'dokan_pro'      => class_exists( 'Dokan_Pro' ) || defined( 'DOKAN_PRO_FILE' ),
			'fluentcart'     => class_exists( 'FluentCart\App\App' ) || function_exists( 'fluentcart' ),
			'fluentcart_pro' => class_exists( 'FluentCartPro\App\Core\App' ),
		];
	}

	public static function has_woocommerce(): bool {
		self::init();
		return (bool) self::$cache['woocommerce'];
	}

	public static function has_wc_bookings(): bool {
		self::init();
		return (bool) self::$cache['wc_bookings'];
	}

	public static function has_dokan(): bool {
		self::init();
		return (bool) self::$cache['dokan'];
	}

	public static function has_dokan_pro(): bool {
		self::init();
		return (bool) self::$cache['dokan_pro'];
	}

	public static function has_fluentcart(): bool {
		self::init();
		return (bool) self::$cache['fluentcart'];
	}

	public static function has_fluentcart_pro(): bool {
		self::init();
		return (bool) self::$cache['fluentcart_pro'];
	}

	/**
	 * Reset cache. For testing only — do not call in production.
	 */
	public static function reset_cache(): void {
		self::$cache = null;
	}

	/**
	 * Return a full snapshot for use by the settings page and diagnostics.
	 *
	 * @return array<string, array{present: bool, required_by: string}>
	 */
	public static function snapshot(): array {
		self::init();

		return [
			'woocommerce' => [
				'present'     => self::$cache['woocommerce'],
				'required_by' => 'Checkout flow, Orders section (enhanced)',
			],
			'wc_bookings' => [
				'present'     => self::$cache['wc_bookings'],
				'required_by' => 'Booking CTA (WC Bookings render path)',
			],
			'dokan' => [
				'present'     => self::$cache['dokan'],
				'required_by' => 'Vendor store pages, DCA frontend',
			],
			'dokan_pro' => [
				'present'     => self::$cache['dokan_pro'],
				'required_by' => 'DCA Pro features',
			],
			'fluentcart' => [
				'present'     => self::$cache['fluentcart'],
				'required_by' => 'Checkout (FluentCart integration)',
			],
			'fluentcart_pro' => [
				'present'     => self::$cache['fluentcart_pro'],
				'required_by' => 'Checkout Pro features',
			],
		];
	}
}
