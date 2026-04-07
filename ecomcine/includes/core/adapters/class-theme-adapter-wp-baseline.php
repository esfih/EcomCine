<?php
/**
 * Baseline adapter when Dokan stack is absent.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Theme_Adapter_WP_Baseline implements EcomCine_Theme_Adapter {
	public function id() {
		return 'wp-baseline';
	}

	public function is_store_page() {
		return false;
	}

	public function is_store_listing() {
		return false;
	}

	public function get_vendor_id_from_query() {
		return 0;
	}
}
