<?php
/**
 * Plugin-owned page template registration.
 *
 * Makes page-platform.php and the showcase templates available as selectable
 * page templates in WP Admin → Page → Page Attributes, regardless of which
 * theme is active. Also handles template_include resolution.
 *
 * DB migration: pages that previously had _wp_page_template = 'page-platform.php'
 * will still be caught by the legacy match in tm_store_ui_template_include().
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register our templates in the WordPress "Page Attributes" dropdown.
 * Key = unique template ID, Value = display name shown in admin.
 */
function tm_store_ui_get_page_templates() {
	return array(
		'tm-store-ui/page-platform'              => __( 'Platform Page (EcomCine)', 'tm-store-ui' ),
		'tm-store-ui/template-talent-showcase'   => __( 'Talent Showcase (EcomCine)', 'tm-store-ui' ),
	);
}

add_filter( 'theme_page_templates', function( $templates ) {
	return array_merge( $templates, tm_store_ui_get_page_templates() );
} );

/**
 * Resolve template_include for plugin-owned page templates.
 *
 * Handles:
 *  - New DB value:    _wp_page_template = 'tm-store-ui/page-platform'
 *  - Legacy DB value: _wp_page_template = 'page-platform.php'
 *  - Auto-detect:     page contains [dokan-stores] shortcode
 */
function tm_store_ui_template_include( $template ) {
	if ( ! is_page() ) { return $template; }

	$post_id     = (int) get_the_ID();
	$stored_tpl  = $post_id ? (string) get_post_meta( $post_id, '_wp_page_template', true ) : '';
	$content     = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';

	// Platform Page.
	$is_platform = 'tm-store-ui/page-platform' === $stored_tpl
		|| 'page-platform.php' === $stored_tpl     // legacy value
		|| ( $post_id && has_shortcode( $content, 'dokan-stores' ) );

	if ( $is_platform ) {
		$tpl = TM_STORE_UI_DIR . 'templates/page-templates/page-platform.php';
		if ( file_exists( $tpl ) ) { return $tpl; }
	}

	// Showcase page (always resolved by tm-media-player via locate_template;
	// we provide a plugin-path fallback so it works on FSE themes).
	$is_showcase = 'tm-store-ui/template-talent-showcase' === $stored_tpl;
	if ( $is_showcase ) {
		$tpl = TM_STORE_UI_DIR . 'templates/page-templates/template-talent-showcase.php';
		if ( file_exists( $tpl ) ) { return $tpl; }
	}

	return $template;
}
add_filter( 'template_include', 'tm_store_ui_template_include', 90 );

/**
 * Fix locate_template('template-talent-showcase-full.php') calls from
 * tm-media-player so they resolve to the plugin copy when FSE themes are active.
 *
 * tm-media-player hooks template_include at priority 99 and calls locate_template().
 * We hook at 91 (after our own resolver at 90) and set a global path so the
 * media-player's locate_template() fallback code can find it.
 *
 * More robustly: we also define a constant the media-player can check.
 */
add_filter( 'template_include', function( $template ) {
	if ( ! defined( 'TM_STORE_UI_SHOWCASE_FULL_TEMPLATE' ) ) {
		$showcase_full = TM_STORE_UI_DIR . 'templates/page-templates/template-talent-showcase-full.php';
		if ( file_exists( $showcase_full ) ) {
			define( 'TM_STORE_UI_SHOWCASE_FULL_TEMPLATE', $showcase_full );
		}
	}
	return $template;
}, 91 );
