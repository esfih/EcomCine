<?php
/**
 * Plugin Name: EcomCine Debug Logger
 * Description: Structured debug logging for EcomCine. Logs to wp-content/logs/ecomcine-debug.log.
 *              Activate per-environment — never ships in production release zips.
 * Version:     1.0.0
 * Author:      EcomCine
 *
 * Activation:
 *   Option A — wp-config.php:   define( 'ECOMCINE_DEBUG', true );
 *   Option B — file toggle:     touch wp-content/ecomcine-debug.txt
 *
 * Log location: wp-content/logs/ecomcine-debug.log  (one JSON line per entry)
 *
 * Helper function available anywhere after WP loads:
 *   ec_log( 'context', 'message', [ 'key' => 'value' ] );
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

// ──────────────────────────────────────────────────────────────
// Constants
// ──────────────────────────────────────────────────────────────

define( 'EC_DEBUG_LOG_DIR',  WP_CONTENT_DIR . '/logs' );
define( 'EC_DEBUG_LOG_FILE', EC_DEBUG_LOG_DIR . '/ecomcine-debug.log' );

// ──────────────────────────────────────────────────────────────
// Toggle detection
// ──────────────────────────────────────────────────────────────

/**
 * Whether EcomCine debug logging is currently enabled.
 *
 * @return bool
 */
function ec_debug_enabled(): bool {
	static $enabled = null;
	if ( $enabled !== null ) {
		return $enabled;
	}
	$enabled = ( defined( 'ECOMCINE_DEBUG' ) && ECOMCINE_DEBUG )
		|| file_exists( WP_CONTENT_DIR . '/ecomcine-debug.txt' );
	return $enabled;
}

// ──────────────────────────────────────────────────────────────
// Core log writer
// ──────────────────────────────────────────────────────────────

/**
 * Write a structured log entry.
 *
 * @param string $context  Short namespace: 'rest', 'player', 'vendor', 'boot', etc.
 * @param string $message  Human-readable message.
 * @param array  $data     Optional key/value payload (no secrets, no passwords).
 */
function ec_log( string $context, string $message, array $data = [] ): void {
	if ( ! ec_debug_enabled() ) {
		return;
	}

	// Create log directory if needed (uses wp_mkdir_p when available, falls back to mkdir).
	if ( ! is_dir( EC_DEBUG_LOG_DIR ) ) {
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( EC_DEBUG_LOG_DIR );
		} else {
			mkdir( EC_DEBUG_LOG_DIR, 0755, true );
		}
	}

	$entry = [
		'ts'  => gmdate( 'c' ),
		'ctx' => $context,
		'msg' => $message,
	];

	if ( ! empty( $data ) ) {
		$entry['data'] = $data;
	}

	// Request context (safe for server-side log only).
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		$entry['url']    = $_SERVER['REQUEST_URI'] ?? '';
		$entry['method'] = $_SERVER['REQUEST_METHOD'] ?? '';
	} else {
		$entry['url'] = 'cli';
	}

	$line = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( EC_DEBUG_LOG_FILE, $line, FILE_APPEND | LOCK_EX );
}

// ──────────────────────────────────────────────────────────────
// PHP error / exception capture (non-fatal)
// ──────────────────────────────────────────────────────────────

if ( ec_debug_enabled() ) {

	// Capture PHP warnings, notices, and deprecated calls.
	set_error_handler( function( int $errno, string $errstr, string $errfile, int $errline ): bool {
	// Suppress noise: hosting environments (e.g. LiteSpeed/N0C) pre-define WP_DEBUG
	// constants before wp-config.php runs, generating harmless redefinition warnings.
	if ( str_contains( $errstr, 'already defined' ) ) {
		return false;
	}

	// Only capture if error reporting would normally surface this.
		if ( ! ( error_reporting() & $errno ) ) {
			return false; // Let PHP handle it.
		}

		static $type_map = [
			E_WARNING         => 'E_WARNING',
			E_NOTICE          => 'E_NOTICE',
			E_DEPRECATED      => 'E_DEPRECATED',
			E_USER_ERROR      => 'E_USER_ERROR',
			E_USER_WARNING    => 'E_USER_WARNING',
			E_USER_NOTICE     => 'E_USER_NOTICE',
			E_USER_DEPRECATED => 'E_USER_DEPRECATED',
		];
		$type = $type_map[ $errno ] ?? "E_{$errno}";

		ec_log( 'php', $errstr, [
			'type' => $type,
			'file' => $errfile,
			'line' => $errline,
		] );

		return false; // Let WP's own handler also run.
	} );

	// Capture uncaught exceptions.
	set_exception_handler( function( Throwable $e ): void {
		ec_log( 'exception', $e->getMessage(), [
			'class' => get_class( $e ),
			'file'  => $e->getFile(),
			'line'  => $e->getLine(),
			'trace' => array_slice(
				array_map(
					fn( $f ) => ( $f['file'] ?? '?' ) . ':' . ( $f['line'] ?? '?' ),
					$e->getTrace()
				),
				0,
				8
			),
		] );
	} );

	// Capture fatal errors via shutdown.
	register_shutdown_function( function(): void {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
			ec_log( 'fatal', $error['message'], [
				'type' => $error['type'],
				'file' => $error['file'],
				'line' => $error['line'],
			] );
		}
	} );

}

// ──────────────────────────────────────────────────────────────
// WP_Error hook — capture REST API errors and WP_Error objects
// ──────────────────────────────────────────────────────────────

add_filter( 'rest_pre_serve_request', function( $served, WP_HTTP_Response $result ) {
	if ( ! ec_debug_enabled() ) {
		return $served;
	}
	$status = $result->get_status();
	if ( $status >= 400 ) {
		$data = $result->get_data();
		ec_log( 'rest', 'REST response ' . $status, [
			'status' => $status,
			'data'   => $data,
			'url'    => $_SERVER['REQUEST_URI'] ?? '',
		] );
	}
	return $served;
}, 10, 2 );

// ──────────────────────────────────────────────────────────────
// WP hook: log slow DB queries (only when SAVEQUERIES is on)
// ──────────────────────────────────────────────────────────────

add_action( 'shutdown', function(): void {
	if ( ! ec_debug_enabled() ) {
		return;
	}
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}
	global $wpdb;
	if ( empty( $wpdb->queries ) ) {
		return;
	}
	$slow = array_filter( $wpdb->queries, fn( $q ) => (float) $q[1] > 0.05 );
	foreach ( $slow as $q ) {
		ec_log( 'slow-query', round( (float) $q[1] * 1000 ) . 'ms', [
			'sql'     => substr( $q[0], 0, 500 ),
			'caller'  => $q[2],
		] );
	}
}, 9999 );
