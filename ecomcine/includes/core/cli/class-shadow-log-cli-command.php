<?php
/**
 * WP-CLI access to the Wave 1 shadow comparison log.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Inspect or clear the Wave 1 shadow comparison log.
	 *
	 * @subcommand shadow-log
	 */
	class EcomCine_Shadow_Log_CLI_Command extends WP_CLI_Command {

		/**
		 * Dump shadow log entries.
		 *
		 * ## OPTIONS
		 *
		 * [--limit=<n>]
		 * : Maximum entries to show. Default: 50.
		 *
		 * [--subsystem=<subsystem>]
		 * : Filter to one subsystem: route, listing, query.
		 *
		 * [--mismatch]
		 * : Show only mismatch entries.
		 *
		 * [--format=<format>]
		 * : Output format: table, json, csv. Default: table.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine shadow-log dump
		 *     wp ecomcine shadow-log dump --mismatch
		 *     wp ecomcine shadow-log dump --subsystem=listing --format=json
		 */
		public function dump( array $args, array $assoc_args ): void {
			if ( ! class_exists( 'EcomCine_Wave1_Authority', false ) ) {
				WP_CLI::error( 'EcomCine_Wave1_Authority is not loaded.' );
			}

			$limit      = max( 1, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 50 ) );
			$subsystem  = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'subsystem', '' ) );
			$only_miss  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'mismatch', false );
			$format     = sanitize_key( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ) );

			$entries = EcomCine_Wave1_Authority::get_shadow_log();

			if ( '' !== $subsystem ) {
				$entries = array_values(
					array_filter(
						$entries,
						static function( array $e ) use ( $subsystem ): bool {
							return ( $e['subsystem'] ?? '' ) === $subsystem;
						}
					)
				);
			}

			if ( $only_miss ) {
				$entries = array_values(
					array_filter(
						$entries,
						static function( array $e ): bool {
							return ( $e['match'] ?? '' ) === 'mismatch';
						}
					)
				);
			}

			$entries = array_slice( $entries, 0, $limit );

			if ( empty( $entries ) ) {
				WP_CLI::line( 'Shadow log is empty (or no entries match your filters).' );

				return;
			}

			$rows = array_map(
				static function( array $e ): array {
					return array(
						'time'      => date( 'Y-m-d H:i:s', (int) ( $e['ts'] ?? 0 ) ),
						'subsystem' => (string) ( $e['subsystem'] ?? '' ),
						'input'     => (string) ( $e['input'] ?? '' ),
						'legacy'    => is_scalar( $e['legacy'] ?? null ) ? (string) $e['legacy'] : wp_json_encode( $e['legacy'] ),
						'core'      => is_scalar( $e['core'] ?? null ) ? (string) $e['core'] : wp_json_encode( $e['core'] ),
						'match'     => (string) ( $e['match'] ?? '' ),
					);
				},
				$entries
			);

			\WP_CLI\Utils\format_items(
				$format,
				$rows,
				array( 'time', 'subsystem', 'input', 'legacy', 'core', 'match' )
			);

			$total      = count( EcomCine_Wave1_Authority::get_shadow_log() );
			$mismatches = count(
				array_filter(
					$entries,
					static function( array $e ): bool {
						return ( $e['match'] ?? '' ) === 'mismatch';
					}
				)
			);

			WP_CLI::line( sprintf( 'Showing %d of %d total entries. Mismatches in view: %d.', count( $entries ), $total, $mismatches ) );
		}

		/**
		 * Clear the shadow log.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine shadow-log clear
		 */
		public function clear( array $args, array $assoc_args ): void {
			if ( ! class_exists( 'EcomCine_Wave1_Authority', false ) ) {
				WP_CLI::error( 'EcomCine_Wave1_Authority is not loaded.' );
			}

			EcomCine_Wave1_Authority::clear_shadow_log();
			WP_CLI::success( 'Shadow log cleared.' );
		}

		/**
		 * Show a summary count of match vs mismatch entries in the shadow log.
		 *
		 * ## EXAMPLES
		 *
		 *     wp ecomcine shadow-log stats
		 */
		public function stats( array $args, array $assoc_args ): void {
			if ( ! class_exists( 'EcomCine_Wave1_Authority', false ) ) {
				WP_CLI::error( 'EcomCine_Wave1_Authority is not loaded.' );
			}

			$entries    = EcomCine_Wave1_Authority::get_shadow_log();
			$total      = count( $entries );
			$mismatches = count(
				array_filter(
					$entries,
					static function( array $e ): bool {
						return ( $e['match'] ?? '' ) === 'mismatch';
					}
				)
			);
			$matches = $total - $mismatches;

			$rows = array(
				array( 'metric' => 'total',      'count' => (string) $total ),
				array( 'metric' => 'match',      'count' => (string) $matches ),
				array( 'metric' => 'mismatch',   'count' => (string) $mismatches ),
			);

			\WP_CLI\Utils\format_items( 'table', $rows, array( 'metric', 'count' ) );
		}
	}

	WP_CLI::add_command( 'ecomcine shadow-log', 'EcomCine_Shadow_Log_CLI_Command' );
}
