<?php
/**
 * Contract: Account data sections for the logged-in panel (tap-004).
 *
 * Provides orders, bookings, and IP assets (invitations/shares) for the
 * current user's account dashboard.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TAP_Account_Data_Provider {
	/**
	 * Retrieve WooCommerce orders (or equivalent) for a user.
	 *
	 * Returns: [ 'orders' => array of order records ]
	 * Each record: [ 'order_id', 'date', 'status', 'total', 'vendor_name' ]
	 *
	 * @param int $user_id WP user ID.
	 * @param int $page    1-based page number.
	 * @return array{orders: array}
	 */
	public function get_orders( int $user_id, int $page = 1 ): array;

	/**
	 * Retrieve WooCommerce Bookings (or equivalent) for a user.
	 *
	 * Returns: [ 'bookings' => array of booking records ]
	 * Each record: [ 'booking_id', 'product_name', 'date', 'status', 'vendor_name' ]
	 *
	 * @param int $user_id WP user ID.
	 * @param int $page    1-based page number.
	 * @return array{bookings: array}
	 */
	public function get_bookings( int $user_id, int $page = 1 ): array;

	/**
	 * Retrieve IP assets (invitations + shares) for a user.
	 *
	 * Returns: [ 'invitations' => array, 'shares' => array ]
	 *
	 * @param int $user_id WP user ID.
	 * @return array{invitations: array, shares: array}
	 */
	public function get_ip_assets( int $user_id ): array;
}
