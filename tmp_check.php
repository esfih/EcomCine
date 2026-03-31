<?php
/**
 * EcomCine Crash Catcher
 *
 * PURPOSE: Capture the exact PHP fatal that crashes WordPress when EcomCine is activated.
 *
 * INSTALL (via FTP):
 *   1. Upload this file to: wp-content/mu-plugins/ecomcine-crash-catcher.php
 *      (create the mu-plugins folder if it does not exist)
 *   2. Try to activate EcomCine from WP Admin → Plugins.
 *   3. After the crash, use FTP to open: wp-content/ecomcine-crash.log
 *   4. Paste the contents of that log file here.
 *
 * CLEANUP: Delete this file and ecomcine-crash.log when diagnosis is done.
 */

defined( 'ABSPATH' ) || exit;

$_ecomcine_log = dirname( __DIR__ ) . '/ecomcine-crash.log';

// Write a "still alive" marker so you can confirm the MU plugin is running.
file_put_contents(
	$_ecomcine_log,
	date( '[Y-m-d H:i:s] ' ) . 'MU plugin loaded. Regular plugins will load next.' . "\n",
	FILE_APPEND | LOCK_EX
);

register_shutdown_function( function() use ( $_ecomcine_log ) {
	$error = error_get_last();
	if ( ! $error ) {
		return;
	}

	$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];
	if ( ! in_array( $error['type'], $fatal_types, true ) ) {
		return;
	}

	$entry = date( '[Y-m-d H:i:s] ' ) . "FATAL CAUGHT\n"
		. 'Message : ' . $error['message'] . "\n"
		. 'File    : ' . $error['file'] . "\n"
		. 'Line    : ' . $error['line'] . "\n"
		. str_repeat( '-', 80 ) . "\n";

	file_put_contents( $_ecomcine_log, $entry, FILE_APPEND | LOCK_EX );
} );
