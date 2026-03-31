<?php
/**
 * Theme-orchestration adapter registry.
 *
 * Auto-selects compat (Dokan present) or default-WP (Dokan absent).
 * Override with define( 'THO_ADAPTER', 'default-wp' ) in wp-config.php.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Adapter_Registry {

	private static ?self $instance = null;

	private ?THO_Template_Router          $router             = null;
	private ?THO_Asset_Policy_Provider    $asset_policy       = null;
	private ?THO_Profile_Meta_Provider    $profile_meta       = null;
	private ?THO_Vendor_Identity_Projector $identity_projector = null;
	private ?THO_Metrics_Provider         $metrics            = null;

	private bool $use_default;

	private function __construct() {
		$forced = defined( 'THO_ADAPTER' ) ? THO_ADAPTER : null;

		$settings_baseline = class_exists( 'EcomCine_Admin_Settings', false )
			&& ! in_array(
				EcomCine_Admin_Settings::get_runtime_mode(),
				array( 'wp_woo_dokan', 'wp_woo_dokan_booking' ),
				true
			);
		$this->use_default = ( 'default-wp' === $forced )
			|| $settings_baseline
			|| ( null === $forced && ! function_exists( 'dokan_is_store_page' ) );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Reset singleton — for parity check isolation only. */
	public static function reset(): void {
		self::$instance = null;
	}

	// -------------------------------------------------------------------------
	// Accessor methods (lazy-initialised)
	// -------------------------------------------------------------------------

	public static function get_template_router(): THO_Template_Router {
		$self = self::instance();
		if ( null === $self->router ) {
			$self->router = $self->use_default
				? new THO_WP_Template_Router()
				: new THO_Compat_Template_Router();
		}

		return $self->router;
	}

	public static function get_asset_policy_provider(): THO_Asset_Policy_Provider {
		$self = self::instance();
		if ( null === $self->asset_policy ) {
			$self->asset_policy = $self->use_default
				? new THO_WP_Asset_Policy_Provider()
				: new THO_Compat_Asset_Policy_Provider();
		}

		return $self->asset_policy;
	}

	public static function get_profile_meta_provider(): THO_Profile_Meta_Provider {
		$self = self::instance();
		if ( null === $self->profile_meta ) {
			$self->profile_meta = $self->use_default
				? new THO_WP_Profile_Meta_Provider()
				: new THO_Compat_Profile_Meta_Provider();
		}

		return $self->profile_meta;
	}

	public static function get_vendor_identity_projector(): THO_Vendor_Identity_Projector {
		$self = self::instance();
		if ( null === $self->identity_projector ) {
			$self->identity_projector = $self->use_default
				? new THO_WP_Vendor_Identity_Projector()
				: new THO_Compat_Vendor_Identity_Projector();
		}

		return $self->identity_projector;
	}

	public static function get_metrics_provider(): THO_Metrics_Provider {
		$self = self::instance();
		if ( null === $self->metrics ) {
			$self->metrics = $self->use_default
				? new THO_WP_Metrics_Provider()
				: new THO_Compat_Metrics_Provider();
		}

		return $self->metrics;
	}

	// -------------------------------------------------------------------------
	// Test injection (parity check only)
	// -------------------------------------------------------------------------

	public static function set_template_router( THO_Template_Router $impl ): void {
		self::instance()->router = $impl;
	}

	public static function set_asset_policy_provider( THO_Asset_Policy_Provider $impl ): void {
		self::instance()->asset_policy = $impl;
	}

	public static function set_profile_meta_provider( THO_Profile_Meta_Provider $impl ): void {
		self::instance()->profile_meta = $impl;
	}

	public static function set_vendor_identity_projector( THO_Vendor_Identity_Projector $impl ): void {
		self::instance()->identity_projector = $impl;
	}

	public static function set_metrics_provider( THO_Metrics_Provider $impl ): void {
		self::instance()->metrics = $impl;
	}
}
