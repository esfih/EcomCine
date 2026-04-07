<?php
/**
 * Compatibility Media Source Provider — reads from Dokan user meta and vendor model.
 *
 * Wraps the existing data sources used by the live playlist function:
 * - Biography: `dokan_get_store_info()['vendor_biography']` with user-meta fallback.
 * - Banner image: `dokan()->vendor->get()->get_banner()` with user-meta fallback.
 * - Banner video: `dokan_banner_video` user meta → attachment URL.
 *
 * @package TM_Media_Player
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 2 — Default WP Pilot Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TMP_Compat_Media_Source_Provider
 *
 * @implements TMP_Media_Source_Provider
 */
class TMP_Compat_Media_Source_Provider implements TMP_Media_Source_Provider {

	/**
	 * @inheritdoc
	 *
	 * Primary source: Dokan store info `vendor_biography` field.
	 * Fallback: `vendor_biography` WP user meta.
	 */
	public function get_biography( int $vendor_id ): string {
		if ( $vendor_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'dokan_get_store_info' ) ) {
			$store_info = dokan_get_store_info( $vendor_id );
			if ( is_array( $store_info ) && ! empty( $store_info['vendor_biography'] ) ) {
				return (string) $store_info['vendor_biography'];
			}
		}

		return (string) get_user_meta( $vendor_id, 'vendor_biography', true );
	}

	/**
	 * @inheritdoc
	 *
	 * Primary source: Dokan vendor model `get_banner()`.
	 * Fallback: `dokan_banner` user meta (attachment ID or direct URL).
	 */
	public function get_banner_image_url( int $vendor_id ): string {
		if ( $vendor_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'dokan' ) ) {
			$vendor = dokan()->vendor->get( $vendor_id );
			if ( $vendor && method_exists( $vendor, 'get_banner' ) ) {
				$url = (string) $vendor->get_banner();
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		$meta = get_user_meta( $vendor_id, 'dokan_banner', true );
		if ( ! $meta ) {
			return '';
		}

		// Meta may be an attachment ID or a direct URL.
		if ( is_numeric( $meta ) ) {
			return (string) ( wp_get_attachment_url( (int) $meta ) ?: '' );
		}

		return (string) $meta;
	}

	/**
	 * @inheritdoc
	 *
	 * Source: `dokan_banner_video` user meta — stored as an attachment ID.
	 */
	public function get_banner_video_url( int $vendor_id ): string {
		if ( $vendor_id <= 0 ) {
			return '';
		}

		$video_id = (int) get_user_meta( $vendor_id, 'dokan_banner_video', true );
		if ( ! $video_id ) {
			return '';
		}

		return (string) ( wp_get_attachment_url( $video_id ) ?: '' );
	}
}
