<?php
/**
 * Adapter registry for TM Account Panel.
 *
 * Selects between compatibility (Dokan) and default-WP adapters at runtime.
 * Set constant TAP_ADAPTER='default-wp' to force the WP-native adapter.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Adapter_Registry {

	/** @var TAP_Page_Context_Resolver|null */
	private static ?TAP_Page_Context_Resolver $context_resolver = null;

	/** @var TAP_Login_Handler|null */
	private static ?TAP_Login_Handler $login_handler = null;

	/** @var TAP_Onboarding_Provider|null */
	private static ?TAP_Onboarding_Provider $onboarding_provider = null;

	/** @var TAP_Account_Data_Provider|null */
	private static ?TAP_Account_Data_Provider $account_data_provider = null;

	// -----------------------------------------------------------------------
	// Resolver factory
	// -----------------------------------------------------------------------

	private static function use_default_wp(): bool {
		if ( defined( 'TAP_ADAPTER' ) && TAP_ADAPTER === 'default-wp' ) {
			return true;
		}
		// Wave 1: when listing authority is 'core' or 'shadow', always prefer the
		// WP-native adapter regardless of Dokan presence or runtime mode setting.
		if ( class_exists( 'EcomCine_Wave1_Authority', false ) ) {
			$listing_state = EcomCine_Wave1_Authority::get_listing_state();
			if ( 'core' === $listing_state || 'shadow' === $listing_state ) {
				return true;
			}
		}
		if ( class_exists( 'EcomCine_Admin_Settings', false ) ) {
			$mode = EcomCine_Admin_Settings::get_runtime_mode();
			// Dokan-compat adapters only needed in Dokan modes.
			if ( ! in_array( $mode, array( 'wp_woo_dokan', 'wp_woo_dokan_booking' ), true ) ) {
				return true;
			}
		}
		return ! function_exists( 'dokan_is_store_page' );
	}

	// -----------------------------------------------------------------------
	// Public getters
	// -----------------------------------------------------------------------

	public static function get_context_resolver(): TAP_Page_Context_Resolver {
		if ( null === self::$context_resolver ) {
			self::$context_resolver = self::use_default_wp()
				? new TAP_WP_Page_Context_Resolver()
				: new TAP_Compat_Page_Context_Resolver();
		}
		return self::$context_resolver;
	}

	public static function get_login_handler(): TAP_Login_Handler {
		if ( null === self::$login_handler ) {
			self::$login_handler = self::use_default_wp()
				? new TAP_WP_Login_Handler()
				: new TAP_Compat_Login_Handler();
		}
		return self::$login_handler;
	}

	public static function get_onboarding_provider(): TAP_Onboarding_Provider {
		if ( null === self::$onboarding_provider ) {
			self::$onboarding_provider = self::use_default_wp()
				? new TAP_WP_Onboarding_Provider()
				: new TAP_Compat_Onboarding_Provider();
		}
		return self::$onboarding_provider;
	}

	public static function get_account_data_provider(): TAP_Account_Data_Provider {
		if ( null === self::$account_data_provider ) {
			self::$account_data_provider = self::use_default_wp()
				? new TAP_WP_Account_Data_Provider()
				: new TAP_Compat_Account_Data_Provider();
		}
		return self::$account_data_provider;
	}

	// -----------------------------------------------------------------------
	// Test injection helpers
	// -----------------------------------------------------------------------

	public static function set_context_resolver( TAP_Page_Context_Resolver $r ): void {
		self::$context_resolver = $r;
	}

	public static function set_login_handler( TAP_Login_Handler $h ): void {
		self::$login_handler = $h;
	}

	public static function set_onboarding_provider( TAP_Onboarding_Provider $p ): void {
		self::$onboarding_provider = $p;
	}

	public static function set_account_data_provider( TAP_Account_Data_Provider $p ): void {
		self::$account_data_provider = $p;
	}

	public static function reset(): void {
		self::$context_resolver      = null;
		self::$login_handler         = null;
		self::$onboarding_provider   = null;
		self::$account_data_provider = null;
	}
}
