<?php
/**
 * Compatibility adapter: vendor profile meta via WP user meta + Dokan hooks.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Profile_Meta_Provider implements THO_Profile_Meta_Provider {

	/** Weighted completeness fields: key => points. */
	const COMPLETENESS_WEIGHTS = [
		'biography'           => 20,
		'tm_vendor_headline'  => 10,
		'tm_vendor_skills'    => 10,
		'tm_social_linkedin'  => 10,
		'tm_social_twitter'   => 10,
		'tm_social_instagram' => 10,
		'tm_social_youtube'   => 10,
	];

	public function get_vendor_profile_meta( int $vendor_id ): array {
		$person_info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();
		$biography = isset( $person_info['bio'] ) ? (string) $person_info['bio'] : '';
		if ( ! $biography ) {
			$biography = (string) get_user_meta( $vendor_id, 'tm_vendor_biography', true );
		}

		$headline  = (string) get_user_meta( $vendor_id, 'tm_vendor_headline', true );
		$skills_raw = get_user_meta( $vendor_id, 'tm_vendor_skills', true );
		$skills     = is_array( $skills_raw )
			? $skills_raw
			: ( $skills_raw ? array_filter( array_map( 'trim', explode( ',', (string) $skills_raw ) ) ) : [] );

		$social = isset( $person_info['social'] ) && is_array( $person_info['social'] ) ? $person_info['social'] : [];
		foreach ( [ 'linkedin', 'twitter', 'instagram', 'youtube', 'facebook' ] as $platform ) {
			$val = isset( $social[ $platform ] ) ? (string) $social[ $platform ] : (string) get_user_meta( $vendor_id, "tm_social_{$platform}", true );
			$social[ $platform ] = $val ?: null;
		}

		$completeness = $this->compute_completeness_score( $vendor_id )['score'];

		return [
			'biography'    => $biography,
			'headline'     => $headline,
			'skills'       => array_values( $skills ),
			'social'       => $social,
			'completeness' => $completeness,
		];
	}

	public function save_vendor_profile_meta( int $vendor_id, array $data ): bool {
		$ok = true;
		foreach ( $data as $key => $value ) {
			$ok = update_user_meta( $vendor_id, $key, $value ) !== false && $ok;
		}
		// Refresh cached completeness score.
		$score = $this->compute_completeness_score( $vendor_id );
		update_user_meta( $vendor_id, 'tm_completeness_score', $score['score'] );
		return $ok;
	}

	public function compute_completeness_score( int $vendor_id ): array {
		$score         = 0;
		$missing       = [];
		$person_info   = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();
		$biography     = isset( $person_info['bio'] ) ? (string) $person_info['bio'] : '';
		if ( ! $biography ) {
			$biography = (string) get_user_meta( $vendor_id, 'tm_vendor_biography', true );
		}

		$field_values = [ 'biography' => $biography ];
		foreach ( [ 'tm_vendor_headline', 'tm_vendor_skills', 'tm_social_linkedin', 'tm_social_twitter', 'tm_social_instagram', 'tm_social_youtube' ] as $key ) {
			$field_values[ $key ] = get_user_meta( $vendor_id, $key, true );
		}

		foreach ( self::COMPLETENESS_WEIGHTS as $field => $weight ) {
			$val = $field_values[ $field ] ?? '';
			if ( ! empty( $val ) ) {
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
			'detail'         => array_combine(
				array_keys( self::COMPLETENESS_WEIGHTS ),
				array_map( fn( $f, $w ) => [ 'weight' => $w, 'has' => ! empty( $field_values[ $f ] ?? '' ) ],
					array_keys( self::COMPLETENESS_WEIGHTS ), array_values( self::COMPLETENESS_WEIGHTS ) )
			),
			'missing_fields' => $missing,
		];
	}
}
