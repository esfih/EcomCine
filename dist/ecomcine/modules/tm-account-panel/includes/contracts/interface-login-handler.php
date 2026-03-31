<?php
/**
 * Contract: AJAX login handler.
 *
 * Validates credentials and establishes a WP session.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TAP_Login_Handler {
	/**
	 * Authenticate a user by username/email + password.
	 *
	 * Returns an associative array:
	 *   On success: [ 'success' => true ]
	 *   On failure: [ 'success' => false, 'message' => string ]
	 *
	 * @param string $identifier Username or email address.
	 * @param string $password   Plain-text password.
	 * @param string $nonce      Nonce for 'tm_account_login'.
	 * @return array{success: bool, message?: string}
	 */
	public function login( string $identifier, string $password, string $nonce ): array;
}
