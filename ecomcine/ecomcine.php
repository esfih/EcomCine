<?php
/**
 * Plugin Name: EcomCine
 * Description: Unified EcomCine app plugin consolidating cinematic media, account panel, and booking modal features.
 * Version: 0.1.91
 * Author: EcomCine
 * Update URI: https://updates.ecomcine.com/update-server.php
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMCINE_VERSION', '0.1.91' );
define( 'ECOMCINE_FILE', __FILE__ );
define( 'ECOMCINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECOMCINE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Keep bootstrap resilient: never crash the whole site when requirements are unmet.
 *
 * @param string[] $errors
 */
function ecomcine_register_bootstrap_notice( array $errors ) {
	if ( empty( $errors ) ) {
		return;
	}

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[EcomCine bootstrap] ' . implode( ' | ', $errors ) );
	}

	add_action(
		'admin_notices',
		static function () use ( $errors ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p><strong>EcomCine:</strong> '
				. esc_html__( 'Plugin bootstrap failed. Review the items below.', 'ecomcine' )
				. '</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	);
}

/**
 * Include a required PHP file only when it exists.
 *
 * @param string   $relative_path
 * @param string[] $errors
 * @return bool
 */
function ecomcine_require_file( $relative_path, array &$errors ) {
	$path = ECOMCINE_DIR . ltrim( (string) $relative_path, '/' );
	if ( ! is_file( $path ) ) {
		$errors[] = sprintf( 'Missing required file: %s', $relative_path );
		return false;
	}

	require_once $path;
	return true;
}

$ecomcine_bootstrap_errors = array();

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	$ecomcine_bootstrap_errors[] = sprintf( 'PHP 8.1 or newer is required. Current: %s', PHP_VERSION );
	ecomcine_register_bootstrap_notice( $ecomcine_bootstrap_errors );
	return;
}

$required_files = array(
	'includes/core/class-plugin-capability.php',
	'includes/core/class-plugin-updater.php',
	'includes/core/class-wave1-authority.php',
	'includes/core/class-listing-service.php',
	'includes/core/class-route-service.php',
	'includes/core/class-query-service.php',
	'includes/core/class-listing-card-view-model.php',
	'includes/core/cli/class-authority-cli-command.php',
	'includes/core/cli/class-parity-check-command.php',
	'includes/core/cli/class-shadow-log-cli-command.php',
	'includes/core/contracts/interface-theme-adapter.php',
	'includes/core/contracts/interface-commerce-adapter.php',
	'includes/core/adapters/class-theme-adapter-dokan-astra.php',
	'includes/core/adapters/class-theme-adapter-wp-baseline.php',
	'includes/core/adapters/class-commerce-adapter-woodokan.php',
	'includes/core/adapters/class-commerce-adapter-wp-baseline.php',
	'includes/core/adapters/class-commerce-adapter-fluentcart.php',
	'includes/core/runtime/class-runtime-adapters.php',
	// EcomCine portability layer — must load before everything else.
	'includes/functions.php',
	'includes/class-person-category-registry.php',
	'includes/admin/class-admin-settings.php',
	'includes/admin/class-admin-categories-tab.php',
	'includes/licensing/class-offer-catalog.php',
	'includes/licensing/class-licensing.php',
	'includes/compat/vendor-utilities.php',
	'includes/migrations/class-ecomcine-dokan-data-migration.php',
);

foreach ( $required_files as $required_file ) {
	ecomcine_require_file( $required_file, $ecomcine_bootstrap_errors );
}

