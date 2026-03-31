<?php
/**
 * Default-WP CPT: tm_booking — replaces WooCommerce Bookings.
 *
 *  - post_type   = 'tm_booking'
 *  - post_author = customer user_id
 *  - meta: _tm_offer_id, _tm_booking_date, _tm_booking_status, _tm_vendor_id
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Booking_CPT {

	const POST_TYPE = 'tm_booking';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => 'TM Bookings',
				'public'          => false,
				'show_ui'         => false,
				'supports'        => [ 'author', 'title', 'custom-fields' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			]
		);
	}
}
