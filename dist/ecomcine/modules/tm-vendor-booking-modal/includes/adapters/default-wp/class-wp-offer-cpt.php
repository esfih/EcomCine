<?php
/**
 * Default-WP CPT: tm_offer — replaces WooCommerce bookable products.
 *
 *  - post_type   = 'tm_offer'
 *  - post_status = 'publish'
 *  - post_author = vendor user_id
 *  - meta: _tm_offer_type ('half-day' etc.), _tm_offer_duration, _tm_offer_price
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_WP_Offer_CPT {

	const POST_TYPE = 'tm_offer';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => 'TM Offers',
				'public'          => false,
				'show_ui'         => false,
				'supports'        => [ 'author', 'title', 'editor', 'custom-fields' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			]
		);
	}
}
