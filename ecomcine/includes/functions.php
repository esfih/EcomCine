<?php
/**
 * EcomCine Core Portable API
 *
 * All functions here work on bare WordPress with zero dependency on Dokan,
 * WooCommerce, or Astra.  When those plugins are present, the functions
 * delegate to them as a compatibility shim so existing meta and calls keep
 * working during a migration period.
 *
 * Naming convention:
 *   ecomcine_*         — stable public API
 *   _ecomcine_*        — private internal helpers (not part of the public API)
 *
 * Meta key registry (authoritative):
 *   ecomcine_store_name    — display / store name
 *   ecomcine_bio           — biography / description
 *   ecomcine_phone         — contact phone
 *   ecomcine_banner_id     — attachment ID for banner image
 *   ecomcine_avatar_id     — attachment ID for avatar/gravatar
 *   ecomcine_address       — serialized address array (street_1, city, state, zip, country)
 *   ecomcine_social        — serialized social links array
 *   ecomcine_geo_address   — human-readable geolocation string  (or lat,lng raw from Mapbox)
 *   ecomcine_geo_lat       — latitude float string
 *   ecomcine_geo_lng       — longitude float string
 *   ecomcine_enabled       — '1' when person is approved/enabled
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

// ── Country list ──────────────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_get_countries' ) ) {
	/**
	 * Return the ISO 3166-1 alpha-2 country code → country name map.
	 *
	 * WooCommerce is used when active (locale/translation support);
	 * otherwise falls back to the bundled static file.
	 *
	 * @return array<string,string>
	 */
	function ecomcine_get_countries(): array {
		if ( function_exists( 'WC' ) && WC()->countries ) {
			return (array) WC()->countries->get_countries();
		}

		static $_cache = null;
		if ( null === $_cache ) {
			$static_file = defined( 'ECOMCINE_DIR' )
				? ECOMCINE_DIR . 'includes/data/iso-countries.php'
				: __DIR__ . '/data/iso-countries.php';
			$_cache = file_exists( $static_file ) ? (array) require $static_file : array();
		}

		return $_cache;
	}
}

// ── Person role helpers ───────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_is_person_user' ) ) {
	/**
	 * Return true when a WP user has the ecomcine_person role.
	 *
	 * Falls back to the legacy Dokan 'seller' role when ecomcine_person is not
	 * registered — preserves compatibility with existing installations that have
	 * not yet run the activation/upgrade routine.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	function ecomcine_is_person_user( int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;

		// Primary check: ecomcine_person role.
		if ( in_array( 'ecomcine_person', $roles, true ) ) {
			return true;
		}

		// Legacy fallback: Dokan vendor role.
		if ( in_array( 'seller', $roles, true ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'ecomcine_get_persons' ) ) {
	/**
	 * Return WP_User objects for all EcomCine persons.
	 *
	 * @param array $args Extra args forwarded to WP_User_Query.
	 * @return WP_User[]
	 */
	function ecomcine_get_persons( array $args = array() ): array {
		// Portability: include both roles so mixed datasets (seller + ecomcine_person)
		// are queryable during migration/cutover.
		$roles = array( 'seller' );
		if ( isset( $GLOBALS['wp_roles'] ) && array_key_exists( 'ecomcine_person', $GLOBALS['wp_roles']->roles ) ) {
			$roles[] = 'ecomcine_person';
		}

		$role_args = array( 'role__in' => array_values( array_unique( $roles ) ) );

		$query_args = array_merge(
			array( 'number' => -1 ),
			$role_args,
			$args
		);

		return get_users( $query_args );
	}
}

