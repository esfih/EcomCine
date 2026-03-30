<?php
/**
 * Compatibility adapter: booking form renderer via WooCommerce Bookings.
 *
 * Uses WC_Booking_Form to generate the booking form HTML and enqueues
 * WC Bookings native scripts.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_Compat_Booking_Form_Renderer implements TVBM_Booking_Form_Renderer {

	public function render_booking_form( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [ 'error' => 'invalid_product' ];
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return [ 'error' => 'woocommerce_unavailable' ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'booking' ) ) {
			return [ 'error' => 'invalid_product' ];
		}

		if ( ! class_exists( 'WC_Booking_Form' ) ) {
			return [ 'error' => 'wc_bookings_unavailable' ];
		}

		ob_start();
		try {
			$form = new WC_Booking_Form( $product );
			$form->output();
		} catch ( \Throwable $e ) {
			ob_end_clean();
			return [ 'error' => 'render_failed' ];
		}
		$html = ob_get_clean();

		if ( ! $html ) {
			return [ 'error' => 'empty_form' ];
		}

		return [ 'html' => $html ];
	}

	public function load_booking_assets( int $vendor_id, int $product_id ): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// WC Bookings enqueues its own scripts when WC_Product_Booking is instantiated.
		// Triggering the standard WC scripts action is sufficient here for asset loading.
		do_action( 'woocommerce_booking_add_to_cart', $product_id );
	}

	public function render_modal_trigger( int $vendor_id, int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}
		return sprintf(
			'<div class="tm-booking-trigger-wrap"><button class="tm-booking-trigger" data-vendor="%d" data-product="%d" type="button">Book Now</button></div>',
			esc_attr( $vendor_id ),
			esc_attr( $product_id )
		);
	}
}
