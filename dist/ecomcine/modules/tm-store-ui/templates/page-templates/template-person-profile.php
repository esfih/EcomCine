<?php
/**
 * Standalone person profile page template.
 *
 * Loaded for /person/{nicename}/ requests via ecomcine_person query var.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vendor_id = absint( get_query_var( 'author' ) );

if ( $vendor_id && function_exists( 'tm_enqueue_talent_showcase_assets' ) ) {
	tm_enqueue_talent_showcase_assets( $vendor_id, 'profile' );
}

add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'ecomcine-person-profile', $classes, true ) ) {
		$classes[] = 'ecomcine-person-profile';
	}
	if ( ! in_array( 'dokan-store', $classes, true ) ) {
		$classes[] = 'dokan-store';
	}

	return $classes;
} );

get_header( 'shop' );
?>

<div class="dokan-store-wrap layout-full">
	<div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
		<?php if ( $vendor_id ) : ?>
			<?php
			$rendered = function_exists( 'tm_store_ui_render_store_header' )
				? tm_store_ui_render_store_header( $vendor_id )
				: false;
			if ( ! $rendered ) {
				echo '<div class="tm-talent-showcase-empty">Unable to render talent profile.</div>';
			}
			?>
		<?php else : ?>
			<div class="tm-talent-showcase-empty">Talent profile unavailable.</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer( 'shop' );
