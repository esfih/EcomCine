<?php
/**
 * Dokan template override — directs Dokan to load templates from this plugin
 * instead of the active theme, making store pages work on any theme.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tell Dokan to look for template overrides in this plugin's templates/dokan/
 * directory instead of the active theme.
 *
 * Dokan calls locate_template() which checks:
 *   1. active-theme/dokan/
 *   2. active-theme/dokan-lite/
 * By filtering dokan_template_path we prepend our plugin directory so it wins
 * even when the active theme has no dokan/ folder (e.g. TT25).
 */
add_filter( 'dokan_template_path', function( $template_path ) {
	return TM_STORE_UI_DIR . 'templates/dokan/';
} );

/**
 * Fallback for Dokan templates that are loaded via
 * dokan_get_template_part() / locate_template() with 'dokan' as the prefix.
 *
 * Some Dokan functions call locate_template('dokan/foo.php') directly.
 * We hook template_include (lower priority than our page-templates.php) to
 * catch any remaining cases.
 */
add_filter( 'dokan_locate_template', function( $template, $template_name, $template_path ) {
	$plugin_file = TM_STORE_UI_DIR . 'templates/dokan/' . ltrim( $template_name, '/' );
	if ( file_exists( $plugin_file ) ) {
		return $plugin_file;
	}
	return $template;
}, 10, 3 );

/**
 * Override templates loaded via dokan_get_template_part().
 *
 * dokan_get_template_part() calls locate_template() first. WordPress's
 * locate_template() prepends the theme directory, so absolute paths fed
 * via dokan_template_path never match. This filter fires after the fallback
 * lookup and swaps in our plugin copy when one exists.
 */
add_filter( 'dokan_get_template_part', function( $template, $slug, $name ) {
	$filename    = $name ? "{$slug}-{$name}.php" : "{$slug}.php";
	$plugin_file = TM_STORE_UI_DIR . 'templates/dokan/' . $filename;
	if ( file_exists( $plugin_file ) ) {
		return $plugin_file;
	}
	return $template;
}, 10, 3 );
