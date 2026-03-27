<?php
/**
 * Commerce integration adapter contract.
 */

defined( 'ABSPATH' ) || exit;

interface EcomCine_Commerce_Adapter {
	/**
	 * Stable adapter identifier.
	 */
	public function id();

	/**
	 * Whether commerce stack APIs are available.
	 */
	public function is_available();

	/**
	 * Resolve a vendor public URL when available.
	 */
	public function get_vendor_store_url( $vendor_id );
}
