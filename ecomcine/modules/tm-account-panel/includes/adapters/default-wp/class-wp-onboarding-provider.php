<?php
/**
 * Default-WP adapter: onboarding provider using tm_invitation CPT.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Onboarding_Provider implements TAP_Onboarding_Provider {

	public function create_invitation( int $talent_user_id, string $admin_nonce ): array {
		if ( ! wp_verify_nonce( $admin_nonce, 'tm_account_admin' ) ) {
			return [ 'error' => 'invalid_nonce' ];
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return [ 'error' => 'forbidden' ];
		}
		if ( ! get_userdata( $talent_user_id ) ) {
			return [ 'error' => 'invalid_user' ];
		}

		$post_id = TAP_WP_Invitation_CPT::create( $talent_user_id, 'invite' );
		if ( ! $post_id ) {
			return [ 'error' => 'create_failed' ];
		}

		$token   = get_post_meta( $post_id, '_tm_inv_token', true );
		$expiry  = (int) get_post_meta( $post_id, '_tm_inv_expiry', true );

		$claim_url = add_query_arg(
			[ 'action' => 'tm_onboard_claim', 'tm_claim' => $token ],
			admin_url( 'admin-post.php' )
		);

		return [
			'token'     => $token,
			'expiry'    => gmdate( 'Y-m-d H:i:s', $expiry ),
			'claim_url' => esc_url_raw( $claim_url ),
		];
	}

	public function claim_invitation( string $token ): array {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return [ 'error' => 'invalid_token' ];
		}

		$post = TAP_WP_Invitation_CPT::get_by_token( $token );
		if ( ! $post ) {
			return [ 'error' => 'invalid_token' ];
		}

		$expiry  = (int) get_post_meta( $post->ID, '_tm_inv_expiry', true );
		$claimed = (int) get_post_meta( $post->ID, '_tm_inv_claimed', true );

		if ( $claimed ) {
			return [ 'error' => 'already_claimed' ];
		}
		if ( $expiry > 0 && time() > $expiry ) {
			return [ 'error' => 'expired_token' ];
		}

		$talent_user_id = (int) $post->post_author;
		$user           = get_userdata( $talent_user_id );
		if ( ! $user ) {
			return [ 'error' => 'invalid_token' ];
		}

		$user->add_role( 'talent' );
		TAP_WP_Invitation_CPT::mark_claimed( $post->ID );

		return [
			'success' => true,
			'user_id' => $talent_user_id,
			'role'    => 'talent',
		];
	}

	public function get_onboarding_status( int $talent_user_id ): array {
		// Find the latest invite-type CPT for this user.
		$posts = get_posts( [
			'post_type'   => TAP_WP_Invitation_CPT::POST_TYPE,
			'author'      => $talent_user_id,
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => [ [ 'key' => '_tm_inv_type', 'value' => 'invite' ] ],
		] );

		if ( empty( $posts ) ) {
			return [ 'lifecycle_stage' => 'anonymous' ];
		}

		$post    = $posts[0];
		$expiry  = (int) get_post_meta( $post->ID, '_tm_inv_expiry', true );
		$claimed = (int) get_post_meta( $post->ID, '_tm_inv_claimed', true );

		if ( $claimed ) {
			$stage = 'claimed';
		} elseif ( $expiry > 0 && time() > $expiry ) {
			$stage = 'expired';
		} else {
			$stage = 'invited';
		}

		$result = [
			'lifecycle_stage' => $stage,
			'invited_at'      => $post->post_date_gmt,
			'token_expiry'    => gmdate( 'Y-m-d H:i:s', $expiry ),
		];
		if ( $claimed ) {
			$result['claimed_at'] = gmdate( 'Y-m-d H:i:s', $claimed );
		}

		return $result;
	}

	public function create_share_link( int $talent_user_id ): array {
		if ( ! get_userdata( $talent_user_id ) ) {
			return [ 'error' => 'invalid_user' ];
		}

		$post_id = TAP_WP_Invitation_CPT::create( $talent_user_id, 'share' );
		if ( ! $post_id ) {
			return [ 'error' => 'create_failed' ];
		}

		$token  = get_post_meta( $post_id, '_tm_inv_token', true );
		$expiry = (int) get_post_meta( $post_id, '_tm_inv_expiry', true );

		$share_base_url = function_exists( 'ecomcine_get_person_route_url' )
			? ecomcine_get_person_route_url( $talent_user_id )
			: '';
		if ( '' === $share_base_url ) {
			$share_base_url = get_author_posts_url( $talent_user_id );
		}

		$share_url = add_query_arg(
			[ 'tm_share' => $token ],
			$share_base_url
		);

		return [
			'share_url' => esc_url_raw( $share_url ),
			'expiry'    => gmdate( 'Y-m-d H:i:s', $expiry ),
		];
	}
}
