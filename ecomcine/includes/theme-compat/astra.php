<?php
/**
 * EcomCine Theme-Compat: Astra
 *
 * Translates the EcomCine global suppression flags into Astra-native filters.
 * Loaded only when the Astra theme is active (checked in ecomcine.php).
 *
 * Globals read (set by tm-media-player and/or theme templates):
 *   $GLOBALS['ecomcine_suppress_header'] — bool
 *   $GLOBALS['ecomcine_suppress_footer'] — bool
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'astra_header' ) ) {
	return;
}

add_filter( 'astra_header_display', function( $display ) {
	return ! empty( $GLOBALS['ecomcine_suppress_header'] ) ? false : $display;
}, 20 );

add_filter( 'astra_footer_display', function( $display ) {
	return ! empty( $GLOBALS['ecomcine_suppress_footer'] ) ? false : $display;
}, 20 );
