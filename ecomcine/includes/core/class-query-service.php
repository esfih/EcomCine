<?php
/**
 * Wave 1 Query Service.
 *
 * Canonical seam for listing collection queries, replacing scattered
 * ecomcine_get_persons() / get_users() calls in shortcodes and admin views.
 *
 * Authority flag semantics:
 *   legacy  — all methods delegate directly to legacy helpers (ecomcine_get_persons, etc.)
 *   shadow  — runs own query AND legacy query, logs divergences, returns legacy result
 *   core    — runs own query and returns it as the sole result
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Query_Service {

	/**
	 * Return user IDs of live (published + L1-complete) person listings.
	 *
	 * "Live" means:
	 *  - user has ecomcine_person or seller role
	 *  - dokan_enable_selling = 'yes' OR legacy tm_vendor_completeness says published
	 *  - tm_l1_complete = '1'
	 *
	 * When authority flag is `legacy`, delegates to the existing shortcode helper
	 * if available, otherwise falls through to its own implementation.
	 *
	 * @param array $args {
	 *   @type int    $number       Max users to return. Default -1 (all).
	 *   @type string $orderby      'display_name' | 'ID'. Default 'display_name'.
	 *   @type string $order        'ASC' | 'DESC'. Default 'ASC'.
	 * }
	 * @return int[]
	 */
	public static function get_live_person_ids( array $args = array() ): array {
		$query_state = class_exists( 'EcomCine_Wave1_Authority', false )
			? EcomCine_Wave1_Authority::get_query_state()
			: EcomCine_Wave1_Authority::STATE_LEGACY;

		$legacy_result = null;
		$core_result   = null;

		// Always compute legacy result unless we're in core-only mode.
		if ( EcomCine_Wave1_Authority::STATE_CORE !== $query_state ) {
			$legacy_result = self::get_live_person_ids_legacy( $args );
		}

		// Always compute core result unless we're in legacy-only mode.
		if ( EcomCine_Wave1_Authority::STATE_LEGACY !== $query_state ) {
			$core_result = self::get_live_person_ids_core( $args );
		}

		// Shadow: log divergences then return legacy.
		if ( EcomCine_Wave1_Authority::STATE_SHADOW === $query_state ) {
			$legacy_sorted = $legacy_result;
			$core_sorted   = $core_result;
			sort( $legacy_sorted );
			sort( $core_sorted );

			if ( class_exists( 'EcomCine_Wave1_Authority', false ) ) {
				EcomCine_Wave1_Authority::shadow_log(
					'query',
					'get_live_person_ids:number=' . ( $args['number'] ?? -1 ),
					$legacy_sorted,
					$core_sorted
				);
			}

			return $legacy_result;
		}

		if ( EcomCine_Wave1_Authority::STATE_CORE === $query_state ) {
			return $core_result;
		}

		return $legacy_result;
	}

	/**
	 * Return full WP_User objects for live vendors.
	 *
	 * @param array $args Forwarded to get_live_person_ids().
	 * @return WP_User[]
	 */
	public static function get_live_persons( array $args = array() ): array {
		$ids = self::get_live_person_ids( $args );
		if ( empty( $ids ) ) {
			return array();
		}

		return get_users(
			array(
				'include' => $ids,
				'orderby' => 'include',
				'number'  => count( $ids ),
			)
		);
	}

	// ── Legacy path ───────────────────────────────────────────────────────────

	/**
	 * @param array $args
	 * @return int[]
	 */
	private static function get_live_person_ids_legacy( array $args ): array {
		// Use the shortcode helper when available — it already applies showcase
		// ordering, enabled checks, and tm_l1_complete filters.
		if ( function_exists( 'tm_store_ui_collect_person_ids_for_listing' ) ) {
			return tm_store_ui_collect_person_ids_for_listing();
		}

		// Fallback: role query + live filter.
		$users = function_exists( 'ecomcine_get_persons' )
			? ecomcine_get_persons( array( 'number' => $args['number'] ?? -1 ) )
			: get_users(
				array(
					'number'   => $args['number'] ?? -1,
					'role__in' => array( 'ecomcine_person', 'seller' ),
				)
			);

		$ids = array_map( static function( $u ): int {
			return (int) ( $u->ID ?? 0 );
		}, (array) $users );

		return self::filter_live_ids( array_filter( $ids ) );
	}

	// ── Core path ─────────────────────────────────────────────────────────────

	/**
	 * @param array $args
	 * @return int[]
	 */
	private static function get_live_person_ids_core( array $args ): array {
		$number  = isset( $args['number'] ) ? (int) $args['number'] : -1;
		$orderby = in_array( $args['orderby'] ?? 'display_name', array( 'display_name', 'ID' ), true )
			? $args['orderby'] ?? 'display_name'
			: 'display_name';
		$order   = strtoupper( $args['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';

		$roles = array( 'seller' );
		if ( isset( $GLOBALS['wp_roles'] ) && is_a( $GLOBALS['wp_roles'], 'WP_Roles' )
			&& array_key_exists( 'ecomcine_person', $GLOBALS['wp_roles']->roles )
		) {
			$roles[] = 'ecomcine_person';
		}

		$users = get_users(
			array(
				'number'   => $number,
				'role__in' => array_values( array_unique( $roles ) ),
				'orderby'  => $orderby,
				'order'    => $order,
				'fields'   => 'ID',
			)
		);

		$ids = array_map( 'intval', (array) $users );

		return self::filter_live_ids( $ids );
	}

	// ── Shared helpers ────────────────────────────────────────────────────────

	/**
	 * Filter a list of user IDs down to those that are "live":
	 * enabled + L1 complete + has a published listing.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	private static function filter_live_ids( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		// Enabled check.
		if ( function_exists( 'ecomcine_is_person_enabled' ) ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function( int $id ): bool {
						return ecomcine_is_person_enabled( $id );
					}
				)
			);
		}

		// L1-complete + published check.
		$ids = array_values(
			array_filter(
				$ids,
				array( __CLASS__, 'is_person_live' )
			)
		);

		// Must have a resolvable public profile URL.
		if ( function_exists( 'ecomcine_get_person_url' ) ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function( int $id ): bool {
						return '' !== trim( (string) ecomcine_get_person_url( $id ) );
					}
				)
			);
		} elseif ( class_exists( 'EcomCine_Listing_Service', false ) ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function( int $id ): bool {
						return EcomCine_Listing_Service::has_public_profile_for_user( $id );
					}
				)
			);
		}

		return $ids;
	}

	/**
	 * Return true when the user's vendor account is published and L1-complete.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_person_live( int $user_id ): bool {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		// Use tm-store-ui helper when available — it checks tm_vendor_completeness.
		if ( function_exists( 'tm_store_ui_is_person_live' ) ) {
			return tm_store_ui_is_person_live( $user_id );
		}

		$published = 'yes' === strtolower( trim( (string) get_user_meta( $user_id, 'dokan_enable_selling', true ) ) );
		$l1_done   = '1' === trim( (string) get_user_meta( $user_id, 'tm_l1_complete', true ) );

		return $published && $l1_done;
	}

	// ── Packet 5b: filterable collection surface ──────────────────────────────

	/**
	 * Return filtered and sorted user IDs for the directory collection surface.
	 *
	 * When query authority is not `legacy`, this method owns both the base query
	 * and the filter application, replacing the inline filter logic in the
	 * [ecomcine-stores] shortcode.
	 *
	 * @param array $filters {
	 *   @type string   $search        Free-text search against name/bio/categories.
	 *   @type string   $category      Category slug to filter by.
	 *   @type bool     $verified      When true, only verified listings.
	 *   @type bool     $featured      When true, only featured listings.
	 *   @type string   $profile_level 'mediatic' | 'basic' | '' (no filter).
	 *   @type string   $age_range     Age range token passed to age_matches_range().
	 *   @type string   $tm_order      'oldest'|'newest'|'name_az'|'name_za'. Default 'oldest'.
	 *   @type string   $country       Country name for geo filter.
	 *   @type array    $meta_filters  Associative array of meta_key => value pairs.
	 * }
	 * @param array $query_args  Forwarded to get_live_person_ids() (e.g. number).
	 * @return int[]
	 */
	public static function get_filtered_person_ids( array $filters = array(), array $query_args = array() ): array {
		$query_state = class_exists( 'EcomCine_Wave1_Authority', false )
			? EcomCine_Wave1_Authority::get_query_state()
			: EcomCine_Wave1_Authority::STATE_LEGACY;

		// When legacy, delegate to the shortcode's own filter function.
		if ( EcomCine_Wave1_Authority::STATE_LEGACY === $query_state ) {
			if ( function_exists( 'tm_store_ui_get_filtered_person_ids_for_listing' ) ) {
				return tm_store_ui_get_filtered_person_ids_for_listing();
			}
		}

		// Base collection (shadow/core own paths).
		$ids = self::get_live_person_ids( $query_args );

		// Shadow: also run legacy and log divergence before returning legacy.
		if ( EcomCine_Wave1_Authority::STATE_SHADOW === $query_state ) {
			$legacy_ids = function_exists( 'tm_store_ui_get_filtered_person_ids_for_listing' )
				? tm_store_ui_get_filtered_person_ids_for_listing()
				: array();

			$core_sorted   = $ids;
			$legacy_sorted = $legacy_ids;
			sort( $core_sorted );
			sort( $legacy_sorted );

			if ( class_exists( 'EcomCine_Wave1_Authority', false ) ) {
				EcomCine_Wave1_Authority::shadow_log(
					'query',
					'get_filtered_person_ids:before_filters',
					$legacy_sorted,
					$core_sorted
				);
			}

			return self::apply_filters_to_ids( $legacy_ids, $filters );
		}

		// Core: apply our own filter logic.
		return self::apply_filters_to_ids( $ids, $filters );
	}

	/**
	 * Apply directory filters and sorting to a list of user IDs.
	 *
	 * @param int[]  $ids
	 * @param array  $filters  Same shape as get_filtered_person_ids() $filters param.
	 * @return int[]
	 */
	private static function apply_filters_to_ids( array $ids, array $filters ): array {
		$search        = trim( (string) ( $filters['search']       ?? '' ) );
		$category      = sanitize_title( (string) ( $filters['category']     ?? '' ) );
		$only_verified = ! empty( $filters['verified'] );
		$only_featured = ! empty( $filters['featured'] );
		$profile_level = sanitize_key( (string) ( $filters['profile_level'] ?? '' ) );
		$age_range     = trim( (string) ( $filters['age_range']    ?? '' ) );
		$country       = trim( (string) ( $filters['country']      ?? '' ) );
		$meta_filters  = is_array( $filters['meta_filters'] ?? null ) ? $filters['meta_filters'] : array();
		$tm_order      = sanitize_key( (string) ( $filters['tm_order'] ?? 'oldest' ) );

		$ids = array_values(
			array_filter(
				$ids,
				static function( int $uid ) use (
					$search, $category, $only_verified, $only_featured,
					$profile_level, $age_range, $country, $meta_filters
				): bool {
					// ── Category ───────────────────────────────────────────
					if ( '' !== $category ) {
						$slugs = function_exists( 'tm_store_ui_get_person_category_slugs' )
							? (array) tm_store_ui_get_person_category_slugs( $uid )
							: array();
						if ( ! in_array( $category, $slugs, true ) ) {
							return false;
						}
					}

					// ── Text search ────────────────────────────────────────
					if ( '' !== $search ) {
						$u        = get_userdata( $uid );
						$profile  = function_exists( 'ecomcine_get_person_info' )
							? (array) ecomcine_get_person_info( $uid )
							: array();
						$cat_text = function_exists( 'tm_store_ui_get_person_category_label' )
							? (string) tm_store_ui_get_person_category_label( $uid )
							: '';
						$haystack = implode(
							' ',
							array_filter(
								array(
									$u ? (string) $u->display_name : '',
									(string) ( $profile['store_name'] ?? '' ),
									(string) ( $profile['bio']        ?? '' ),
									$cat_text,
								)
							)
						);
						if ( false === stripos( $haystack, $search ) ) {
							return false;
						}
					}

					// ── Verified / Featured ────────────────────────────────
					if ( $only_verified || $only_featured ) {
						$badges = function_exists( 'tm_store_ui_get_person_status_badges' )
							? (array) tm_store_ui_get_person_status_badges( $uid )
							: array( 'featured' => false, 'verified' => false );
						if ( $only_verified && empty( $badges['verified'] ) ) {
							return false;
						}
						if ( $only_featured && empty( $badges['featured'] ) ) {
							return false;
						}
					}

					// ── Profile level ──────────────────────────────────────
					if ( '' !== $profile_level ) {
						$l2 = '1' === trim( (string) get_user_meta( $uid, 'tm_l2_complete', true ) );
						if ( 'mediatic' === $profile_level && ! $l2 ) {
							return false;
						}
						if ( 'basic' === $profile_level && $l2 ) {
							return false;
						}
					}

					// ── Age range ──────────────────────────────────────────
					if ( '' !== $age_range && function_exists( 'age_matches_range' ) ) {
						$birth = get_user_meta( $uid, 'demo_birth_date', true );
						if ( ! age_matches_range( $birth, $age_range ) ) {
							return false;
						}
					}

					// ── Geo / country ──────────────────────────────────────
					if ( '' !== $country ) {
						$geo_addr = trim( (string) get_user_meta( $uid, 'ecomcine_geo_address', true ) );
						$matches  = ( $geo_addr === $country )
							|| ( '' !== $geo_addr && str_ends_with( $geo_addr, ', ' . $country ) );
						if ( ! $matches ) {
							return false;
						}
					}

					// ── Attribute / meta filters ───────────────────────────
					foreach ( $meta_filters as $meta_key => $value ) {
						if ( function_exists( 'tm_store_ui_person_matches_meta_filter' ) ) {
							if ( ! tm_store_ui_person_matches_meta_filter( $uid, (string) $meta_key, (string) $value ) ) {
								return false;
							}
						}
					}

					return true;
				}
			)
		);

		// ── Sorting ────────────────────────────────────────────────────────
		if ( function_exists( 'tm_store_ui_sort_person_ids_for_listing' ) ) {
			return tm_store_ui_sort_person_ids_for_listing( $ids, $tm_order );
		}

		return array_values( $ids );
	}
}
