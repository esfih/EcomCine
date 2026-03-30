<?php
/**
 * Asset enqueueing for TM Store UI — theme-agnostic.
 *
 * Replaces child_enqueue_styles() from astra-child/functions.php.
 * CSS dependencies no longer reference astra-child-theme-css; instead they
 * depend on 'tm-store-ui-responsive' (the plugin-owned responsive-config.css).
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue plugin CSS + JS on the frontend.
 * Priority 15 — same as the original child theme callback, so existing
 * CSS specificity is unchanged.
 */
function tm_store_ui_enqueue_assets() {
	// Responsive config CSS — provides CSS variables used by all other plugin styles.
	wp_enqueue_style(
		'tm-store-ui-responsive',
		TM_STORE_UI_URL . 'assets/css/responsive-config.css',
		array( 'tm-theme-css' ),
		TM_STORE_UI_VERSION,
		'all'
	);

	// Main vendor-store CSS (listing filters, editing UI, store page layout).
	wp_enqueue_style(
		'tm-store-ui-css',
		TM_STORE_UI_URL . 'assets/css/vendor-store.css',
		array( 'tm-store-ui-responsive' ),
		TM_STORE_UI_VERSION,
		'all'
	);

	// Vendor store JS — biography lightbox, inline editing, social metrics polling,
	// location map modal, onboard share link.
	wp_enqueue_script(
		'tm-store-ui-js',
		TM_STORE_UI_URL . 'assets/js/vendor-store.js',
		array( 'jquery' ),
		TM_STORE_UI_VERSION,
		true
	);

	// Resolve vendor context for localization.
	$_vendor_id  = 0;
	$_is_owner   = false;
	$_can_edit   = false;
	$_mapbox     = '';

	if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
		$_vendor_id     = absint( get_query_var( 'author' ) );
		$_current_uid   = get_current_user_id();
		if ( $_vendor_id ) {
			$_is_owner = ( (int) $_current_uid === (int) $_vendor_id );
			$_can_edit = function_exists( 'tm_can_edit_vendor_profile' )
				? (bool) tm_can_edit_vendor_profile( $_vendor_id )
				: $_is_owner;
		}
		$_mapbox = function_exists( 'dokan_get_option' )
			? (string) dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' )
			: '';
	}

	wp_localize_script( 'tm-store-ui-js', 'vendorStoreUiData', array(
		'ajaxurl'               => admin_url( 'admin-ajax.php' ),
		'ajax_url'              => admin_url( 'admin-ajax.php' ),
		'nonce'                 => wp_create_nonce( 'tm_social_fetch' ),
		'editNonce'             => wp_create_nonce( 'vendor_inline_edit' ),
		'onboardNonce'          => wp_create_nonce( 'tm_onboard_share_link' ),
		'userId'                => $_vendor_id,
		'isOwner'               => $_is_owner,
		'canEdit'               => $_can_edit,
		'mapbox_token'          => $_mapbox,
		'jqueryUiCssUrl'        => WP_CONTENT_URL . '/plugins/woocommerce-bookings/dist/jquery-ui-styles.css',
		'jqueryUiCoreUrl'       => includes_url( 'js/jquery/ui/core.min.js' ),
		'jqueryUiDatepickerUrl' => includes_url( 'js/jquery/ui/datepicker.min.js' ),
		'jqueryUiWidgetUrl'     => includes_url( 'js/jquery/ui/widget.min.js' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'tm_store_ui_enqueue_assets', 15 );

// =============================================================================
// Stack-mode CSS culling — dequeue third-party plugin stylesheets that are
// irrelevant for the active EcomCine runtime mode.
//
// Third-party plugins (Dokan, WooCommerce, WC Bookings, FluentCart…) are
// installed but the runtime mode governs which commerce stack is actually
// in use.  In non-Dokan / non-WooCommerce modes we strip their CSS so our
// own styles are never fighting hidden !important rules from unused stacks.
// =============================================================================
add_action( 'wp_enqueue_scripts', function() {
	if ( ! class_exists( 'EcomCine_Admin_Settings', false ) ) {
		return;
	}

	$mode = EcomCine_Admin_Settings::get_runtime_mode();

	$is_dokan_mode   = in_array( $mode, array( 'wp_woo_dokan', 'wp_woo_dokan_booking' ), true );
	$is_woo_mode     = $is_dokan_mode || in_array( $mode, array( 'wp_woo', 'wp_woo_booking' ), true );
	$is_booking_mode = in_array( $mode, array( 'wp_woo_booking', 'wp_woo_dokan_booking' ), true );
	$is_fc_mode      = ( $mode === 'wp_fluentcart' );

	// Always dequeue Dokan's FontAwesome bundle — we ship our own (tm-fontawesome).
	wp_dequeue_style( 'dokan-fontawesome' );

	// Dequeue remaining Dokan CSS when not in a Dokan mode.
	if ( ! $is_dokan_mode ) {
		foreach ( array(
			'dokan-style',
			'dokan-modal',
			'dokan-shipping-block-checkout-support',
			'dca-frontend',
		) as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	// Dequeue WooCommerce CSS when not in any WooCommerce mode.
	if ( ! $is_woo_mode ) {
		foreach ( array(
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce-general',
			'woocommerce-inline',
			'jquery-ui-style',
		) as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	// Dequeue WC Bookings CSS when not in a booking mode.
	if ( ! $is_booking_mode ) {
		wp_dequeue_style( 'wc-bookings-styles' );
	}

	// Dequeue FluentCart checkout CSS when not in FluentCart mode.
	if ( ! $is_fc_mode ) {
		wp_dequeue_style( 'fluentcart-modal-checkout-css' );
	}
}, 99 );
