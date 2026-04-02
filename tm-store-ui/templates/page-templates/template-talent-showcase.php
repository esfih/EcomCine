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

add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'tm-showcase-page', $classes, true ) ) {
		$classes[] = 'tm-showcase-page';
	}
	return $classes;
} );

// Output the vendor ID list for the JS swap loop.
if ( ! empty( $vendor_ids ) ) {
	$_sc_ids = array_values( array_map( 'intval', $vendor_ids ) );
	add_action( 'wp_head', function() use ( $_sc_ids ) {
		echo '<script>window.tmShowcaseIds=' . wp_json_encode( $_sc_ids ) . ';</script>' . "\n";
	} );
}

// Tell LiteSpeed Cache not to cache the showcase page — it is dynamically composed
// per-request (vendor rotation) so a stale cache would break it.
if ( defined( 'LSCWP_V' ) ) {
	do_action( 'litespeed_control_set_nocache', 'showcase page — per-request vendor selection' );
}

get_header( 'shop' );
?>

<div class="dokan-store-wrap layout-full">
	<div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
		<?php if ( $vendor_id ) : ?>
			<?php
			$tm_rendered = function_exists( 'tm_store_ui_render_store_header' )
				? tm_store_ui_render_store_header( $vendor_id )
				: false;
			if ( ! $tm_rendered ) {
				echo '<div class="tm-talent-showcase-empty">Unable to render talent profile.</div>';
			}
			?>
		<?php else : ?>
			<div class="tm-talent-showcase-empty">No talent available.</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer( 'shop' );
