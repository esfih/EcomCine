<?php
/**
 * Preferred commerce adapter for WooCommerce + Dokan stack.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Commerce_Adapter_WooDokan implements EcomCine_Commerce_Adapter {
	public function id() {
		return 'woo-dokan';
	}

	public function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'dokan_get_store_url' );
	}

	public function get_vendor_store_url( $vendor_id ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || ! function_exists( 'dokan_get_store_url' ) ) {
			return '';
		}

		return (string) dokan_get_store_url( $vendor_id );
	}
}
