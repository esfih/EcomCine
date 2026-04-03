<?php
/**
 * Standalone person profile page template.
 *
 * Loaded for /person/{nicename}/ requests via ecomcine_person query var.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['ecomcine_suppress_site_header'] = true;

$vendor_id = absint( get_query_var( 'author' ) );
$is_publicly_available = (bool) get_query_var( 'ecomcine_person_publicly_available', false );
$can_edit_profile      = $vendor_id && function_exists( 'tm_can_edit_vendor_profile' )
	? (bool) tm_can_edit_vendor_profile( $vendor_id )
	: false;
$can_view_profile      = $is_publicly_available || $can_edit_profile;

if ( $vendor_id && function_exists( 'tm_enqueue_talent_showcase_assets' ) ) {
	tm_enqueue_talent_showcase_assets( $vendor_id, 'profile' );
}

add_filter( 'body_class', function( $classes ) use ( $is_publicly_available, $can_edit_profile ) {
	if ( ! in_array( 'ecomcine-person-profile', $classes, true ) ) {
		$classes[] = 'ecomcine-person-profile';
	}
	if ( ! in_array( 'dokan-store', $classes, true ) ) {
		$classes[] = 'dokan-store';
	}
	if ( $is_publicly_available ) {
		$classes[] = 'ecomcine-person-public';
	} else {
		$classes[] = 'ecomcine-person-unavailable';
	}
	if ( $can_edit_profile ) {
		$classes[] = 'ecomcine-person-editable';
	}

	return $classes;
} );

get_header( 'shop' );
?>

<div class="dokan-store-wrap layout-full">
	<div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
		<?php if ( $vendor_id && $can_view_profile ) : ?>
			<?php
			$rendered = function_exists( 'tm_store_ui_render_store_header' )
				? tm_store_ui_render_store_header( $vendor_id )
				: false;
			if ( ! $rendered ) {
				echo '<div class="tm-talent-showcase-empty">Unable to render talent profile.</div>';
			}
			?>
		<?php else : ?>
			<div class="tm-person-unavailable-state">
				<div class="tm-person-unavailable-state__card">
					<div class="tm-person-unavailable-state__eyebrow">Profile unavailable</div>
					<div class="tm-person-unavailable-state__message">Content not available. Contact-us for more information.</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer( 'shop' );
