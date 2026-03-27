<?php
/**
 * Default WP Dashboard Renderer — Gutenberg/block-editor scaffold (Phase 1 stub).
 *
 * Phase 1: Both methods are stubbed.  render_fields() outputs a placeholder notice
 * visible to logged-in admins so that development environments show a clear signal
 * that the method has not yet been implemented.
 *
 * Phase 2 implementation plan:
 *  - render_fields()          → register and enqueue a React-based Gutenberg sidebar
 *                               panel via @wordpress/plugins + @wordpress/edit-post
 *  - save_submitted_values()  → REST endpoint `/wp-json/dca/v1/vendor/{id}/attributes`
 *                               handled by a dedicated REST controller
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * TODO(phase-2): Implement Gutenberg / block-editor rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_Dashboard_Renderer
 *
 * @implements DCA_Dashboard_Renderer
 */
class DCA_WP_Dashboard_Renderer implements DCA_Dashboard_Renderer {

	/**
	 * @inheritdoc
	 *
	 * Phase 1: outputs an admin-only placeholder notice.
	 * Phase 2: enqueue Gutenberg sidebar component.
	 *
	 * @param int   $vendor_id
	 * @param array $store_settings
	 */
	public function render_fields( $vendor_id, array $store_settings = array() ) {
		// TODO(phase-2): Enqueue React sidebar component.
		if ( current_user_can( 'manage_options' ) ) {
			echo '<p style="color:#c7254e;background:#f9f2f4;padding:8px;border:1px solid #e1b7c0;">'
				. esc_html__( '[DCA] Default WP dashboard renderer not yet implemented. Switch to compatibility adapter.', 'dokan-category-attributes' )
				. '</p>';
		}
	}

	/**
	 * @inheritdoc
	 *
	 * Phase 1: no-op stub — returns empty saved/errors arrays.
	 * Phase 2: delegate to REST controller save handler.
	 *
	 * @param int   $vendor_id
	 * @param array $post_data
	 * @return array{saved: string[], errors: string[]}
	 */
	public function save_submitted_values( $vendor_id, array $post_data ) {
		// TODO(phase-2): Validate and persist via REST controller.
		_doing_it_wrong(
			__METHOD__,
			'Default WP dashboard renderer "save_submitted_values" not yet implemented.',
			'1.1.0'
		);

		return array(
			'saved'  => array(),
			'errors' => array( 'not_implemented' ),
		);
	}
}
