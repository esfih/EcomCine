<?php
/**
 * Parity check: tm-account-panel adapter layer.
 *
 * Verifies that both the compatibility and default-WP adapters produce
 * equivalent results for the four feature contracts.
 *
 * Run via WP-CLI:
 *   TAP_ADAPTER=default-wp wp eval-file includes/parity/class-parity-check.php
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Parity_Check {

	/** @var array{pass: int, fail: int, results: array} */
	private array $report = [ 'pass' => 0, 'fail' => 0, 'results' => [] ];

	// -----------------------------------------------------------------------
	// Entry point
	// -----------------------------------------------------------------------

	public function run(): array {
		$this->check_context_resolver_returns_bool();
		$this->check_login_rejects_invalid_nonce();
		$this->check_login_rejects_empty_credentials();
		$this->check_onboarding_invalid_user_returns_error();
		$this->check_claim_invalid_token_returns_error();
		$this->check_get_orders_returns_array();
		$this->check_get_bookings_returns_array();
		$this->check_get_ip_assets_returns_structure();

		return $this->report;
	}

	// -----------------------------------------------------------------------
	// Individual checks
	// -----------------------------------------------------------------------

	private function check_context_resolver_returns_bool(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$resolver = $this->make_context_resolver( $mode );
			$result   = $resolver->is_eligible_page();
			$this->assert(
				"context_resolver[{$mode}] is_eligible_page() returns bool",
				is_bool( $result )
			);
		}
	}

	private function check_login_rejects_invalid_nonce(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$handler = $this->make_login_handler( $mode );
			$result  = $handler->login( 'user@example.com', 'password', 'bad-nonce' );
			$this->assert(
				"login_handler[{$mode}] rejects invalid nonce → success=false",
				isset( $result['success'] ) && false === $result['success']
			);
		}
	}

	private function check_login_rejects_empty_credentials(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$handler = $this->make_login_handler( $mode );
			$nonce   = wp_create_nonce( 'tm_account_login' );
			$result  = $handler->login( '', '', $nonce );
			$this->assert(
				"login_handler[{$mode}] rejects empty credentials → success=false",
				isset( $result['success'] ) && false === $result['success']
			);
		}
	}

	private function check_onboarding_invalid_user_returns_error(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$provider = $this->make_onboarding_provider( $mode );
			$nonce    = wp_create_nonce( 'tm_account_admin' );
			$result   = $provider->create_invitation( 9999999, $nonce );
			$this->assert(
				"onboarding_provider[{$mode}] create_invitation with invalid user → error key present or forbidden",
				isset( $result['error'] ) && in_array( $result['error'], [ 'invalid_user', 'forbidden', 'invalid_nonce' ], true )
			);
		}
	}

	private function check_claim_invalid_token_returns_error(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$provider = $this->make_onboarding_provider( $mode );
			$result   = $provider->claim_invitation( 'nonexistent-token-xyz' );
			$this->assert(
				"onboarding_provider[{$mode}] claim_invitation with bad token → error='invalid_token'",
				isset( $result['error'] ) && 'invalid_token' === $result['error']
			);
		}
	}

	private function check_get_orders_returns_array(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$provider = $this->make_account_data_provider( $mode );
			$result   = $provider->get_orders( 1 );
			$this->assert(
				"account_data_provider[{$mode}] get_orders() returns array with 'orders' key",
				is_array( $result ) && array_key_exists( 'orders', $result ) && is_array( $result['orders'] )
			);
		}
	}

	private function check_get_bookings_returns_array(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$provider = $this->make_account_data_provider( $mode );
			$result   = $provider->get_bookings( 1 );
			$this->assert(
				"account_data_provider[{$mode}] get_bookings() returns array with 'bookings' key",
				is_array( $result ) && array_key_exists( 'bookings', $result ) && is_array( $result['bookings'] )
			);
		}
	}

	private function check_get_ip_assets_returns_structure(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$provider = $this->make_account_data_provider( $mode );
			$result   = $provider->get_ip_assets( 1 );
			$this->assert(
				"account_data_provider[{$mode}] get_ip_assets() returns array with 'invitations' and 'shares' keys",
				is_array( $result )
					&& array_key_exists( 'invitations', $result )
					&& array_key_exists( 'shares', $result )
					&& is_array( $result['invitations'] )
					&& is_array( $result['shares'] )
			);
		}
	}

	// -----------------------------------------------------------------------
	// Factory helpers
	// -----------------------------------------------------------------------

	private function make_context_resolver( string $mode ): TAP_Page_Context_Resolver {
		return 'default-wp' === $mode
			? new TAP_WP_Page_Context_Resolver()
			: new TAP_Compat_Page_Context_Resolver();
	}

	private function make_login_handler( string $mode ): TAP_Login_Handler {
		return 'default-wp' === $mode
			? new TAP_WP_Login_Handler()
			: new TAP_Compat_Login_Handler();
	}

	private function make_onboarding_provider( string $mode ): TAP_Onboarding_Provider {
		return 'default-wp' === $mode
			? new TAP_WP_Onboarding_Provider()
			: new TAP_Compat_Onboarding_Provider();
	}

	private function make_account_data_provider( string $mode ): TAP_Account_Data_Provider {
		return 'default-wp' === $mode
			? new TAP_WP_Account_Data_Provider()
			: new TAP_Compat_Account_Data_Provider();
	}

	// -----------------------------------------------------------------------
	// Assertion helper
	// -----------------------------------------------------------------------

	private function assert( string $label, bool $pass ): void {
		$this->report['results'][] = [ 'label' => $label, 'pass' => $pass ];
		if ( $pass ) {
			$this->report['pass']++;
		} else {
			$this->report['fail']++;
		}
	}
}
