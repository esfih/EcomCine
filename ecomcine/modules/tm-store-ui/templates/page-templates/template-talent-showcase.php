<?php
/**
 * Template Name: Talent Showcase
 * Description: Full takeover showcase page using the talent profile layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $_GET['tm_ids'] ) ) {
	$_raw_ids   = array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_GET['tm_ids'] ) ) ) );
	$vendor_ids = array_values( array_filter( $_raw_ids, function( $id ) { return $id > 0; } ) );
	$has_custom_vendor_filter = ! empty( $vendor_ids );
} else {
	$vendor_ids = function_exists( 'tm_get_showcase_vendor_ids' ) ? tm_get_showcase_vendor_ids() : array();
	$has_custom_vendor_filter = false;
}
$vendor_id = ! empty( $vendor_ids ) ? (int) $vendor_ids[0] : 0;

if ( $vendor_id ) {
	$GLOBALS['tm_showcase_page'] = true;
	set_query_var( 'author', $vendor_id );
	if ( function_exists( 'tm_enqueue_talent_showcase_assets' ) ) {
		tm_enqueue_talent_showcase_assets( $vendor_id, 'showcase' );
	}
}

add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'tm-showcase-page', $classes, true ) ) {
		$classes[] = 'tm-showcase-page';
	}
	return $classes;
} );

// The canonical anonymous showcase shell is safe to cache because vendor rotation
// and media swaps are loaded dynamically client-side. Bypass cache only for
// logged-in viewers and custom tm_ids variants that truly vary per request.
if ( defined( 'LSCWP_V' ) && ( is_user_logged_in() || $has_custom_vendor_filter ) ) {
	do_action( 'litespeed_control_set_nocache', 'showcase page — custom vendor selection or logged-in viewer' );
}

$GLOBALS['ecomcine_suppress_site_header'] = true;
$GLOBALS['ecomcine_suppress_header'] = true;

get_header( 'shop' );
?>

<?php
if ( $vendor_id && function_exists( 'tm_render_showcase_shell' ) ) {
	echo tm_render_showcase_shell( $vendor_id, $vendor_ids );
} else {
	$person_singular = function_exists( 'ecomcine_get_person_public_label_singular' ) ? strtolower( ecomcine_get_person_public_label_singular() ) : 'talent';
	echo '<div class="tm-talent-showcase-empty">No ' . esc_html( $person_singular ) . ' available.</div>';
}
?>

<?php
get_footer( 'shop' );
