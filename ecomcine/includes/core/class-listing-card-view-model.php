<?php
/**
 * Packet 5b — Listing card view model.
 *
 * Canonical data shape for a single directory listing card.
 * All fields that the [ecomcine-stores] shortcode renders per card are
 * gathered here through one factory method, so templates and shortcodes
 * have a single stable contract rather than scattered helper calls.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Listing_Card_View_Model {

	/** @var int WordPress user ID of the listing owner. */
	public int $user_id;

	/** @var string Display name for the card heading. */
	public string $name;

	/** @var string Canonical public profile URL. */
	public string $profile_url;

	/** @var string Full-size banner image URL (empty when none). */
	public string $banner_url;

	/** @var string Avatar image URL (falls back to gravatar). */
	public string $avatar_url;

	/** @var string Comma-separated category label string for display. */
	public string $category_label;

	/** @var string[] Category slugs used for filter matching. */
	public array $category_slugs;

	/** @var string Pre-escaped HTML fragment for location + flag (may be empty). */
	public string $location_html;

	/** @var bool True when the listing is marked as featured. */
	public bool $is_featured;

	/** @var bool True when the listing is marked as verified. */
	public bool $is_verified;

	private function __construct() {}

	/**
	 * Build a view model for one vendor user.
	 *
	 * Returns null when the user does not exist or cannot produce a valid
	 * profile URL (i.e. should be excluded from the directory).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return static|null
	 */
	public static function build_for_user( int $user_id ): ?self {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return null;
		}

		// ── Profile URL ───────────────────────────────────────────────────────
		$profile_url = '';

		if ( class_exists( 'EcomCine_Listing_Service', false ) ) {
			$profile_url = EcomCine_Listing_Service::build_public_url_for_user( $user_id );
		}

		if ( '' === $profile_url && function_exists( 'ecomcine_get_person_route_url' ) ) {
			$profile_url = (string) ecomcine_get_person_route_url( $user_id );
		}

		if ( '' === trim( $profile_url ) ) {
			return null;
		}

		// ── Profile info ──────────────────────────────────────────────────────
		$profile = function_exists( 'ecomcine_get_person_info' ) ? (array) ecomcine_get_person_info( $user_id ) : array();

		$name = isset( $profile['store_name'] ) && '' !== trim( (string) $profile['store_name'] )
			? trim( (string) $profile['store_name'] )
			: trim( (string) $user->display_name );

		// ── Banner & avatar ───────────────────────────────────────────────────
		$banner_url = '';
		$avatar_url = '';

		$store_user = function_exists( 'tm_store_ui_get_store_user' ) ? tm_store_ui_get_store_user( $user_id ) : null;

		if ( $store_user && method_exists( $store_user, 'get_banner' ) ) {
			$banner_url = (string) $store_user->get_banner();
		}
		if ( $store_user && method_exists( $store_user, 'get_avatar' ) ) {
			$avatar_url = (string) $store_user->get_avatar();
		}

		if ( '' === $banner_url && ! empty( $profile['banner_id'] ) ) {
			$img = wp_get_attachment_image_url( (int) $profile['banner_id'], 'full' );
			if ( is_string( $img ) ) {
				$banner_url = $img;
			}
		}
		if ( '' === $avatar_url && ! empty( $profile['avatar_id'] ) ) {
			$img = wp_get_attachment_image_url( (int) $profile['avatar_id'], 'thumbnail' );
			if ( is_string( $img ) ) {
				$avatar_url = $img;
			}
		}
		if ( '' === $avatar_url ) {
			$avatar_url = (string) get_avatar_url( $user_id, array( 'size' => 300 ) );
		}

		// ── Categories ────────────────────────────────────────────────────────
		$category_label = function_exists( 'tm_store_ui_get_person_category_label' )
			? (string) tm_store_ui_get_person_category_label( $user_id )
			: '';

		$category_slugs = function_exists( 'tm_store_ui_get_person_category_slugs' )
			? (array) tm_store_ui_get_person_category_slugs( $user_id )
			: array();

		// ── Location ──────────────────────────────────────────────────────────
		$location_html = function_exists( 'tm_store_ui_get_person_location_display_html' )
			? (string) tm_store_ui_get_person_location_display_html( $user_id, $profile )
			: '';

		// ── Badges ────────────────────────────────────────────────────────────
		$badges = function_exists( 'tm_store_ui_get_person_status_badges' )
			? (array) tm_store_ui_get_person_status_badges( $user_id )
			: array( 'featured' => false, 'verified' => false );

		// ── Assemble ──────────────────────────────────────────────────────────
		$vm                  = new self();
		$vm->user_id         = $user_id;
		$vm->name            = $name;
		$vm->profile_url     = $profile_url;
		$vm->banner_url      = $banner_url;
		$vm->avatar_url      = $avatar_url;
		$vm->category_label  = $category_label;
		$vm->category_slugs  = array_values( array_filter( $category_slugs ) );
		$vm->location_html   = $location_html;
		$vm->is_featured     = ! empty( $badges['featured'] );
		$vm->is_verified     = ! empty( $badges['verified'] );

		return $vm;
	}

	/**
	 * Build view models for a list of user IDs, skipping any that fail.
	 *
	 * @param int[] $user_ids
	 * @return static[]
	 */
	public static function build_collection( array $user_ids ): array {
		$models = array();
		foreach ( (array) $user_ids as $uid ) {
			$vm = self::build_for_user( (int) $uid );
			if ( null !== $vm ) {
				$models[] = $vm;
			}
		}
		return $models;
	}
}
