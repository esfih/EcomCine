<?php
/**
 * Commerce integration adapter contract.
 */

defined( 'ABSPATH' ) || exit;

interface EcomCine_Commerce_Adapter {
	/**
	 * Stable adapter identifier.
	 */
	public function id();

	/**
	 * Whether commerce stack APIs are available.
	 */
	public function is_available();

	/**
	 * Resolve a vendor public URL when available.
	 */
	public function get_vendor_store_url( $vendor_id );

	// -----------------------------------------------------------------------
	// Financial KPIs — implemented per commerce stack.
	// -----------------------------------------------------------------------

	/**
	 * Return rendered HTML showing the vendor's current available balance.
	 * Returns empty string when the concept does not apply.
	 *
	 * @param int $vendor_id  WP user ID of the vendor / account owner.
	 * @return string
	 */
	public function get_vendor_balance_html( $vendor_id );

	/**
	 * Return current-month gross revenue for the vendor as a float.
	 *
	 * @param int $vendor_id
	 * @return float
	 */
	public function get_vendor_mrr( $vendor_id );

	/**
	 * Return current-year gross revenue for the vendor as a float.
	 *
	 * @param int $vendor_id
	 * @return float
	 */
	public function get_vendor_arr( $vendor_id );

	/**
	 * Format a monetary amount as a locale/currency string.
	 *
	 * @param float $amount
	 * @return string
	 */
	public function format_price( $amount );

	// -----------------------------------------------------------------------
	// Orders — HTML rendering delegated to the adapter.
	// -----------------------------------------------------------------------

	/**
	 * Return the orders-table HTML for the given vendor.
	 *
	 * @param int $vendor_id
	 * @return string
	 */
	public function get_orders_table_html( $vendor_id );

	/**
	 * Return the order-detail HTML for a single order.
	 * Returns empty string on failure.
	 *
	 * @param int $order_id
	 * @return string
	 */
	public function get_order_detail_html( $order_id );

	/**
	 * Return true when $user_id is authorised to view $order_id.
	 *
	 * @param int $order_id
	 * @param int $user_id
	 * @return bool
	 */
	public function can_view_order( $order_id, $user_id );
}