if ( ! function_exists( 'ecomcine_is_person_enabled' ) ) {
	/**
	 * Return true when the person's account is enabled/approved.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	function ecomcine_is_person_enabled( int $user_id ): bool {
		$own = get_user_meta( $user_id, 'ecomcine_enabled', true );
		if ( '' !== $own ) {
			return '1' === (string) $own || 'yes' === (string) $own;
		}
		// Dokan fallback.
		$dokan = get_user_meta( $user_id, 'dokan_enable_selling', true );
		if ( '' !== $dokan ) {
			return 'yes' === (string) $dokan;
		}

		return false;
	}
}

if ( ! function_exists( 'ecomcine_get_person_status_flags' ) ) {
	/**
	 * Return featured / verified status for a person.
	 *
	 * Canonical keys are read first. Legacy Dokan keys remain a core-level
	 * migration fallback so UI layers do not need to touch third-party meta.
	 *
	 * @param int $user_id User ID.
	 * @return array{featured: bool, verified: bool}
	 */
	function ecomcine_get_person_status_flags( int $user_id ): array {
		$featured_raw = get_user_meta( $user_id, 'ecomcine_is_featured', true );
		if ( '' === (string) $featured_raw ) {
			$featured_raw = get_user_meta( $user_id, 'ecomcine_featured', true );
		}
		if ( '' === (string) $featured_raw ) {
			$featured_raw = get_user_meta( $user_id, 'dokan_feature_seller', true );
		}

		$verified_raw = get_user_meta( $user_id, 'ecomcine_is_verified', true );
		if ( '' === (string) $verified_raw ) {
			$verified_raw = get_user_meta( $user_id, 'ecomcine_verified', true );
		}
		if ( '' === (string) $verified_raw ) {
			$verified_raw = get_user_meta( $user_id, 'dokan_store_verified', true );
		}

		$normalize = static function( $value ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}

			return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
		};

		return array(
			'featured' => $normalize( $featured_raw ),
			'verified' => $normalize( $verified_raw ),
		);
	}
}

// ── Person info ───────────────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_get_person_info' ) ) {
	/**
	 * Return a normalised info array for a person.
	 *
	 * Reads ecomcine_* meta keys first; when absent, falls back to
	 * dokan_profile_settings for seamless migration.
	 *
	 * @param int $user_id
	 * @return array
	 */
	function ecomcine_get_person_info( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		// Own meta keys.
		$store_name = get_user_meta( $user_id, 'ecomcine_store_name', true );
		$bio        = get_user_meta( $user_id, 'ecomcine_bio', true );
		$phone      = get_user_meta( $user_id, 'ecomcine_phone', true );
		$banner_id  = get_user_meta( $user_id, 'ecomcine_banner_id', true );
		$avatar_id  = get_user_meta( $user_id, 'ecomcine_avatar_id', true );
		$address    = get_user_meta( $user_id, 'ecomcine_address', true );
		$social     = get_user_meta( $user_id, 'ecomcine_social', true );

		// Dokan fallback when own keys are empty.
		$dps = null;
		if ( '' === $store_name || '' === $bio || '' === $phone || empty( $banner_id ) || empty( $avatar_id ) || empty( $address ) || empty( $social ) ) {
			$raw = get_user_meta( $user_id, 'dokan_profile_settings', true );
			$dps = is_array( $raw ) ? $raw : array();
		}

		if ( '' === $store_name && $dps !== null ) {
			$store_name = $dps['store_name'] ?? '';
		}
		if ( '' === $bio && $dps !== null ) {
			$bio = $dps['vendor_biography'] ?? '';
		}
		if ( '' === $phone && $dps !== null ) {
			$phone = $dps['phone'] ?? '';
		}
		if ( ( '' === $banner_id || ! $banner_id ) && $dps !== null ) {
			$banner_id = $dps['banner'] ?? '';
		}
		if ( ( '' === $avatar_id || ! $avatar_id ) && $dps !== null ) {
			$avatar_id = $dps['gravatar'] ?? '';
		}
		if ( empty( $address ) && $dps !== null ) {
			$address = isset( $dps['address'] ) && is_array( $dps['address'] ) ? $dps['address'] : array();
		}
		if ( empty( $social ) && $dps !== null ) {
			$social = isset( $dps['social'] ) && is_array( $dps['social'] ) ? $dps['social'] : array();
		}

		return array(
			'store_name' => (string) $store_name,
			'bio'        => (string) $bio,
			'phone'      => (string) $phone,
			'banner_id'  => (int) $banner_id,
			'avatar_id'  => (int) $avatar_id,
			'address'    => is_array( $address ) ? $address : array(),
			'social'     => is_array( $social ) ? $social : array(),
		);
	}
}

