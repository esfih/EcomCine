<?php
/**
 * Plugin Name: EcomCine
 * Description: Unified EcomCine app plugin consolidating cinematic media, account panel, and booking modal features.
 * Version: 0.1.13
 * Author: EcomCine
 * Update URI: https://updates.ecomcine.com/update-server.php
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMCINE_VERSION', '0.1.13' );
define( 'ECOMCINE_FILE', __FILE__ );
define( 'ECOMCINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECOMCINE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Keep bootstrap resilient: never crash the whole site when requirements are unmet.
 *
 * @param string[] $errors
 */
function ecomcine_register_bootstrap_notice( array $errors ) {
	if ( empty( $errors ) ) {
		return;
	}

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[EcomCine bootstrap] ' . implode( ' | ', $errors ) );
	}

	add_action(
		'admin_notices',
		static function () use ( $errors ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p><strong>EcomCine:</strong> '
				. esc_html__( 'Plugin bootstrap failed. Review the items below.', 'ecomcine' )
				. '</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	);
}

/**
 * Include a required PHP file only when it exists.
 *
 * @param string   $relative_path
 * @param string[] $errors
 * @return bool
 */
function ecomcine_require_file( $relative_path, array &$errors ) {
	$path = ECOMCINE_DIR . ltrim( (string) $relative_path, '/' );
	if ( ! is_file( $path ) ) {
		$errors[] = sprintf( 'Missing required file: %s', $relative_path );
		return false;
	}

	require_once $path;
	return true;
}

$ecomcine_bootstrap_errors = array();

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	$ecomcine_bootstrap_errors[] = sprintf( 'PHP 8.1 or newer is required. Current: %s', PHP_VERSION );
	ecomcine_register_bootstrap_notice( $ecomcine_bootstrap_errors );
	return;
}

$required_files = array(
	'includes/core/class-plugin-capability.php',
	'includes/core/class-plugin-updater.php',
	'includes/core/contracts/interface-theme-adapter.php',
	'includes/core/contracts/interface-commerce-adapter.php',
	'includes/core/adapters/class-theme-adapter-dokan-astra.php',
	'includes/core/adapters/class-theme-adapter-wp-baseline.php',
	'includes/core/adapters/class-commerce-adapter-woodokan.php',
	'includes/core/adapters/class-commerce-adapter-wp-baseline.php',
	'includes/core/adapters/class-commerce-adapter-fluentcart.php',
	'includes/core/runtime/class-runtime-adapters.php',
	'includes/admin/class-admin-settings.php',
	'includes/licensing/class-offer-catalog.php',
	'includes/licensing/class-licensing.php',
	'includes/compat/vendor-utilities.php',
);

foreach ( $required_files as $required_file ) {
	ecomcine_require_file( $required_file, $ecomcine_bootstrap_errors );
}

if ( ! class_exists( 'EcomCine_Admin_Settings', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Admin_Settings';
}
if ( ! class_exists( 'EcomCine_Licensing', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Licensing';
}
if ( ! class_exists( 'EcomCine_Plugin_Updater', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Plugin_Updater';
}

if ( ! empty( $ecomcine_bootstrap_errors ) ) {
	ecomcine_register_bootstrap_notice( $ecomcine_bootstrap_errors );
	return;
}

EcomCine_Admin_Settings::init();
EcomCine_Licensing::init();
EcomCine_Plugin_Updater::init();

// Demo data (non-critical — load after core bootstrap).
$_demo_errors = [];
ecomcine_require_file( 'includes/class-demo-importer.php', $_demo_errors );
ecomcine_require_file( 'includes/admin/class-demo-data-page.php', $_demo_errors );
if ( class_exists( 'EcomCine_Demo_Data_Page', false ) ) {
	EcomCine_Demo_Data_Page::init();
}
unset( $_demo_errors );

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

// ── Upgrade routines ─────────────────────────────────────────────────────────

/**
 * Run any pending upgrade routines once per plugin version bump.
 * Hooked to init (after all plugins load) so Dokan functions are available.
 */
add_action( 'init', function() {
	$stored = get_option( 'ecomcine_version', '0' );
	if ( version_compare( $stored, ECOMCINE_VERSION, '>=' ) ) {
		return;
	}

	// v0.1.13 — Backfill tm_l1_complete for all approved Dokan vendors.
	if ( version_compare( $stored, '0.1.13', '<' ) && function_exists( 'dokan_get_sellers' ) ) {
		$paged   = 1;
		$updated = 0;
		do {
			$res     = dokan_get_sellers( [ 'status' => 'approved', 'number' => 200, 'paged' => $paged ] );
			$vendors = $res['users'] ?? [];
			foreach ( $vendors as $v ) {
				$uid = (int) $v->ID;
				if ( get_user_meta( $uid, 'tm_l1_complete', true ) !== '1' ) {
					update_user_meta( $uid, 'tm_l1_complete', '1' );
					$updated++;
				}
			}
			$paged++;
		} while ( count( $vendors ) === 200 );
		error_log( "[EcomCine 0.1.13] tm_l1_complete backfill: set flag for {$updated} vendors." );
	}

	update_option( 'ecomcine_version', ECOMCINE_VERSION );
}, 10 );

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

// 4) Store UI module (cinematic vendor store template overrides, attribute panels, social metrics).
ecomcine_load_legacy_module(
	'modules/tm-store-ui/tm-store-ui.php',
	defined( 'TM_STORE_UI_VERSION' )
		|| ecomcine_is_plugin_slug_active( 'tm-store-ui' )
);

// ── Activation / deactivation ─────────────────────────────────────────────

/**
 * On activation:
 *  1. Deploy the bundled ecomcine-debug.php into wp-content/mu-plugins/ so the
 *     debug logger is always available without a manual file copy.
 *  2. Create the wp-content/ecomcine-debug.txt flag file to enable logging immediately.
 *     This lets the site owner see structured error output from the first page load
 *     after install — essential for diagnosing live-server issues.
 *
 * The flag file can be deleted at any time to disable logging with zero code changes:
 *   wp eval "unlink( WP_CONTENT_DIR . '/ecomcine-debug.txt' );"
 */
register_activation_hook( ECOMCINE_FILE, function() {
	// ── 1. Deploy MU-plugin ───────────────────────────────────────────────
	$src    = ECOMCINE_DIR . 'mu-plugins/ecomcine-debug.php';
	$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
	$dest   = $mu_dir . '/ecomcine-debug.php';

	if ( file_exists( $src ) ) {
		if ( ! is_dir( $mu_dir ) ) {
			wp_mkdir_p( $mu_dir );
		}
		@copy( $src, $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	// ── 2. Create the debug flag file ─────────────────────────────────────
	$flag = WP_CONTENT_DIR . '/ecomcine-debug.txt';
	if ( ! file_exists( $flag ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $flag, "EcomCine debug logging enabled.\nDelete this file to disable.\n" );
	}
} );

/**
 * On deactivation: remove the debug flag file to stop logging immediately.
 * The MU-plugin file stays installed so it can be re-enabled by recreating
 * the flag file — but it silently no-ops when the flag file is absent.
 */
register_deactivation_hook( ECOMCINE_FILE, function() {
	$flag = WP_CONTENT_DIR . '/ecomcine-debug.txt';
	if ( file_exists( $flag ) ) {
		@unlink( $flag ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
} );
