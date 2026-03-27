<?php
/**
 * Plugin Name: EcomCine Control Plane
 * Description: Private billing control-plane plugin for license activation and entitlement resolution.
 * Version: 0.1.0
 * Author: EcomCine
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMCINE_CP_VERSION', '0.1.0' );

final class EcomCine_CP_Plan_Registry {
	const OPTION_KEY = 'ecomcine_cp_plan_mappings_v1';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$defaults = array(
			array(
				'plan'         => 'freemium',
				'product_id'   => 2566,
				'variation_id' => 1,
			),
			array(
				'plan'         => 'solo',
				'product_id'   => 2569,
				'variation_id' => 2,
			),
			array(
				'plan'         => 'maestro',
				'product_id'   => 2571,
				'variation_id' => 3,
			),
			array(
				'plan'         => 'agency',
				'product_id'   => 2573,
				'variation_id' => 4,
			),
		);

		$custom = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $custom ) || empty( $custom ) ) {
			return $defaults;
		}

		$rows = array_merge( $defaults, $custom );
		$normalized = array();
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$plan = sanitize_key( (string) ( $row['plan'] ?? '' ) );
			$product_id = (int) ( $row['product_id'] ?? 0 );
			$variation_id = max( 0, (int) ( $row['variation_id'] ?? 0 ) );
			if ( '' === $plan || $product_id <= 0 ) {
				continue;
			}
			$key = $product_id . ':' . $variation_id;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$normalized[] = array(
				'plan'         => $plan,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			);
		}

		return $normalized;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function resolve_from_product_reference( $product_id, $variation_id = 0 ) {
		$product_id = (int) $product_id;
		$variation_id = (int) $variation_id;
		foreach ( self::all() as $row ) {
			if ( (int) $row['product_id'] !== $product_id ) {
				continue;
			}
			if ( $variation_id > 0 && (int) $row['variation_id'] !== $variation_id ) {
				continue;
			}
			return $row;
		}

		return array();
	}
}

final class EcomCine_CP_Activation_Repository {
	const OPTION_KEY = 'wmos_cp_activations';

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		$value = get_option( self::OPTION_KEY, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param array<string,mixed> $activation
	 */
	public static function save( $activation_id, $activation ) {
		$all = self::all();
		$all[ (string) $activation_id ] = $activation;
		update_option( self::OPTION_KEY, $all, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function find( $activation_id ) {
		$all = self::all();
		$hit = $all[ (string) $activation_id ] ?? array();
		return is_array( $hit ) ? $hit : array();
	}
}

final class EcomCine_CP_Request_Verifier {
	const OPTION_OPS_ERROR_LOG = 'ecomcine_cp_ops_error_log';

	/**
	 * @param array<string,mixed> $context
	 */
	public static function log_ops_error( $code, $context = array() ) {
		$log = get_option( self::OPTION_OPS_ERROR_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'at'      => gmdate( 'c' ),
			'code'    => sanitize_key( (string) $code ),
			'context' => is_array( $context ) ? $context : array(),
		);

		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}

		update_option( self::OPTION_OPS_ERROR_LOG, $log, false );
	}
}

final class EcomCine_CP_Billing_Allowance_Repository {
	const META_KEY_ALLOWANCES       = 'wmos_allowances_v1';
	const META_KEY_LICENSE_SETTINGS = 'license_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function resolve_limits( $product_id, $variation_id = 0 ) {
		$product_id = (int) $product_id;
		$variation_id = (int) $variation_id;
		if ( $product_id <= 0 ) {
			return array();
		}

		$payload = self::read_product_meta_payload( $product_id, self::META_KEY_ALLOWANCES );
		if ( ! empty( $payload ) ) {
			$limits = self::extract_limits_from_payload( $payload, $variation_id );
			if ( ! empty( $limits ) ) {
				return $limits;
			}
		}

