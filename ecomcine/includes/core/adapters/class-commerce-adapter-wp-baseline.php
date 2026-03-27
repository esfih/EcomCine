<?php
/**
 * Baseline commerce adapter for plain WordPress mode.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Commerce_Adapter_WP_Baseline implements EcomCine_Commerce_Adapter {
	public function id() {
		return 'wp-baseline';
	}

	public function is_available() {
		return false;
	}

	public function get_vendor_store_url( $vendor_id ) {
		return '';
	}
}
