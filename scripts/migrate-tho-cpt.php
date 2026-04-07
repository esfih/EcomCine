<?php
/**
 * THO CPT Migration Script — Phase 4 Controlled Cutover.
 *
 * Migrates Dokan vendor user-meta to the WordPress-native tm_vendor CPT:
 *
 *   THO source (compat)                    → THO target (default-WP)
 *   ────────────────────────────────────────────────────────────────────
 *   dokan_profile_settings user meta        → tm_vendor CPT post_content (biography)
 *   tm_vendor_headline user meta            → _tm_vendor_headline post meta
 *   tm_vendor_skills user meta              → _tm_vendor_skills post meta
 *   tm_social_* user meta keys             → _tm_social_* post meta
 *   dokan_store_banner_id user meta         → post thumbnail (featured image)
 *   WP user display_name                    → tm_vendor CPT post_title
 *
 * The tm_vendor CPT is registered by TMP_WP_Vendor_CPT (tm-media-player).
 * This script uses TMP_WP_Vendor_CPT::upsert_vendor() when available,
 * falling back to direct wp_insert_post/update_post_meta.
 *
 * Safety guarantees:
 *   - Idempotent: skips vendors that already have a tm_vendor CPT record
 *   - --dry-run flag: prints what would be written without writing
 *   - Does not delete user meta
 *
 * Run via catalog command: migrate.tho.cpt
 *   ./scripts/wp.sh php scripts/migrate-tho-cpt.php
 *   ./scripts/wp.sh php scripts/migrate-tho-cpt.php -- dry-run
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
	echo "[tho-migrate] DRY RUN — no data will be written\n\n";
} else {
	echo "[tho-migrate] LIVE RUN — writing to database\n\n";
}

$GLOBALS['_tho_migrate_errors']  = 0;
$GLOBALS['_tho_migrate_skipped'] = 0;
$GLOBALS['_tho_migrate_created'] = 0;

function tho_mig_log( string $status, string $msg ): void {
	echo "[{$status}] {$msg}\n";
	if ( 'FAIL' === $status ) {
		$GLOBALS['_tho_migrate_errors']++;
	}
}

// ---------------------------------------------------------------------------
// Collect vendor users
//
// A vendor is any WP user who has the 'seller' role (Dokan) or
// who has a 'dokan_profile_settings' user meta record.
// ---------------------------------------------------------------------------

echo "--- Collecting Dokan vendor users ---\n";

$role_users = get_users( [ 'role' => 'seller', 'number' => -1, 'fields' => 'ID' ] );
$meta_users = get_users( [
	'meta_key' => 'dokan_profile_settings',
	'number'   => -1,
	'fields'   => 'ID',
] );

// Merge and deduplicate.
$vendor_ids = array_unique( array_map( 'intval', array_merge( $role_users, $meta_users ) ) );
$total      = count( $vendor_ids );

echo "Vendor users found: {$total}\n\n";

// ---------------------------------------------------------------------------
// Migrate each vendor
// ---------------------------------------------------------------------------

echo "--- Migrating vendor profiles → tm_vendor CPT ---\n";

foreach ( $vendor_ids as $vendor_id ) {
	$user = get_userdata( $vendor_id );
	if ( ! $user ) {
		tho_mig_log( 'SKIP', "vendor #{$vendor_id}: get_userdata returned null" );
		continue;
	}

	// Idempotency check.
	$has_cpt = false;
	if ( class_exists( 'TMP_WP_Vendor_CPT' ) ) {
		$has_cpt = (bool) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
	} else {
		$existing = get_posts( [
			'post_type'      => 'tm_vendor',
			'meta_key'       => '_tm_vendor_user_id',
			'meta_value'     => $vendor_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );
		$has_cpt = ! empty( $existing );
	}

	if ( $has_cpt ) {
		$GLOBALS['_tho_migrate_skipped']++;
		tho_mig_log( 'SKIP', "tm_vendor: vendor #{$vendor_id} ({$user->display_name}) already has CPT record" );
		continue;
	}

	// --- Extract source data from user-meta ---

	// Biography: try Dokan profile settings array first, then bare user meta.
	$dokan_settings = maybe_unserialize( get_user_meta( $vendor_id, 'dokan_profile_settings', true ) );
	$biography      = '';
	if ( is_array( $dokan_settings ) ) {
		$biography = $dokan_settings['vendor_biography'] ?? '';
	}
	if ( ! $biography ) {
		$biography = (string) get_user_meta( $vendor_id, 'tm_vendor_biography', true );
	}

	// Headline + skills.
	$headline   = (string) get_user_meta( $vendor_id, 'tm_vendor_headline', true );
	$skills_raw = get_user_meta( $vendor_id, 'tm_vendor_skills', true );
	$skills     = is_array( $skills_raw )
		? $skills_raw
		: ( $skills_raw ? array_filter( array_map( 'trim', explode( ',', (string) $skills_raw ) ) ) : [] );

	// Social links.
	$social_fields = [ 'linkedin', 'twitter', 'instagram', 'youtube', 'facebook', 'tiktok', 'imdb' ];
	$social        = [];
	foreach ( $social_fields as $platform ) {
		$val = (string) get_user_meta( $vendor_id, "tm_social_{$platform}", true );
		if ( $val ) {
			$social[ $platform ] = $val;
		}
	}

	// Banner image: Dokan store banner attachment ID.
	$banner_image_id = (int) get_user_meta( $vendor_id, 'dokan_store_banner_id', true );

	// Store name.
	$store_name = $user->display_name;
	if ( is_array( $dokan_settings ) && ! empty( $dokan_settings['store_name'] ) ) {
		$store_name = $dokan_settings['store_name'];
	}

	if ( $dry_run ) {
		tho_mig_log( 'DRY', "tm_vendor: would create for vendor #{$vendor_id} name='{$store_name}' bio=" . ( $biography ? strlen( $biography ) . 'ch' : 'empty' ) . " skills=" . count( $skills ) . " social=" . count( $social ) . " banner_id={$banner_image_id}" );
		$GLOBALS['_tho_migrate_created']++;
		continue;
	}

	// --- Create tm_vendor CPT record ---

	if ( class_exists( 'TMP_WP_Vendor_CPT' ) ) {
		$result = TMP_WP_Vendor_CPT::upsert_vendor( $vendor_id, [
			'biography'    => $biography,
			'banner_image' => $banner_image_id,
		] );

		if ( is_wp_error( $result ) ) {
			tho_mig_log( 'FAIL', "tm_vendor: upsert_vendor failed for #{$vendor_id}: " . $result->get_error_message() );
			continue;
		}

		$post_id = (int) $result;

		// Update post title to store name (upsert_vendor may use display_name by default).
		wp_update_post( [ 'ID' => $post_id, 'post_title' => $store_name ] );
	} else {
		// Fallback when TMP not loaded.
		$post_id = wp_insert_post( [
			'post_type'    => 'tm_vendor',
			'post_status'  => 'publish',
			'post_author'  => $vendor_id,
			'post_title'   => $store_name,
			'post_content' => $biography,
		], true );

		if ( is_wp_error( $post_id ) ) {
			tho_mig_log( 'FAIL', "tm_vendor: wp_insert_post failed for vendor #{$vendor_id}: " . $post_id->get_error_message() );
			continue;
		}

		update_post_meta( $post_id, '_tm_vendor_user_id', $vendor_id );
		update_user_meta( $vendor_id, '_tm_vendor_cpt_id', $post_id );

		if ( $banner_image_id ) {
			set_post_thumbnail( $post_id, $banner_image_id );
		}
	}

	// Shared meta regardless of creation path.
	if ( $headline ) {
		update_post_meta( $post_id, '_tm_vendor_headline', $headline );
	}
	if ( $skills ) {
		update_post_meta( $post_id, '_tm_vendor_skills', array_values( $skills ) );
	}
	foreach ( $social as $platform => $url ) {
		update_post_meta( $post_id, "_tm_social_{$platform}", $url );
	}

	$GLOBALS['_tho_migrate_created']++;
	tho_mig_log( 'OK', "tm_vendor: created post #{$post_id} for vendor #{$vendor_id} ({$store_name})" );
}

echo "\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$created = $GLOBALS['_tho_migrate_created'];
$skipped = $GLOBALS['_tho_migrate_skipped'];
$errors  = $GLOBALS['_tho_migrate_errors'];

echo "[tho-migrate] created={$created} skipped={$skipped} errors={$errors}\n";

if ( 0 === $errors ) {
	echo "[tho-migrate] DONE\n";
	exit( 0 );
} else {
	echo "[tho-migrate] DONE WITH ERRORS — review [FAIL] lines\n";
	exit( 1 );
}
