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
