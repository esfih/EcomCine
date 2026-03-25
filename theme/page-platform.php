<?php
/**
 * Template Name: Platform Page
 * Description: Full-width platform page with the cinematic dark header/footer.
 *              Automatically applied to any page containing [dokan-stores] via
 *              the template_include filter in functions.php.
 *              Can also be assigned manually: WP Admin → Pages → Page Attributes → Template.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard: if this file is included outside of a real frontend page render
// (e.g. during REST API requests, admin-ajax, or block-template scanning),
// return immediately — executing get_header() would output raw HTML that
// corrupts REST/JSON responses and causes Gutenberg "not a valid JSON" errors.
if (
	( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
	( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
	is_admin()
) {
	return;
}

// Suppress Astra's native header and footer bars so the page only renders
// our cinematic dark header (output by the wp_body_open hook in functions.php).
// astra_header_display covers the base Astra header; the CSS rule below covers
// the Astra HFB (Header Footer Builder / astra-addon) which ignores that filter.
add_filter( 'astra_header_display', '__return_false' );
add_filter( 'astra_footer_display', '__return_false' );

// All cinematic header CSS in player.css is scoped to body.dokan-store.
// Add that class here so those rules apply on platform pages too.
add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'dokan-store', $classes, true ) ) {
		$classes[] = 'dokan-store';
	}
	return $classes;
} );

// Inject the masthead-hide rule into <head> so it fires before the browser paints.
add_action( 'wp_head', function() {
	echo '<style id="platform-page-header-hide">'
		. '#masthead,#ast-desktop-header,#ast-mobile-header,'
		. '.site-footer,.site-below-footer-wrap,#colophon,.ast-site-footer-wrap{'
		. 'display:none!important;}'
		. 'html,body.dokan-store{overflow:hidden!important;height:100%!important;}'
		. '</style>';
}, 1 );

get_header();
?>

<style>
	/* Astra HFB header + footer — hidden by wp_head CSS above; this is a belt-and-suspenders fallback */
	#masthead,
	#ast-desktop-header,
	#ast-mobile-header { display: none !important; }

	/* Hide any residual Astra footer wrappers */
	.site-footer,
	.site-below-footer-wrap,
	.ast-site-footer-wrap,
	#colophon { display: none !important; }

	/* Lock the document to the viewport — no document-level scrollbar.
	   Mirrors the vendors-map.css approach used by the Location page. */
	html,
	body.dokan-store {
		overflow: hidden !important;
		height: 100% !important;
	}

	/* Content area: exact viewport height, internal scroll if content overflows.
	   box-sizing: border-box ensures padding-top is counted inside the 100vh, not added on top.
	   padding-bottom clears the fixed prev/next arrows (42px tall, 24px from bottom = 66px)
	   so the last row of store cards is never hidden behind them when scrolled to the bottom. */
	#tm-platform-content {
		width: 100%;
		height: 100vh;
		box-sizing: border-box;
		background: #0a0a0a;
		padding-top: var(--tm-header-height, 70px);
		padding-bottom: 80px;
		overflow-y: auto;
	}

	/* Astra wraps our <main> in #content.site-content which has flex-grow:1 on #page.
	   Collapse it so it doesn't contribute extra height. */
	#page { min-height: 0 !important; }
	#content.site-content { flex-grow: 0 !important; padding: 0 !important; margin: 0 !important; }
</style>

<main id="tm-platform-content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>

<?php get_footer();
