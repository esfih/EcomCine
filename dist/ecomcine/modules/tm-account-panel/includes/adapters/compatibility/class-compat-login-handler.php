<?php
/**
 * Compatibility adapter: login handler via admin-ajax.php + wp_signon.
 *
 * Implements the tap-002 login contract using WP core authentication functions.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Compat_Login_Handler implements TAP_Login_Handler {

	public function login( string $identifier, string $password, string $nonce ): array {
		if ( ! wp_verify_nonce( $nonce, 'tm_account_login' ) ) {
			return [ 'success' => false, 'message' => 'invalid_nonce' ];
		}

		$identifier = trim( sanitize_text_field( $identifier ) );
		if ( '' === $identifier || '' === $password ) {
			return [ 'success' => false, 'message' => 'empty_credentials' ];
		}

		// Resolve email → user_login.
		$user_login = $identifier;
		if ( str_contains( $identifier, '@' ) ) {
			$user = get_user_by( 'email', $identifier );
			if ( ! $user ) {
				return [ 'success' => false, 'message' => 'invalid_username' ];
			}
			$user_login = $user->user_login;
		}

		$result = wp_signon(
			[
				'user_login'    => $user_login,
				'user_password' => $password,
				'remember'      => true,
			],
			is_ssl()
		);

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'message' => $result->get_error_message() ];
		}

		wp_set_current_user( $result->ID );
		wp_set_auth_cookie( $result->ID, true );

		return [ 'success' => true ];
	}
}
