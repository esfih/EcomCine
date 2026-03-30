<?php
/**
 * Default-WP adapter: asset policy without Dokan handles.
 *
 * When Dokan is removed, the Dokan-specific handles are also gone, so the
 * dequeue list is shorter. Same WC checkout handles retained for tvbm.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_WP_Asset_Policy_Provider implements THO_Asset_Policy_Provider {

	public function get_asset_policy( array $context ): array {
		if ( empty( $context['is_vendor_page'] ) && empty( $context['is_listing'] ) ) {
			return [
				'dequeue_scripts' => [],
				'dequeue_styles'  => [],
				'keep_scripts'    => [],
			];
		}

		// In default-WP mode Dokan scripts are not registered, so no Dokan handles to remove.
		return [
			'dequeue_scripts' => [],
			'dequeue_styles'  => [],
			'keep_scripts'    => [
				// Retained for tvbm if WC is still active in hybrid mode.
				'wc-checkout',
				'wc-add-to-cart',
				'woocommerce',
			],
		];
	}
}
