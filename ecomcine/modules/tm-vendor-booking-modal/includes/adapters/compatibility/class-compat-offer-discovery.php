<?php
/**
 * Compatibility adapter: offer discovery via WooCommerce product taxonomy.
 *
 * Queries for a published WC product with product_type=booking and
 * product_cat matching $offer_type, authored by $vendor_id.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_Compat_Offer_Discovery implements TVBM_Offer_Discovery {

	public function discover_booking_offer( int $vendor_id, string $offer_type = 'booking' ): array {
		if ( $vendor_id <= 0 ) {
			return [ 'product_id' => 0 ];
		}

		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'author'         => $vendor_id,
			'tax_query'      => [
				'relation' => 'AND',
				[
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => [ 'booking' ],
				],
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => [ $offer_type ],
				],
			],
		] );

		$product_id = ( $query->have_posts() ) ? (int) $query->posts[0]->ID : 0;

		return [ 'product_id' => $product_id ];
	}
}
