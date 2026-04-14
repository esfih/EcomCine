<?php
/**
 * Initial Wave 1 Listing service.
 *
 * Establishes a single seam around the canonical tm_vendor storage object while
 * preserving legacy behaviour until the authority flags move out of legacy.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Listing_Service {
	const POST_TYPE = 'tm_vendor';

	/**
	 * @return string[]
	 */
	private static function listing_post_statuses(): array {
		return array( 'publish', 'private', 'draft', 'pending', 'future' );
	}

	public static function get_active_profile_base(): string {
		if ( class_exists( 'EcomCine_Wave1_Authority', false ) && EcomCine_Wave1_Authority::is_route_core() ) {
			return 'profile';
		}

		$default_base = function_exists( 'ecomcine_get_person_public_base_singular' )
			? sanitize_title( ecomcine_get_person_public_base_singular() )
			: 'person';
		$base = trim( (string) get_option( 'ecomcine_person_base', $default_base ), '/' );

		if ( '' === $base ) {
			$base = $default_base;
		}

		return sanitize_title( $base );
	}

	/**
	 * @return string[]
	 */
	public static function get_profile_base_aliases(): array {
		$aliases = array(
			self::get_active_profile_base(),
			'profile',
			function_exists( 'ecomcine_get_person_public_base_singular' ) ? ecomcine_get_person_public_base_singular() : 'person',
			'person',
			'talent',
		);

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_title', $aliases )
				)
			)
		);
	}

	public static function get_listing_post( int $listing_id ): ?WP_Post {
		$listing_id = absint( $listing_id );
		if ( $listing_id <= 0 ) {
			return null;
		}

		$post = get_post( $listing_id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	public static function get_listing_id_for_user( int $user_id ): int {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		$cached_id = (int) get_user_meta( $user_id, '_tm_vendor_cpt_id', true );
		if ( $cached_id > 0 ) {
			$post = self::get_listing_post( $cached_id );
			if ( $post instanceof WP_Post ) {
				return (int) $post->ID;
			}
		}

		if ( class_exists( 'TMP_WP_Vendor_CPT', false ) && method_exists( 'TMP_WP_Vendor_CPT', 'get_post_id_for_vendor' ) ) {
			$post_id = (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $user_id );
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => self::listing_post_statuses(),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_tm_vendor_user_id',
						'value' => $user_id,
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$post_id = (int) $posts[0];
		update_user_meta( $user_id, '_tm_vendor_cpt_id', $post_id );

		return $post_id;
	}

	public static function get_listing_post_for_user( int $user_id ): ?WP_Post {
		$listing_id = self::get_listing_id_for_user( $user_id );
		if ( $listing_id <= 0 ) {
			return null;
		}

		return self::get_listing_post( $listing_id );
	}

	public static function get_listing_post_by_slug( string $slug ): ?WP_Post {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		$posts = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => self::POST_TYPE,
				'post_status'    => self::listing_post_statuses(),
				'posts_per_page' => 1,
			)
		);

		if ( empty( $posts ) || ! $posts[0] instanceof WP_Post ) {
			return null;
		}

		return $posts[0];
	}

	public static function get_owner_user_id( int $listing_id ): int {
		$post = self::get_listing_post( $listing_id );
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}

		$owner_user_id = (int) get_post_meta( $post->ID, '_tm_vendor_user_id', true );
		if ( $owner_user_id > 0 ) {
			return $owner_user_id;
		}

		return (int) $post->post_author;
	}

	public static function resolve_owner_user_id_by_profile_slug( string $slug ): int {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}

		$prefer_listing_lookup = class_exists( 'EcomCine_Wave1_Authority', false )
			&& EcomCine_Wave1_Authority::STATE_LEGACY !== EcomCine_Wave1_Authority::get_listing_state();

		if ( $prefer_listing_lookup ) {
			$listing = self::get_listing_post_by_slug( $slug );
			if ( $listing instanceof WP_Post ) {
				$owner_user_id = self::get_owner_user_id( (int) $listing->ID );
				if ( $owner_user_id > 0 ) {
					return $owner_user_id;
				}
			}
		}

		$user = get_user_by( 'slug', $slug );
		if ( $user instanceof WP_User ) {
			$user_id = (int) $user->ID;
			if ( $user_id > 0 && ( ! function_exists( 'ecomcine_is_person_user' ) || ecomcine_is_person_user( $user_id ) ) ) {
				return $user_id;
			}
		}

		$listing = self::get_listing_post_by_slug( $slug );
		if ( $listing instanceof WP_Post ) {
			return self::get_owner_user_id( (int) $listing->ID );
		}

		return 0;
	}

	public static function get_profile_slug_for_user( int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return '';
		}

		$listing = self::get_listing_post_for_user( $user_id );
		$raw_slug = $listing instanceof WP_Post ? sanitize_title( $listing->post_name ) : '';

		// Skip auto-generated post slugs (pattern: tm_vendor_<digits>) — they are
		// not meaningful URL segments and must not be used as canonical profile slugs.
		$listing_slug = ( '' !== $raw_slug && ! preg_match( '/^tm_vendor_\d+$/', $raw_slug ) )
			? $raw_slug
			: '';

		$core_slug = '';
		if ( class_exists( 'EcomCine_Wave1_Authority', false ) && EcomCine_Wave1_Authority::is_route_core() && '' !== $listing_slug ) {
			$core_slug = $listing_slug;
			return $core_slug;
		}

		$user = get_userdata( $user_id );
		$legacy_slug = ( $user instanceof WP_User && ! empty( $user->user_nicename ) )
			? sanitize_title( (string) $user->user_nicename )
			: $listing_slug;

		// Shadow: compare core listing slug against legacy user_nicename slug.
		// Only log when a meaningful listing slug exists (not auto-generated, not empty).
		if ( class_exists( 'EcomCine_Wave1_Authority', false )
			&& EcomCine_Wave1_Authority::STATE_SHADOW === EcomCine_Wave1_Authority::get_route_state()
			&& '' !== $listing_slug
		) {
			EcomCine_Wave1_Authority::shadow_log(
				'listing',
				'profile_slug:user_id=' . $user_id,
				$legacy_slug,
				$listing_slug
			);
		}

		if ( '' !== $legacy_slug ) {
			return $legacy_slug;
		}

		return $listing_slug;
	}

	public static function build_public_url_for_user( int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return '';
		}

		$slug = self::get_profile_slug_for_user( $user_id );
		if ( '' === $slug ) {
			return '';
		}

		return trailingslashit( home_url( '/' . self::get_active_profile_base() . '/' . $slug ) );
	}

	public static function has_public_profile_for_user( int $user_id ): bool {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return true;
		}

		$listing = self::get_listing_post_for_user( $user_id );
		if ( ! $listing instanceof WP_Post ) {
			return false;
		}

		return 'publish' === get_post_status( $listing );
	}

	public static function get_listing_type_for_user( int $user_id ): string {
		$listing = self::get_listing_post_for_user( $user_id );
		if ( ! $listing instanceof WP_Post ) {
			return 'person';
		}

		$type = sanitize_key( (string) get_post_meta( $listing->ID, 'ecomcine_listing_type', true ) );
		if ( in_array( $type, array( 'person', 'company', 'venue' ), true ) ) {
			return $type;
		}

		return 'person';
	}
}