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

	public function get_vendor_balance_html( $vendor_id ) {
		return '';
	}

	public function get_vendor_mrr( $vendor_id ) {
		return 0.0;
	}

	public function get_vendor_arr( $vendor_id ) {
		return 0.0;
	}

	public function format_price( $amount ) {
		return '$' . number_format( (float) $amount, 0 );
	}

	public function get_orders_table_html( $vendor_id ) {
		return '';
	}

	public function get_order_detail_html( $order_id ) {
		return '';
	}

	public function can_view_order( $order_id, $user_id ) {
		return false;
	}
}
