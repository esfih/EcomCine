<?php
/**
 * Wave 1 Route service.
 *
 * Centralizes profile route registration, alias redirects, and template
 * resolution so Packet 2 route ownership can evolve behind one core seam.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Route_Service {
	const PERSON_QUERY_VAR = 'ecomcine_person';
	const REWRITE_FLUSH_OPTION = 'ecomcine_person_rewrite_flushed';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_person_routes' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 20 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'filter_redirect_canonical' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_template_redirect' ), 5 );
		add_filter( 'template_include', array( __CLASS__, 'filter_template_include' ), 91 );
	}

	public static function register_person_routes(): void {
		$rewrite_bases = function_exists( 'ecomcine_get_person_profile_base_aliases' )
			? ecomcine_get_person_profile_base_aliases()
			: array( trim( (string) get_option( 'ecomcine_person_base', 'person' ), '/' ) );
		$rewrite_bases = array_values( array_unique( array_filter( array_map( 'sanitize_title', $rewrite_bases ) ) ) );
		if ( empty( $rewrite_bases ) ) {
			$rewrite_bases = array( 'person' );
		}

		add_rewrite_tag( '%' . self::PERSON_QUERY_VAR . '%', '([^&]+)' );
		foreach ( $rewrite_bases as $rewrite_base ) {
			add_rewrite_rule( '^' . preg_quote( $rewrite_base, '/' ) . '/([^/]+)/?$', 'index.php?' . self::PERSON_QUERY_VAR . '=$matches[1]', 'top' );
		}

		$flush_expected = '2|' . implode( ',', $rewrite_bases );
		$flush_state    = (string) get_option( self::REWRITE_FLUSH_OPTION, '' );
		if ( $flush_expected !== $flush_state ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_FLUSH_OPTION, $flush_expected, false );
		}
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function register_query_vars( array $vars ): array {
		if ( ! in_array( self::PERSON_QUERY_VAR, $vars, true ) ) {
			$vars[] = self::PERSON_QUERY_VAR;
		}

		return $vars;
	}

	public static function filter_document_title( string $title ): string {
		$slug = self::get_person_slug();
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
			$full_name = function_exists( 'ecomcine_get_person_public_label_singular' )
				? ecomcine_get_person_public_label_singular()
				: 'Person';
		}

		$site_name = trim( (string) get_bloginfo( 'name' ) );
		if ( '' === $site_name ) {
			$site_name = 'EcomCine';
		}

		return sprintf( '%s profile page on %s', $full_name, $site_name );
	}

	public static function filter_redirect_canonical( $redirect_url, $requested_url ) {
		if ( '' !== self::get_person_slug() ) {
			return false;
		}

		return $redirect_url;
	}

	public static function handle_template_redirect(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$person_slug = self::get_person_slug();
		if ( '' !== $person_slug ) {
			$requested_path = self::get_requested_path();
			$request_parts  = '' === $requested_path ? array() : explode( '/', $requested_path );
			$requested_base = sanitize_title( $request_parts[0] ?? '' );
			$canonical_base = function_exists( 'ecomcine_get_person_profile_base' ) ? ecomcine_get_person_profile_base() : 'person';

			if ( $requested_base && $requested_base !== $canonical_base && in_array( $requested_base, ecomcine_get_person_profile_base_aliases(), true ) ) {
				$user_id = function_exists( 'ecomcine_find_person_user_id_by_slug' )
					? ecomcine_find_person_user_id_by_slug( $person_slug )
					: 0;

				if ( $user_id > 0 ) {
					self::safe_redirect_with_query( ecomcine_get_person_url( $user_id ) );
				}
			}
		}

		$listing_page = function_exists( 'ecomcine_get_person_listing_page' ) ? ecomcine_get_person_listing_page() : null;
		if ( $listing_page instanceof WP_Post && is_page() ) {
			$queried_page = get_queried_object();
			if ( $queried_page instanceof WP_Post && 'page' === $queried_page->post_type && (int) $queried_page->ID !== (int) $listing_page->ID && false !== strpos( (string) $queried_page->post_content, '[ecomcine-stores]' ) ) {
				self::safe_redirect_with_query( ecomcine_get_person_listing_url() );
			}
		}

		$requested_path = self::get_requested_path();
		$legacy_slugs   = array_values( array_unique( array_filter( array( 'talents', ecomcine_get_person_public_base_singular() ) ) ) );
		$canonical_path = trim( (string) wp_parse_url( ecomcine_get_person_listing_url(), PHP_URL_PATH ), '/' );

		if ( '' === $requested_path || ! in_array( $requested_path, $legacy_slugs, true ) || $requested_path === $canonical_path ) {
			return;
		}

		self::safe_redirect_with_query( ecomcine_get_person_listing_url() );
	}

	public static function filter_template_include( string $template ): string {
		$slug = self::get_person_slug();
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
	}

	public static function get_person_slug(): string {
		return (string) get_query_var( self::PERSON_QUERY_VAR, '' );
	}

	private static function get_requested_path(): string {
		return trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
	}

	private static function safe_redirect_with_query( string $target_url ): void {
		if ( '' === trim( $target_url ) ) {
			return;
		}

		if ( ! empty( $_GET ) ) {
			$target_url = add_query_arg( wp_unslash( $_GET ), $target_url );
		}

		wp_safe_redirect( $target_url, 301, 'EcomCine' );
		exit;
	}
}