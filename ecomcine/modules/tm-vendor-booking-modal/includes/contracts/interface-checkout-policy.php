<?php
/**
 * Contract: Checkout field and policy customizations (tvbm-004).
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TVBM_Checkout_Policy {
	/**
	 * Return the checkout field policy.
	 *
	 * @return array{required_fields: string[], removed_fields: string[], show_coupon: bool, show_order_notes: bool}
	 */
	public function checkout_field_policy(): array;

	/**
	 * Return the privacy/terms policy for the checkout modal.
	 *
	 * @return array{privacy_text: string, terms_text: string}
	 */
	public function privacy_terms_policy(): array;

	/**
	 * Flag an order as modal-originated.
	 *
	 * @param mixed $order WC_Order or tm_order post ID.
	 * @return void
	 */
	public function set_order_modal_flag( $order ): void;
}
