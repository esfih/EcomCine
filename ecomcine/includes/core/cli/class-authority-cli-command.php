<?php
/**
 * WP-CLI control surface for Wave 1 authority flags.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) ) {
	class EcomCine_Authority_CLI_Command extends WP_CLI_Command {
		/**
		 * Show current Wave 1 authority states.
		 *
		 * @subcommand list
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine authority list
		 */
		public function list_( array $args, array $assoc_args ): void {
			$state = EcomCine_Wave1_Authority::snapshot();
			WP_CLI\Utils\format_items(
				'table',
				array(
					array( 'surface' => 'route', 'state' => $state['route'] ),
					array( 'surface' => 'listing', 'state' => $state['listing'] ),
					array( 'surface' => 'query', 'state' => $state['query'] ),
					array( 'surface' => 'observe_only', 'state' => $state['observe_only'] ? 'on' : 'off' ),
				),
				array( 'surface', 'state' )
			);
		}

		/**
		 * Set a Wave 1 authority state.
		 *
		 * ## OPTIONS
		 *
		 * <surface>
		 * : One of: route, listing, query.
		 *
		 * <state>
		 * : One of: legacy, shadow, core.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine authority set route shadow
		 */
		public function set( array $args, array $assoc_args ): void {
			list( $surface, $state ) = $args;

			if ( ! EcomCine_Wave1_Authority::set_state( (string) $surface, (string) $state ) ) {
				WP_CLI::error( 'Invalid surface or state. Use: route|listing|query and legacy|shadow|core.' );
			}

			WP_CLI::success( sprintf( 'Set %s authority to %s.', $surface, $state ) );
		}

		/**
		 * Set observe-only mode.
		 *
		 * ## OPTIONS
		 *
		 * <state>
		 * : on or off.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine authority observe on
		 */
		public function observe( array $args, array $assoc_args ): void {
			$raw_state = isset( $args[0] ) ? sanitize_key( (string) $args[0] ) : '';
			if ( ! in_array( $raw_state, array( 'on', 'off' ), true ) ) {
				WP_CLI::error( 'Observe-only state must be on or off.' );
			}

			EcomCine_Wave1_Authority::set_observe_only( 'on' === $raw_state );
			WP_CLI::success( sprintf( 'Observe-only mode is now %s.', $raw_state ) );
		}
	}

	WP_CLI::add_command( 'ecomcine authority', 'EcomCine_Authority_CLI_Command' );
}