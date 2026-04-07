<?php
/**
 * Adapter toggle validation script — Phase 4 controlled cutover.
 *
 * Verifies that each registry:
 *   1. Auto-detects the correct adapter based on dependency presence.
 *   2. Uses only the expected concrete implementation classes.
 *
 * Note: PHP constants cannot be redefined within a single process, so
 * this script validates the AUTO-DETECT path only (as-deployed state).
 * Override-constant testing is documented in the runbook as a
 * wp-config.php change, verified with a re-run of this script.
 *
 * Run via catalog command:  adapter.toggle.validate
 *   ./scripts/wp.sh php scripts/validate-adapter-toggles.php
 *
 * Exit codes:
 *   0  — all checks passed
 *   1  — one or more checks failed
 */

if ( ! defined( 'ABSPATH' ) ) {
	// When run through wp.sh 'php' the script is executed inside WP bootstrap.
	// If called directly, abort.
	echo "[ERROR] This script must run inside a WordPress context via wp.sh\n";
	exit( 1 );
}

$GLOBALS['_atv_failures'] = 0;

// ---------------------------------------------------------------------------
// Helper — uses $GLOBALS explicitly to survive wp eval-file function scope
// ---------------------------------------------------------------------------

function tap_report( string $label, bool $pass, string $detail = '' ): void {
	$status = $pass ? '[PASS]' : '[FAIL]';
	echo "{$status} {$label}" . ( $detail ? " — {$detail}" : '' ) . "\n";
	if ( ! $pass ) {
		$GLOBALS['_atv_failures']++;
	}
}

// ---------------------------------------------------------------------------
// TAP — tm-account-panel
// ---------------------------------------------------------------------------

echo "\n--- TAP Adapter Registry ---\n";

$dokan_present = function_exists( 'dokan_is_store_page' );
$tap_forced    = defined( 'TAP_ADAPTER' ) ? TAP_ADAPTER : null;

tap_report(
	'TAP auto-detect function resolve',
	true,
	'dokan_is_store_page ' . ( $dokan_present ? 'PRESENT' : 'ABSENT' )
);

if ( null !== $tap_forced ) {
	tap_report(
		'TAP override constant',
		in_array( $tap_forced, [ 'default-wp', 'compat' ], true ),
		"TAP_ADAPTER = '{$tap_forced}'"
	);
} else {
	tap_report( 'TAP override constant', true, 'not defined (auto-detect active)' );
}

// Determine expected adapter mode
$tap_expect_default = ( 'default-wp' === $tap_forced )
	|| ( null === $tap_forced && ! $dokan_present );
$tap_mode_label = $tap_expect_default ? 'default-wp' : 'compat';

tap_report( 'TAP expected mode', true, $tap_mode_label );

if ( class_exists( 'TAP_Adapter_Registry' ) ) {
	TAP_Adapter_Registry::reset();
	$resolver             = TAP_Adapter_Registry::get_context_resolver();
	$login                = TAP_Adapter_Registry::get_login_handler();
	$onboarding           = TAP_Adapter_Registry::get_onboarding_provider();
	$account_data         = TAP_Adapter_Registry::get_account_data_provider();

	$expect_resolver  = $tap_expect_default ? 'TAP_WP_Page_Context_Resolver'      : 'TAP_Compat_Page_Context_Resolver';
	$expect_login     = $tap_expect_default ? 'TAP_WP_Login_Handler'               : 'TAP_Compat_Login_Handler';
	$expect_onboard   = $tap_expect_default ? 'TAP_WP_Onboarding_Provider'         : 'TAP_Compat_Onboarding_Provider';
	$expect_data      = $tap_expect_default ? 'TAP_WP_Account_Data_Provider'       : 'TAP_Compat_Account_Data_Provider';

	tap_report( 'TAP context_resolver class',     get_class( $resolver )     === $expect_resolver,  get_class( $resolver ) );
	tap_report( 'TAP login_handler class',        get_class( $login )        === $expect_login,     get_class( $login ) );
	tap_report( 'TAP onboarding_provider class',  get_class( $onboarding )   === $expect_onboard,   get_class( $onboarding ) );
	tap_report( 'TAP account_data_provider class',get_class( $account_data ) === $expect_data,      get_class( $account_data ) );
} else {
	tap_report( 'TAP_Adapter_Registry class available', false, 'class not loaded' );
	$failures++;
}

// ---------------------------------------------------------------------------
// TVBM — tm-vendor-booking-modal
// ---------------------------------------------------------------------------

echo "\n--- TVBM Adapter Registry ---\n";

$wc_present   = function_exists( 'wc_get_product' );
$tvbm_forced  = defined( 'TVBM_ADAPTER' ) ? TVBM_ADAPTER : null;

tap_report(
	'TVBM auto-detect function resolve',
	true,
	'wc_get_product ' . ( $wc_present ? 'PRESENT' : 'ABSENT' )
);

if ( null !== $tvbm_forced ) {
	tap_report(
		'TVBM override constant',
		in_array( $tvbm_forced, [ 'default-wp', 'compat' ], true ),
		"TVBM_ADAPTER = '{$tvbm_forced}'"
	);
} else {
	tap_report( 'TVBM override constant', true, 'not defined (auto-detect active)' );
}

$tvbm_expect_default = ( 'default-wp' === $tvbm_forced )
	|| ( null === $tvbm_forced && ! $wc_present );
