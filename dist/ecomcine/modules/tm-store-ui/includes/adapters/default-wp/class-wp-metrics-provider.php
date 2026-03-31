<?php
/**
 * Default-WP adapter: social metrics and map embed via tm_vendor CPT post meta.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_WP_Metrics_Provider implements THO_Metrics_Provider {

	private const PLATFORM_LABELS = [
		'instagram' => 'Instagram',
		'twitter'   => 'Twitter / X',
		'youtube'   => 'YouTube',
		'linkedin'  => 'LinkedIn',
		'tiktok'    => 'TikTok',
		'imdb'      => 'IMDb',
	];

	private const COMPLETENESS_WEIGHTS = [
		'biography' => 20,
		'headline'  => 10,
		'skills'    => 10,
		'banner'    => 10,
		'social'    => 10,
		'reels'     => 10,
		'location'  => 10,
	];

	public function compute_social_metrics( int $vendor_id ): array {
		$post_id = $this->get_cpt_post_id( $vendor_id );
		$links   = [];

		foreach ( array_keys( self::PLATFORM_LABELS ) as $platform ) {
			$url = $post_id
				? (string) get_post_meta( $post_id, '_tm_social_' . $platform, true )
				: '';

			$links[] = [
				'platform' => $platform,
				'label'    => self::PLATFORM_LABELS[ $platform ],
				'url'      => $url,
				'active'   => ! empty( $url ),
			];
		}

		return [
			'links'        => $links,
			'active_count' => count( array_filter( $links, fn( $l ) => $l['active'] ) ),
		];
	}

	public function compute_completeness( int $vendor_id ): array {
		$post_id = $this->get_cpt_post_id( $vendor_id );
		$score   = 0;
		$detail  = [];

		foreach ( self::COMPLETENESS_WEIGHTS as $key => $weight ) {
			$has = false;
			if ( 'social' === $key ) {
				if ( $post_id ) {
					foreach ( array_keys( self::PLATFORM_LABELS ) as $platform ) {
						if ( get_post_meta( $post_id, '_tm_social_' . $platform, true ) ) {
							$has = true;
							break;
						}
					}
				}
			} elseif ( 'reels' === $key ) {
				$posts = get_posts( [
					'post_type'      => 'tm_media',
					'author'         => $vendor_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				] );
				$has = ! empty( $posts );
			} elseif ( 'location' === $key ) {
				$has = $post_id && (
					get_post_meta( $post_id, '_tm_vendor_lat', true ) ||
					get_post_meta( $post_id, '_tm_vendor_lng', true )
				);
			} elseif ( 'biography' === $key ) {
				$post = $post_id ? get_post( $post_id ) : null;
				$has  = $post && ! empty( $post->post_content );
			} elseif ( $post_id ) {
				$meta_key = '_tm_vendor_' . $key;
				$has      = ! empty( get_post_meta( $post_id, $meta_key, true ) );
			}

			if ( $has ) {
				$score += $weight;
			}
			$detail[ $key ] = [ 'weight' => $weight, 'has' => $has ];
		}

		$max = array_sum( self::COMPLETENESS_WEIGHTS );

		return [
			'score'   => min( 100, $score ),
			'max'     => $max,
			'detail'  => $detail,
			'percent' => (int) round( $score / $max * 100 ),
		];
	}

	public function render_map_embed( int $vendor_id, array $options = [] ): string {
		$post_id = $this->get_cpt_post_id( $vendor_id );

		if ( ! $post_id ) {
			return '';
		}

		$lat = (float) get_post_meta( $post_id, '_tm_vendor_lat', true );
		$lng = (float) get_post_meta( $post_id, '_tm_vendor_lng', true );

		if ( empty( $lat ) && empty( $lng ) ) {
			return '';
		}

		$maps_enabled = get_option( 'tm_maps_enabled', '1' );
		if ( '0' === $maps_enabled ) {
			return '';
		}

		$zoom  = isset( $options['zoom'] ) ? (int) $options['zoom'] : 12;
		$width = esc_attr( $options['width'] ?? '100%' );
		$height = esc_attr( $options['height'] ?? '300' );

		return sprintf(
			'<iframe class="tm-vendor-map" src="https://maps.google.com/maps?q=%1$F,%2$F&z=%3$d&output=embed" width="%4$s" height="%5$s" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
			$lat,
			$lng,
			$zoom,
			$width,
			$height
		);
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function get_cpt_post_id( int $vendor_id ): int {
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
}
