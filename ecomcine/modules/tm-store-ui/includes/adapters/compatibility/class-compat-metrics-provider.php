<?php
/**
 * Compatibility adapter: social metrics, completeness, and map via user meta.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Metrics_Provider implements THO_Metrics_Provider {

	const PLATFORM_LABELS = [
		'linkedin'  => 'LinkedIn',
		'twitter'   => 'X / Twitter',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'facebook'  => 'Facebook',
		'tiktok'    => 'TikTok',
	];

	const COMPLETENESS_WEIGHTS = [
		'biography'           => 20,
		'tm_vendor_headline'  => 10,
		'tm_vendor_skills'    => 10,
		'tm_social_linkedin'  => 10,
		'tm_social_twitter'   => 10,
		'tm_social_instagram' => 10,
		'tm_social_youtube'   => 10,
	];

	public function compute_social_metrics( int $vendor_id ): array {
		$person_info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();
		$social      = isset( $person_info['social'] ) && is_array( $person_info['social'] ) ? $person_info['social'] : array();
		$links = [];
		foreach ( self::PLATFORM_LABELS as $platform => $label ) {
			$url = isset( $social[ $platform ] ) ? (string) $social[ $platform ] : (string) get_user_meta( $vendor_id, "tm_social_{$platform}", true );
			$links[] = [
				'platform' => $platform,
				'label'    => $label,
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
		$score    = 0;
		$sections = [];
		$missing  = [];

		$person_info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();
		$biography = isset( $person_info['bio'] ) ? (string) $person_info['bio'] : '';
		if ( ! $biography ) {
			$biography = (string) get_user_meta( $vendor_id, 'tm_vendor_biography', true );
		}

		$field_values = [ 'biography' => $biography ];
		foreach ( array_keys( self::COMPLETENESS_WEIGHTS ) as $key ) {
			if ( 'biography' !== $key ) {
				$field_values[ $key ] = get_user_meta( $vendor_id, $key, true );
			}
		}

		foreach ( self::COMPLETENESS_WEIGHTS as $field => $weight ) {
			$val    = $field_values[ $field ] ?? '';
			$filled = ! empty( $val );
			$sections[] = [
				'name'   => $field,
				'filled' => $filled,
				'weight' => $weight,
			];
			if ( $filled ) {
				$score += $weight;
			} else {
				$missing[] = $field;
			}
		}

		$max = array_sum( self::COMPLETENESS_WEIGHTS );

		return [
			'score'          => min( 100, $score ),
			'max'            => $max,
			'percent'        => (int) round( $score / $max * 100 ),
			'detail'         => $sections,
			'missing_fields' => $missing,
		];
	}

	public function render_map_embed( int $vendor_id, array $options = [] ): string {
		if ( ! get_option( 'tm_maps_enabled' ) ) {
			return '';
		}

		$lat = (float) get_user_meta( $vendor_id, 'tm_vendor_lat', true );
		$lng = (float) get_user_meta( $vendor_id, 'tm_vendor_lng', true );
		if ( ! $lat || ! $lng ) {
			return '';
		}

		$width  = esc_attr( $options['width']  ?? '100%' );
		$height = esc_attr( $options['height'] ?? '300px' );
		$zoom   = (int) ( $options['zoom']   ?? 14 );

		return sprintf(
			'<div class="tm-vendor-map" data-lat="%f" data-lng="%f" data-zoom="%d" style="width:%s;height:%s;"></div>',
			$lat,
			$lng,
			$zoom,
			$width,
			$height
		);
	}
}
