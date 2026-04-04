<?php
/**
 * TM Media Player Adapter Registry
 *
 * Returns the active TMP_Media_Source_Provider for the current runtime.
 * Selection logic:
 *   - If the `TMP_ADAPTER` constant is set to 'default-wp', use the WP CPT adapter.
 *   - If runtime_mode is `wp_cpt`, use the WP CPT adapter.
 *   - Otherwise use the compat adapter for legacy marketplace stacks.
 *
 * @package TM_Media_Player
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TMP_Adapter_Registry
 */
final class TMP_Adapter_Registry {

	/** @var TMP_Media_Source_Provider|null */
	private static ?TMP_Media_Source_Provider $instance = null;

	/**
	 * Return the singleton provider instance.
	 *
	 * @return TMP_Media_Source_Provider
	 */
	public static function get_provider(): TMP_Media_Source_Provider {
		if ( null === self::$instance ) {
			self::$instance = self::resolve_provider();
		}

		return self::$instance;
	}

	/**
	 * Override the active provider (used in tests and parity checks).
	 *
	 * @param TMP_Media_Source_Provider $provider
	 */
	public static function set_provider( TMP_Media_Source_Provider $provider ): void {
		self::$instance = $provider;
	}

	/**
	 * Reset to auto-resolved provider (used in tests).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Resolve which provider to use.
	 *
	 * @return TMP_Media_Source_Provider
	 */
	private static function resolve_provider(): TMP_Media_Source_Provider {
		// Explicit override via constant.
		if ( defined( 'TMP_ADAPTER' ) && 'default-wp' === TMP_ADAPTER ) {
			return new TMP_WP_Media_Source_Provider();
		}

		$runtime_mode = ( class_exists( 'EcomCine_Admin_Settings', false ) && method_exists( 'EcomCine_Admin_Settings', 'get_runtime_mode' ) )
			? (string) EcomCine_Admin_Settings::get_runtime_mode()
			: 'wp_cpt';
		if ( 'wp_cpt' === $runtime_mode ) {
			return new TMP_WP_Media_Source_Provider();
		}

		// Compat adapter for explicit legacy marketplace stacks.
		return new TMP_Compat_Media_Source_Provider();
	}
}
