<?php
/**
 * Default-WP adapter: page-context resolver without Dokan dependency.
 *
 * Uses WP-native author archives and post-type archives for vendor-page
 * detection — no Dokan functions required.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Page_Context_Resolver implements TAP_Page_Context_Resolver {

	public function is_eligible_page(): bool {
		if ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() ) {
			return true;
		}
		// Route service delegation: ecomcine_is_person_page() consults
		// EcomCine_Route_Service first, then falls back to Dokan.
		if ( function_exists( 'ecomcine_is_person_page' ) && ecomcine_is_person_page() ) {
			return true;
		}
		// Dokan store / listing page — Dokan plugin may be installed even in non-Dokan modes.
		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			return true;
		}
		if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
			return true;
		}
		// Platform page templates (both slug variants).
		if ( function_exists( 'is_page_template' )
			&& ( is_page_template( 'page-platform.php' )
				|| is_page_template( 'tm-store-ui/page-platform' ) ) ) {
			return true;
		}
		// Author archive → individual vendor profile page.
		if ( is_author() ) {
			return true;
		}
		// Post-type archive for the tm_vendor CPT.
		if ( is_post_type_archive( 'tm_vendor' ) ) {
			return true;
		}
		return false;
	}
}
