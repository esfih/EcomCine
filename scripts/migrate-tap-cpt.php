<?php
/**
 * TAP CPT Migration Script — Phase 4 Controlled Cutover.
 *
 * Migrates existing Dokan/WooCommerce data to WordPress-native CPTs:
 *
 *   TAP source (compat)           → TAP target (default-WP)
 *   ─────────────────────────────────────────────────────────
 *   WC orders (wc_get_orders)     → tm_order  CPT
 *   WC Bookings                    → tm_booking CPT
 *   User-meta invitations          → tm_invitation CPT
 *
 * Safety guarantees:
 *   - Idempotent: skips records already present in the target CPT
 *   - --dry-run flag: prints what would be written without writing
 *   - Does not delete source data
 *
 * Run via catalog command:  migrate.tap.cpt
 *   ./scripts/wp.sh php scripts/migrate-tap-cpt.php
 *   ./scripts/wp.sh php scripts/migrate-tap-cpt.php -- dry-run
 *
 * Exit codes:
 *   0 — migration completed (or dry-run completed) with no unexpected errors
 *   1 — one or more records failed; see [FAIL] lines
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "[ERROR] Must run inside WordPress context via wp.sh\n";
	exit( 1 );
}

// ---------------------------------------------------------------------------
// CLI args: support `-- --dry-run` as passed by wp eval-file
// ---------------------------------------------------------------------------

$dry_run = in_array( 'dry-run', $GLOBALS['argv'] ?? [], true );

if ( $dry_run ) {
	echo "[tap-migrate] DRY RUN — no data will be written\n\n";
} else {
	echo "[tap-migrate] LIVE RUN — writing to database\n\n";
}

$GLOBALS['_tap_migrate_errors'] = 0;
$GLOBALS['_tap_migrate_skipped'] = 0;
$GLOBALS['_tap_migrate_created'] = 0;

function tap_mig_log( string $status, string $msg ): void {
	echo "[{$status}] {$msg}\n";
	if ( 'FAIL' === $status ) {
		$GLOBALS['_tap_migrate_errors']++;
	}
}

// ---------------------------------------------------------------------------
// 1. WC Orders → tm_order CPT
// ---------------------------------------------------------------------------

echo "--- Migrating WC Orders → tm_order CPT ---\n";

if ( ! function_exists( 'wc_get_orders' ) ) {
	tap_mig_log( 'SKIP', 'wc_get_orders not available — WooCommerce not active' );
} else {
	$page      = 1;
	$page_size = 50;
	$total_orders = 0;

	do {
		$wc_orders = wc_get_orders( [
			'limit'  => $page_size,
			'offset' => ( $page - 1 ) * $page_size,
			'return' => 'objects',
		] );

		foreach ( $wc_orders as $order ) {
			/** @var WC_Order $order */
			$wc_id = $order->get_id();

			// Check idempotency: skip if tm_order already has this source ID.
			$existing = get_posts( [
				'post_type'      => 'tm_order',
				'meta_key'       => '_tm_src_wc_order_id',
				'meta_value'     => $wc_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );

			if ( ! empty( $existing ) ) {
				$GLOBALS['_tap_migrate_skipped']++;
				tap_mig_log( 'SKIP', "tm_order: WC order #{$wc_id} already migrated (post #{$existing[0]})" );
				continue;
			}

			$vendor_id   = 0;
			$vendor_name = '';
			if ( function_exists( 'dokan_get_seller_id_by_order' ) ) {
				$vendor_id = (int) dokan_get_seller_id_by_order( $wc_id );
				if ( $vendor_id ) {
					$u           = get_userdata( $vendor_id );
					$vendor_name = $u ? $u->display_name : '';
				}
			}

			$date_str = $order->get_date_created()
				? $order->get_date_created()->date( 'Y-m-d H:i:s' )
				: current_time( 'mysql' );

			if ( $dry_run ) {
				tap_mig_log( 'DRY', "tm_order: would create for WC order #{$wc_id} customer={$order->get_customer_id()} status={$order->get_status()} total={$order->get_total()}" );
				$GLOBALS['_tap_migrate_created']++;
				continue;
			}

			$post_id = wp_insert_post( [
				'post_type'   => 'tm_order',
				'post_title'  => 'Order #' . $wc_id,
				'post_status' => 'publish',
				'post_author' => $order->get_customer_id() ?: 1,
				'post_date'   => $date_str,
			], true );

			if ( is_wp_error( $post_id ) ) {
				tap_mig_log( 'FAIL', "tm_order: wp_insert_post failed for WC order #{$wc_id}: " . $post_id->get_error_message() );
				continue;
			}

			update_post_meta( $post_id, '_tm_src_wc_order_id', $wc_id );
			update_post_meta( $post_id, '_tm_order_status', $order->get_status() );
			update_post_meta( $post_id, '_tm_order_total', (string) $order->get_total() );
			update_post_meta( $post_id, '_tm_vendor_id', $vendor_id );
			update_post_meta( $post_id, '_tm_vendor_name', $vendor_name );
			update_post_meta( $post_id, '_tm_modal_checkout', 0 );

			$GLOBALS['_tap_migrate_created']++;
			tap_mig_log( 'OK', "tm_order: created post #{$post_id} from WC order #{$wc_id}" );
		}

		$total_orders += count( $wc_orders );
		$page++;
	} while ( count( $wc_orders ) === $page_size );

	echo "WC Orders scanned: {$total_orders}\n\n";
}