if ( ! class_exists( 'EcomCine_Admin_Settings', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Admin_Settings';
}
if ( ! class_exists( 'EcomCine_Licensing', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Licensing';
}
if ( ! class_exists( 'EcomCine_Plugin_Updater', false ) ) {
	$ecomcine_bootstrap_errors[] = 'Missing class: EcomCine_Plugin_Updater';
}

if ( ! empty( $ecomcine_bootstrap_errors ) ) {
	ecomcine_register_bootstrap_notice( $ecomcine_bootstrap_errors );
	return;
}

EcomCine_Admin_Settings::init();
EcomCine_Licensing::init();
EcomCine_Plugin_Updater::init();
EcomCine_Admin_Categories_Tab::init();
EcomCine_Wave1_Authority::init();
EcomCine_Route_Service::init();

// Demo data (non-critical — load after core bootstrap).
$_demo_errors = [];
ecomcine_require_file( 'includes/class-demo-importer.php', $_demo_errors );
ecomcine_require_file( 'includes/admin/class-demo-data-page.php', $_demo_errors );
ecomcine_require_file( 'includes/admin/class-debug-page.php', $_demo_errors );
if ( class_exists( 'EcomCine_Demo_Data_Page', false ) ) {
	EcomCine_Demo_Data_Page::init();
}
if ( class_exists( 'EcomCine_Debug_Page', false ) ) {
	EcomCine_Debug_Page::init();
}
unset( $_demo_errors );

/**
 * Helper for diagnostics/tests to inspect selected abstraction adapters.
 */
function ecomcine_get_runtime_adapter_snapshot() {
	return EcomCine_Runtime_Adapters::snapshot();
}

/**
 * Helper for diagnostics/tests to inspect current admin settings and plugin capabilities.
 */
function ecomcine_get_settings_snapshot() {
	$settings = EcomCine_Admin_Settings::get_settings();
	$settings['plugin_capabilities'] = EcomCine_Plugin_Capability::snapshot();
	$settings['wave1_authority'] = EcomCine_Wave1_Authority::snapshot();
	return $settings;
}

/**
 * Helper for diagnostics/tests to inspect current licensing status.
 */
function ecomcine_get_license_status_snapshot() {
	return EcomCine_Licensing::get_status();
}

/**
 * Helper for diagnostics/tests to inspect current offer catalog parity map.
 */
function ecomcine_get_offer_catalog_snapshot() {
	return EcomCine_Offer_Catalog::get_catalog();
}

/**
 * Runtime helper to check module feature toggles.
 */
function ecomcine_feature_enabled( $feature_key ) {
	return EcomCine_Admin_Settings::is_feature_enabled( $feature_key );
}

/**
 * Load a bundled module by relative path from ECOMCINE_DIR.
 */
function ecomcine_load_module( string $module_file ): void {
	$path = ECOMCINE_DIR . ltrim( $module_file, '/' );
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}

/**
 * Deploy the bundled debug MU-plugin and create the flag file.
 * Called from both the activation hook and the upgrade routine so it
 * works whether the plugin is freshly installed OR updated over an
 * existing active installation (WP does not call activation hooks on updates).
 */
function ecomcine_deploy_debug_infrastructure() {
	// 1. Ensure log directory exists.
	$log_dir = WP_CONTENT_DIR . '/logs';
	if ( ! is_dir( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
	}

	// 2. Deploy MU-plugin.
	$src    = ECOMCINE_DIR . 'mu-plugins/ecomcine-debug.php';
	$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
	$dest   = $mu_dir . '/ecomcine-debug.php';
	if ( file_exists( $src ) ) {
		if ( ! is_dir( $mu_dir ) ) {
			wp_mkdir_p( $mu_dir );
		}
		@copy( $src, $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	// 3. Create the flag file so logging is active immediately.
	$flag = WP_CONTENT_DIR . '/ecomcine-debug.txt';
	if ( ! file_exists( $flag ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $flag, "EcomCine debug logging enabled.\nDelete this file to disable.\n" );
	}
}

// ── Upgrade routines ─────────────────────────────────────────────────────────

/**
 * Run any pending upgrade routines once per plugin version bump.
 * Hooked to init (after all plugins load) so Dokan functions are available.
 */
add_action( 'init', function() {
	$stored = get_option( 'ecomcine_version', '0' );
	if ( version_compare( $stored, ECOMCINE_VERSION, '>=' ) ) {
		return;
	}

	// Always re-deploy debug infrastructure on every version bump so that
	// plugin updates (which skip the activation hook) also provision the files.
	ecomcine_deploy_debug_infrastructure();
	if ( class_exists( 'EcomCine_Admin_Settings', false ) ) {
		EcomCine_Admin_Settings::ensure_bootstrap_pages();
		EcomCine_Admin_Settings::sync_bundled_theme( 'ecomcine-base' === wp_get_theme()->get_stylesheet() );
	}

	// v0.1.32 — Backfill ecomcine_geo_lat/lng from dokan_profile_settings.geolocation
	// for vendors that have DPS geo but not the canonical flat meta keys.
	// The v0.1.26 migration covers dokan_geo_latitude, but not the geolocation array
	// inside dokan_profile_settings which is where the demo importer stores coords.
	if ( version_compare( $stored, '0.1.32', '<' ) ) {
		$geo_users = get_users( array(
			'role__in' => array( 'seller', 'ecomcine_person' ),
			'number'   => -1,
			'fields'   => array( 'ID' ),
		) );
		$geo_backfilled = 0;
		foreach ( $geo_users as $gu ) {
			$uid = (int) $gu->ID;
			if ( '' !== get_user_meta( $uid, 'ecomcine_geo_lat', true ) ) {
				continue; // Already set.
			}
			$dps = get_user_meta( $uid, 'dokan_profile_settings', true );
			if ( ! is_array( $dps ) ) {
				continue;
			}
			$geo = isset( $dps['geolocation'] ) && is_array( $dps['geolocation'] ) ? $dps['geolocation'] : array();
			if ( empty( $geo['latitude'] ) || empty( $geo['longitude'] ) ) {
				continue;
			}
			update_user_meta( $uid, 'ecomcine_geo_lat', (string) $geo['latitude'] );
			update_user_meta( $uid, 'ecomcine_geo_lng', (string) $geo['longitude'] );
			$addr = isset( $dps['location'] ) ? sanitize_text_field( (string) $dps['location'] ) : '';
			if ( '' !== $addr ) {
				update_user_meta( $uid, 'ecomcine_geo_address', $addr );
			}
			$geo_backfilled++;
		}
		if ( $geo_backfilled > 0 ) {
			error_log( "[EcomCine 0.1.32] ecomcine_geo_lat/lng backfill: set {$geo_backfilled} vendors." );
		}
	}

	// v0.1.75 — Reconcile public person terminology routes so plural listing
	//            pages and singular profile/terms routes stay in sync on
	//            upgraded installs without requiring a manual settings resave.
	if ( version_compare( $stored, '0.1.75', '<' ) && class_exists( 'EcomCine_Admin_Settings', false ) ) {
		EcomCine_Admin_Settings::sync_public_terminology_from_saved_settings();
	}

	// v0.1.48 — Migrate Dokan-era vendor meta to EcomCine canonical keys,
	//            recalculate L1 completeness, and scrub legacy Dokan profile
	//            ownership fields once canonical data exists.
	//
	// Covers three gaps on legacy installs:
	//   (a) dokan_geo_latitude/longitude → ecomcine_geo_lat/lng (then delete legacy)
	//   (b) dokan_enable_selling='yes'   → ecomcine_enabled='1' (when not already set)
	//   (c) tm_l1_complete recalculated via tm_vendor_completeness() using
	//       the now-canonical meta, correcting the v0.1.13 blind back-fill.
	//   (d) profile/media/address/social/geo remnants removed from Dokan legacy meta.
	if ( version_compare( $stored, '0.1.48', '<' ) && class_exists( 'EcomCine_Dokan_Data_Migration', false ) ) {
		EcomCine_Dokan_Data_Migration::run();
	}

	// v0.1.22 — Flush rewrite rules for EcomCine person routes + standalone grid/listing wiring.
	if ( version_compare( $stored, '0.1.22', '<' ) ) {
		delete_option( 'ecomcine_rewrite_flushed' );
	}

	// v0.1.23 — Backfill native person/category assignments from legacy sources.
	if ( version_compare( $stored, '0.1.23', '<' ) ) {
		if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			EcomCine_Person_Category_Registry::install();
			EcomCine_Person_Category_Registry::seed_defaults();
			EcomCine_Person_Category_Registry::migrate_from_store_category();
		}
	}

	// v0.1.21 — Ensure ecomcine_person role exists and DB tables are installed.
	if ( version_compare( $stored, '0.1.21', '<' ) ) {
		ecomcine_register_person_role();
		if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			EcomCine_Person_Category_Registry::install();
			EcomCine_Person_Category_Registry::seed_defaults();
		}
	}

	// v0.1.13 — Backfill tm_l1_complete for all approved Dokan vendors.
	if ( version_compare( $stored, '0.1.13', '<' ) && function_exists( 'dokan_get_sellers' ) ) {
		$paged   = 1;
		$updated = 0;
		do {
			$res     = dokan_get_sellers( [ 'status' => 'approved', 'number' => 200, 'paged' => $paged ] );
			$vendors = $res['users'] ?? [];
			foreach ( $vendors as $v ) {
				$uid = (int) $v->ID;
				if ( get_user_meta( $uid, 'tm_l1_complete', true ) !== '1' ) {
					update_user_meta( $uid, 'tm_l1_complete', '1' );
					$updated++;
				}
			}
			$paged++;
		} while ( count( $vendors ) === 200 );
		error_log( "[EcomCine 0.1.13] tm_l1_complete backfill: set flag for {$updated} vendors." );
	}

	update_option( 'ecomcine_version', ECOMCINE_VERSION );
}, 10 );

// 1) Media player module.
if ( ecomcine_feature_enabled( 'media_player' ) ) {
	ecomcine_load_module( 'modules/tm-media-player/tm-media-player.php' );
}

// 2) Account panel module.
if ( ecomcine_feature_enabled( 'account_panel' ) ) {
	ecomcine_load_module( 'modules/tm-account-panel/tm-account-panel.php' );
}

// 3) Booking modal module.
if ( ecomcine_feature_enabled( 'booking_modal' ) ) {
	ecomcine_load_module( 'modules/tm-vendor-booking-modal/tm-vendor-booking-modal.php' );
}

// 4) Store UI module (cinematic vendor store template overrides, attribute panels, social metrics).
ecomcine_load_module( 'modules/tm-store-ui/tm-store-ui.php' );

// 5) Video tools module (browser-side WebM converter at /converter).
ecomcine_load_module( 'modules/tm-video-tools/tm-video-tools.php' );

// ── Activation / deactivation ─────────────────────────────────────────────

/**
 * On activation:
 *  1. Deploy the bundled ecomcine-debug.php into wp-content/mu-plugins/ so the
 *     debug logger is always available without a manual file copy.
 *  2. Create the wp-content/ecomcine-debug.txt flag file to enable logging immediately.
 *     This lets the site owner see structured error output from the first page load
 *     after install — essential for diagnosing live-server issues.
 *
 * The flag file can be deleted at any time to disable logging with zero code changes:
 *   wp eval "unlink( WP_CONTENT_DIR . '/ecomcine-debug.txt' );"
 */
/**
 * Register the ecomcine_person WP role.
 * Safe to call multiple times — add_role() is a no-op when the role already exists.
 */
function ecomcine_register_person_role(): void {
	$role = get_role( 'ecomcine_person' );
	if ( ! $role ) {
		add_role(
			'ecomcine_person',
			__( 'EcomCine Person', 'ecomcine' ),
			array(
				'read'                   => true,
				'upload_files'           => true,
				'edit_posts'             => false,
				'publish_posts'          => false,
				'delete_posts'           => false,
				'manage_woocommerce'     => false,
			)
		);
	} else {
		// Ensure upload_files is always present — may be missing on installs where
		// the role was created before this capability was added (add_role() is a no-op
		// when the role already exists in the DB).
		if ( empty( $role->capabilities['upload_files'] ) ) {
			$role->add_cap( 'upload_files' );
		}
	}
}

// Register role on every init in case it was removed (e.g. by another plugin).
add_action( 'init', 'ecomcine_register_person_role', 1 );

// Persistently grant upload_files to ecomcine_person users on every request,
// including async-upload.php and admin-ajax.php. The enqueue_media_library()
// filter is temporary (removed immediately after wp_enqueue_media()), so the
// actual plupload HTTP upload would otherwise fail the capability check.
add_filter( 'user_has_cap', function( array $allcaps, array $caps, array $args, $user ): array {
	if ( isset( $user->roles ) && in_array( 'ecomcine_person', (array) $user->roles, true ) ) {
		$allcaps['upload_files'] = true;
	}
	return $allcaps;
}, 10, 4 );

// wp_ajax_upload_attachment() (WP 6.7+) checks current_user_can('edit_post', $post_id)
// even when post_id=0. map_meta_cap returns ['do_not_allow'] for post_id=0 because no
// post exists, blocking uploads for non-admin users. Remap edit_post on post_id=0 to
// upload_files so ecomcine_person users can attach media without needing edit_posts.
add_filter( 'map_meta_cap', function( array $caps, string $cap, int $user_id, array $args ): array {
	if ( 'edit_post' === $cap && isset( $args[0] ) && (int) $args[0] === 0 ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'ecomcine_person', (array) $user->roles, true ) ) {
			return [ 'upload_files' ];
		}
	}
	return $caps;
}, 10, 4 );

// ── Theme-compat layer ────────────────────────────────────────────────────────
// Load the thin compat file for whichever active theme supports the
// ecomcine_suppress_header / ecomcine_suppress_footer globals.
add_action( 'after_setup_theme', function() {
	$compat_dir = ECOMCINE_DIR . 'includes/theme-compat/';
	if ( function_exists( 'astra_header' ) ) {
		require_once $compat_dir . 'astra.php';
	}
}, 20 );

// ── Dokan bridge hooks ────────────────────────────────────────────────────────
// When Dokan is active, its display/settings/save hooks are bridged to the
// EcomCine equivalents so ecomcine-aware code works whether Dokan is present
// or not.  These must be registered early (priority 1) so tm-store-ui modules
// that hook onto ecomcine_person_* fire at the correct time relative to Dokan.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'dokan' ) ) {
			return;
		}

		// Profile display (Dokan store page, bottom drawer).
		add_action(
			'dokan_store_profile_bottom_drawer',
			function ( $store_user, $store_info ) {
				do_action( 'ecomcine_person_profile_display', $store_user, $store_info );
			},
			1,
			2
		);

		// Settings form fields (Dokan vendor dashboard, after phone field).
		add_action(
			'dokan_settings_after_store_phone',
			function ( $user_id, $profile_info ) {
				do_action( 'ecomcine_person_settings_fields', $user_id, $profile_info );
			},
			1,
			2
		);

		// Profile save (Dokan vendor dashboard).
		add_action(
			'dokan_store_profile_saved',
			function ( $store_id, $settings ) {
				do_action( 'ecomcine_person_profile_saved', $store_id, $settings );
			},
			1,
			2
		);
	},
	1
);

register_activation_hook( ECOMCINE_FILE, function() {
	ecomcine_deploy_debug_infrastructure();
	ecomcine_register_person_role();
	if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
		EcomCine_Person_Category_Registry::install();
		EcomCine_Person_Category_Registry::seed_defaults();
		EcomCine_Person_Category_Registry::migrate_from_store_category();
	}
	if ( class_exists( 'EcomCine_Admin_Settings', false ) ) {
		EcomCine_Admin_Settings::ensure_bootstrap_pages();
		EcomCine_Admin_Settings::sync_bundled_theme( true );
	}
} );

/**
 * On deactivation: remove the debug flag file to stop logging immediately.
 * The MU-plugin file stays installed so it can be re-enabled by recreating
 * the flag file — but it silently no-ops when the flag file is absent.
 */
register_deactivation_hook( ECOMCINE_FILE, function() {
	$flag = WP_CONTENT_DIR . '/ecomcine-debug.txt';
	if ( file_exists( $flag ) ) {
		@unlink( $flag ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
} );
