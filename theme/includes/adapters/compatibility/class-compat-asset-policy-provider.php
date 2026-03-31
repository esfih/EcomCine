<?php
/**
 * Compatibility adapter: asset policy using Dokan + WC handle lists.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Asset_Policy_Provider implements THO_Asset_Policy_Provider {

	public function get_asset_policy( array $context ): array {
		if ( empty( $context['is_vendor_page'] ) && empty( $context['is_listing'] ) ) {
			return $this->empty_policy();
		}

		return [
			'dequeue_scripts' => [
				'dokan-scripts',
				'dokan-vendor-registration',
				'dokan-geo-locator',
			],
			'dequeue_styles'  => [
				'dokan-style',
				'dokan-vendor-registration-style',
			],
			'keep_scripts'    => [
				// Retained for tvbm checkout flow.
				'wc-checkout',
				'wc-add-to-cart',
				'woocommerce',
			],
		];
	}

	private function empty_policy(): array {
		return [
			'dequeue_scripts' => [],
			'dequeue_styles'  => [],
			'keep_scripts'    => [],
		];
	}
}
