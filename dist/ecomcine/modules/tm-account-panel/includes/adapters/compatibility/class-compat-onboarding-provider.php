<?php
/**
 * Compatibility adapter: onboarding provider.
 *
 * Stores invitation tokens as WP user meta and manages lifecycle via
 * admin-ajax.php handlers. No custom table required.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Compat_Onboarding_Provider implements TAP_Onboarding_Provider {

	/** Token TTL in seconds (48 hours). */
	const TOKEN_TTL = 172800;

	// -----------------------------------------------------------------------
	// Public contract methods
	// -----------------------------------------------------------------------

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

		$token   = wp_generate_password( 32, false );
		$expiry  = time() + self::TOKEN_TTL;

		update_user_meta( $talent_user_id, 'tm_invitation_token', $token );
		update_user_meta( $talent_user_id, 'tm_invitation_expiry', $expiry );
		update_user_meta( $talent_user_id, 'tm_invitation_claimed', 0 );
		update_user_meta( $talent_user_id, 'tm_invited_at', time() );

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

		// Find the user with this token.
		$users = get_users( [
			'meta_key'   => 'tm_invitation_token',
			'meta_value' => $token,
			'number'     => 1,
		] );

		if ( empty( $users ) ) {
			return [ 'error' => 'invalid_token' ];
		}

		$user   = $users[0];
		$expiry = (int) get_user_meta( $user->ID, 'tm_invitation_expiry', true );
		$claimed = (int) get_user_meta( $user->ID, 'tm_invitation_claimed', true );

		if ( $claimed ) {
			return [ 'error' => 'already_claimed' ];
		}
		if ( $expiry > 0 && time() > $expiry ) {
			return [ 'error' => 'expired_token' ];
		}

		// Perform claim.
		$user->add_role( 'talent' );
		update_user_meta( $user->ID, 'tm_invitation_claimed', 1 );
		update_user_meta( $user->ID, 'tm_claimed_at', time() );

		return [
			'success' => true,
			'user_id' => $user->ID,
			'role'    => 'talent',
		];
	}

	public function get_onboarding_status( int $talent_user_id ): array {
		$invited_at  = (int) get_user_meta( $talent_user_id, 'tm_invited_at', true );
		$claimed_at  = (int) get_user_meta( $talent_user_id, 'tm_claimed_at', true );
		$expiry      = (int) get_user_meta( $talent_user_id, 'tm_invitation_expiry', true );
		$claimed     = (int) get_user_meta( $talent_user_id, 'tm_invitation_claimed', true );

		if ( $claimed_at ) {
			$stage = 'claimed';
		} elseif ( $invited_at && $expiry > 0 && time() > $expiry ) {
			$stage = 'expired';
		} elseif ( $invited_at ) {
			$stage = 'invited';
		} else {
			$stage = 'anonymous';
		}

		$result = [ 'lifecycle_stage' => $stage ];
		if ( $invited_at ) {
			$result['invited_at']    = gmdate( 'Y-m-d H:i:s', $invited_at );
			$result['token_expiry']  = gmdate( 'Y-m-d H:i:s', $expiry );
		}
		if ( $claimed_at ) {
			$result['claimed_at'] = gmdate( 'Y-m-d H:i:s', $claimed_at );
		}

		return $result;
	}

	public function create_share_link( int $talent_user_id ): array {
		if ( ! get_userdata( $talent_user_id ) ) {
			return [ 'error' => 'invalid_user' ];
		}

		$token  = wp_generate_password( 32, false );
		$expiry = time() + 3600; // 1-hour share link.

		// Store as separate meta so it doesn't conflict with invitation token.
		$share_tokens   = (array) get_user_meta( $talent_user_id, 'tm_share_tokens', true );
		$share_tokens[] = [
			'token'  => $token,
			'expiry' => $expiry,
		];
		update_user_meta( $talent_user_id, 'tm_share_tokens', $share_tokens );

		$share_url = add_query_arg(
			[ 'tm_share' => $token ],
			get_author_posts_url( $talent_user_id )
		);

		return [
			'share_url' => esc_url_raw( $share_url ),
			'expiry'    => gmdate( 'Y-m-d H:i:s', $expiry ),
		];
	}
}
