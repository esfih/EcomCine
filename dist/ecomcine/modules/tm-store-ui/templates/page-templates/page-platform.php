<?php
/**
 * Template Name: Platform Page
 * Description: Full-width platform page with the cinematic dark header/footer.
 *              Automatically applied to any page containing [dokan-stores]
 *              or [ecomcine-stores] via
 *              the template_include filter in functions.php.
 *              Can also be assigned manually: WP Admin → Pages → Page Attributes → Template.
 *
 * @package TM_Store_UI
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

// All cinematic header CSS in player.css is scoped to body.dokan-store.
// Add that class here so those rules apply on platform pages too.
add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'dokan-store', $classes, true ) ) {
		$classes[] = 'dokan-store';
	}
	if ( ! in_array( 'tm-platform-page', $classes, true ) ) {
		$classes[] = 'tm-platform-page';
	}
	return $classes;
} );

// Suppress the bundled theme's site header on platform pages — the cinematic
// header rendered by tm-media-player replaces it entirely.
$GLOBALS['ecomcine_suppress_site_header'] = true;

get_header();
?>

<main id="tm-platform-content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>

<?php get_footer();
