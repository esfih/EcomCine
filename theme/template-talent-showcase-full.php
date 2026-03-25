<?php
/**
 * Template Name: Talent Showcase Full
 * Description: Full takeover showcase template (forced via template_include).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'astra_header_display', '__return_false' );
add_filter( 'astra_footer_display', '__return_false' );

// Belt-and-suspenders: inject the hide rule at priority 1 so it is in the DOM
// before Astra's transparent-header JS can measure #masthead.offsetHeight and
// inject an inline padding-top on #content (which CSS !important cannot beat).
// Also strip ast-theme-transparent-header from body classes to prevent Astra's
// transparent-header JS from ever running its offset measurement in the first place.
add_action( 'wp_head', function() {
	echo '<style id="showcase-header-hide">'
		. '#masthead,#ast-desktop-header,#ast-mobile-header{'
		. 'display:none!important;height:0!important;}'
		. '#content.site-content,#page{'
		. 'padding-top:0!important;margin-top:0!important;}'
		. '</style>' . "\n";
}, 1 );

add_filter( 'body_class', function( $classes ) {
	return array_diff( $classes, [ 'ast-theme-transparent-header' ] );
} );

if ( ! empty( $_GET['tm_ids'] ) ) {
	$_raw_ids   = array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_GET['tm_ids'] ) ) ) );
	$vendor_ids = array_values( array_filter( $_raw_ids, function( $id ) { return $id > 0; } ) );
} else {
	$vendor_ids = function_exists( 'tm_get_showcase_vendor_ids' ) ? tm_get_showcase_vendor_ids() : array();
}
$vendor_id = ! empty( $vendor_ids ) ? (int) $vendor_ids[0] : 0;

if ( $vendor_id ) {
	set_query_var( 'author', $vendor_id );
	if ( function_exists( 'tm_enqueue_talent_showcase_assets' ) ) {
		tm_enqueue_talent_showcase_assets( $vendor_id, 'showcase' );
	}
}

// Output the full filtered ID list to JS so the swap loop uses only these vendors.
if ( ! empty( $vendor_ids ) ) {
	$_sc_ids = array_values( array_map( 'intval', $vendor_ids ) );
	add_action( 'wp_head', function() use ( $_sc_ids ) {
		echo '<script>window.tmShowcaseIds=' . wp_json_encode( $_sc_ids ) . ';</script>';
	} );
}

get_header();
?>

<style>
	.tm-showcase-takeover { width: 100vw; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw); margin-top: -30px; }
	.tm-showcase-takeover .dokan-store-wrap { width: 100%; margin: 0; }
	.tm-showcase-takeover #dokan-primary { width: 100%; }
	.tm-showcase-takeover .dokan-single-store { width: 100%; }
	.tm-showcase-takeover .profile-frame { min-height: 100vh; }
	.site-footer, .site-below-footer-wrap, #colophon { display: none !important; }
</style>

<div class="tm-showcase-takeover">
	<div class="dokan-store-wrap layout-full">
		<div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
			<?php if ( $vendor_id ) : ?>
				<?php dokan_get_template_part( 'store-header' ); ?>
			<?php else : ?>
				<div class="tm-talent-showcase-empty">No talent available.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php
get_footer();