$tvbm_mode_label = $tvbm_expect_default ? 'default-wp' : 'compat';

tap_report( 'TVBM expected mode', true, $tvbm_mode_label );

if ( class_exists( 'TVBM_Adapter_Registry' ) ) {
	TVBM_Adapter_Registry::reset();
	$offer       = TVBM_Adapter_Registry::get_offer_discovery();
	$form        = TVBM_Adapter_Registry::get_form_renderer();
	$checkout_h  = TVBM_Adapter_Registry::get_checkout_handler();
	$checkout_p  = TVBM_Adapter_Registry::get_checkout_policy();

	$expect_offer     = $tvbm_expect_default ? 'TVBM_WP_Offer_Discovery'      : 'TVBM_Compat_Offer_Discovery';
	$expect_form      = $tvbm_expect_default ? 'TVBM_WP_Booking_Form_Renderer'        : 'TVBM_Compat_Booking_Form_Renderer';
	$expect_checkout_h= $tvbm_expect_default ? 'TVBM_WP_Checkout_Handler'     : 'TVBM_Compat_Checkout_Handler';
	$expect_checkout_p= $tvbm_expect_default ? 'TVBM_WP_Checkout_Policy'      : 'TVBM_Compat_Checkout_Policy';

	tap_report( 'TVBM offer_discovery class',   get_class( $offer )      === $expect_offer,      get_class( $offer ) );
	tap_report( 'TVBM form_renderer class',     get_class( $form )       === $expect_form,       get_class( $form ) );
	tap_report( 'TVBM checkout_handler class',  get_class( $checkout_h ) === $expect_checkout_h, get_class( $checkout_h ) );
	tap_report( 'TVBM checkout_policy class',   get_class( $checkout_p ) === $expect_checkout_p, get_class( $checkout_p ) );
} else {
	tap_report( 'TVBM_Adapter_Registry class available', false, 'class not loaded' );
	$failures++;
}

// ---------------------------------------------------------------------------
// THO — theme-orchestration
// ---------------------------------------------------------------------------

echo "\n--- THO Adapter Registry ---\n";

$tho_forced = defined( 'THO_ADAPTER' ) ? THO_ADAPTER : null;

tap_report(
	'THO auto-detect function resolve',
	true,
	'dokan_is_store_page ' . ( $dokan_present ? 'PRESENT' : 'ABSENT' )
);

if ( null !== $tho_forced ) {
	tap_report(
		'THO override constant',
		in_array( $tho_forced, [ 'default-wp', 'compat' ], true ),
		"THO_ADAPTER = '{$tho_forced}'"
	);
} else {
	tap_report( 'THO override constant', true, 'not defined (auto-detect active)' );
}

$tho_expect_default = ( 'default-wp' === $tho_forced )
	|| ( null === $tho_forced && ! $dokan_present );
$tho_mode_label = $tho_expect_default ? 'default-wp' : 'compat';

tap_report( 'THO expected mode', true, $tho_mode_label );

if ( class_exists( 'THO_Adapter_Registry' ) ) {
	THO_Adapter_Registry::reset();
	$router     = THO_Adapter_Registry::get_template_router();
	$asset      = THO_Adapter_Registry::get_asset_policy_provider();
	$profile    = THO_Adapter_Registry::get_profile_meta_provider();
	$identity   = THO_Adapter_Registry::get_vendor_identity_projector();
	$metrics    = THO_Adapter_Registry::get_metrics_provider();

	$expect_router   = $tho_expect_default ? 'THO_WP_Template_Router'              : 'THO_Compat_Template_Router';
	$expect_asset    = $tho_expect_default ? 'THO_WP_Asset_Policy_Provider'        : 'THO_Compat_Asset_Policy_Provider';
	$expect_profile  = $tho_expect_default ? 'THO_WP_Profile_Meta_Provider'        : 'THO_Compat_Profile_Meta_Provider';
	$expect_identity = $tho_expect_default ? 'THO_WP_Vendor_Identity_Projector'    : 'THO_Compat_Vendor_Identity_Projector';
	$expect_metrics  = $tho_expect_default ? 'THO_WP_Metrics_Provider'             : 'THO_Compat_Metrics_Provider';

	tap_report( 'THO template_router class',            get_class( $router )   === $expect_router,   get_class( $router ) );
	tap_report( 'THO asset_policy_provider class',      get_class( $asset )    === $expect_asset,    get_class( $asset ) );
	tap_report( 'THO profile_meta_provider class',      get_class( $profile )  === $expect_profile,  get_class( $profile ) );
	tap_report( 'THO vendor_identity_projector class',  get_class( $identity ) === $expect_identity, get_class( $identity ) );
	tap_report( 'THO metrics_provider class',           get_class( $metrics )  === $expect_metrics,  get_class( $metrics ) );
} else {
	tap_report( 'THO_Adapter_Registry class available', false, 'class not loaded' );
	$failures++;
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";

$_atv_failures = $GLOBALS['_atv_failures'];
if ( 0 === $_atv_failures ) {
	echo "ADAPTER TOGGLES: ALL PASS — runtime mode confirmed\n";
	exit( 0 );
} else {
	echo "ADAPTER TOGGLES: {$_atv_failures} FAILURE(S) — see [FAIL] lines above\n";
	exit( 1 );
}
