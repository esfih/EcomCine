<?php
/**
 * Compatibility adapter: page-context resolver using Dokan functions.
 *
 * Used when Dokan is active; delegates to dokan_is_store_page() and
 * dokan_is_store_listing() for vendor-page detection.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Compat_Page_Context_Resolver implements TAP_Page_Context_Resolver {

	public function is_eligible_page(): bool {
		if ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() ) {
			return true;
		}
		// Route service delegation: ecomcine_is_person_page() consults
		// EcomCine_Route_Service first, then falls back to Dokan.
		if ( function_exists( 'ecomcine_is_person_page' ) && ecomcine_is_person_page() ) {
			return true;
		}
		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			return true;
		}
		if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
			return true;
		}
		if ( function_exists( 'is_page_template' )
			&& ( is_page_template( 'page-platform.php' )
				|| is_page_template( 'tm-store-ui/page-platform' ) ) ) {
			return true;
		}
		return false;
	}
}
