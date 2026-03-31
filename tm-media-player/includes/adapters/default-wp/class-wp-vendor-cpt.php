<?php
/**
 * WP Vendor CPT — Registers and manages the `tm_vendor` custom post type.
 *
 * One `tm_vendor` post per vendor user. The post ID is linked to the WP user
 * via the `_tm_vendor_user_id` post meta and the inverse lookup key
 * `_tm_vendor_cpt_id` stored on user meta.
 *
 * Post structure:
 *   post_type      = 'tm_vendor'
 *   post_author    = vendor user ID
 *   post_content   = vendor biography (replaces dokan_get_store_info biography)
 *   post_thumbnail = banner image (replaces Dokan banner attachment)
 *   meta:
 *     _tm_vendor_user_id  = (int) vendor user ID
 *     _tm_banner_video    = (int|string) attachment ID or direct video URL
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
 * Class TMP_WP_Vendor_CPT
 */
class TMP_WP_Vendor_CPT {

	const POST_TYPE = 'tm_vendor';

	/**
	 * Register the CPT. Hooked to 'init'.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'TM Vendors', 'tm-media-player' ),
				'labels'              => array(
					'name'          => __( 'TM Vendors', 'tm-media-player' ),
					'singular_name' => __( 'TM Vendor', 'tm-media-player' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields' ),
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);
	}

	/**
	 * Retrieve the CPT post ID for a given vendor user.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return int Post ID, or 0 if no CPT record exists for this vendor.
	 */
	public static function get_post_id_for_vendor( int $vendor_id ): int {
		// Fast path via inverse meta on the user.
		$cpt_id = (int) get_user_meta( $vendor_id, '_tm_vendor_cpt_id', true );
		if ( $cpt_id && 'publish' === get_post_status( $cpt_id ) ) {
			return $cpt_id;
		}

		// Slower fallback: query CPT by meta.
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'   => '_tm_vendor_user_id',
						'value' => $vendor_id,
						'type'  => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$post_id = (int) $posts[0];
		// Cache the result on the user so future lookups are fast.
		update_user_meta( $vendor_id, '_tm_vendor_cpt_id', $post_id );

		return $post_id;
	}

	/**
	 * Create or update the `tm_vendor` CPT record for a vendor user.
	 *
	 * @param int   $vendor_id WP user ID.
	 * @param array $data      {
	 *   @type string $biography    Post content (biography).
	 *   @type int    $banner_image Attachment ID for the banner image.
	 *   @type mixed  $banner_video Attachment ID or direct URL for the banner video.
	 * }
	 * @return int|\WP_Error New or existing post ID on success, WP_Error on failure.
	 */
	public static function upsert_vendor( int $vendor_id, array $data ) {
		$post_id = self::get_post_id_for_vendor( $vendor_id );

		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'post_author'  => $vendor_id,
			'post_title'   => 'tm_vendor_' . $vendor_id,
			'post_content' => isset( $data['biography'] ) ? wp_kses_post( $data['biography'] ) : '',
		);

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		// Link user ↔ CPT.
		update_post_meta( $post_id, '_tm_vendor_user_id', $vendor_id );
		update_user_meta( $vendor_id, '_tm_vendor_cpt_id', $post_id );

		// Banner image (featured image).
		if ( isset( $data['banner_image'] ) && (int) $data['banner_image'] > 0 ) {
			set_post_thumbnail( $post_id, (int) $data['banner_image'] );
		}

		// Banner video.
		if ( isset( $data['banner_video'] ) ) {
			update_post_meta( $post_id, '_tm_banner_video', sanitize_text_field( (string) $data['banner_video'] ) );
		}

		return $post_id;
	}
}
