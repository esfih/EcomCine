<?php
/**
 * Compatibility adapter: checkout handler via WooCommerce cart + checkout.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_Compat_Checkout_Handler implements TVBM_Checkout_Handler {

	public function add_to_cart( int $product_id, array $booking_data ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return [ 'success' => false, 'error' => 'woocommerce_unavailable' ];
		}
		if ( $product_id <= 0 ) {
			return [ 'success' => false, 'error' => 'invalid_product' ];
		}

		$cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, [], $booking_data );

		if ( ! $cart_key ) {
			return [ 'success' => false, 'error' => 'add_to_cart_failed' ];
		}

		return [ 'success' => true, 'cart_key' => $cart_key ];
	}

	public function process_checkout( array $billing_data, string $nonce ): array {
		if ( ! wp_verify_nonce( $nonce, 'tm_vendor_booking_modal' )
			&& ! wp_verify_nonce( $nonce, 'woocommerce-process-checkout' ) ) {
			return [ 'success' => false, 'errors' => [ 'invalid_nonce' ] ];
		}

		if ( ! function_exists( 'WC' ) || ! WC()->checkout() ) {
			return [ 'success' => false, 'errors' => [ 'woocommerce_unavailable' ] ];
		}

		// Inject billing data into $_POST for WC checkout.
		$allowed = [ 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone' ];
		foreach ( $allowed as $field ) {
			if ( isset( $billing_data[ $field ] ) ) {
				$_POST[ $field ] = sanitize_text_field( $billing_data[ $field ] );
			}
		}
		$_POST['_wpnonce'] = $nonce;

		// Use WC_Checkout to create the order.
		$checkout = WC()->checkout();
		$errors   = new WP_Error();
		$data     = $checkout->get_posted_data();
		$checkout->validate_checkout( $data, $errors );

		if ( $errors->has_errors() ) {
			return [ 'success' => false, 'errors' => $errors->get_error_messages() ];
		}

		$order_id = $checkout->create_order( $data );
		if ( is_wp_error( $order_id ) ) {
			return [ 'success' => false, 'errors' => $order_id->get_error_messages() ];
		}

		$order        = wc_get_order( $order_id );
		$redirect_url = $order ? $order->get_checkout_order_received_url() : wc_get_checkout_url();

		return [
			'success'      => true,
			'order_id'     => $order_id,
			'redirect_url' => $redirect_url,
		];
	}
}
