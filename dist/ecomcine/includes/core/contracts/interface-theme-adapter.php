<?php
/**
 * Theme/runtime adapter contract.
 */

defined( 'ABSPATH' ) || exit;

interface EcomCine_Theme_Adapter {
	/**
	 * Stable adapter identifier.
	 */
	public function id();

	/**
	 * Detect whether current request is a vendor store page.
	 */
	public function is_store_page();

	/**
	 * Detect whether current request is a vendor store listing page.
	 */
	public function is_store_listing();

	/**
	 * Resolve vendor id from current query context.
	 */
	public function get_vendor_id_from_query();
}
