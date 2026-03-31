<?php
/**
 * Default-WP adapter: checkout handler via tm_booking + tm_order CPTs.
 *
 * No WooCommerce cart or checkout dependencies.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_WP_Checkout_Handler implements TVBM_Checkout_Handler {

	public function add_to_cart( int $product_id, array $booking_data ): array {
		if ( $product_id <= 0 ) {
			return [ 'success' => false, 'error' => 'invalid_product' ];
		}

		// Validate the product is a tm_offer.
		$post = get_post( $product_id );
		if ( ! $post || TVBM_WP_Offer_CPT::POST_TYPE !== $post->post_type ) {
			return [ 'success' => false, 'error' => 'invalid_product' ];
		}

		// Create a pending tm_booking CPT as the "cart" representation.
		$booking_date = sanitize_text_field( $booking_data['booking_date'] ?? '' );
		$user_id      = get_current_user_id();

		$booking_id = wp_insert_post( [
			'post_type'   => 'tm_booking',
			'post_status' => 'draft',  // Draft = "in cart".
			'post_author' => $user_id,
			'post_title'  => 'Booking draft',
		], true );

		if ( is_wp_error( $booking_id ) ) {
			return [ 'success' => false, 'error' => 'booking_create_failed' ];
		}

		update_post_meta( $booking_id, '_tm_offer_id', $product_id );
		update_post_meta( $booking_id, '_tm_booking_date', $booking_date );
		update_post_meta( $booking_id, '_tm_booking_status', 'pending' );
		update_post_meta( $booking_id, '_tm_vendor_id', (int) $post->post_author );

		return [ 'success' => true, 'cart_key' => (string) $booking_id ];
	}

	public function process_checkout( array $billing_data, string $nonce ): array {
		if ( ! wp_verify_nonce( $nonce, 'tm_vendor_booking_modal' ) ) {
			return [ 'success' => false, 'errors' => [ 'invalid_nonce' ] ];
		}

		// Validate required fields.
		$required = [ 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone' ];
		$errors   = [];
		foreach ( $required as $field ) {
			if ( empty( $billing_data[ $field ] ) ) {
				$errors[] = "{$field}_required";
			}
		}
		if ( ! empty( $errors ) ) {
			return [ 'success' => false, 'errors' => $errors ];
		}
		if ( ! is_email( $billing_data['billing_email'] ) ) {
			return [ 'success' => false, 'errors' => [ 'billing_email_invalid' ] ];
		}

		// Retrieve the booking draft (cart_key passed via booking_id in billing_data).
		$booking_id = isset( $billing_data['booking_id'] ) ? (int) $billing_data['booking_id'] : 0;

		$user_id  = get_current_user_id();
		$order_id = wp_insert_post( [
			'post_type'   => 'tm_order',
			'post_status' => 'publish',
			'post_author' => $user_id,
			'post_title'  => 'TM Order',
		], true );

		if ( is_wp_error( $order_id ) ) {
			return [ 'success' => false, 'errors' => [ 'order_create_failed' ] ];
		}

		update_post_meta( $order_id, '_tm_order_status', 'pending' );
		update_post_meta( $order_id, '_tm_modal_checkout', '1' );
		update_post_meta( $order_id, '_tm_billing_first_name', sanitize_text_field( $billing_data['billing_first_name'] ) );
		update_post_meta( $order_id, '_tm_billing_last_name', sanitize_text_field( $billing_data['billing_last_name'] ) );
		update_post_meta( $order_id, '_tm_billing_email', sanitize_email( $billing_data['billing_email'] ) );
		update_post_meta( $order_id, '_tm_billing_phone', sanitize_text_field( $billing_data['billing_phone'] ) );

		// Link booking to order.
		if ( $booking_id ) {
			update_post_meta( $order_id, '_tm_booking_id', $booking_id );
			update_post_meta( $booking_id, '_tm_order_id', $order_id );
			wp_update_post( [ 'ID' => $booking_id, 'post_status' => 'publish' ] );
		}

		$redirect_url = add_query_arg( 'tm_order', $order_id, home_url( '/order-received/' ) );

		return [
			'success'      => true,
			'order_id'     => $order_id,
			'redirect_url' => $redirect_url,
		];
	}
}
