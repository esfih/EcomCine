<?php
/**
 * TVBM CPT Migration Script — Phase 4 Controlled Cutover.
 *
 * Migrates WooCommerce bookable products to the WordPress-native tm_offer CPT:
 *
 *   TVBM source (compat)                     → TVBM target (default-WP)
 *   ─────────────────────────────────────────────────────────────────────
 *   WC product (type=booking, cat=half-day)  → tm_offer CPT
 *
 * For each vendor that has a WC booking product in the 'half-day' category,
 * creates a corresponding tm_offer CPT record authored by that vendor.
 *
 * Safety guarantees:
 *   - Idempotent: skips vendors that already have a tm_offer CPT record
 *   - --dry-run flag: prints what would be written without writing
 *   - Does not delete WC products
 *
 * Run via catalog command: migrate.tvbm.cpt
 *   ./scripts/wp.sh php scripts/migrate-tvbm-cpt.php
 *   ./scripts/wp.sh php scripts/migrate-tvbm-cpt.php -- dry-run
 *
 * Exit codes:
 *   0 — migration completed (or dry-run) with no unexpected errors
 *   1 — one or more records failed; see [FAIL] lines
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "[ERROR] Must run inside WordPress context via wp.sh\n";
	exit( 1 );
}

$dry_run = in_array( 'dry-run', $GLOBALS['argv'] ?? [], true );

if ( $dry_run ) {
	echo "[tvbm-migrate] DRY RUN — no data will be written\n\n";
} else {
	echo "[tvbm-migrate] LIVE RUN — writing to database\n\n";
}

$GLOBALS['_tvbm_migrate_errors']  = 0;
$GLOBALS['_tvbm_migrate_skipped'] = 0;
$GLOBALS['_tvbm_migrate_created'] = 0;

function tvbm_mig_log( string $status, string $msg ): void {
	echo "[{$status}] {$msg}\n";
	if ( 'FAIL' === $status ) {
		$GLOBALS['_tvbm_migrate_errors']++;
	}
}

// ---------------------------------------------------------------------------
// Find all WC booking products across all vendors
// ---------------------------------------------------------------------------

echo "--- Migrating WC booking products → tm_offer CPT ---\n";

if ( ! function_exists( 'wc_get_product' ) ) {
	tvbm_mig_log( 'SKIP', 'WooCommerce not active — nothing to migrate' );
} else {
	// Query all published WC products with product_type=booking.
	$query = new WP_Query( [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => [
			[
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => [ 'booking' ],
			],
		],
		'fields'         => 'ids',
		'no_found_rows'  => false,
	] );

	$product_ids = $query->posts;
	$total       = count( $product_ids );

	echo "WC booking products found: {$total}\n";

	foreach ( $product_ids as $product_id ) {
		$product   = wc_get_product( $product_id );
		$vendor_id = (int) get_post_field( 'post_author', $product_id );

		if ( ! $product ) {
			tvbm_mig_log( 'SKIP', "product #{$product_id}: wc_get_product returned null" );
			continue;
		}

		// Determine offer_type from product_cat.
		$cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );
		$offer_type = 'half-day'; // Default.
		foreach ( [ 'half-day', 'full-day', 'hourly' ] as $candidate ) {
			if ( in_array( $candidate, (array) $cats, true ) ) {
				$offer_type = $candidate;
				break;
			}
		}

		// Price and duration from product meta.
		$price    = (string) get_post_meta( $product_id, '_price', true );
		$duration = (string) get_post_meta( $product_id, '_wc_booking_duration', true );
		$duration = $duration ?: ( 'half-day' === $offer_type ? '4' : '8' );

		// Idempotency: skip if tm_offer already exists for this source product.
		$existing = get_posts( [
			'post_type'      => 'tm_offer',
			'meta_key'       => '_tm_src_wc_product_id',
			'meta_value'     => $product_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			$GLOBALS['_tvbm_migrate_skipped']++;
			tvbm_mig_log( 'SKIP', "tm_offer: WC product #{$product_id} already migrated (post #{$existing[0]})" );
			continue;
		}

		$title = $product->get_name() ?: ( ucfirst( $offer_type ) . ' Offer — vendor #' . $vendor_id );

		if ( $dry_run ) {
			tvbm_mig_log( 'DRY', "tm_offer: would create '{$title}' type={$offer_type} price={$price} duration={$duration}h vendor={$vendor_id}" );
			$GLOBALS['_tvbm_migrate_created']++;
			continue;
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'tm_offer',
			'post_status'  => 'publish',
			'post_author'  => $vendor_id ?: 1,
			'post_title'   => $title,
			'post_content' => $product->get_description(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			tvbm_mig_log( 'FAIL', "tm_offer: wp_insert_post failed for WC product #{$product_id}: " . $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, '_tm_src_wc_product_id', $product_id );
		update_post_meta( $post_id, '_tm_offer_type', $offer_type );
		update_post_meta( $post_id, '_tm_offer_duration', $duration );
		update_post_meta( $post_id, '_tm_offer_price', $price );

		$GLOBALS['_tvbm_migrate_created']++;
		tvbm_mig_log( 'OK', "tm_offer: created post #{$post_id} from WC product #{$product_id} vendor={$vendor_id} type={$offer_type}" );
	}
}

echo "\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$created = $GLOBALS['_tvbm_migrate_created'];
$skipped = $GLOBALS['_tvbm_migrate_skipped'];
$errors  = $GLOBALS['_tvbm_migrate_errors'];

echo "[tvbm-migrate] created={$created} skipped={$skipped} errors={$errors}\n";

if ( 0 === $errors ) {
	echo "[tvbm-migrate] DONE\n";
	exit( 0 );
} else {
	echo "[tvbm-migrate] DONE WITH ERRORS — review [FAIL] lines\n";
	exit( 1 );
}
