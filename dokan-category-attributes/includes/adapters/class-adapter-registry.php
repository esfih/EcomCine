<?php
/**
 * Adapter Registry — static factory for feature-layer adapter resolution.
 *
 * Reads the `dca_active_adapter` WP option (default 'compatibility') and returns
 * the correct concrete implementation for each interface.  Caches one instance per
 * adapter type per request so callers never pay repeated construction costs.
 *
 * @package DCA\Adapters
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 1 — Core Contract Scaffolding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_Adapter_Registry
 *
 * Usage:
 *   $repo     = DCA_Adapter_Registry::get_repository();
 *   $resolver = DCA_Adapter_Registry::get_category_resolver();
 */
final class DCA_Adapter_Registry {

	/** @var array<string, object> Per-request instance cache. */
	private static $instances = array();

	/** @var string|null Resolved adapter key for this request. */
	private static $active = null;

	// -------------------------------------------------------------------------
	// Public getters
	// -------------------------------------------------------------------------

	/**
	 * Return the active DCA_Attribute_Repository implementation.
	 *
	 * @return DCA_Attribute_Repository
	 */
	public static function get_repository() {
		return self::resolve( 'repository' );
	}

	/**
	 * Return the active DCA_Vendor_Category_Resolver implementation.
	 *
	 * @return DCA_Vendor_Category_Resolver
	 */
	public static function get_category_resolver() {
		return self::resolve( 'category_resolver' );
	}

	/**
	 * Return the active DCA_Dashboard_Renderer implementation.
	 *
	 * @return DCA_Dashboard_Renderer
	 */
	public static function get_dashboard_renderer() {
		return self::resolve( 'dashboard_renderer' );
	}

	/**
	 * Return the active DCA_Profile_Projector implementation.
	 *
	 * @return DCA_Profile_Projector
	 */
	public static function get_profile_projector() {
		return self::resolve( 'profile_projector' );
	}

	/**
	 * Return the active DCA_Filter_Provider implementation.
	 *
	 * @return DCA_Filter_Provider
	 */
	public static function get_filter_provider() {
		return self::resolve( 'filter_provider' );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve and cache an adapter instance by slot name.
	 *
	 * @param string $slot One of: repository|category_resolver|dashboard_renderer|profile_projector|filter_provider
	 * @return object
	 */
	private static function resolve( $slot ) {
		$key = self::active_adapter() . '.' . $slot;
		if ( isset( self::$instances[ $key ] ) ) {
			return self::$instances[ $key ];
		}
		self::$instances[ $key ] = self::make( self::active_adapter(), $slot );
		return self::$instances[ $key ];
	}

	/**
	 * Instantiate the correct class for the given adapter + slot combination.
	 *
	 * @param string $adapter  'compatibility' | 'default_wp'
	 * @param string $slot     see resolve()
	 * @return object
	 */
	private static function make( $adapter, $slot ) {
		$map = array(
			'compatibility' => array(
				'repository'         => 'DCA_Compat_Attribute_Repository',
				'category_resolver'  => 'DCA_Compat_Category_Resolver',
				'dashboard_renderer' => 'DCA_Compat_Dashboard_Renderer',
				'profile_projector'  => 'DCA_Compat_Profile_Projector',
				'filter_provider'    => 'DCA_Compat_Filter_Provider',
			),
			'default_wp'    => array(
				'repository'         => 'DCA_WP_Attribute_Repository',
				'category_resolver'  => 'DCA_WP_Category_Resolver',
				'dashboard_renderer' => 'DCA_WP_Dashboard_Renderer',
				'profile_projector'  => 'DCA_WP_Profile_Projector',
				'filter_provider'    => 'DCA_WP_Filter_Provider',
			),
		);

		if ( ! isset( $map[ $adapter ][ $slot ] ) ) {
			// Unknown adapter key — fall back to compatibility so the site stays live.
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Unknown adapter "%s" for slot "%s". Falling back to compatibility.', esc_html( $adapter ), esc_html( $slot ) ),
				'1.1.0'
			);
			$class = $map['compatibility'][ $slot ];
		} else {
			$class = $map[ $adapter ][ $slot ];
		}

		return new $class();
	}

	/**
	 * Return the active adapter key, reading from WP options on first call.
	 *
	 * Allowed values: 'compatibility' (default), 'default_wp'.
	 * Any other stored value is silently normalised to 'compatibility'.
	 *
	 * @return string
	 */
	private static function active_adapter() {
		if ( null !== self::$active ) {
			return self::$active;
		}

		$stored = get_option( 'dca_active_adapter', 'compatibility' );
		self::$active = in_array( $stored, array( 'compatibility', 'default_wp' ), true )
			? $stored
			: 'compatibility';

		return self::$active;
	}

	/**
	 * Flush the instance and adapter key caches.
	 * Useful in tests or after toggling the option at runtime.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$instances = array();
		self::$active    = null;
	}
}