		$settings = self::read_product_meta_payload( $product_id, self::META_KEY_LICENSE_SETTINGS );
		if ( ! empty( $settings ) ) {
			$limit = (int) ( $settings['activation_limit'] ?? $settings['activations_limit'] ?? 0 );
			if ( $limit > 0 ) {
				return array( 'max_site_activations' => $limit );
			}
			if ( isset( $settings['variations'] ) && is_array( $settings['variations'] ) ) {
				foreach ( $settings['variations'] as $variation ) {
					if ( ! is_array( $variation ) ) {
						continue;
					}
					$v_id = (int) ( $variation['variation_id'] ?? 0 );
					if ( $variation_id > 0 && $v_id !== $variation_id ) {
						continue;
					}
					$v_limit = (int) ( $variation['activation_limit'] ?? $variation['activations_limit'] ?? 0 );
					if ( $v_limit > 0 ) {
						return array( 'max_site_activations' => $v_limit );
					}
				}
			}
		}

		return array();
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function read_product_meta_payload( $product_id, $meta_key ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return array();
		}

		$table = self::resolve_product_meta_table( $wpdb );
		if ( '' === $table ) {
			return array();
		}

		$id_column = self::resolve_meta_object_column( $wpdb, $table );
		if ( '' === $id_column ) {
			return array();
		}

		foreach ( self::resolve_candidate_ids( $wpdb, (int) $product_id ) as $candidate_id ) {
			$sql = $wpdb->prepare(
				'SELECT meta_value FROM `' . esc_sql( $table ) . '` WHERE `' . esc_sql( $id_column ) . '` = %d AND meta_key = %s LIMIT 1',
				(int) $candidate_id,
				(string) $meta_key
			);
			$raw = $wpdb->get_var( $sql );
			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				continue;
			}
			$decoded = maybe_unserialize( $raw );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
			$json = json_decode( $raw, true );
			if ( is_array( $json ) ) {
				return $json;
			}
		}

		return array();
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function extract_limits_from_payload( $payload, $variation_id ) {
		$variation_id = (int) $variation_id;
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$overrides = array();
		if ( isset( $payload['variation_overrides'][ (string) $variation_id ] ) && is_array( $payload['variation_overrides'][ (string) $variation_id ] ) ) {
			$overrides = $payload['variation_overrides'][ (string) $variation_id ];
		}
		$merged = array_merge( $payload, $overrides );
		unset( $merged['variation_overrides'] );

		$limits = array();
		foreach ( $merged as $key => $value ) {
			if ( is_scalar( $value ) || is_array( $value ) ) {
				$limits[ (string) $key ] = $value;
			}
		}

		return $limits;
	}

	private static function resolve_product_meta_table( $wpdb ) {
		$prefix = (string) $wpdb->prefix;
		$candidates = array(
			$prefix . 'fct_product_meta',
			$prefix . 'fluentcart_product_meta',
			$prefix . 'fc_product_meta',
			$prefix . 'postmeta',
		);
		foreach ( $candidates as $table ) {
			$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found === $table ) {
				return $table;
			}
		}

		return '';
	}

	private static function resolve_meta_object_column( $wpdb, $table ) {
		$rows = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( (string) $table ) . '`', ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return '';
		}

		$columns = array_map( function( $row ) {
			return strtolower( (string) ( $row['Field'] ?? '' ) );
		}, $rows );

		foreach ( array( 'object_id', 'post_id', 'product_id' ) as $column ) {
			if ( in_array( $column, $columns, true ) ) {
				return $column;
			}
		}

		return '';
	}

	/**
	 * @return array<int,int>
	 */
	private static function resolve_candidate_ids( $wpdb, $product_id ) {
		$ids = array( (int) $product_id );
		$fc_table = $wpdb->prefix . 'fluentcart_products';
		$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fc_table ) );
		if ( $found === $fc_table ) {
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM `' . esc_sql( $fc_table ) . '` WHERE id = %d LIMIT 1', (int) $product_id ) );
			if ( $post_id > 0 && $post_id !== $product_id ) {
				$ids[] = $post_id;
			}
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}
}

final class EcomCine_CP_FluentCart_Native_Resolver {
	/**
	 * @return array<string,mixed>
	 */
	public static function resolve_from_license_key( $license_key ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}

