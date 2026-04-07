<?php
/**
 * Asset enqueueing for TM Store UI — theme-agnostic.
 *
 * Replaces the former theme-owned enqueue path.
 * CSS dependencies no longer reference legacy theme handles; instead they
 * depend on 'tm-store-ui-responsive' (the plugin-owned responsive-config.css).
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tm_store_ui_is_asset_context' ) ) {
	function tm_store_ui_is_asset_context(): bool {
		return ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() )
			|| ( function_exists( 'tm_store_lists_is_listing_page' ) && tm_store_lists_is_listing_page() )
			|| ( function_exists( 'ecomcine_is_person_page' ) && ecomcine_is_person_page() )
			|| ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() );
	}
}

if ( ! function_exists( 'tm_store_ui_resolve_vendor_context' ) ) {
	function tm_store_ui_resolve_vendor_context(): array {
		$vendor_id   = 0;
		$is_owner    = false;
		$can_edit    = false;
		$current_uid = get_current_user_id();

		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			$vendor_id = absint( get_query_var( 'author' ) );
		} elseif ( function_exists( 'ecomcine_is_person_page' ) && ecomcine_is_person_page() ) {
			$vendor_id = absint( get_query_var( 'author' ) );
		}

		if ( $vendor_id ) {
			$is_owner = ( (int) $current_uid === (int) $vendor_id );
			$can_edit = function_exists( 'tm_can_edit_vendor_profile' )
				? (bool) tm_can_edit_vendor_profile( $vendor_id, $current_uid )
				: $is_owner;
		}

		return array(
			'vendor_id' => $vendor_id,
			'is_owner'  => $is_owner,
			'can_edit'  => $can_edit,
		);
	}
}

/**
 * Enqueue plugin CSS + JS on the frontend.
 * Priority 15 — same as the original child theme callback, so existing
 * CSS specificity is unchanged.
 */
function tm_store_ui_enqueue_assets() {
	// Guarantee 'ecomcine-base-css' is always registered as a dependency anchor.
	// Themes that own this handle (e.g. ecomcine-base) register it themselves at
	// priority 10; for every other theme we register a virtual/empty placeholder
	// so the CSS dependency chain never silently collapses.
	// Using src=false creates a no-output placeholder — no double-load risk.
	if ( ! wp_style_is( 'ecomcine-base-css', 'registered' ) ) {
		wp_register_style( 'ecomcine-base-css', false, array(), null );
	}

	// Register shared handles so other modules can depend on them without forcing a global load.
	wp_register_style(
		'tm-store-ui-responsive',
		TM_STORE_UI_URL . 'assets/css/responsive-config.css',
		array( 'ecomcine-base-css' ),
		TM_STORE_UI_VERSION,
		'all'
	);

	// FontAwesome icons — replaces Dokan's bundled FA (dequeued below).
	wp_register_style(
		'tm-fontawesome',
		TM_STORE_UI_URL . 'assets/css/tm-fontawesome.css',
		array(),
		TM_STORE_UI_VERSION,
		'all'
	);

	// Main vendor-store CSS (listing filters, editing UI, store page layout).
	wp_register_style(
		'tm-store-ui-css',
		TM_STORE_UI_URL . 'assets/css/vendor-store.css',
		array( 'tm-store-ui-responsive', 'tm-fontawesome' ),
		TM_STORE_UI_VERSION,
		'all'
	);

	// Vendor store JS — biography lightbox, inline editing, social metrics polling,
	// location map modal, onboard share link.
	wp_register_script(
		'tm-store-ui-js',
		TM_STORE_UI_URL . 'assets/js/vendor-store.js',
		array( 'jquery' ),
		TM_STORE_UI_VERSION,
		true
	);

	if ( tm_store_ui_is_asset_context() ) {
		wp_enqueue_style( 'tm-store-ui-responsive' );
		wp_enqueue_style( 'tm-fontawesome' );
		wp_enqueue_style( 'tm-store-ui-css' );
		wp_enqueue_script( 'tm-store-ui-js' );
	}

	// Resolve vendor context for localization.
	$context     = tm_store_ui_resolve_vendor_context();
	$_vendor_id  = (int) $context['vendor_id'];
	$_is_owner   = (bool) $context['is_owner'];
	$_can_edit   = (bool) $context['can_edit'];

	// Mapbox token — needed on every page that could show the location geocoder
	// (store profile, showcase, locations page). Use EcomCine canonical getter
	// which reads own settings first, then falls back to Dokan geolocation key.
	$_mapbox = function_exists( 'ecomcine_get_mapbox_token' )
		? ecomcine_get_mapbox_token()
		: ( function_exists( 'dokan_get_option' )
			? (string) dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' )
			: '' );

	if ( wp_script_is( 'tm-store-ui-js', 'enqueued' ) ) {
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
