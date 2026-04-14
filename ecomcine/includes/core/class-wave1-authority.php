<?php
/**
 * Wave 1 authority state registry.
 *
 * Keeps route, listing, and query authority flags small and explicit so
 * rollout and rollback remain precise.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Wave1_Authority {
	const ROUTE_OPTION        = 'ecomcine_core_route_authority';
	const LISTING_OPTION      = 'ecomcine_core_listing_authority';
	const QUERY_OPTION        = 'ecomcine_core_query_authority';
	const OBSERVE_ONLY_OPTION = 'ecomcine_core_wave1_observe_only';
	const SHADOW_LOG_OPTION   = 'ecomcine_wave1_shadow_log';
	const SHADOW_LOG_MAX      = 500;

	const STATE_LEGACY = 'legacy';
	const STATE_SHADOW = 'shadow';
	const STATE_CORE   = 'core';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 1 );
	}

	public static function ensure_defaults(): void {
		foreach ( self::option_defaults() as $option_name => $default_value ) {
			$current_value = get_option( $option_name, null );
			if ( null === $current_value ) {
				update_option( $option_name, $default_value, false );
			}
		}
	}

	/**
	 * @return array<string,string>
	 */
	private static function option_defaults(): array {
		return array(
			self::ROUTE_OPTION   => self::STATE_LEGACY,
			self::LISTING_OPTION => self::STATE_LEGACY,
			self::QUERY_OPTION   => self::STATE_LEGACY,
		);
	}

	/**
	 * @return string[]
	 */
	public static function valid_states(): array {
		return array(
			self::STATE_LEGACY,
			self::STATE_SHADOW,
			self::STATE_CORE,
		);
	}

	public static function get_route_state(): string {
		return self::get_state( self::ROUTE_OPTION );
	}

	public static function get_listing_state(): string {
		return self::get_state( self::LISTING_OPTION );
	}

	public static function get_query_state(): string {
		return self::get_state( self::QUERY_OPTION );
	}

	public static function is_route_core(): bool {
		return self::STATE_CORE === self::get_route_state();
	}

	public static function is_listing_core(): bool {
		return self::STATE_CORE === self::get_listing_state();
	}

	public static function is_query_core(): bool {
		return self::STATE_CORE === self::get_query_state();
	}

	public static function is_observe_only(): bool {
		return (bool) get_option( self::OBSERVE_ONLY_OPTION, false );
	}

	/**
	 * @return string[]
	 */
	public static function valid_surfaces(): array {
		return array( 'route', 'listing', 'query' );
	}

	public static function set_state( string $surface, string $state ): bool {
		$surface = sanitize_key( $surface );
		$state   = sanitize_key( $state );

		if ( ! in_array( $surface, self::valid_surfaces(), true ) ) {
			return false;
		}

		if ( ! in_array( $state, self::valid_states(), true ) ) {
			return false;
		}

		if ( self::get_state_for_surface( $surface ) === $state ) {
			return true;
		}

		$result = update_option( self::get_surface_option_name( $surface ), $state, false );

		// Clear stale shadow log whenever a flag changes.
		if ( $result ) {
			self::clear_shadow_log();
		}

		return $result;
	}

	public static function set_observe_only( bool $enabled ): bool {
		$current = self::is_observe_only();
		if ( $current === $enabled ) {
			return true;
		}

		return update_option( self::OBSERVE_ONLY_OPTION, $enabled ? '1' : '0', false );
	}

	public static function get_state_for_surface( string $surface ): string {
		$surface = sanitize_key( $surface );
		if ( ! in_array( $surface, self::valid_surfaces(), true ) ) {
			return self::STATE_LEGACY;
		}

		return self::get_state( self::get_surface_option_name( $surface ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function snapshot(): array {
		return array(
			'route'        => self::get_route_state(),
			'listing'      => self::get_listing_state(),
			'query'        => self::get_query_state(),
			'observe_only' => self::is_observe_only(),
		);
	}

	private static function get_state( string $option_name ): string {
		$state = get_option( $option_name, self::STATE_LEGACY );
		if ( ! is_string( $state ) || ! in_array( $state, self::valid_states(), true ) ) {
			return self::STATE_LEGACY;
		}

		return $state;
	}

	private static function get_surface_option_name( string $surface ): string {
		switch ( $surface ) {
			case 'route':
				return self::ROUTE_OPTION;
			case 'listing':
				return self::LISTING_OPTION;
			case 'query':
			default:
				return self::QUERY_OPTION;
		}
	}

	// ── Shadow log ────────────────────────────────────────────────────────────

	/**
	 * Append one comparison entry to the shadow log.
	 *
	 * @param string $subsystem  One of: route, listing, query.
	 * @param string $input      Human-readable description of the input (e.g. "user_id=7").
	 * @param mixed  $legacy     Legacy result (scalar or array — will be JSON-encoded).
	 * @param mixed  $core       Core result (scalar or array).
	 */
	public static function shadow_log( string $subsystem, string $input, $legacy, $core ): void {
		$match = ( wp_json_encode( $legacy ) === wp_json_encode( $core ) ) ? 'match' : 'mismatch';

		$entry = array(
			'ts'        => time(),
			'subsystem' => sanitize_key( $subsystem ),
			'input'     => substr( (string) $input, 0, 256 ),
			'legacy'    => $legacy,
			'core'      => $core,
			'match'     => $match,
		);

		$log = self::get_shadow_log();
		array_unshift( $log, $entry );

		if ( count( $log ) > self::SHADOW_LOG_MAX ) {
			$log = array_slice( $log, 0, self::SHADOW_LOG_MAX );
		}

		update_option( self::SHADOW_LOG_OPTION, wp_json_encode( $log ), false );
	}

	/**
	 * Return the current shadow log as an array of entries, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_shadow_log(): array {
		$raw = get_option( self::SHADOW_LOG_OPTION, '[]' );
		if ( ! is_string( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Erase the shadow log.
	 */
	public static function clear_shadow_log(): void {
		update_option( self::SHADOW_LOG_OPTION, '[]', false );
	}
}