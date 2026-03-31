<?php
/**
 * Default-WP adapter: checkout policy for default-WP mode.
 *
 * Same policy data as compat, but set_order_modal_flag targets tm_order post meta.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_WP_Checkout_Policy implements TVBM_Checkout_Policy {

	public function checkout_field_policy(): array {
		return [
			'required_fields' => [
				'billing_first_name',
				'billing_last_name',
				'billing_email',
				'billing_phone',
			],
			'removed_fields'  => [
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_company',
				'ship_to_different_address',
				'order_comments',
			],
			'show_coupon'      => false,
			'show_order_notes' => false,
		];
	}

	public function privacy_terms_policy(): array {
		$talent_terms = esc_url( home_url( '/talent-terms/' ) );
		$hirer_terms  = esc_url( home_url( '/hirer-terms/' ) );
		$privacy_text = sprintf(
			'By booking, you agree to our <a href="%s">Talent Terms</a> and <a href="%s">Hirer Terms</a>.',
			$talent_terms,
			$hirer_terms
		);

		return [
			'privacy_text' => $privacy_text,
			'terms_text'   => $privacy_text,
		];
	}

	public function set_order_modal_flag( $order ): void {
		// In default-WP mode, $order is a tm_order post ID (int).
		if ( is_int( $order ) && $order > 0 ) {
			update_post_meta( $order, '_tm_modal_checkout', '1' );
		}
	}
}
