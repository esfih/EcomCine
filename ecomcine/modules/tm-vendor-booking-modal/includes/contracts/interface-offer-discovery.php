<?php
/**
 * Contract: Booking offer discovery (tvbm-001).
 *
 * Resolves the bookable offer for a given vendor.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TVBM_Offer_Discovery {
	/**
	 * Discover the primary bookable offer for a vendor.
	 *
	 * Returns: [ 'product_id' => int ]  (0 if no offer found)
	 *
	 * @param int    $vendor_id  WP user ID of the vendor.
	 * @param string $offer_type Offer category slug (default: 'booking').
	 * @return array{product_id: int}
	 */
	public function discover_booking_offer( int $vendor_id, string $offer_type = 'booking' ): array;
}
