<?php
/**
 * Adapter registry for TM Vendor Booking Modal.
 *
 * Set constant TVBM_ADAPTER='default-wp' to force the WP-native adapters.
 * Otherwise auto-detects WooCommerce presence.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_Adapter_Registry {

	/** @var TVBM_Offer_Discovery|null */
	private static ?TVBM_Offer_Discovery $offer_discovery = null;

	/** @var TVBM_Booking_Form_Renderer|null */
	private static ?TVBM_Booking_Form_Renderer $form_renderer = null;

	/** @var TVBM_Checkout_Handler|null */
	private static ?TVBM_Checkout_Handler $checkout_handler = null;

	/** @var TVBM_Checkout_Policy|null */
	private static ?TVBM_Checkout_Policy $checkout_policy = null;

	private static function use_default_wp(): bool {
		if ( defined( 'TVBM_ADAPTER' ) && TVBM_ADAPTER === 'default-wp' ) {
			return true;
		}
		if ( class_exists( 'EcomCine_Admin_Settings', false )
			 && 'wp_cpt' === EcomCine_Admin_Settings::get_runtime_mode() ) {
			return true;
		}
		return ! function_exists( 'wc_get_product' );
	}

	public static function get_offer_discovery(): TVBM_Offer_Discovery {
		if ( null === self::$offer_discovery ) {
			self::$offer_discovery = self::use_default_wp()
				? new TVBM_WP_Offer_Discovery()
				: new TVBM_Compat_Offer_Discovery();
		}
		return self::$offer_discovery;
	}

	public static function get_form_renderer(): TVBM_Booking_Form_Renderer {
		if ( null === self::$form_renderer ) {
			self::$form_renderer = self::use_default_wp()
				? new TVBM_WP_Booking_Form_Renderer()
				: new TVBM_Compat_Booking_Form_Renderer();
		}
		return self::$form_renderer;
	}

	public static function get_checkout_handler(): TVBM_Checkout_Handler {
		if ( null === self::$checkout_handler ) {
			self::$checkout_handler = self::use_default_wp()
				? new TVBM_WP_Checkout_Handler()
				: new TVBM_Compat_Checkout_Handler();
		}
		return self::$checkout_handler;
	}

	public static function get_checkout_policy(): TVBM_Checkout_Policy {
		if ( null === self::$checkout_policy ) {
			self::$checkout_policy = self::use_default_wp()
				? new TVBM_WP_Checkout_Policy()
				: new TVBM_Compat_Checkout_Policy();
		}
		return self::$checkout_policy;
	}

	// Test injection helpers.
	public static function set_offer_discovery( TVBM_Offer_Discovery $d ): void { self::$offer_discovery = $d; }
	public static function set_form_renderer( TVBM_Booking_Form_Renderer $r ): void { self::$form_renderer = $r; }
	public static function set_checkout_handler( TVBM_Checkout_Handler $h ): void { self::$checkout_handler = $h; }
	public static function set_checkout_policy( TVBM_Checkout_Policy $p ): void { self::$checkout_policy = $p; }

	public static function reset(): void {
		self::$offer_discovery  = null;
		self::$form_renderer    = null;
		self::$checkout_handler = null;
		self::$checkout_policy  = null;
	}
}
