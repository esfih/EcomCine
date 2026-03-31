<?php
/**
 * Astra Child Theme — thin CSS shim.
 *
 * All business logic (hooks, adapters, Dokan templates, page templates, vendor
 * attributes, social metrics, etc.) now lives in the TM Store UI plugin
 * (tm-store-ui/tm-store-ui.php).  This file only provides the Astra child-theme
 * CSS chain and a safe fallback in case the plugin is not active.
 *
 * @package Astra Child
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue Astra child-theme stylesheet.
 *
 * responsive-config.css and vendor-store.css are now owned by the plugin.
 * We only enqueue the child-theme style.css here so Astra's CSS cascade
 * (astra-theme-css → astra-child-theme-css) is preserved when Astra is active.
 */
function child_enqueue_styles() {
wp_enqueue_style(
'astra-child-theme-css',
get_stylesheet_uri(),
array( 'astra-theme-css' ),
CHILD_THEME_ASTRA_CHILD_VERSION,
'all'
);
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles' );

/**
 * Fallback when the TM Store UI plugin is NOT active.
 * Re-registers bare-minimum hooks + require_once chain so the site does not
 * completely break. Full feature parity requires the plugin to be active.
 */
if ( ! defined( 'TM_STORE_UI_VERSION' ) ) {
add_filter( 'astra_footer_display', '__return_false', 20 );

$_tpl_dir = get_stylesheet_directory();

$_fallback_files = array(
'/includes/vendor-attributes/vendor-attribute-sets.php',
'/includes/vendor-attributes/vendor-attributes-hooks.php',
'/dokan/store-lists/store-lists-hooks.php',
'/includes/adapters/contracts/interface-template-router.php',
'/includes/adapters/contracts/interface-asset-policy-provider.php',
'/includes/adapters/contracts/interface-profile-meta-provider.php',
'/includes/adapters/contracts/interface-vendor-identity-projector.php',
'/includes/adapters/contracts/interface-metrics-provider.php',
'/includes/adapters/compatibility/class-compat-template-router.php',
'/includes/adapters/compatibility/class-compat-asset-policy-provider.php',
'/includes/adapters/compatibility/class-compat-profile-meta-provider.php',
'/includes/adapters/compatibility/class-compat-vendor-identity-projector.php',
'/includes/adapters/compatibility/class-compat-metrics-provider.php',
'/includes/adapters/default-wp/class-wp-template-router.php',
'/includes/adapters/default-wp/class-wp-asset-policy-provider.php',
'/includes/adapters/default-wp/class-wp-profile-meta-provider.php',
'/includes/adapters/default-wp/class-wp-vendor-identity-projector.php',
'/includes/adapters/default-wp/class-wp-metrics-provider.php',
'/includes/adapters/class-adapter-registry.php',
'/includes/social-metrics/social-metrics.php',
'/includes/admin/vendor-edit-logs.php',
'/includes/admin/vendor-completeness-admin.php',
'/includes/vendor-profile/vendor-completeness.php',
'/includes/vendor-profile/vendor-profile-ajax.php',
'/includes/vendors-map/vendors-map-shortcode.php',
);

foreach ( $_fallback_files as $_f ) {
if ( file_exists( $_tpl_dir . $_f ) ) {
require_once $_tpl_dir . $_f;
}
}
}