if ( ! function_exists( '_ecomcine_normalize_person_address' ) ) {
	/**
	 * Normalize a person address payload to EcomCine's canonical shape.
	 *
	 * @param mixed $address Raw address payload.
	 * @return array<string,string>
	 */
	function _ecomcine_normalize_person_address( $address ): array {
		$address = is_array( $address ) ? $address : array();

		return array(
			'street_1' => sanitize_text_field( (string) ( $address['street_1'] ?? '' ) ),
			'street_2' => sanitize_text_field( (string) ( $address['street_2'] ?? '' ) ),
			'city'     => sanitize_text_field( (string) ( $address['city'] ?? '' ) ),
			'zip'      => sanitize_text_field( (string) ( $address['zip'] ?? '' ) ),
			'country'  => sanitize_text_field( (string) ( $address['country'] ?? '' ) ),
			'state'    => sanitize_text_field( (string) ( $address['state'] ?? '' ) ),
		);
	}
}

if ( ! function_exists( '_ecomcine_normalize_person_social' ) ) {
	/**
	 * Normalize a person social-links payload.
	 *
	 * @param mixed $social Raw social payload.
	 * @return array<string,string>
	 */
	function _ecomcine_normalize_person_social( $social ): array {
		if ( ! is_array( $social ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $social as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_scalar( $value ) ) {
				$normalized[ $key ] = esc_url_raw( (string) $value );
			}
		}

		return $normalized;
	}
}

if ( ! function_exists( 'ecomcine_sync_person_canonical_meta' ) ) {
	/**
	 * Sync a person's legacy vendor meta into EcomCine-owned canonical fields.
	 *
	 * Canonical keys are always written. When legacy values are unavailable,
	 * existing canonical values are preserved.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	function ecomcine_sync_person_canonical_meta( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! ecomcine_is_person_user( $user_id ) ) {
			return array();
		}

		$dps = get_user_meta( $user_id, 'dokan_profile_settings', true );
		$dps = is_array( $dps ) ? $dps : array();

		$current_info = array(
			'store_name' => (string) get_user_meta( $user_id, 'ecomcine_store_name', true ),
			'bio'        => (string) get_user_meta( $user_id, 'ecomcine_bio', true ),
			'phone'      => (string) get_user_meta( $user_id, 'ecomcine_phone', true ),
			'banner_id'  => (int) get_user_meta( $user_id, 'ecomcine_banner_id', true ),
			'avatar_id'  => (int) get_user_meta( $user_id, 'ecomcine_avatar_id', true ),
			'address'    => _ecomcine_normalize_person_address( get_user_meta( $user_id, 'ecomcine_address', true ) ),
			'social'     => _ecomcine_normalize_person_social( get_user_meta( $user_id, 'ecomcine_social', true ) ),
		);

		$legacy_store_name = sanitize_text_field( (string) ( $dps['store_name'] ?? '' ) );
		$legacy_bio        = (string) ( $dps['vendor_biography'] ?? '' );
		$legacy_phone      = sanitize_text_field( (string) ( $dps['phone'] ?? '' ) );
		$legacy_banner_id  = absint( $dps['banner'] ?? 0 );
		$legacy_avatar_id  = absint( $dps['gravatar'] ?? 0 );
		$legacy_address    = _ecomcine_normalize_person_address( $dps['address'] ?? array() );
		$legacy_social     = _ecomcine_normalize_person_social( $dps['social'] ?? array() );

		$payload = array(
			'ecomcine_store_name' => '' !== $legacy_store_name ? $legacy_store_name : $current_info['store_name'],
			'ecomcine_bio'        => '' !== $legacy_bio ? wp_kses_post( $legacy_bio ) : $current_info['bio'],
			'ecomcine_phone'      => '' !== $legacy_phone ? $legacy_phone : $current_info['phone'],
			'ecomcine_banner_id'  => $legacy_banner_id > 0 ? $legacy_banner_id : $current_info['banner_id'],
			'ecomcine_avatar_id'  => $legacy_avatar_id > 0 ? $legacy_avatar_id : $current_info['avatar_id'],
			'ecomcine_address'    => ! empty( array_filter( $legacy_address ) ) ? $legacy_address : $current_info['address'],
			'ecomcine_social'     => ! empty( $legacy_social ) ? $legacy_social : $current_info['social'],
		);

		$current_geo = array(
			'address' => (string) get_user_meta( $user_id, 'ecomcine_geo_address', true ),
			'lat'     => (string) get_user_meta( $user_id, 'ecomcine_geo_lat', true ),
			'lng'     => (string) get_user_meta( $user_id, 'ecomcine_geo_lng', true ),
		);

		$legacy_geo_address = (string) get_user_meta( $user_id, 'dokan_geo_address', true );
		if ( '' === $legacy_geo_address ) {
			$legacy_geo_address = sanitize_text_field( (string) ( $dps['location'] ?? '' ) );
		}

		$legacy_geo_lat = (string) get_user_meta( $user_id, 'dokan_geo_latitude', true );
		$legacy_geo_lng = (string) get_user_meta( $user_id, 'dokan_geo_longitude', true );
		$legacy_geolocation = isset( $dps['geolocation'] ) && is_array( $dps['geolocation'] ) ? $dps['geolocation'] : array();
		if ( '' === $legacy_geo_lat && isset( $legacy_geolocation['latitude'] ) ) {
			$legacy_geo_lat = (string) $legacy_geolocation['latitude'];
		}
		if ( '' === $legacy_geo_lng && isset( $legacy_geolocation['longitude'] ) ) {
			$legacy_geo_lng = (string) $legacy_geolocation['longitude'];
		}

		$payload['ecomcine_geo_address'] = '' !== $legacy_geo_address ? sanitize_text_field( $legacy_geo_address ) : $current_geo['address'];
		$payload['ecomcine_geo_lat']     = '' !== $legacy_geo_lat ? (string) $legacy_geo_lat : $current_geo['lat'];
		$payload['ecomcine_geo_lng']     = '' !== $legacy_geo_lng ? (string) $legacy_geo_lng : $current_geo['lng'];

		$own_enabled   = (string) get_user_meta( $user_id, 'ecomcine_enabled', true );
		$legacy_enable = (string) get_user_meta( $user_id, 'dokan_enable_selling', true );
		if ( '' !== $legacy_enable ) {
			$payload['ecomcine_enabled'] = 'yes' === $legacy_enable ? '1' : '0';
		} else {
			$payload['ecomcine_enabled'] = '' !== $own_enabled ? $own_enabled : '0';
		}

		foreach ( $payload as $meta_key => $meta_value ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}

		return $payload;
	}
}

if ( ! function_exists( 'ecomcine_sync_all_persons_to_canonical_meta' ) ) {
	/**
	 * Sync all current person/vendor users into EcomCine-owned canonical meta.
	 *
	 * @return int Number of users synced.
	 */
	function ecomcine_sync_all_persons_to_canonical_meta(): int {
		$user_ids = ecomcine_get_persons( array( 'fields' => 'ids', 'number' => -1 ) );
		$synced   = 0;

		foreach ( (array) $user_ids as $user_id ) {
			if ( ! empty( ecomcine_sync_person_canonical_meta( (int) $user_id ) ) ) {
				$synced++;
			}
		}

		return $synced;
	}
}

