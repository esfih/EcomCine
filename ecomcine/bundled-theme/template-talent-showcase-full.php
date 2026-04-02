<?php
/**
 * Template Name: Talent Showcase Full
 * Description: Full takeover showcase template (forced via template_include).
 *
 * @package EcomCine_Base_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'body_class', function( $classes ) {
	if ( ! in_array( 'tm-showcase-page', $classes, true ) ) {
		$classes[] = 'tm-showcase-page';
	}
	return $classes;
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

$GLOBALS['ecomcine_suppress_header'] = true;
get_header();
?>

<div class="tm-showcase-takeover">
	<div class="ecomcine-person-wrap layout-full">
		<div id="ecomcine-person-primary" class="ecomcine-person-profile ecomcine-full-width">
			<?php if ( $vendor_id ) : ?>
				<?php
				$tm_rendered = false;
				if ( function_exists( 'ecomcine_load_template' ) ) {
					ob_start();
					ecomcine_load_template( 'person-header', [ 'vendor_id' => $vendor_id ] );
					$tm_template_html = trim( (string) ob_get_clean() );
					if ( '' !== $tm_template_html ) {
						echo $tm_template_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$tm_rendered = true;
					}
				}
				if ( ! $tm_rendered && function_exists( 'dokan_get_template_part' ) ) {
					dokan_get_template_part( 'store-header' );
					$tm_rendered = true;
				}
				if ( ! $tm_rendered ) {
					echo '<div class="tm-talent-showcase-empty">Unable to render talent profile.</div>';
				}
				?>
			<?php else : ?>
				<div class="tm-talent-showcase-empty">No talent available.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php
get_footer();