		$key = strtoupper( trim( (string) $license_key ) );
		if ( '' === $key ) {
			return array();
		}

		$tables = self::candidate_tables( $wpdb );
		foreach ( $tables as $table ) {
			$columns = self::table_columns( $wpdb, $table );
			$key_columns = self::key_columns( $columns );
			if ( empty( $key_columns ) ) {
				continue;
			}

			$row = self::find_license_row( $wpdb, $table, $key_columns, $key );
			if ( empty( $row ) ) {
				continue;
			}

			$product_id = self::first_int( $row, array( 'product_id', 'fluentcart_product_id', 'item_id' ) );
			$variation_id = self::first_int( $row, array( 'variation_id', 'variant_id' ) );
			$limit = self::first_int( $row, array( 'limit', 'activation_limit', 'max_activations', 'activations_limit' ) );

			if ( $product_id > 0 ) {
				$mapped = EcomCine_CP_Plan_Registry::resolve_from_product_reference( $product_id, $variation_id );
				if ( ! empty( $mapped ) ) {
					if ( $variation_id > 0 ) {
						$mapped['variation_id'] = $variation_id;
					}
					if ( $limit > 0 ) {
						$mapped['max_site_activations'] = $limit;
					}
					return $mapped;
				}
			}

			$hint = self::first_text( $row, array( 'plan', 'plan_slug', 'product_slug', 'product_name', 'item_name', 'title' ) );
			if ( '' !== $hint ) {
				$hint_map = self::map_from_hint( $hint );
				if ( ! empty( $hint_map ) ) {
					if ( $product_id > 0 ) {
						$hint_map['product_id'] = $product_id;
					}
					if ( $variation_id > 0 ) {
						$hint_map['variation_id'] = $variation_id;
					}
					if ( $limit > 0 ) {
						$hint_map['max_site_activations'] = $limit;
					}
					return $hint_map;
				}
			}

			if ( $product_id > 0 ) {
				return array(
					'plan'                 => 'paid',
					'product_id'           => $product_id,
					'variation_id'         => max( 0, $variation_id ),
					'max_site_activations' => max( 1, $limit > 0 ? $limit : 1 ),
				);
			}
		}

		return array();
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function debug_probe( $license_key ) {
		global $wpdb;
		$result = array(
			'candidate_tables'         => 0,
			'tables_with_key_column'   => 0,
			'matched_table'            => '',
			'matched_product_id'       => 0,
			'matched_variation_id'     => 0,
			'matched_activation_limit' => 0,
			'match_mode'               => '',
			'mapped_plan'              => '',
		);

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return $result;
		}

		$key = strtoupper( trim( (string) $license_key ) );
		if ( '' === $key ) {
			return $result;
		}

		$tables = self::candidate_tables( $wpdb );
		$result['candidate_tables'] = count( $tables );

		foreach ( $tables as $table ) {
			$columns = self::table_columns( $wpdb, $table );
			$key_columns = self::key_columns( $columns );
			if ( empty( $key_columns ) ) {
				continue;
			}
			++$result['tables_with_key_column'];

			$row = self::find_license_row( $wpdb, $table, $key_columns, $key );
			if ( empty( $row ) ) {
				continue;
			}

			$product_id = self::first_int( $row, array( 'product_id', 'fluentcart_product_id', 'item_id' ) );
			$variation_id = self::first_int( $row, array( 'variation_id', 'variant_id' ) );
			$limit = self::first_int( $row, array( 'limit', 'activation_limit', 'max_activations', 'activations_limit' ) );

			$result['matched_table']            = (string) $table;
			$result['matched_product_id']       = $product_id;
			$result['matched_variation_id']     = $variation_id;
			$result['matched_activation_limit'] = $limit;
			$result['match_mode']               = 'exact_key_column';

			$mapped = $product_id > 0 ? EcomCine_CP_Plan_Registry::resolve_from_product_reference( $product_id, $variation_id ) : array();
			if ( ! empty( $mapped ) ) {
				$result['mapped_plan'] = (string) ( $mapped['plan'] ?? '' );
			}

			return $result;
		}

