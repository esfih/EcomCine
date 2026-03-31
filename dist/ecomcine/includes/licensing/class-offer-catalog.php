<?php
/**
 * EcomCine offer catalog parity aligned with WMOS FluentCart stack.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Offer_Catalog {
	const OPTION_KEY = 'ecomcine_offer_catalog_overrides';

	/**
	 * Canonical four-offer parity map sourced from WMOS billing data.
	 */
	public static function defaults() {
		return array(
			'freemium' => array(
				'plan'                 => 'freemium',
				'product_id'           => 2566,
				'variation_id'         => 1,
				'max_site_activations' => 1,
				'allowances'           => array(
					'ai_queries_hour'  => 1,
					'ai_queries_day'   => 1,
					'ai_queries_month' => 30,
					'remixes_max'      => 1,
					'promotions_max'   => 1,
					'queue_max'        => 10,
					'ai_mode'          => 'mutualized_ai',
				),
			),
			'solo' => array(
				'plan'                 => 'solo',
				'product_id'           => 2569,
				'variation_id'         => 2,
				'max_site_activations' => 3,
				'allowances'           => array(
					'ai_queries_hour'  => 12,
					'ai_queries_day'   => 120,
					'ai_queries_month' => 2500,
					'remixes_max'      => 10,
					'promotions_max'   => 10,
					'queue_max'        => 50,
					'ai_mode'          => 'mutualized_ai',
				),
			),
			'maestro' => array(
				'plan'                 => 'maestro',
				'product_id'           => 2571,
				'variation_id'         => 3,
				'max_site_activations' => 10,
				'allowances'           => array(
					'ai_queries_hour'  => 30,
					'ai_queries_day'   => 360,
					'ai_queries_month' => 7200,
					'remixes_max'      => 150,
					'promotions_max'   => 75,
					'queue_max'        => 100,
					'ai_mode'          => 'mutualized_ai',
				),
			),
			'agency' => array(
				'plan'                 => 'agency',
				'product_id'           => 2573,
				'variation_id'         => 4,
				'max_site_activations' => 100,
				'allowances'           => array(
					'ai_queries_hour'  => 100,
					'ai_queries_day'   => 1000,
					'ai_queries_month' => 30000,
					'remixes_max'      => 10000,
					'promotions_max'   => 10000,
					'queue_max'        => 1000,
					'ai_mode'          => 'confidential_ai',
				),
			),
		);
	}

	/**
	 * Return merged catalog with optional admin overrides.
	 */
	public static function get_catalog() {
		$catalog = self::defaults();
		$stored  = get_option( self::OPTION_KEY, array() );

		if ( is_array( $stored ) ) {
			foreach ( $stored as $slug => $row ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug || ! is_array( $row ) ) {
					continue;
				}
				$catalog[ $slug ] = self::normalize_row( $slug, $row );
			}
		}

		return $catalog;
	}

	/**
	 * Resolve offer details from license key token hints.
	 */
	public static function resolve_from_license_key( $license_key ) {
		$key = strtoupper( trim( (string) $license_key ) );
		if ( '' === $key ) {
			return array();
		}

		$catalog = self::get_catalog();
		$token_map = array(
			'FREEMIUM' => 'freemium',
			'FREE'     => 'freemium',
			'SOLO'     => 'solo',
			'MAESTRO'  => 'maestro',
			'AGENCY'   => 'agency',
		);

		foreach ( $token_map as $token => $slug ) {
			if ( false !== strpos( $key, $token ) && isset( $catalog[ $slug ] ) ) {
				return $catalog[ $slug ];
			}
		}

		return array();
	}

	/**
	 * Resolve offer from FluentCart product and variation IDs.
	 */
	public static function resolve_from_product_reference( $product_id, $variation_id = 0 ) {
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;
		if ( $product_id <= 0 ) {
			return array();
		}

		foreach ( self::get_catalog() as $row ) {
			$row_product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
			$row_variation_id = isset( $row['variation_id'] ) ? (int) $row['variation_id'] : 0;
			if ( $row_product_id !== $product_id ) {
				continue;
			}
			if ( $variation_id > 0 && $row_variation_id > 0 && $row_variation_id !== $variation_id ) {
				continue;
			}
			return $row;
		}

		return array();
	}

	/**
	 * Build option-compatible rows from extracted WMOS seed payload.
	 */
	public static function build_overrides_from_seed( $seed_rows ) {
		$normalized = array();
		if ( ! is_array( $seed_rows ) ) {
			return $normalized;
		}

		foreach ( $seed_rows as $slug => $row ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! is_array( $row ) ) {
				continue;
			}
			$normalized[ $slug ] = self::normalize_row( $slug, $row );
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function normalize_row( $slug, $row ) {
		$normalized = array(
			'plan'                 => sanitize_key( (string) ( $row['plan'] ?? $slug ) ),
			'product_id'           => max( 0, (int) ( $row['product_id'] ?? 0 ) ),
			'variation_id'         => max( 0, (int) ( $row['variation_id'] ?? 0 ) ),
			'max_site_activations' => max( 1, (int) ( $row['max_site_activations'] ?? 1 ) ),
			'allowances'           => array(),
		);

		if ( isset( $row['allowances'] ) && is_array( $row['allowances'] ) ) {
			$normalized['allowances'] = $row['allowances'];
		}

		return $normalized;
	}
}