foreach ( array( 'added_user_meta', 'updated_user_meta' ) as $_ecomcine_sync_hook ) {
	add_action(
		$_ecomcine_sync_hook,
		function( $meta_id, $user_id, $meta_key ) {
			static $queued = array();

			$watched = array(
				'dokan_profile_settings',
				'dokan_geo_address',
				'dokan_geo_latitude',
				'dokan_geo_longitude',
				'dokan_enable_selling',
			);

			if ( ! in_array( $meta_key, $watched, true ) ) {
				return;
			}

			$user_id = (int) $user_id;
			if ( $user_id <= 0 || ! ecomcine_is_person_user( $user_id ) || ! empty( $queued[ $user_id ] ) ) {
				return;
			}

			$queued[ $user_id ] = true;

			if ( wp_doing_ajax() ) {
				ecomcine_sync_person_canonical_meta( $user_id );
				return;
			}

			add_action(
				'shutdown',
				function() use ( $user_id ) {
					ecomcine_sync_person_canonical_meta( $user_id );
				},
				5
			);
		},
		10,
		3
	);
}

add_action(
	'user_register',
	function( $user_id ) {
		ecomcine_sync_person_canonical_meta( (int) $user_id );
	},
	25
);

// ── URL / page detection ──────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_get_person_url' ) ) {
	/**
	 * Return true when a person has a published standalone profile artifact.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	function ecomcine_has_public_person_profile( int $user_id ): bool {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		// When the tm_vendor CPT is not registered (e.g. media-player module is
		// disabled or not yet initialised), there is no profile-artifact system
		// active on this site.  Treat every enabled person as having a public
		// profile — the listing layer already gates on ecomcine_enabled + tm_l1_complete.
		if ( ! post_type_exists( 'tm_vendor' ) ) {
			return true;
		}

		if ( class_exists( 'TMP_WP_Vendor_CPT', false ) && method_exists( 'TMP_WP_Vendor_CPT', 'get_post_id_for_vendor' ) ) {
			$post_id = (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $user_id );
			if ( $post_id > 0 ) {
				return 'publish' === get_post_status( $post_id );
			}
		}

		$posts = get_posts(
			array(
				'post_type'      => 'tm_vendor',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_tm_vendor_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		return ! empty( $posts );
	}

	/**
	 * Resolve a person user ID from nicename slug without public visibility checks.
	 *
	 * @param string $slug
	 * @return int
	 */
	function ecomcine_find_person_user_id_by_slug( string $slug ): int {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}

		$user = get_user_by( 'slug', $slug );
		if ( ! $user ) {
			return 0;
		}

		$user_id = (int) $user->ID;
		if ( $user_id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'ecomcine_is_person_user' ) && ! ecomcine_is_person_user( $user_id ) ) {
			return 0;
		}

		return $user_id;
	}

	/**
	 * Resolve a person user ID from nicename slug.
	 *
	 * @param string $slug
	 * @return int
	 */
	function ecomcine_resolve_person_user_id_by_slug( string $slug ): int {
		$user_id = function_exists( 'ecomcine_find_person_user_id_by_slug' )
			? ecomcine_find_person_user_id_by_slug( $slug )
			: 0;
		if ( $user_id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'ecomcine_is_person_enabled' ) && ! ecomcine_is_person_enabled( $user_id ) ) {
			return 0;
		}

		if ( function_exists( 'ecomcine_has_public_person_profile' ) && ! ecomcine_has_public_person_profile( $user_id ) ) {
			return 0;
		}

		return $user_id;
	}

	/**
	 * Return the canonical public profile URL for a person.
	 *
	 * Uses EcomCine's own /person/{nicename}/ rewrite when the rewrite rule is
	 * registered; otherwise delegates to dokan_get_store_url() as a fallback.
	 *
	 * @param int $user_id
	 * @return string URL or empty string.
	 */
	function ecomcine_get_person_url( int $user_id ): string {
		if ( ! $user_id ) {
			return '';
		}

		if ( function_exists( 'ecomcine_is_person_user' ) && ! ecomcine_is_person_user( $user_id ) ) {
			return '';
		}

		if ( function_exists( 'ecomcine_is_person_enabled' ) && ! ecomcine_is_person_enabled( $user_id ) ) {
			return '';
		}

		if ( function_exists( 'ecomcine_has_public_person_profile' ) && ! ecomcine_has_public_person_profile( $user_id ) ) {
			return '';
		}

		// EcomCine native rewrite rule: /person/{user_nicename}/.
		$rewrite_base = get_option( 'ecomcine_person_base', 'person' );
		$user         = get_userdata( $user_id );
		if ( $user && $user->user_nicename ) {
			$url = trailingslashit( home_url( '/' . trim( $rewrite_base, '/' ) . '/' . $user->user_nicename ) );
			return $url;
		}

		// Dokan fallback for sites that have not yet migrated rewrite rules.
		if ( function_exists( 'dokan_get_store_url' ) ) {
			return (string) dokan_get_store_url( $user_id );
		}

		return '';
	}
}