		return $result;
	}

	/**
	 * @return array<int,string>
	 */
	private static function candidate_tables( $wpdb ) {
		$prefix = (string) $wpdb->prefix;
		$like = $wpdb->esc_like( $prefix ) . '%';
		$all = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( ! is_array( $all ) ) {
			return array();
		}

		$candidates = array();
		foreach ( $all as $table ) {
			$name = strtolower( (string) $table );
			$is_fc = false !== strpos( $name, 'fluentcart' )
				|| false !== strpos( $name, 'fluent_cart' )
				|| false !== strpos( $name, 'flc' )
				|| false !== strpos( $name, 'fct_' );
			if ( ! $is_fc ) {
				continue;
			}
			$has_key = false !== strpos( $name, 'license' )
				|| false !== strpos( $name, 'licence' )
				|| false !== strpos( $name, 'key' )
				|| false !== strpos( $name, 'item' )
				|| false !== strpos( $name, 'subscription' )
				|| false !== strpos( $name, 'order' )
				|| false !== strpos( $name, 'purchase' );
			if ( ! $has_key ) {
				continue;
			}
			$candidates[] = (string) $table;
		}

		return $candidates;
	}

	/**
	 * @return array<int,string>
	 */
	private static function table_columns( $wpdb, $table ) {
		$rows = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( (string) $table ) . '`', ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_column( $rows, 'Field' );
	}

	/**
	 * @param array<int,string> $columns
	 * @return array<int,string>
	 */
	private static function key_columns( $columns ) {
		$key_names = array( 'license_key', 'key_code', 'activation_key', 'serial', 'license_code', 'license', 'key' );
		return array_values( array_filter( $columns, function( $col ) use ( $key_names ) {
			return in_array( strtolower( (string) $col ), $key_names, true );
		} ) );
	}

	/**
	 * @param array<int,string> $key_columns
	 * @return array<string,mixed>
	 */
	private static function find_license_row( $wpdb, $table, $key_columns, $key ) {
		foreach ( $key_columns as $col ) {
			$sql = $wpdb->prepare(
				'SELECT * FROM `' . esc_sql( (string) $table ) . '` WHERE `' . esc_sql( (string) $col ) . '` = %s LIMIT 1',
				(string) $key
			);
			$row = $wpdb->get_row( $sql, ARRAY_A );
			if ( is_array( $row ) && ! empty( $row ) ) {
				return $row;
			}
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $row
	 * @param array<int,string>   $candidates
	 */
	private static function first_int( $row, $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( isset( $row[ $candidate ] ) && is_numeric( $row[ $candidate ] ) ) {
				return (int) $row[ $candidate ];
			}
		}

		return 0;
	}

	/**
	 * @param array<string,mixed> $row
	 * @param array<int,string>   $candidates
	 */
	private static function first_text( $row, $candidates ) {
		foreach ( $candidates as $candidate ) {
			if ( isset( $row[ $candidate ] ) && is_string( $row[ $candidate ] ) && '' !== trim( $row[ $candidate ] ) ) {
				return strtolower( trim( (string) $row[ $candidate ] ) );
			}
		}

		return '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function map_from_hint( $hint ) {
		$hint = (string) $hint;
		$map = array(
			'freemium' => 'freemium',
			'free'     => 'freemium',
			'solo'     => 'solo',
			'maestro'  => 'maestro',
			'agency'   => 'agency',
			'business' => 'agency',
			'team'     => 'maestro',
			'starter'  => 'solo',
			'pro'      => 'maestro',
			'premium'  => 'agency',
		);

		foreach ( $map as $pattern => $plan ) {
			if ( false !== strpos( $hint, $pattern ) ) {
				return array(
					'plan'         => $plan,
					'product_id'   => 0,
					'variation_id' => 0,
				);
			}
		}

		return array();
	}
}

