<?php
/**
 * WP-CLI parity checks for Wave 1 core service delegation.
 *
 * Compares EcomCine core service outputs against legacy Dokan/TMP paths
 * for every vendor user, surfacing any divergences before flag promotion.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Parity checks between Wave 1 core services and legacy adapter paths.
	 *
	 * @subcommand parity
	 */
	class EcomCine_Parity_CLI_Command extends WP_CLI_Command {

		/**
		 * Run Wave 1 parity checks: listing identity, profile slug, URL, and
		 * slug-reverse resolution for all vendor/person users.
		 *
		 * ## OPTIONS
		 *
		 * [--limit=<n>]
		 * : Maximum number of vendor users to check. Default: 50.
		 *
		 * [--format=<format>]
		 * : Output format. Options: table, json, csv. Default: table.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine parity wave1
		 *     wp ecomcine parity wave1 --limit=100 --format=json
		 */
		public function wave1( array $args, array $assoc_args ): void {
			$limit  = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 50 ) );
			$format = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ) );

			if ( ! class_exists( 'EcomCine_Listing_Service', false ) ) {
				WP_CLI::error( 'EcomCine_Listing_Service is not loaded. Ensure the plugin is active.' );
			}

			$users = get_users(
				array(
					'role__in' => array( 'ecomcine_person', 'seller' ),
					'number'   => $limit,
					'fields'   => array( 'ID', 'user_nicename' ),
					'orderby'  => 'ID',
					'order'    => 'ASC',
				)
			);

			if ( empty( $users ) ) {
				WP_CLI::warning( 'No vendor/person users found.' );
				return;
			}

			$rows     = array();
			$mismatch = 0;

			foreach ( $users as $u ) {
				$uid = (int) $u->ID;
				foreach ( $this->check_user( $uid, (string) $u->user_nicename ) as $row ) {
					if ( 'FAIL' === $row['match'] ) {
						$mismatch++;
					}
					$rows[] = $row;
				}
			}

			\WP_CLI\Utils\format_items(
				$format,
				$rows,
				array( 'user_id', 'surface', 'core', 'legacy', 'match' )
			);

			if ( $mismatch > 0 ) {
				WP_CLI::error(
					sprintf(
						'Parity FAIL: %d mismatch(es) across %d check(s) for %d user(s). See FAIL rows above.',
						$mismatch,
						count( $rows ),
						count( $users )
					),
					false
				);
				exit( 1 );
			}

			WP_CLI::success(
				sprintf(
					'Parity PASS: %d check(s) across %d user(s) — all match.',
					count( $rows ),
					count( $users )
				)
			);
		}

		/**
		 * Generate parity rows for a single vendor user.
		 *
		 * @param int    $uid             WordPress user ID.
		 * @param string $legacy_nicename user_nicename from the WP users row.
		 * @return array<int,array<string,string>>
		 */
		private function check_user( int $uid, string $legacy_nicename ): array {
			$rows = array();

			// ── 1. Listing ID ─────────────────────────────────────────────────
			$core_lid = EcomCine_Listing_Service::get_listing_id_for_user( $uid );

			if ( class_exists( 'TMP_WP_Vendor_CPT' ) ) {
				$legacy_lid = (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $uid );
				$lid_match  = ( $core_lid === $legacy_lid ) ? 'PASS' : 'FAIL';
			} else {
				$legacy_lid = 'n/a';
				$lid_match  = 'n/a';
			}

			$rows[] = array(
				'user_id' => (string) $uid,
				'surface' => 'listing_id',
				'core'    => (string) $core_lid,
				'legacy'  => (string) $legacy_lid,
				'match'   => $lid_match,
			);

			// ── 2. Profile slug (core vs user_nicename) ───────────────────────
			$core_slug  = EcomCine_Listing_Service::get_profile_slug_for_user( $uid );
			$slug_match = ( $core_slug === $legacy_nicename ) ? 'PASS' : 'FAIL';

			$rows[] = array(
				'user_id' => (string) $uid,
				'surface' => 'profile_slug',
				'core'    => $core_slug,
				'legacy'  => $legacy_nicename,
				'match'   => $slug_match,
			);

			// ── 3. Slug reverse lookup ────────────────────────────────────────
			if ( '' !== $core_slug ) {
				$resolved  = EcomCine_Listing_Service::resolve_owner_user_id_by_profile_slug( $core_slug );
				$rev_match = ( $resolved === $uid ) ? 'PASS' : 'FAIL';

				$rows[] = array(
					'user_id' => (string) $uid,
					'surface' => 'slug_reverse',
					'core'    => 'uid=' . (string) $resolved,
					'legacy'  => 'uid=' . (string) $uid,
					'match'   => $rev_match,
				);
			}

			// ── 4. URL slug equivalence (path segment only) ───────────────────
			if ( function_exists( 'dokan_get_store_url' ) ) {
				$core_url       = EcomCine_Listing_Service::build_public_url_for_user( $uid );
				$dokan_url      = (string) dokan_get_store_url( $uid );
				$core_url_slug  = $this->extract_url_slug( $core_url );
				$dokan_url_slug = $this->extract_url_slug( $dokan_url );

				if ( '' !== $dokan_url_slug ) {
					$url_match = ( $core_url_slug === $dokan_url_slug ) ? 'PASS' : 'FAIL';

					$rows[] = array(
						'user_id' => (string) $uid,
						'surface' => 'url_slug',
						'core'    => $core_url_slug,
						'legacy'  => $dokan_url_slug,
						'match'   => $url_match,
					);
				}
			}

			return $rows;
		}

		/**
		 * Extract the trailing path segment (slug) from a URL.
		 *
		 * @param string $url Absolute or root-relative URL.
		 * @return string Slug segment, or empty string on failure.
		 */
		private function extract_url_slug( string $url ): string {
			if ( '' === $url ) {
				return '';
			}

			$path     = rtrim( wp_make_link_relative( $url ), '/' );
			$segments = array_filter( explode( '/', $path ) );

			if ( empty( $segments ) ) {
				return '';
			}

			return (string) end( $segments );
		}
		/**
		 * Sync tm_vendor post slugs to match their owner's user_nicename.
		 *
		 * Fixes auto-generated slugs (tm_vendor_N pattern) that cannot serve
		 * as canonical public URL segments. Required before flipping route
		 * authority to 'core'.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without writing to the database.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine parity sync-slugs
		 *     wp ecomcine parity sync-slugs --dry-run
		 */
		public function sync_slugs( array $args, array $assoc_args ): void {
			if ( ! class_exists( 'EcomCine_Listing_Service', false ) ) {
				WP_CLI::error( 'EcomCine_Listing_Service is not loaded.' );
			}

			$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

			$posts = get_posts(
				array(
					'post_type'      => 'tm_vendor',
					'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			if ( empty( $posts ) ) {
				WP_CLI::warning( 'No tm_vendor posts found.' );
				return;
			}

			$updated  = 0;
			$skipped  = 0;
			$already  = 0;

			foreach ( $posts as $post_id ) {
				$post_id  = (int) $post_id;
				$post     = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$current_slug = sanitize_title( $post->post_name );

				// Skip if slug is already meaningful (not auto-generated).
				if ( '' !== $current_slug && ! preg_match( '/^tm_vendor_\d+$/', $current_slug ) ) {
					$already++;
					continue;
				}

				// Resolve the owner user.
				$owner_user_id = (int) get_post_meta( $post_id, '_tm_vendor_user_id', true );
				if ( $owner_user_id <= 0 ) {
					$owner_user_id = (int) $post->post_author;
				}

				if ( $owner_user_id <= 0 ) {
					$skipped++;
					WP_CLI::warning( "Post #{$post_id}: no owner user found — skipped." );
					continue;
				}

				$user = get_userdata( $owner_user_id );
				if ( ! $user instanceof WP_User || empty( $user->user_nicename ) ) {
					$skipped++;
					WP_CLI::warning( "Post #{$post_id}: owner user #{$owner_user_id} has no nicename — skipped." );
					continue;
				}

				$new_slug = sanitize_title( $user->user_nicename );
				if ( '' === $new_slug ) {
					$skipped++;
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::line( "Would update post #{$post_id}: '{$current_slug}' → '{$new_slug}' (owner={$owner_user_id})" );
				} else {
					wp_update_post(
						array(
							'ID'        => $post_id,
							'post_name' => $new_slug,
						)
					);
					WP_CLI::line( "Updated post #{$post_id}: '{$current_slug}' → '{$new_slug}' (owner={$owner_user_id})" );
				}

				$updated++;
			}

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Dry run: %d would update, %d already OK, %d skipped.', $updated, $already, $skipped ) );
			} else {
				WP_CLI::success( sprintf( 'Done: %d updated, %d already OK, %d skipped.', $updated, $already, $skipped ) );
			}
		}	}

	WP_CLI::add_command( 'ecomcine parity', 'EcomCine_Parity_CLI_Command' );
}
