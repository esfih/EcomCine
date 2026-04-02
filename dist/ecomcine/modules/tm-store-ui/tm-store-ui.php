<?php
/**
 * Plugin Name: TM Store UI
 * Plugin URI:  https://ecomcine.com
 * Description: Theme-agnostic store UI layer for EcomCine — Dokan template overrides,
 *              vendor attributes, social metrics, cinematic header, store-listing filters,
 *              and all hooks previously coupled to the astra-child theme. Works with Astra,
 *              Twenty Twenty-Five, or any other WordPress theme.
 * Version:     1.0.1
 * Author:      EcomCine
 * Requires at least: 6.3
 * Requires PHP: 8.0
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

define( 'TM_STORE_UI_VERSION', '1.0.2' );
define( 'TM_STORE_UI_FILE',    __FILE__ );
define( 'TM_STORE_UI_DIR',     plugin_dir_path( __FILE__ ) );
define( 'TM_STORE_UI_URL',     plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap order:
 *  1. Helper functions (used in templates + hooks).
 *  2. All includes/ groups (adapters, attributes, social, etc.).
 *  3. Hook registrations (same hooks that were in theme/functions.php).
 *  4. Dokan template override registration.
 *  5. Plugin-owned page template registration.
 *  6. Block template injection for FSE themes.
 */
add_action( 'plugins_loaded', 'tm_store_ui_bootstrap', 5 );

function tm_store_ui_bootstrap() {
	// Guard: require Dokan to be present for store-page features, but still
	// load WC-only features (vendor identity on products) without Dokan.
	require_once TM_STORE_UI_DIR . 'includes/class-icons.php';
	require_once TM_STORE_UI_DIR . 'includes/template-helpers.php';
	require_once TM_STORE_UI_DIR . 'includes/vendor-attributes/vendor-attribute-sets.php';
	require_once TM_STORE_UI_DIR . 'includes/vendor-attributes/vendor-attributes-hooks.php';
	require_once TM_STORE_UI_DIR . 'includes/social-metrics/social-metrics.php';
	// Admin tools
	require_once TM_STORE_UI_DIR . 'includes/admin/vendor-edit-logs.php';
	require_once TM_STORE_UI_DIR . 'includes/admin/vendor-completeness-admin.php';
	// Vendor profile
	require_once TM_STORE_UI_DIR . 'includes/vendor-profile/vendor-completeness.php';
	require_once TM_STORE_UI_DIR . 'includes/vendor-profile/vendor-profile-ajax.php';
	// Vendors map shortcode
	require_once TM_STORE_UI_DIR . 'includes/vendors-map/vendors-map-shortcode.php';
	// Store-listing hooks (lives in templates/dokan/store-lists/ since it ships with templates)
	require_once TM_STORE_UI_DIR . 'templates/dokan/store-lists/store-lists-hooks.php';
	// THO adapter layer
	require_once TM_STORE_UI_DIR . 'includes/adapters/contracts/interface-template-router.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/contracts/interface-asset-policy-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/contracts/interface-profile-meta-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/contracts/interface-vendor-identity-projector.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/contracts/interface-metrics-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/compatibility/class-compat-template-router.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/compatibility/class-compat-asset-policy-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/compatibility/class-compat-profile-meta-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/compatibility/class-compat-vendor-identity-projector.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/compatibility/class-compat-metrics-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/default-wp/class-wp-template-router.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/default-wp/class-wp-asset-policy-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/default-wp/class-wp-profile-meta-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/default-wp/class-wp-vendor-identity-projector.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/default-wp/class-wp-metrics-provider.php';
	require_once TM_STORE_UI_DIR . 'includes/adapters/class-adapter-registry.php';

	// Register all hooks extracted from astra-child/functions.php.
	require_once TM_STORE_UI_DIR . 'includes/hooks.php';
	// Dokan template override registration.
	require_once TM_STORE_UI_DIR . 'includes/dokan-templates.php';
	// Plugin-owned page template registration (classic + FSE).
	require_once TM_STORE_UI_DIR . 'includes/page-templates.php';
	// Asset enqueue (theme-agnostic).
	require_once TM_STORE_UI_DIR . 'includes/enqueue.php';
}
