<?php
/**
 * Default-WP adapter: tm_invitation CPT for onboarding flows (tap-003).
 *
 * Replaces the user-meta storage in the compat adapter with a custom
 * post type, enabling richer querying and lifecycle management.
 *
 * Post fields:
 *  - post_type     = 'tm_invitation'
 *  - post_status   = 'publish'  (initial) | 'private' (claimed) | 'trash' (expired)
 *  - post_author   = talent_user_id
 *  - meta: _tm_inv_token, _tm_inv_expiry, _tm_inv_claimed_at, _tm_inv_admin_id
 *  - meta: _tm_inv_type  ('invite' | 'share')
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Invitation_CPT {

	const POST_TYPE = 'tm_invitation';
	const TOKEN_TTL = 172800; // 48 hours.

	// -----------------------------------------------------------------------
	// CPT registration
	// -----------------------------------------------------------------------

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => 'Invitations',
				'public'          => false,
				'show_ui'         => false,
				'supports'        => [ 'author' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			]
		);
	}

	// -----------------------------------------------------------------------
	// Factory helpers
	// -----------------------------------------------------------------------

	/** Create a new invite or share record; return post ID. */
	public static function create( int $talent_user_id, string $type = 'invite' ): int {
		$token  = wp_generate_password( 32, false );
		$ttl    = 'share' === $type ? 3600 : self::TOKEN_TTL;
		$expiry = time() + $ttl;

		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_author' => $talent_user_id,
			'post_title'  => $type . '-' . $talent_user_id . '-' . $token,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_tm_inv_token', $token );
		update_post_meta( $post_id, '_tm_inv_expiry', $expiry );
		update_post_meta( $post_id, '_tm_inv_type', $type );
		update_post_meta( $post_id, '_tm_inv_claimed', 0 );

		return $post_id;
	}

	/** Look up a CPT post by token string; return WP_Post or null. */
	public static function get_by_token( string $token ): ?WP_Post {
		$posts = get_posts( [
			'post_type'  => self::POST_TYPE,
			'meta_key'   => '_tm_inv_token',
			'meta_value' => $token,
			'numberposts'=> 1,
		] );

		return $posts[0] ?? null;
	}

	/** Mark a CPT invitation as claimed. */
	public static function mark_claimed( int $post_id ): void {
		update_post_meta( $post_id, '_tm_inv_claimed', time() );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'private' ] );
	}
}
