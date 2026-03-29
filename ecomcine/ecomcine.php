<?php
/**
 * Plugin Name: EcomCine
 * Description: Unified EcomCine app plugin consolidating cinematic media, account panel, and booking modal features.
 * Version: 0.1.0
 * Author: EcomCine
 * Update URI: https://updates.ecomcine.com/update-server.php
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMCINE_VERSION', '0.1.0' );
define( 'ECOMCINE_FILE', __FILE__ );
define( 'ECOMCINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECOMCINE_URL', plugin_dir_url( __FILE__ ) );

require_once ECOMCINE_DIR . 'includes/core/class-plugin-capability.php';
require_once ECOMCINE_DIR . 'includes/core/class-plugin-updater.php';
require_once ECOMCINE_DIR . 'includes/core/contracts/interface-theme-adapter.php';
require_once ECOMCINE_DIR . 'includes/core/contracts/interface-commerce-adapter.php';
require_once ECOMCINE_DIR . 'includes/core/adapters/class-theme-adapter-dokan-astra.php';
require_once ECOMCINE_DIR . 'includes/core/adapters/class-theme-adapter-wp-baseline.php';
require_once ECOMCINE_DIR . 'includes/core/adapters/class-commerce-adapter-woodokan.php';
require_once ECOMCINE_DIR . 'includes/core/adapters/class-commerce-adapter-wp-baseline.php';
require_once ECOMCINE_DIR . 'includes/core/adapters/class-commerce-adapter-fluentcart.php';
require_once ECOMCINE_DIR . 'includes/core/runtime/class-runtime-adapters.php';
require_once ECOMCINE_DIR . 'includes/admin/class-admin-settings.php';
require_once ECOMCINE_DIR . 'includes/licensing/class-offer-catalog.php';
require_once ECOMCINE_DIR . 'includes/licensing/class-licensing.php';
require_once ECOMCINE_DIR . 'includes/compat/vendor-utilities.php';

EcomCine_Admin_Settings::init();
EcomCine_Licensing::init();
EcomCine_Plugin_Updater::init();

/**
 * Helper for diagnostics/tests to inspect selected abstraction adapters.
 */
function ecomcine_get_runtime_adapter_snapshot() {
	return EcomCine_Runtime_Adapters::snapshot();
}

/**
 * Helper for diagnostics/tests to inspect current admin settings and plugin capabilities.
 */
function ecomcine_get_settings_snapshot() {
	$settings = EcomCine_Admin_Settings::get_settings();
	$settings['plugin_capabilities'] = EcomCine_Plugin_Capability::snapshot();
	return $settings;
}

/**
 * Helper for diagnostics/tests to inspect current licensing status.
 */
function ecomcine_get_license_status_snapshot() {
	return EcomCine_Licensing::get_status();
}

/**
 * Helper for diagnostics/tests to inspect current offer catalog parity map.
 */
function ecomcine_get_offer_catalog_snapshot() {
	return EcomCine_Offer_Catalog::get_catalog();
}

/**
 * Runtime helper to check module feature toggles.
 */
function ecomcine_feature_enabled( $feature_key ) {
	return EcomCine_Admin_Settings::is_feature_enabled( $feature_key );
}

/**
 * Check whether a plugin slug is already active in WordPress.
 */
function ecomcine_is_plugin_slug_active( $slug ) {
	$plugin_basename = trim( (string) $slug ) . '/' . trim( (string) $slug ) . '.php';
	$active_plugins  = (array) get_option( 'active_plugins', array() );

	if ( in_array( $plugin_basename, $active_plugins, true ) ) {
		return true;
	}

	if ( is_multisite() ) {
		$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( isset( $network_active[ $plugin_basename ] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Load a legacy module only when its original plugin is not already loaded.
 */
function ecomcine_load_legacy_module( $module_file, $already_loaded ) {
	if ( $already_loaded ) {
		return;
	}

	$path = ECOMCINE_DIR . ltrim( $module_file, '/' );
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

// 1) Media player module.
ecomcine_load_legacy_module(
	'modules/tm-media-player/tm-media-player.php',
	defined( 'TM_MEDIA_PLAYER_VERSION' )
		|| function_exists( 'tm_get_vendor_media_playlist' )
		|| ecomcine_is_plugin_slug_active( 'tm-media-player' )
		|| ! ecomcine_feature_enabled( 'media_player' )
);

// 2) Account panel module.
ecomcine_load_legacy_module(
	'modules/tm-account-panel/tm-account-panel.php',
	function_exists( 'tm_account_panel_is_store_page' )
		|| ecomcine_is_plugin_slug_active( 'tm-account-panel' )
		|| ! ecomcine_feature_enabled( 'account_panel' )
);

// 3) Booking modal module.
ecomcine_load_legacy_module(
	'modules/tm-vendor-booking-modal/tm-vendor-booking-modal.php',
	class_exists( 'TM_Vendor_Booking_Modal', false )
		|| ecomcine_is_plugin_slug_active( 'tm-vendor-booking-modal' )
		|| ! ecomcine_feature_enabled( 'booking_modal' )
);