// Register /person/{nicename}/ routing and map it to ecomcine_person query var.
add_action( 'init', function() {
	$rewrite_base = trim( (string) get_option( 'ecomcine_person_base', 'person' ), '/' );
	if ( '' === $rewrite_base ) {
		$rewrite_base = 'person';
	}

	add_rewrite_tag( '%ecomcine_person%', '([^&]+)' );
	add_rewrite_rule( '^' . preg_quote( $rewrite_base, '/' ) . '/([^/]+)/?$', 'index.php?ecomcine_person=$matches[1]', 'top' );

	$flush_key      = 'ecomcine_person_rewrite_flushed';
	$flush_expected = '1|' . $rewrite_base;
	$flush_state    = (string) get_option( $flush_key, '' );
	if ( $flush_expected !== $flush_state ) {
		flush_rewrite_rules( false );
		update_option( $flush_key, $flush_expected, false );
	}
}, 20 );

add_filter( 'query_vars', function( array $vars ): array {
	if ( ! in_array( 'ecomcine_person', $vars, true ) ) {
		$vars[] = 'ecomcine_person';
	}

	return $vars;
} );

// Set dynamic document title for standalone person profile pages.
add_filter( 'pre_get_document_title', function( string $title ): string {
	$slug = (string) get_query_var( 'ecomcine_person', '' );
	if ( '' === $slug ) {
		return $title;
	}

	$user_id = function_exists( 'ecomcine_find_person_user_id_by_slug' )
		? ecomcine_find_person_user_id_by_slug( $slug )
		: 0;
	if ( $user_id <= 0 ) {
		return $title;
	}

	$full_name = trim( (string) get_the_author_meta( 'display_name', $user_id ) );
	if ( '' === $full_name ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$full_name = trim( (string) $user->display_name );
		}
	}
	if ( '' === $full_name ) {
		$full_name = 'Person';
	}

	$site_name = trim( (string) get_bloginfo( 'name' ) );
	if ( '' === $site_name ) {
		$site_name = 'EcomCine';
	}

	return sprintf( '%s profile page on %s', $full_name, $site_name );
}, 20 );

