<?php
/**
 * Default WP Media Source Provider — reads from the `tm_vendor` CPT.
 *
 * Eliminates all Dokan/user-meta dependencies for biography and banner data:
 * - Biography:    CPT `post_content`.
 * - Banner image: CPT featured image → `wp_get_attachment_url`.
 * - Banner video: `_tm_banner_video` CPT meta (attachment ID or direct URL).
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
 * Class TMP_WP_Media_Source_Provider
 *
 * @implements TMP_Media_Source_Provider
 */
class TMP_WP_Media_Source_Provider implements TMP_Media_Source_Provider {

	/**
	 * @inheritdoc
	 *
	 * Source: `post_content` of the vendor's `tm_vendor` CPT record.
	 */
	public function get_biography( int $vendor_id ): string {
		$post_id = TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
		if ( ! $post_id ) {
			return '';
		}

		return (string) get_post_field( 'post_content', $post_id, 'raw' );
	}

	/**
	 * @inheritdoc
	 *
	 * Source: CPT featured image attachment → `wp_get_attachment_url`.
	 */
	public function get_banner_image_url( int $vendor_id ): string {
		$post_id = TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
		if ( ! $post_id ) {
			return '';
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return '';
		}

		return (string) ( wp_get_attachment_url( $thumbnail_id ) ?: '' );
	}

	/**
	 * @inheritdoc
	 *
	 * Source: `_tm_banner_video` CPT meta — may be an attachment ID or a direct URL.
	 */
	public function get_banner_video_url( int $vendor_id ): string {
		$post_id = TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
		if ( ! $post_id ) {
			return '';
		}

		$meta = get_post_meta( $post_id, '_tm_banner_video', true );
		if ( ! $meta ) {
			return '';
		}

		// If meta is a numeric attachment ID, resolve to URL.
		if ( is_numeric( $meta ) && (int) $meta > 0 ) {
			return (string) ( wp_get_attachment_url( (int) $meta ) ?: '' );
		}

		// Otherwise treat as a direct URL.
		return (string) $meta;
	}
}
