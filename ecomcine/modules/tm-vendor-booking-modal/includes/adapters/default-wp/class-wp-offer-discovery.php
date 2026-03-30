<?php
/**
 * Default-WP adapter: offer discovery via tm_offer CPT meta.
 *
 * No WooCommerce taxonomy needed — offer type stored as CPT post meta.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_WP_Offer_Discovery implements TVBM_Offer_Discovery {

	public function discover_booking_offer( int $vendor_id, string $offer_type = 'booking' ): array {
		if ( $vendor_id <= 0 ) {
			return [ 'product_id' => 0, 'offer_id' => 0 ];
		}

		$posts = get_posts( [
			'post_type'   => TVBM_WP_Offer_CPT::POST_TYPE,
			'post_status' => 'publish',
			'author'      => $vendor_id,
			'numberposts' => 1,
			'meta_query'  => [
				[
					'key'   => '_tm_offer_type',
					'value' => sanitize_text_field( $offer_type ),
				],
			],
		] );

		if ( empty( $posts ) ) {
			return [ 'product_id' => 0, 'offer_id' => 0 ];
		}

		$offer_id   = (int) $posts[0]->ID;
		$product_id = (int) get_post_meta( $offer_id, '_tm_src_wc_product_id', true );

		return [ 'product_id' => $product_id, 'offer_id' => $offer_id ];
	}
}