// Prevent core canonical redirects from hijacking person routes when slugs collide with attachments.
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
	$slug = (string) get_query_var( 'ecomcine_person', '' );
	if ( '' !== $slug ) {
		return false;
	}

	return $redirect_url;
}, 10, 2 );

// Route single person pages to the standalone profile template.
add_filter( 'template_include', function( string $template ): string {
	$slug = (string) get_query_var( 'ecomcine_person', '' );
	if ( '' === $slug ) {
		return $template;
	}

	$user_id = function_exists( 'ecomcine_find_person_user_id_by_slug' )
		? ecomcine_find_person_user_id_by_slug( $slug )
		: 0;
	if ( $user_id <= 0 ) {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
		$not_found = get_404_template();
		return $not_found ? $not_found : $template;
	}

	set_query_var( 'author', $user_id );
	set_query_var(
		'ecomcine_person_publicly_available',
		( function_exists( 'ecomcine_is_person_enabled' ) ? ecomcine_is_person_enabled( $user_id ) : false )
		&& ( function_exists( 'ecomcine_has_public_person_profile' ) ? ecomcine_has_public_person_profile( $user_id ) : false )
	);
	$GLOBALS['tm_showcase_page'] = true;

	$person_template = defined( 'TM_STORE_UI_DIR' )
		? TM_STORE_UI_DIR . 'templates/page-templates/template-person-profile.php'
		: '';

	if ( $person_template && file_exists( $person_template ) ) {
		return $person_template;
	}

	return $template;
}, 91 );

if ( ! function_exists( 'ecomcine_is_person_page' ) ) {
	/**
	 * Return true when the current request is a single-person profile page.
	 *
	 * @return bool
	 */
	function ecomcine_is_person_page(): bool {
		// EcomCine native query var.
		if ( get_query_var( 'ecomcine_person' ) ) {
			return true;
		}

		// Dokan fallback.
		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'ecomcine_is_person_listing' ) ) {
	/**
	 * Return true when the current request is the person/vendors listing page.
	 *
	 * @return bool
	 */
	function ecomcine_is_person_listing(): bool {
		// Check against the configured talents/listing page ID.
		$listing_page_id = (int) get_option( 'ecomcine_listing_page_id', 0 );
		if ( $listing_page_id && is_page( $listing_page_id ) ) {
			return true;
		}

		// Fallback: page with the 'talents' slug.
		$talents_page = get_page_by_path( 'talents' );
		if ( $talents_page && is_page( $talents_page->ID ) ) {
			return true;
		}

		// Dokan fallback.
		if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
			return true;
		}

		return false;
	}
}

