<?php
/**
 * Contract: Cart and checkout handler (tvbm-003).
 *
 * Adds the booking to the cart and processes checkout from within the modal.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TVBM_Checkout_Handler {
	/**
	 * Add a bookable product to the cart with booking data.
	 *
	 * Returns: [ 'success' => true, 'cart_key' => string ]
	 *   or:    [ 'success' => false, 'error' => string ]
	 *
	 * @param int   $product_id   Bookable product / offer ID.
	 * @param array $booking_data date, duration, resource_id, etc.
	 * @return array{success: bool, cart_key?: string, error?: string}
	 */
	public function add_to_cart( int $product_id, array $booking_data ): array;

	/**
	 * Process checkout from within the modal.
	 *
	 * Returns: [ 'success' => true, 'order_id' => int, 'redirect_url' => string ]
	 *   or:    [ 'success' => false, 'errors' => string[] ]
	 *
	 * @param array  $billing_data first_name, last_name, email, phone.
	 * @param string $nonce        Nonce for 'woocommerce-process-checkout' or 'tm_vendor_booking_modal'.
	 * @return array{success: bool, order_id?: int, redirect_url?: string, errors?: array}
	 */
	public function process_checkout( array $billing_data, string $nonce ): array;
}
