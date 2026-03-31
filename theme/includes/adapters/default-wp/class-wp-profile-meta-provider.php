<?php
/**
 * Default-WP adapter: vendor profile meta via tm_vendor CPT post meta.
 *
 * Reads from the tm_vendor CPT registered by tm-media-player rather than
 * from Dokan user-meta or dokan_profile_settings option arrays.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_WP_Profile_Meta_Provider implements THO_Profile_Meta_Provider {

	/** Same weight map used by the compat adapter. */
	private const COMPLETENESS_WEIGHTS = [
		'biography'  => 20,
		'headline'   => 10,
		'skills'     => 10,
		'banner'     => 10,
		'social'     => 10, // any social link counts once
		'reels'      => 10,
		'location'   => 10,
	];

	// -------------------------------------------------------------------------
	// THO_Profile_Meta_Provider contract
	// -------------------------------------------------------------------------

	public function get_vendor_profile_meta( int $vendor_id ): array {
		$post_id = $this->get_cpt_post_id( $vendor_id );

		if ( ! $post_id ) {
			return $this->empty_meta();
		}

		$post = get_post( $post_id );

		return [
			'biography' => $post ? $post->post_content : '',
			'headline'  => (string) get_post_meta( $post_id, '_tm_vendor_headline', true ),
			'skills'    => array_filter( (array) get_post_meta( $post_id, '_tm_vendor_skills', true ) ),
			'social'    => [
				'instagram' => (string) get_post_meta( $post_id, '_tm_social_instagram', true ),
				'twitter'   => (string) get_post_meta( $post_id, '_tm_social_twitter', true ),
				'youtube'   => (string) get_post_meta( $post_id, '_tm_social_youtube', true ),
				'linkedin'  => (string) get_post_meta( $post_id, '_tm_social_linkedin', true ),
				'tiktok'    => (string) get_post_meta( $post_id, '_tm_social_tiktok', true ),
				'imdb'      => (string) get_post_meta( $post_id, '_tm_social_imdb', true ),
			],
			'lat'       => (float) get_post_meta( $post_id, '_tm_vendor_lat', true ),
			'lng'       => (float) get_post_meta( $post_id, '_tm_vendor_lng', true ),
		];
	}

	public function save_vendor_profile_meta( int $vendor_id, array $data ): bool {
		$post_id = $this->get_cpt_post_id( $vendor_id );

		if ( ! $post_id ) {
			return false;
		}

		if ( isset( $data['biography'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_content' => sanitize_textarea_field( $data['biography'] ) ] );
		}
		if ( isset( $data['headline'] ) ) {
			update_post_meta( $post_id, '_tm_vendor_headline', sanitize_text_field( $data['headline'] ) );
		}
		if ( isset( $data['skills'] ) ) {
			update_post_meta( $post_id, '_tm_vendor_skills', array_map( 'sanitize_text_field', (array) $data['skills'] ) );
		}
		$social_fields = [ 'instagram', 'twitter', 'youtube', 'linkedin', 'tiktok', 'imdb' ];
		foreach ( $social_fields as $field ) {
			if ( isset( $data[ 'social_' . $field ] ) ) {
				update_post_meta( $post_id, '_tm_social_' . $field, esc_url_raw( $data[ 'social_' . $field ] ) );
			}
		}

		return true;
	}

	public function compute_completeness_score( int $vendor_id ): array {
		$meta   = $this->get_vendor_profile_meta( $vendor_id );
		$score  = 0;
		$detail = [];

		foreach ( self::COMPLETENESS_WEIGHTS as $key => $weight ) {
			$has = false;
			if ( 'social' === $key ) {
				$has = ! empty( array_filter( $meta['social'] ) );
			} elseif ( 'reels' === $key ) {
				// Check if vendor has any media CPT posts (proxy for reels).
				$has = $this->vendor_has_media( $vendor_id );
			} elseif ( 'location' === $key ) {
				$has = ! empty( $meta['lat'] ) || ! empty( $meta['lng'] );
			} else {
				$has = ! empty( $meta[ $key ] );
			}

			if ( $has ) {
				$score += $weight;
			}
			$detail[ $key ] = [ 'weight' => $weight, 'has' => $has ];
		}

		return [
			'score'   => min( 100, $score ),
			'max'     => array_sum( self::COMPLETENESS_WEIGHTS ),
			'detail'  => $detail,
			'percent' => (int) round( $score / array_sum( self::COMPLETENESS_WEIGHTS ) * 100 ),
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_cpt_post_id( int $vendor_id ): int {
		// Prefer the static helper from tm-media-player's CPT class if available.
		if ( class_exists( 'TMP_WP_Vendor_CPT' ) ) {
			return (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
		}

		$posts = get_posts( [
			'post_type'      => 'tm_vendor',
			'meta_key'       => '_tm_vendor_user_id',
			'meta_value'     => $vendor_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	private function vendor_has_media( int $vendor_id ): bool {
		$posts = get_posts( [
			'post_type'      => 'tm_media',
			'author'         => $vendor_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		return ! empty( $posts );
	}

	private function empty_meta(): array {
		return [
			'biography' => '',
			'headline'  => '',
			'skills'    => [],
			'social'    => [
				'instagram' => '', 'twitter'  => '',
				'youtube'   => '', 'linkedin' => '',
				'tiktok'    => '', 'imdb'     => '',
			],
			'lat' => 0.0,
			'lng' => 0.0,
		];
	}
}
