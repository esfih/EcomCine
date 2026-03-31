<?php
/**
 * Preferred theme/runtime adapter for Astra + Dokan stack.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Theme_Adapter_Dokan_Astra implements EcomCine_Theme_Adapter {
	public function id() {
		return 'dokan-astra';
	}

	public function is_store_page() {
		return function_exists( 'dokan_is_store_page' ) && dokan_is_store_page();
	}

	public function is_store_listing() {
		return function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing();
	}

	public function get_vendor_id_from_query() {
		return absint( get_query_var( 'author' ) );
	}
}
