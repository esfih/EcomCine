<?php
/**
 * Contract: Talent onboarding / invitation / share-link flows.
 *
 * Covers the admin-assisted lifecycle for talent users (tap-003).
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TAP_Onboarding_Provider {
	/**
	 * Generate an invitation token for a talent user.
	 *
	 * Returns on success:
	 *   [ 'token' => string, 'expiry' => string (Y-m-d H:i:s), 'claim_url' => string ]
	 * Returns on failure:
	 *   [ 'error' => string ]
	 *
	 * @param int    $talent_user_id Target user ID.
	 * @param string $admin_nonce    Nonce for 'tm_account_admin'.
	 * @return array
	 */
	public function create_invitation( int $talent_user_id, string $admin_nonce ): array;

	/**
	 * Claim an invitation by token.
	 *
	 * Returns on success:
	 *   [ 'success' => true, 'user_id' => int, 'role' => string ]
	 * Returns on failure:
	 *   [ 'error' => 'invalid_token'|'expired_token'|'already_claimed' ]
	 *
	 * @param string $token The invitation token from the claim URL.
	 * @return array
	 */
	public function claim_invitation( string $token ): array;

	/**
	 * Get the full onboarding lifecycle status for a talent user.
	 *
	 * @param int $talent_user_id Target user ID.
	 * @return array{lifecycle_stage: string, invited_at?: string, claimed_at?: string, token_expiry?: string}
	 */
	public function get_onboarding_status( int $talent_user_id ): array;

	/**
	 * Create a short-lived, non-claim share link for a talent profile.
	 *
	 * Returns: [ 'share_url' => string, 'expiry' => string ]
	 *
	 * @param int $talent_user_id Target user ID.
	 * @return array
	 */
	public function create_share_link( int $talent_user_id ): array;
}