// ── Geolocation ───────────────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_get_geo' ) ) {
	/**
	 * Return the geolocation data array for a person.
	 *
	 * Reads ecomcine_geo_* meta first; falls back to dokan_geo_* keys.
	 *
	 * @param int $user_id
	 * @return array{ address: string, lat: string, lng: string }
	 */
	function ecomcine_get_geo( int $user_id ): array {
		$address = get_user_meta( $user_id, 'ecomcine_geo_address', true );
		$lat     = get_user_meta( $user_id, 'ecomcine_geo_lat', true );
		$lng     = get_user_meta( $user_id, 'ecomcine_geo_lng', true );

		// Dokan fallback.
		$dps = null;
		if ( '' === $address || '' === $lat || '' === $lng ) {
			$raw = get_user_meta( $user_id, 'dokan_profile_settings', true );
			$dps = is_array( $raw ) ? $raw : array();
		}
		if ( '' === $address ) {
			$address = (string) get_user_meta( $user_id, 'dokan_geo_address', true );
			if ( '' === $address && is_array( $dps ) ) {
				$candidate = sanitize_text_field( (string) ( $dps['location'] ?? '' ) );
				// Only use as human-readable address — skip raw coordinate strings ("lat,lng").
				if ( ! preg_match( '/^\s*-?\d+\.?\d*\s*,\s*-?\d+\.?\d*\s*$/', $candidate ) ) {
					$address = $candidate;
				}
			}
			// find_address fallback: more readable than dokan location.
			if ( '' === $address && is_array( $dps ) ) {
				$address = sanitize_text_field( (string) ( $dps['find_address'] ?? '' ) );
			}
		}
		if ( '' === $lat ) {
			$lat = (string) get_user_meta( $user_id, 'dokan_geo_latitude', true );
			if ( '' === $lat && is_array( $dps ) && isset( $dps['geolocation']['latitude'] ) ) {
				$lat = (string) $dps['geolocation']['latitude'];
			}
			// Parse lat from coordinate-format dps['location'] when geolocation is absent.
			if ( '' === $lat && is_array( $dps ) ) {
				$loc = (string) ( $dps['location'] ?? '' );
				if ( preg_match( '/^\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/', $loc, $m ) ) {
					$lat = $m[1];
				}
			}
		}
		if ( '' === $lng ) {
			$lng = (string) get_user_meta( $user_id, 'dokan_geo_longitude', true );
			if ( '' === $lng && is_array( $dps ) && isset( $dps['geolocation']['longitude'] ) ) {
				$lng = (string) $dps['geolocation']['longitude'];
			}
			// Parse lng from coordinate-format dps['location'] when geolocation is absent.
			if ( '' === $lng && is_array( $dps ) ) {
				$loc = (string) ( $dps['location'] ?? '' );
				if ( preg_match( '/^\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/', $loc, $m ) ) {
					$lng = $m[2];
				}
			}
		}

		return array(
			'address' => (string) $address,
			'lat'     => (string) $lat,
			'lng'     => (string) $lng,
		);
	}
}

// ── Mapbox token ──────────────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_get_mapbox_token' ) ) {
	/**
	 * Return the Mapbox public token used for geocoding / map embeds.
	 *
	 * @return string Token string or empty string when not configured.
	 */
	function ecomcine_get_mapbox_token(): string {
		// Own settings first.
		if ( class_exists( 'EcomCine_Admin_Settings', false ) ) {
			$settings = EcomCine_Admin_Settings::get_settings();
			$token    = $settings['mapbox_token'] ?? '';
			if ( '' !== (string) $token ) {
				return (string) $token;
			}
		}

		// Dokan fallback.
		if ( function_exists( 'dokan_get_option' ) ) {
			$dokan_token = dokan_get_option( 'mapbox_api_key', 'dokan_geolocation', '' );
			if ( '' !== (string) $dokan_token ) {
				return (string) $dokan_token;
			}
		}

		return '';
	}
}

// ── Template loader ───────────────────────────────────────────────────────────

if ( ! function_exists( 'ecomcine_load_template' ) ) {
	/**
	 * Load a template file from (in priority order):
	 *   1. Active theme:               ecomcine/{$name}.php
	 *   2. EcomCine plugin templates:  ecomcine/templates/{$name}.php
	 *   3. EcomCine Base templates:    ecomcine/ecomcine-base/templates/{$name}.php
	 *
	 * @param string $name  Template slug (no extension, no leading slash).
	 * @param array  $args  Variables to extract into template scope.
	 * @return void
	 */
	function ecomcine_load_template( string $name, array $args = array() ): void {
		$file_name = sanitize_file_name( $name ) . '.php';

		$candidates = array(
			get_stylesheet_directory() . '/ecomcine/' . $file_name,
			get_template_directory()   . '/ecomcine/' . $file_name,
		);

		if ( defined( 'ECOMCINE_DIR' ) ) {
			$candidates[] = ECOMCINE_DIR . 'templates/' . $file_name;
			$candidates[] = ECOMCINE_DIR . 'ecomcine-base/templates/' . $file_name;
		}

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				if ( ! empty( $args ) ) {
					// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
					extract( $args, EXTR_SKIP );
				}
				include $path;
				return;
			}
		}
	}
}
