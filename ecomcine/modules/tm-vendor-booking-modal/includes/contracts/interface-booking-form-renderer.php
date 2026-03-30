<?php
/**
 * Contract: Booking form renderer (tvbm-002).
 *
 * Handles booking form rendering and asset loading for the modal.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TVBM_Booking_Form_Renderer {
	/**
	 * Render the booking form HTML for injection into the modal.
	 *
	 * Returns: [ 'html' => string ]  or  [ 'error' => string ]
	 *
	 * @param int $product_id Bookable product / offer ID.
	 * @return array{html?: string, error?: string}
	 */
	public function render_booking_form( int $product_id ): array;

	/**
	 * Enqueue all scripts and styles required for the booking form to work.
	 *
	 * @param int $vendor_id  Vendor user ID.
	 * @param int $product_id Bookable product ID.
	 * @return void
	 */
	public function load_booking_assets( int $vendor_id, int $product_id ): void;

	/**
	 * Render the modal trigger button HTML (suitable for wp_footer).
	 *
	 * @param int $vendor_id  Vendor user ID.
	 * @param int $product_id Bookable product ID.
	 * @return string
	 */
	public function render_modal_trigger( int $vendor_id, int $product_id ): string;
}
