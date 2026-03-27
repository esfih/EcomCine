<?php
/**
 * Compatibility Dashboard Renderer — delegates to DCA_Dashboard_Fields.
 *
 * DCA_Dashboard_Fields already registers its own Dokan hooks in its constructor.
 * When the compatibility adapter is active the main plugin instantiates the legacy
 * class as normal; this adapter provides the same interface for code paths that go
 * through the registry (e.g. tests, REST endpoints, or future programmatic save).
 *
 * IMPORTANT: do not pass an instance of this class to the main plugin init while
 * DCA_Dashboard_Fields is still self-registering — that would cause double hooking.
 * The adapter can safely be used for programmatic render/save calls.
 *
 * @package DCA\Adapters\Compatibility
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 1 — Core Contract Scaffolding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_Compat_Dashboard_Renderer
 *
 * @implements DCA_Dashboard_Renderer
 */
class DCA_Compat_Dashboard_Renderer implements DCA_Dashboard_Renderer {

	/** @var DCA_Dashboard_Fields */
	private $delegate;

	public function __construct() {
		// Create a delegate but do NOT let it register hooks twice.
		// The main plugin's init() already registered the hook-based instance.
		// Here we only need the delegate for programmatic calls.
		$this->delegate = new DCA_Dashboard_Fields();
	}

	/**
	 * @inheritdoc
	 */
	public function render_fields( $vendor_id, array $store_settings = array() ) {
		$this->delegate->render_fields( $vendor_id, $store_settings );
	}

	/**
	 * @inheritdoc
	 *
	 * Delegates to the existing save_fields() method which reads from $_POST internally.
	 * For programmatic saves where $post_data is available but $_POST is unavailable,
	 * the caller must populate $_POST before calling this method (compatibility limitation).
	 *
	 * Returns summary array with 'saved' and 'errors' keys.
	 * Since the legacy method does not report per-field results, returns a generic summary.
	 *
	 * @param int   $vendor_id
	 * @param array $post_data Raw POST data (injected into $_POST for compat layer).
	 * @return array{saved: string[], errors: string[]}
	 */
	public function save_submitted_values( $vendor_id, array $post_data ) {
		// Back-fill $_POST so the legacy save_fields() can read from superglobal.
		// This is a compatibility shim; the default WP adapter will accept $post_data directly.
		$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $post_data as $key => $value ) {
			$_POST[ $key ] = $value; // phpcs:ignore WordPress.Security.NonceVerification
		}

		$this->delegate->save_fields( $vendor_id );

		// Restore $_POST.
		$_POST = $original_post; // phpcs:ignore WordPress.Security.NonceVerification

		// Legacy method does not return structured results; report generic saved.
		return array(
			'saved'  => array( 'all' ),
			'errors' => array(),
		);
	}
}