final class EcomCine_CP_REST_Router {
	const NAMESPACE = 'ecomcine-control-plane/v1';

	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/health', array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'health' ),
		) );

		register_rest_route( self::NAMESPACE, '/activations', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'activation_create' ),
		) );

		register_rest_route( self::NAMESPACE, '/entitlements/resolve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'entitlement_resolve' ),
		) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function health() {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'status'  => 'ok',
					'version' => ECOMCINE_CP_VERSION,
				),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function activation_create( WP_REST_Request $request ) {
		$license_key = strtoupper( trim( (string) $request->get_param( 'license_key' ) ) );
		$site_url = esc_url_raw( (string) $request->get_param( 'site_url' ) );
		$fingerprint = strtoupper( trim( (string) $request->get_param( 'site_fingerprint' ) ) );

		if ( '' === $license_key || '' === $site_url || '' === $fingerprint ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'invalid_request' ),
					'error'   => 'Missing required activation fields.',
				),
				400
			);
		}

		$native = EcomCine_CP_FluentCart_Native_Resolver::resolve_from_license_key( $license_key );
		if ( empty( $native ) ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'invalid_license_key', array(
				'route'           => '/activations',
				'license_key_ref' => substr( $license_key, 0, 4 ) . '...' . substr( $license_key, -4 ),
				'key_length'      => strlen( $license_key ),
				'native_probe'    => EcomCine_CP_FluentCart_Native_Resolver::debug_probe( $license_key ),
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'invalid_license' ),
					'error'   => 'Unable to find this license key in FluentCart native records.',
					'code'    => 'no_native_license_match',
				),
				403
			);
		}

		$max_act = max( 1, (int) ( $native['max_site_activations'] ?? 1 ) );
		$activation_id = 'act_' . wp_generate_password( 20, false, false );
		$site_token = 'st_' . wp_generate_password( 32, false, false );
		$now = gmdate( 'c' );

		EcomCine_CP_Activation_Repository::save( $activation_id, array(
			'activation_id'        => $activation_id,
			'site_token'           => $site_token,
			'license_key'          => $license_key,
			'license_key_ref'      => substr( $license_key, 0, 4 ) . '...' . substr( $license_key, -4 ),
			'license_status'       => 'active',
			'plan'                 => (string) ( $native['plan'] ?? 'paid' ),
			'product_id'           => (int) ( $native['product_id'] ?? 0 ),
			'variation_id'         => (int) ( $native['variation_id'] ?? 0 ),
			'max_site_activations' => $max_act,
			'site_url'             => $site_url,
			'site_fingerprint'     => $fingerprint,
			'created_at'           => $now,
			'updated_at'           => $now,
		) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'status'         => 'active',
					'activation_id'  => $activation_id,
					'site_token'     => $site_token,
					'plan'           => (string) ( $native['plan'] ?? 'paid' ),
					'product_id'     => (int) ( $native['product_id'] ?? 0 ),
					'plugin_version' => (string) $request->get_param( 'plugin_version' ),
				),
				'error' => null,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function entitlement_resolve( WP_REST_Request $request ) {
		$activation_id = trim( (string) $request->get_param( 'activation_id' ) );
		$fingerprint = strtoupper( trim( (string) $request->get_param( 'site_fingerprint' ) ) );
		$site_token = trim( (string) $request->get_header( 'x_wmos_site_token' ) );
		if ( '' === $site_token ) {
			$site_token = trim( (string) $request->get_header( 'x_ecomcine_site_token' ) );
		}

		$activation = EcomCine_CP_Activation_Repository::find( $activation_id );
		if ( empty( $activation ) ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'activation_not_found', array(
				'activation_id' => $activation_id,
				'route'         => '/entitlements/resolve',
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'invalid' ),
					'error'   => 'Activation not found.',
				),
				404
			);
		}

		if ( (string) ( $activation['site_fingerprint'] ?? '' ) !== $fingerprint ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'fingerprint_mismatch', array(
				'activation_id' => $activation_id,
				'route'         => '/entitlements/resolve',
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'invalid' ),
					'error'   => 'Site fingerprint mismatch.',
				),
				403
			);
		}

		if ( '' === $site_token || ! hash_equals( (string) ( $activation['site_token'] ?? '' ), $site_token ) ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'site_token_invalid', array(
				'activation_id' => $activation_id,
				'route'         => '/entitlements/resolve',
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'invalid' ),
					'error'   => 'Missing or invalid site token.',
				),
				403
			);
		}

		$product_id = (int) ( $activation['product_id'] ?? 0 );
		$variation_id = (int) ( $activation['variation_id'] ?? 0 );
		$allowances = EcomCine_CP_Billing_Allowance_Repository::resolve_limits( $product_id, $variation_id );

		if ( empty( $allowances ) ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'missing_billing_allowances', array(
				'route'         => '/entitlements/resolve',
				'activation_id' => $activation_id,
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'meta_key'      => 'wmos_allowances_v1',
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'status' => 'billing_data_missing' ),
					'error'   => 'Billing allowances missing for this product/variation. Configure wmos_allowances_v1 product meta on the billing site.',
					'code'    => 'missing_billing_allowances',
				),
				424
			);
		}

		$max_act = max( 1, (int) ( $activation['max_site_activations'] ?? 1 ) );
		$used = 1;
		$remaining = max( 0, $max_act - $used );
		$plan = (string) ( $activation['plan'] ?? 'paid' );

		$contract = array(
			'status' => 'active',
			'plan'   => $plan,
			'limits' => $allowances,
			'activation_policy' => array(
				'max_activations'       => $max_act,
				'used_activations'      => $used,
				'remaining_activations' => $remaining,
			),
			'contract_signature' => hash( 'sha256', $plan . ':' . wp_json_encode( $allowances ) ),
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $contract,
				'error'   => null,
			),
			200
		);
	}
}

