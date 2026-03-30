<?php
/**
 * Default-WP CPT: tm_order — replaces WooCommerce orders for default-WP mode.
 *
 * Each tm_order post represents one booking transaction:
 *  - post_type   = 'tm_order'
 *  - post_author = customer user_id
 *  - post_status = 'publish' (pending) | 'private' (processing) | 'draft' (completed)
 *  - meta: _tm_order_status, _tm_order_total, _tm_vendor_id, _tm_modal_checkout
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Order_CPT {

	const POST_TYPE = 'tm_order';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => 'TM Orders',
				'public'          => false,
				'show_ui'         => false,
				'supports'        => [ 'author', 'title', 'custom-fields' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			]
		);
	}
}
