<?php
/**
 * Backfill tm_l1_complete = 1 for all approved Dokan vendors.
 *
 * Run locally:
 *   ./scripts/wp.sh wp eval-file scripts/set-vendor-l1-complete.php
 *
 * Run on live server via SSH:
 *   wp eval-file set-vendor-l1-complete.php --path=/path/to/wordpress
 *
 * Safe to run multiple times (idempotent — skips vendors that already have the flag).
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Run this via WP-CLI: wp eval-file set-vendor-l1-complete.php' . PHP_EOL );
}

if ( ! function_exists( 'dokan_get_sellers' ) ) {
	WP_CLI::error( 'Dokan is not active. Cannot backfill.' );
	exit( 1 );
}

$paged    = 1;
$per_page = 100;
$updated  = 0;
$skipped  = 0;

WP_CLI::log( 'Scanning approved Dokan vendors for missing tm_l1_complete flag...' );

do {
	$result  = dokan_get_sellers( [
		'status'   => 'approved',
		'number'   => $per_page,
		'paged'    => $paged,
	] );

	$vendors = $result['users'] ?? [];

	foreach ( $vendors as $vendor ) {
		$uid      = (int) $vendor->ID;
		$existing = get_user_meta( $uid, 'tm_l1_complete', true );

		if ( $existing === '1' || $existing === 1 ) {
			$skipped++;
			continue;
		}

		update_user_meta( $uid, 'tm_l1_complete', '1' );
		$updated++;
		WP_CLI::log( "  Set tm_l1_complete=1 for user #{$uid} ({$vendor->user_login})" );
	}

	$paged++;
} while ( count( $vendors ) === $per_page );

WP_CLI::success( "Done. Updated: {$updated}  |  Already set: {$skipped}" );