final class EcomCine_Control_Plane {
	const MENU_SLUG = 'ecomcine-control-plane';

	public static function init() {
		add_action( 'rest_api_init', array( 'EcomCine_CP_REST_Router', 'register_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'EcomCine Control Plane',
			'EcomCine CP',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-shield',
			57
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$activations = EcomCine_CP_Activation_Repository::all();
		$ops_errors = get_option( EcomCine_CP_Request_Verifier::OPTION_OPS_ERROR_LOG, array() );
		if ( ! is_array( $ops_errors ) ) {
			$ops_errors = array();
		}
		$base_url = trailingslashit( rest_url( EcomCine_CP_REST_Router::NAMESPACE ) );
		$plans = EcomCine_CP_Plan_Registry::all();
		?>
		<div class="wrap">
			<h1>EcomCine Control Plane</h1>
			<p>Private billing-only licensing control plane. Keep this plugin installed only on ecomcine.com billing site.</p>
			<table class="widefat striped" style="max-width: 900px; margin: 12px 0 24px;">
				<tbody>
					<tr><th style="width:260px;">REST Base URL</th><td><code><?php echo esc_html( $base_url ); ?></code></td></tr>
					<tr><th>Activation Storage Option</th><td><code><?php echo esc_html( EcomCine_CP_Activation_Repository::OPTION_KEY ); ?></code></td></tr>
					<tr><th>Total Activations</th><td><?php echo esc_html( (string) count( $activations ) ); ?></td></tr>
				</tbody>
			</table>

			<h2>Plan Registry</h2>
			<table class="widefat striped" style="max-width: 900px; margin: 12px 0 24px;">
				<thead><tr><th>Plan</th><th>Product ID</th><th>Variation ID</th></tr></thead>
				<tbody>
				<?php foreach ( $plans as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $row['plan'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( (int) ( $row['product_id'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( (int) ( $row['variation_id'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Recent Ops Errors</h2>
			<table class="widefat striped" style="max-width: 1100px; margin: 12px 0 24px;">
				<thead><tr><th style="width:220px;">At</th><th style="width:180px;">Code</th><th>Context</th></tr></thead>
				<tbody>
				<?php if ( empty( $ops_errors ) ) : ?>
					<tr><td colspan="3">No ops errors logged.</td></tr>
				<?php else : ?>
					<?php foreach ( array_slice( $ops_errors, -20 ) as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $entry['at'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['code'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( wp_json_encode( $entry['context'] ?? array() ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

EcomCine_Control_Plane::init();
