<?php
/**
 * EcomCine — Dokan-to-EcomCine data migration.
 *
 * Migrates vendor meta from Dokan's legacy key schema to the canonical
 * EcomCine key schema, making existing Dokan-era vendors visible in the
 * EcomCine Talents listing and Locations map without weakening any plugin
 * query or filtering logic.
 *
 * Migration steps (all idempotent – safe to run repeatedly):
 *
 *   1. Geo migration
 *      Read: dokan_geo_latitude / dokan_geo_longitude / dokan_geo_address
 *            (and dokan_profile_settings[geolocation] as a deeper fallback)
 *      Write: ecomcine_geo_lat / ecomcine_geo_lng / ecomcine_geo_address
 *      Delete: dokan_geo_latitude / dokan_geo_longitude / dokan_geo_address
 *
 *   2. Enabled-status backfill
 *      Read: dokan_enable_selling = 'yes'
 *      Write: ecomcine_enabled = '1'   (only when ecomcine_enabled not set)
 *
 *   3. L1 completeness recalculation
 *      After steps 1 + 2, call tm_vendor_completeness() with updated data
 *      and write the real result to tm_l1_complete.
 *      Previous blind back-fills (v0.1.13) set tm_l1_complete='1' for every
 *      approved Dokan vendor regardless of actual completeness — this step
 *      corrects that by evaluating each vendor properly.
 *
 * Usage (automatic — called from ecomcine.php version upgrade path):
 *
 *   EcomCine_Dokan_Data_Migration::run();
 *
 * Usage (manual on-demand — WP admin AJAX):
 *
 *   POST /wp-admin/admin-ajax.php
 *   action  = ecomcine_run_dokan_migration
 *   nonce   = wp_create_nonce('ecomcine_run_dokan_migration')
 *
 * @package EcomCine
 * @since   0.1.26
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Dokan_Data_Migration {

	/**
	 * Run the full migration for all persons/vendors.
	 *
	 * @return array{migrated: int, skipped: int, errors: string[]}  Summary.
	 */
	public static function run(): array {
		$summary = array(
			'migrated' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		$users = self::get_all_persons();

		foreach ( $users as $user ) {
			$uid = (int) $user->ID;
			try {
				$changed = self::migrate_one( $uid );
				if ( $changed ) {
					$summary['migrated']++;
				} else {
					$summary['skipped']++;
				}
			} catch ( \Throwable $e ) {
				$summary['errors'][] = "user {$uid}: " . $e->getMessage();
				error_log( "[EcomCine migration] Error on user {$uid}: " . $e->getMessage() );
			}
		}

		error_log( sprintf(
			'[EcomCine migration] Dokan→EcomCine: migrated=%d skipped=%d errors=%d',
			$summary['migrated'],
			$summary['skipped'],
			count( $summary['errors'] )
		) );

		return $summary;
	}

	/**
	 * Migrate one vendor/person. Idempotent.
	 *
	 * @param  int  $user_id  WP user ID.
	 * @return bool           True if at least one field was updated.
	 */
	public static function migrate_one( int $user_id ): bool {
		$changed = false;

		// ── Step 1: Geo data migration ────────────────────────────────────────

		$has_native_geo = '' !== (string) get_user_meta( $user_id, 'ecomcine_geo_lat', true )
		               && '' !== (string) get_user_meta( $user_id, 'ecomcine_geo_lng', true );

		if ( ! $has_native_geo ) {
			$legacy_lat  = (string) get_user_meta( $user_id, 'dokan_geo_latitude',  true );
			$legacy_lng  = (string) get_user_meta( $user_id, 'dokan_geo_longitude', true );
			$legacy_addr = (string) get_user_meta( $user_id, 'dokan_geo_address',   true );

			// Deeper fallback: dokan_profile_settings[geolocation]
			if ( '' === $legacy_lat || '' === $legacy_lng ) {
				$dps = get_user_meta( $user_id, 'dokan_profile_settings', true );
				if ( is_array( $dps ) ) {
					if ( '' === $legacy_lat && isset( $dps['geolocation']['latitude'] ) ) {
						$legacy_lat = (string) $dps['geolocation']['latitude'];
					}
					if ( '' === $legacy_lng && isset( $dps['geolocation']['longitude'] ) ) {
						$legacy_lng = (string) $dps['geolocation']['longitude'];
					}
					if ( '' === $legacy_addr && isset( $dps['location'] ) ) {
						$legacy_addr = (string) $dps['location'];
					}
				}
			}

			if ( '' !== $legacy_lat && '' !== $legacy_lng
					&& is_numeric( $legacy_lat ) && is_numeric( $legacy_lng ) ) {
				update_user_meta( $user_id, 'ecomcine_geo_lat', $legacy_lat );
				update_user_meta( $user_id, 'ecomcine_geo_lng', $legacy_lng );
				if ( '' !== $legacy_addr ) {
					update_user_meta( $user_id, 'ecomcine_geo_address', $legacy_addr );
				}
				// Delete the legacy keys now that canonical keys are set.
				delete_user_meta( $user_id, 'dokan_geo_latitude' );
				delete_user_meta( $user_id, 'dokan_geo_longitude' );
				delete_user_meta( $user_id, 'dokan_geo_address' );
				$changed = true;
			}
		}

		// ── Step 2: Enabled-status backfill ──────────────────────────────────

		$native_enabled = get_user_meta( $user_id, 'ecomcine_enabled', true );
		if ( '' === (string) $native_enabled ) {
			$dokan_selling = (string) get_user_meta( $user_id, 'dokan_enable_selling', true );
			if ( 'yes' === strtolower( trim( $dokan_selling ) ) ) {
				update_user_meta( $user_id, 'ecomcine_enabled', '1' );
				$changed = true;
			}
		}

		// ── Step 3: L1 completeness recalculation ────────────────────────────
		// Run tm_vendor_completeness() with the now-updated canonical meta,
		// and record the actual result. This corrects the v0.1.13 blind back-fill
		// which set tm_l1_complete='1' for all approved Dokan vendors regardless
		// of whether their profile fields were actually complete.

		if ( function_exists( 'tm_vendor_completeness' ) ) {
			$completeness = tm_vendor_completeness( $user_id );
			if ( is_array( $completeness ) ) {
				$l1_actual = ! empty( $completeness['level1']['complete'] ) ? '1' : '0';
				$l1_stored = (string) get_user_meta( $user_id, 'tm_l1_complete', true );
				if ( $l1_stored !== $l1_actual ) {
					update_user_meta( $user_id, 'tm_l1_complete', $l1_actual );
					$changed = true;
				}
			}
		}

		return $changed;
	}

	/**
	 * Return all WP users with either seller or ecomcine_person role.
	 *
	 * @return \WP_User[]
	 */
	private static function get_all_persons(): array {
		$roles = array( 'seller', 'ecomcine_person' );
		$found = array();
		$seen  = array();

		foreach ( $roles as $role ) {
			$users = get_users( array(
				'role'   => $role,
				'number' => -1,
				'fields' => 'all',
			) );
			foreach ( $users as $u ) {
				if ( ! isset( $seen[ $u->ID ] ) ) {
					$found[]       = $u;
					$seen[ $u->ID ] = true;
				}
			}
		}

		return $found;
	}
}

