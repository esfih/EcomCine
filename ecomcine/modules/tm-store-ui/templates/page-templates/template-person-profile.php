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

// Allow the claim flow for a recipient who arrives via a valid onboarding link,
// even when the profile is still inactive (not yet live).
$_onboard_token_raw = isset( $_GET['tm_onboard'] ) ? sanitize_text_field( wp_unslash( $_GET['tm_onboard'] ) ) : '';
$has_valid_onboard_token = $vendor_id && '' !== $_onboard_token_raw
	&& function_exists( 'tm_account_panel_get_onboard_state' )
	&& ! empty( tm_account_panel_get_onboard_state( $vendor_id, $_onboard_token_raw )['valid'] );

$can_view_profile = $is_publicly_available || $can_edit_profile || $has_valid_onboard_token;

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
				$person_singular = function_exists( 'ecomcine_get_person_public_label_singular' ) ? strtolower( ecomcine_get_person_public_label_singular() ) : 'talent';
				echo '<div class="tm-talent-showcase-empty">Unable to render ' . esc_html( $person_singular ) . ' profile.</div>';
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
