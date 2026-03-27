<?php
/**
 * Import extracted WMOS billing seed into current WP options.
 *
 * Run with:
 *   ./scripts/wp.sh wp eval-file scripts/licensing/import-billing-seed.php -- /path/to/seed.json
 */

defined( 'ABSPATH' ) || exit;

$seed_path = isset( $args[0] ) ? (string) $args[0] : '';
if ( '' === $seed_path || ! file_exists( $seed_path ) ) {
	WP_CLI::error( 'Seed JSON path is required and must exist.' );
}

$raw = file_get_contents( $seed_path );
if ( false === $raw ) {
	WP_CLI::error( 'Could not read seed file.' );
}

$seed = json_decode( $raw, true );
if ( ! is_array( $seed ) ) {
	WP_CLI::error( 'Seed file is not valid JSON.' );
}

$offers = isset( $seed['offers'] ) && is_array( $seed['offers'] ) ? $seed['offers'] : array();
$activations_serialized = isset( $seed['activations_serialized'] ) ? (string) $seed['activations_serialized'] : '';

if ( class_exists( 'EcomCine_Offer_Catalog' ) ) {
	$overrides = EcomCine_Offer_Catalog::build_overrides_from_seed( $offers );
	update_option( EcomCine_Offer_Catalog::OPTION_KEY, $overrides, false );
} else {
	update_option( 'ecomcine_offer_catalog_overrides', $offers, false );
}

if ( '' !== $activations_serialized ) {
	$activations = maybe_unserialize( $activations_serialized );
	if ( is_array( $activations ) ) {
		update_option( 'ecomcine_cp_seed_activations', $activations, false );
	}
}

update_option( 'ecomcine_cp_seed_last_import', gmdate( 'c' ), false );

WP_CLI::success( 'Imported billing seed into EcomCine options.' );
