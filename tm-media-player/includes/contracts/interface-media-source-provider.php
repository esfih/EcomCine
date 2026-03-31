<?php
/**
 * Media Source Provider Contract
 *
 * Abstracts vendor data sources (biography, banner image, banner video)
 * used to assemble the media player playlist payload (tmp-001 / tmp-002).
 *
 * Implementations must not leak vendor-stack details (Dokan user meta,
 * WP CPT internals) into callers — callers depend only on this interface.
 *
 * @package TM_Media_Player
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TMP_Media_Source_Provider {

	/**
	 * Return the vendor's biography / profile description as a raw HTML/shortcode string.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 * @return string Biography content, or '' if none is set.
	 */
	public function get_biography( int $vendor_id ): string;

	/**
	 * Return a fully-resolved URL for the vendor's banner image.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 * @return string Absolute attachment URL, or '' if no banner is set.
	 */
	public function get_banner_image_url( int $vendor_id ): string;

	/**
	 * Return a fully-resolved URL for the vendor's banner video.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 * @return string Absolute video URL or attachment URL, or '' if none is set.
	 */
	public function get_banner_video_url( int $vendor_id ): string;
}