// ── On-demand AJAX endpoint (admin only) ──────────────────────────────────────
add_action( 'wp_ajax_ecomcine_run_dokan_migration', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}
	check_ajax_referer( 'ecomcine_run_dokan_migration', 'nonce' );

	$summary = EcomCine_Dokan_Data_Migration::run();

	wp_send_json_success( $summary );
} );

// ── Diagnostic AJAX endpoint (admin only) ─────────────────────────────────────
// Returns a structured snapshot of the live site's vendor data state so
// problems with the Talents listing or Locations map can be diagnosed remotely
// without SSH access.
//
// Browser console usage (while logged in as admin on https://example.com/wp-admin/):
//
//   const nonce = document.querySelector('#wpadminbar') && await fetch('/wp-admin/admin-ajax.php?action=ecomcine_diagnostic&nonce=' + (typeof wpApiSettings !== 'undefined' ? encodeURIComponent(wp.ajax.settings.url) : ''), {method:'GET'}).then(()=>'');
//
// Simpler: paste the full JS snippet from specs/IDE-AI-Command-Catalog.md#ecomcine.diagnostic.
add_action( 'wp_ajax_ecomcine_diagnostic', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	global $wpdb;

	// ── 1. Plugin version state ───────────────────────────────────────────────
	$version_info = array(
		'ECOMCINE_VERSION_constant' => defined( 'ECOMCINE_VERSION' ) ? ECOMCINE_VERSION : 'undefined',
		'ecomcine_version_option'   => get_option( 'ecomcine_version', '(not set)' ),
	);

	// ── 2. All vendor-capable roles present on this site ─────────────────────
	$candidate_roles = array( 'seller', 'vendor', 'ecomcine_person', 'administrator' );
	$role_counts     = array();
	foreach ( $candidate_roles as $role ) {
		$count = (int) ( new \WP_User_Query( array( 'role' => $role, 'number' => 1, 'count_total' => true ) ) )->get_total();
		if ( $count > 0 ) {
			$role_counts[ $role ] = $count;
		}
	}

	// ── 3. Sample vendors (first 10 across all vendor roles) ─────────────────
	$roles_with_users = array_keys( $role_counts );
	$roles_with_users = array_diff( $roles_with_users, array( 'administrator' ) );
	$sample_vendors   = array();

	if ( ! empty( $roles_with_users ) ) {
		$users = get_users( array(
			'role__in' => $roles_with_users,
			'number'   => 10,
			'orderby'  => 'ID',
			'order'    => 'ASC',
		) );

		$watch_keys = array(
			'ecomcine_enabled', 'dokan_enable_selling',
			'ecomcine_geo_lat', 'ecomcine_geo_lng', 'ecomcine_geo_address',
			'dokan_geo_latitude', 'dokan_geo_longitude', 'dokan_geo_address',
			'tm_l1_complete',
		);

		foreach ( $users as $user ) {
			$meta = array();
			foreach ( $watch_keys as $key ) {
				$val         = get_user_meta( $user->ID, $key, true );
				$meta[ $key ] = ( '' === $val || false === $val ) ? '(not set)' : $val;
			}

			// Also report how many other meta keys this user has (to gauge data richness).
			$all_keys      = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d",
				$user->ID
			) );
			$meta['_total_meta_keys'] = count( (array) $all_keys );

			// Roles (from capabilities meta, not role__in).
			$user_obj = get_userdata( $user->ID );
			$meta['_roles'] = implode( ', ', (array) $user_obj->roles );

			$sample_vendors[] = array(
				'ID'       => $user->ID,
				'login'    => $user->user_login,
				'meta'     => $meta,
			);
		}
	}

	// ── 4. Pages: locate talents + locations shortcodes ──────────────────────
	$shortcodes_to_find = array( 'ecomcine-stores', 'vendors_map', 'dokan-stores', 'ecomcine_locations' );
	$page_scan          = array();

	foreach ( $shortcodes_to_find as $sc ) {
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			's'              => '[' . $sc,
		) );
		foreach ( (array) $pages as $pid ) {
			$page_scan[] = array(
				'shortcode' => $sc,
				'post_id'   => $pid,
				'url'       => get_permalink( $pid ),
				'template'  => get_page_template_slug( $pid ),
			);
		}
	}

	// ── 5. meta_query dry-run: would shortcodes find anyone? ─────────────────
	$strict_map_query = array(
		'relation' => 'AND',
		array( 'key' => 'ecomcine_geo_lat', 'compare' => 'EXISTS' ),
		array( 'key' => 'ecomcine_geo_lng', 'compare' => 'EXISTS' ),
		array( 'key' => 'ecomcine_enabled', 'value'   => '1',      'compare' => '=' ),
		array( 'key' => 'tm_l1_complete',   'value'   => '1',      'compare' => '=' ),
	);
	$map_count = ( new \WP_User_Query( array(
		'role__in'   => array( 'seller', 'ecomcine_person' ),
		'number'     => 1,
		'count_total' => true,
		'meta_query' => $strict_map_query,
	) ) )->get_total();

	$l1_gate_query = array(
		array( 'key' => 'tm_l1_complete', 'value' => '1', 'compare' => '=' ),
	);
	$l1_count = ( new \WP_User_Query( array(
		'role__in'    => array( 'seller', 'ecomcine_person' ),
		'number'      => 1,
		'count_total' => true,
		'meta_query'  => $l1_gate_query,
	) ) )->get_total();

	$enabled_query = array(
		array( 'key' => 'ecomcine_enabled', 'value' => '1', 'compare' => '=' ),
	);
	$enabled_count = ( new \WP_User_Query( array(
		'role__in'    => array( 'seller', 'ecomcine_person' ),
		'number'      => 1,
		'count_total' => true,
		'meta_query'  => $enabled_query,
	) ) )->get_total();

	$geo_lat_count = ( new \WP_User_Query( array(
		'role__in'    => array( 'seller', 'ecomcine_person' ),
		'number'      => 1,
		'count_total' => true,
		'meta_query'  => array( array( 'key' => 'ecomcine_geo_lat', 'compare' => 'EXISTS' ) ),
	) ) )->get_total();

	$dokan_lat_count = ( new \WP_User_Query( array(
		'role__in'    => array( 'seller', 'ecomcine_person' ),
		'number'      => 1,
		'count_total' => true,
		'meta_query'  => array( array( 'key' => 'dokan_geo_latitude', 'compare' => 'EXISTS' ) ),
	) ) )->get_total();

	wp_send_json_success( array(
		'version'         => $version_info,
		'role_counts'     => $role_counts,
		'sample_vendors'  => $sample_vendors,
		'page_scan'       => $page_scan,
		'query_dry_run'   => array(
			'vendors_pass_strict_map_query' => (int) $map_count,
			'vendors_with_tm_l1_complete_1'  => (int) $l1_count,
			'vendors_with_ecomcine_enabled_1' => (int) $enabled_count,
			'vendors_with_ecomcine_geo_lat'   => (int) $geo_lat_count,
			'vendors_with_dokan_geo_latitude'  => (int) $dokan_lat_count,
		),
	) );
} );