// ---------------------------------------------------------------------------
// 2. WC Bookings → tm_booking CPT
// ---------------------------------------------------------------------------

echo "--- Migrating WC Bookings → tm_booking CPT ---\n";

if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
	tap_mig_log( 'SKIP', 'WC_Booking_Data_Store not available — WC Bookings not active' );
} else {
	// Get all WC booking post IDs via direct query (no per-user loop needed).
	$booking_ids = get_posts( [
		'post_type'      => 'wc_booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	$total_bookings = count( $booking_ids );

	foreach ( $booking_ids as $bk_id ) {
		$booking = new WC_Booking( $bk_id );

		$existing = get_posts( [
			'post_type'      => 'tm_booking',
			'meta_key'       => '_tm_src_wc_booking_id',
			'meta_value'     => $bk_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			$GLOBALS['_tap_migrate_skipped']++;
			tap_mig_log( 'SKIP', "tm_booking: WC booking #{$bk_id} already migrated (post #{$existing[0]})" );
			continue;
		}

		$product    = $booking->get_product();
		$offer_name = $product ? $product->get_name() : '';
		$vendor_id  = $product ? (int) $product->get_post_data()->post_author : 0;
		$customer   = (int) $booking->get_customer_id();
		$date_str   = $booking->get_start_date( 'Y-m-d H:i:s' ) ?: current_time( 'mysql' );
		$status     = $booking->get_status();

		if ( $dry_run ) {
			tap_mig_log( 'DRY', "tm_booking: would create for WC booking #{$bk_id} customer={$customer} status={$status} offer={$offer_name}" );
			$GLOBALS['_tap_migrate_created']++;
			continue;
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'tm_booking',
			'post_title'  => 'Booking #' . $bk_id,
			'post_status' => 'publish',
			'post_author' => $customer ?: 1,
			'post_date'   => $date_str,
		], true );

		if ( is_wp_error( $post_id ) ) {
			tap_mig_log( 'FAIL', "tm_booking: wp_insert_post failed for WC booking #{$bk_id}: " . $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, '_tm_src_wc_booking_id', $bk_id );
		update_post_meta( $post_id, '_tm_booking_date', $date_str );
		update_post_meta( $post_id, '_tm_booking_status', $status );
		update_post_meta( $post_id, '_tm_vendor_id', $vendor_id );
		update_post_meta( $post_id, '_tm_offer_id', 0 ); // Linked after TVBM migration.

		$GLOBALS['_tap_migrate_created']++;
		tap_mig_log( 'OK', "tm_booking: created post #{$post_id} from WC booking #{$bk_id}" );
	}

	echo "WC Bookings scanned: {$total_bookings}\n\n";
}

// ---------------------------------------------------------------------------
// 3. User-meta invitations → tm_invitation CPT
// ---------------------------------------------------------------------------

echo "--- Migrating user-meta invitations → tm_invitation CPT ---\n";

$talent_users = get_users( [
	'meta_key' => 'tm_preonboard_admin_id',
	'number'   => -1,
] );

$total_inv = count( $talent_users );

foreach ( $talent_users as $talent ) {
	$talent_id     = (int) $talent->ID;
	$token         = (string) get_user_meta( $talent_id, 'tm_invitation_token', true );
	$admin_id      = (int) get_user_meta( $talent_id, 'tm_preonboard_admin_id', true );
	$invited_at    = (string) get_user_meta( $talent_id, 'tm_invitation_sent_at', true );
	$claimed_at    = (string) get_user_meta( $talent_id, 'tm_invitation_claimed_at', true );

	if ( ! $token ) {
		tap_mig_log( 'SKIP', "tm_invitation: user #{$talent_id} has no token — skipping" );
		continue;
	}

	// Idempotency: check by token.
	$existing = get_posts( [
		'post_type'      => 'tm_invitation',
		'meta_key'       => '_tm_inv_token',
		'meta_value'     => $token,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	] );

	if ( ! empty( $existing ) ) {
		$GLOBALS['_tap_migrate_skipped']++;
		tap_mig_log( 'SKIP', "tm_invitation: talent #{$talent_id} token already migrated (post #{$existing[0]})" );
		continue;
	}

	$is_claimed    = ! empty( $claimed_at );
	$post_status   = $is_claimed ? 'private' : 'publish';
	$expiry        = $invited_at ? ( strtotime( $invited_at ) + TAP_WP_Invitation_CPT::TOKEN_TTL ) : ( time() + TAP_WP_Invitation_CPT::TOKEN_TTL );
	$post_date_str = $invited_at ?: current_time( 'mysql' );

	if ( $dry_run ) {
		tap_mig_log( 'DRY', "tm_invitation: would create for talent #{$talent_id} admin={$admin_id} claimed=" . ( $is_claimed ? 'yes' : 'no' ) );
		$GLOBALS['_tap_migrate_created']++;
		continue;
	}

	$post_id = wp_insert_post( [
		'post_type'   => 'tm_invitation',
		'post_status' => $post_status,
		'post_author' => $talent_id,
		'post_title'  => 'invite-' . $talent_id . '-' . $token,
		'post_date'   => $post_date_str,
	], true );

	if ( is_wp_error( $post_id ) ) {
		tap_mig_log( 'FAIL', "tm_invitation: wp_insert_post failed for talent #{$talent_id}: " . $post_id->get_error_message() );
		continue;
	}

	update_post_meta( $post_id, '_tm_inv_token', $token );
	update_post_meta( $post_id, '_tm_inv_expiry', $expiry );
	update_post_meta( $post_id, '_tm_inv_type', 'invite' );
	update_post_meta( $post_id, '_tm_inv_claimed', $is_claimed ? strtotime( $claimed_at ) : 0 );
	update_post_meta( $post_id, '_tm_inv_admin_id', $admin_id );
	update_post_meta( $post_id, '_tm_src_user_id', $talent_id );

	$GLOBALS['_tap_migrate_created']++;
	tap_mig_log( 'OK', "tm_invitation: created post #{$post_id} for talent #{$talent_id}" );
}

echo "Invitation users scanned: {$total_inv}\n\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$created = $GLOBALS['_tap_migrate_created'];
$skipped = $GLOBALS['_tap_migrate_skipped'];
$errors  = $GLOBALS['_tap_migrate_errors'];

echo "[tap-migrate] created={$created} skipped={$skipped} errors={$errors}\n";

if ( 0 === $errors ) {
	echo "[tap-migrate] DONE\n";
	exit( 0 );
} else {
	echo "[tap-migrate] DONE WITH ERRORS — review [FAIL] lines\n";
	exit( 1 );
}
