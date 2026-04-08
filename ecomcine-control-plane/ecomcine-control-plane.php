<?php
/**
 * Plugin Name: EcomCine Control Plane
 * Description: Private billing control-plane plugin for license activation and entitlement resolution.
 * Version: 0.1.2
 * Author: EcomCine
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMCINE_CP_VERSION', '0.1.2' );

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

	/**
	 * @return array<string,mixed>
	 */
	public static function find_by_license_and_fingerprint( $license_key, $fingerprint ) {
		$license_key = strtoupper( trim( (string) $license_key ) );
		$fingerprint = strtoupper( trim( (string) $fingerprint ) );
		if ( '' === $license_key || '' === $fingerprint ) {
			return array();
		}

		foreach ( self::all() as $activation ) {
			if ( ! is_array( $activation ) ) {
				continue;
			}
			if ( $license_key !== strtoupper( trim( (string) ( $activation['license_key'] ?? '' ) ) ) ) {
				continue;
			}
			if ( $fingerprint !== strtoupper( trim( (string) ( $activation['site_fingerprint'] ?? '' ) ) ) ) {
				continue;
			}

			return $activation;
		}

		return array();
	}

	public static function count_for_license( $license_key ) {
		$license_key = strtoupper( trim( (string) $license_key ) );
		if ( '' === $license_key ) {
			return 0;
		}

		$count = 0;
		foreach ( self::all() as $activation ) {
			if ( ! is_array( $activation ) ) {
				continue;
			}
			if ( $license_key !== strtoupper( trim( (string) ( $activation['license_key'] ?? '' ) ) ) ) {
				continue;
			}
			if ( 'inactive' === sanitize_key( (string) ( $activation['license_status'] ?? 'active' ) ) ) {
				continue;
			}
			++$count;
		}

		return $count;
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
	const OPTION_LAST_WRITE_ERROR   = 'ecomcine_cp_allowances_write_error';

	/**
	 * @return array<string,mixed>
	 */
	public static function resolve_limits( $product_id, $variation_id = 0 ) {
		$product_id = (int) $product_id;
		$variation_id = (int) $variation_id;
		if ( $product_id <= 0 ) {
			return array();
		}

		$payload = self::load_allowances_payload( $product_id );
		if ( ! empty( $payload ) ) {
			$limits = self::extract_limits_from_payload( $payload, $variation_id );
			if ( ! empty( $limits ) ) {
				return $limits;
			}
		}

		$settings = self::load_license_settings_payload( $product_id );
		if ( ! empty( $settings ) ) {
			if ( isset( $settings['wmos_allowances'] ) && is_array( $settings['wmos_allowances'] ) ) {
				$limits = self::extract_limits_from_payload( $settings['wmos_allowances'], $variation_id );
				if ( ! empty( $limits ) ) {
					return $limits;
				}
			}

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
	public static function load_allowances_payload( $product_id ) {
		return self::read_product_meta_payload( $product_id, self::META_KEY_ALLOWANCES );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function load_license_settings_payload( $product_id ) {
		return self::read_product_meta_payload( $product_id, self::META_KEY_LICENSE_SETTINGS );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function resolve_payload_limits( $payload, $variation_id = 0 ) {
		return self::extract_limits_from_payload( $payload, $variation_id );
	}

	/**
	 * @return array<int,int>
	 */
	public static function discover_product_ids() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
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

		$sql = $wpdb->prepare(
			'SELECT DISTINCT `' . esc_sql( $id_column ) . '` FROM `' . esc_sql( $table ) . '` WHERE meta_key IN (%s, %s) ORDER BY `' . esc_sql( $id_column ) . '` ASC',
			self::META_KEY_ALLOWANCES,
			self::META_KEY_LICENSE_SETTINGS
		);
		if ( ! is_string( $sql ) || '' === $sql ) {
			return array();
		}

		$rows = $wpdb->get_col( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $rows ) ) ) );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,error:string}
	 */
	public static function save_allowances_payload( $product_id, $payload ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! is_array( $payload ) || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			update_option( self::OPTION_LAST_WRITE_ERROR, 'Invalid product id or payload.', false );
			return array(
				'success' => false,
				'error'   => 'Invalid product id or payload.',
			);
		}

		$table = self::resolve_product_meta_table( $wpdb );
		if ( '' === $table ) {
			update_option( self::OPTION_LAST_WRITE_ERROR, 'Could not resolve FluentCart product meta table.', false );
			return array(
				'success' => false,
				'error'   => 'Could not resolve FluentCart product meta table.',
			);
		}

		$id_column = self::resolve_meta_object_column( $wpdb, $table );
		if ( '' === $id_column ) {
			update_option( self::OPTION_LAST_WRITE_ERROR, 'Could not resolve FluentCart product meta identifier column.', false );
			return array(
				'success' => false,
				'error'   => 'Could not resolve FluentCart product meta identifier column.',
			);
		}

		$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			update_option( self::OPTION_LAST_WRITE_ERROR, 'Payload encoding failed before write.', false );
			return array(
				'success' => false,
				'error'   => 'Payload encoding failed before write.',
			);
		}

		$existing_sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE `' . esc_sql( $id_column ) . '` = %d AND meta_key = %s',
			$product_id,
			self::META_KEY_ALLOWANCES
		);
		if ( ! is_string( $existing_sql ) || '' === $existing_sql ) {
			update_option( self::OPTION_LAST_WRITE_ERROR, 'Failed to prepare existing-row detection query.', false );
			return array(
				'success' => false,
				'error'   => 'Failed to prepare existing-row detection query.',
			);
		}

		$existing_count = (int) $wpdb->get_var( $existing_sql );
		$result = true;
		if ( $existing_count > 0 ) {
			$result = $wpdb->update(
				$table,
				array( 'meta_value' => $encoded ),
				array(
					$id_column => $product_id,
					'meta_key' => self::META_KEY_ALLOWANCES,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					$id_column   => $product_id,
					'meta_key'   => self::META_KEY_ALLOWANCES,
					'meta_value' => $encoded,
				),
				array( '%d', '%s', '%s' )
			);
		}

		if ( false === $result ) {
			$error = trim( (string) $wpdb->last_error );
			if ( '' === $error ) {
				$error = 'Database write failed without error text.';
			}
			update_option( self::OPTION_LAST_WRITE_ERROR, $error, false );
			return array(
				'success' => false,
				'error'   => $error,
			);
		}

		update_option( self::OPTION_LAST_WRITE_ERROR, '', false );

		return array(
			'success' => true,
			'error'   => '',
		);
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

		$base = $payload;
		if ( isset( $payload['defaults'] ) && is_array( $payload['defaults'] ) ) {
			$base = $payload['defaults'];
		}

		$overrides = array();
		if ( $variation_id > 0 && isset( $payload['variations'] ) && is_array( $payload['variations'] ) ) {
			if ( isset( $payload['variations'][ (string) $variation_id ] ) && is_array( $payload['variations'][ (string) $variation_id ] ) ) {
				$overrides = $payload['variations'][ (string) $variation_id ];
			} else {
				foreach ( $payload['variations'] as $variation_payload ) {
					if ( ! is_array( $variation_payload ) ) {
						continue;
					}
					if ( $variation_id !== (int) ( $variation_payload['variation_id'] ?? 0 ) ) {
						continue;
					}
					$overrides = $variation_payload;
					break;
				}
			}
		}

		if ( $variation_id > 0 && empty( $overrides ) && isset( $payload['variation_overrides'][ (string) $variation_id ] ) && is_array( $payload['variation_overrides'][ (string) $variation_id ] ) ) {
			$overrides = $payload['variation_overrides'][ (string) $variation_id ];
		}

		$merged = array_merge( is_array( $base ) ? $base : array(), $overrides );
		unset( $merged['variation_overrides'], $merged['variations'], $merged['defaults'], $merged['variation_id'] );

		if ( isset( $merged['remixes_month'] ) && ! isset( $merged['remixes_max'] ) ) {
			$merged['remixes_max'] = $merged['remixes_month'];
		}
		if ( isset( $merged['promotions_month'] ) && ! isset( $merged['promotions_max'] ) ) {
			$merged['promotions_max'] = $merged['promotions_month'];
		}

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
		$existing_activation = EcomCine_CP_Activation_Repository::find_by_license_and_fingerprint( $license_key, $fingerprint );
		if ( ! empty( $existing_activation ) ) {
			$existing_activation_id = sanitize_text_field( (string) ( $existing_activation['activation_id'] ?? '' ) );
			$existing_site_token = sanitize_text_field( (string) ( $existing_activation['site_token'] ?? '' ) );
			if ( '' !== $existing_activation_id && '' !== $existing_site_token ) {
				$existing_activation['site_url'] = $site_url;
				$existing_activation['updated_at'] = gmdate( 'c' );
				EcomCine_CP_Activation_Repository::save( $existing_activation_id, $existing_activation );

				return new WP_REST_Response(
					array(
						'success' => true,
						'data'    => array(
							'status'              => 'active',
							'activation_id'       => $existing_activation_id,
							'site_token'          => $existing_site_token,
							'plan'                => (string) ( $existing_activation['plan'] ?? $native['plan'] ?? 'paid' ),
							'product_id'          => (int) ( $existing_activation['product_id'] ?? $native['product_id'] ?? 0 ),
							'plugin_version'      => (string) $request->get_param( 'plugin_version' ),
							'current_activations' => EcomCine_CP_Activation_Repository::count_for_license( $license_key ),
						),
						'error' => null,
					),
					200
				);
			}
		}

		$current_activations = EcomCine_CP_Activation_Repository::count_for_license( $license_key );
		if ( $current_activations >= $max_act ) {
			EcomCine_CP_Request_Verifier::log_ops_error( 'activation_limit_reached', array(
				'route'               => '/activations',
				'license_key_ref'     => substr( $license_key, 0, 4 ) . '...' . substr( $license_key, -4 ),
				'current_activations' => $current_activations,
				'max_activations'     => $max_act,
				'fingerprint'         => $fingerprint,
			) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'data'    => array(
						'status'              => 'over_limit',
						'current_activations' => $current_activations,
						'max_activations'     => $max_act,
					),
					'error'   => 'This license has reached its maximum number of site activations.',
					'code'    => 'activation_limit_reached',
				),
				403
			);
		}

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
					'current_activations' => $current_activations + 1,
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
		$used = max( 1, EcomCine_CP_Activation_Repository::count_for_license( (string) ( $activation['license_key'] ?? '' ) ) );
		$remaining = max( 0, $max_act - $used );
		$plan = (string) ( $activation['plan'] ?? 'paid' );

		$contract = array(
			'status' => 'active',
			'plan'   => $plan,
			'limits' => $allowances,
			'activation_policy' => array(
				'max_activations'       => $max_act,
				'used_activations'      => $used,
				'current_activations'   => $used,
				'site_activations_used' => $used,
				'remaining_activations' => $remaining,
				'site_is_activated'     => true,
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
	const BILLING_ALLOWANCES_SLUG = 'ecomcine-control-plane-billing-allowances';

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

		add_submenu_page(
			self::MENU_SLUG,
			'Home',
			'Home',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Billing Allowances',
			'Billing Allowances',
			'manage_options',
			self::BILLING_ALLOWANCES_SLUG,
			array( __CLASS__, 'render_billing_allowances_screen' )
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

	public static function render_billing_allowances_screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ecomcine-control-plane' ) );
		}

		$catalog = self::discover_licensed_products();
		$selected_key = isset( $_REQUEST['product_selection'] ) ? sanitize_text_field( (string) wp_unslash( $_REQUEST['product_selection'] ) ) : '';
		$product_id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0;
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;
		if ( '' !== $selected_key ) {
			$parts = array_map( 'trim', explode( ':', $selected_key ) );
			if ( 2 === count( $parts ) && ctype_digit( $parts[0] ) && ctype_digit( $parts[1] ) ) {
				$product_id = (int) $parts[0];
				$variation_id = (int) $parts[1];
			}
		}

		if ( '' === $selected_key && $product_id > 0 ) {
			$selected_key = $product_id . ':' . $variation_id;
		}

		$messages = array(
			'success'  => array(),
			'error'    => array(),
			'resolved' => array(),
		);

		if ( isset( $_POST['ecomcine_cp_allowances_action'] ) ) {
			check_admin_referer( 'ecomcine_cp_allowances_action' );
			$action = sanitize_key( (string) wp_unslash( $_POST['ecomcine_cp_allowances_action'] ) );
			if ( 'save_form' === $action ) {
				$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
				$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
				if ( $product_id <= 0 ) {
					$messages['error'][] = 'Product ID is required.';
				} else {
					$current_payload = EcomCine_CP_Billing_Allowance_Repository::load_allowances_payload( $product_id );
					$payload = self::build_allowances_payload_from_request( $current_payload, $variation_id );
					$result = EcomCine_CP_Billing_Allowance_Repository::save_allowances_payload( $product_id, $payload );
					if ( empty( $result['success'] ) ) {
						$messages['error'][] = (string) ( $result['error'] ?? 'Failed to save billing allowances.' );
						EcomCine_CP_Request_Verifier::log_ops_error(
							'allowances_save_failed',
							array(
								'product_id' => $product_id,
								'variation_id' => $variation_id,
								'reason'     => (string) ( $result['error'] ?? '' ),
							)
						);
					} else {
						$messages['success'][] = 'Saved wmos_allowances_v1 payload.';
						$messages['resolved'] = EcomCine_CP_Billing_Allowance_Repository::resolve_limits( $product_id, $variation_id );
					}
				}
			}
		}

		$current_payload = array();
		if ( $product_id > 0 ) {
			$current_payload = EcomCine_CP_Billing_Allowance_Repository::load_allowances_payload( $product_id );
			if ( empty( $messages['resolved'] ) ) {
				$messages['resolved'] = EcomCine_CP_Billing_Allowance_Repository::resolve_limits( $product_id, $variation_id );
			}
		}

		$form_values = self::extract_allowance_form_values( $current_payload, $variation_id );
		$selected_label = self::resolve_catalog_product_label( $product_id, $variation_id, $catalog );
		$variation_options = self::build_variation_options( $product_id );
		?>
		<div class="wrap">
			<h1>Billing Allowances</h1>
			<p>Manage the billing-authoritative <code>wmos_allowances_v1</code> payload for licensed EcomCine offers.</p>

			<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px 16px;margin:14px 0 16px;">
				<h2 style="margin:0 0 8px;">Quick Select</h2>
				<?php if ( empty( $catalog ) ) : ?>
					<p style="margin:0;color:#646970;">No licensed products were discovered yet. Ensure billing products have <code>license_settings</code> or <code>wmos_allowances_v1</code> metadata.</p>
				<?php else : ?>
					<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::BILLING_ALLOWANCES_SLUG ); ?>" />
						<label for="ecomcine-cp-allowances-product-selection" style="font-weight:600;">Licensed Product</label>
						<select id="ecomcine-cp-allowances-product-selection" name="product_selection" style="min-width:420px;">
							<option value="">Select a product to edit allowances...</option>
							<?php foreach ( $catalog as $item ) : ?>
								<?php $item_key = (string) $item['product_id'] . ':' . (string) $item['variation_id']; ?>
								<option value="<?php echo esc_attr( $item_key ); ?>" <?php selected( $selected_key, $item_key ); ?>>
									<?php echo esc_html( (string) $item['entry_label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button class="button button-primary" type="submit">Open</button>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $catalog ) ) : ?>
				<h2>Licensed Products</h2>
				<table class="widefat striped" style="max-width:1100px;margin-bottom:14px;">
					<thead>
						<tr>
							<th>Product</th>
							<th>Activation Limit</th>
							<th>Allowances</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $catalog as $item ) : ?>
							<tr>
								<td><strong><?php echo esc_html( (string) $item['entry_label'] ); ?></strong></td>
								<td><?php echo esc_html( (string) $item['activation_limit'] ); ?></td>
								<td><?php echo esc_html( ! empty( $item['has_allowances'] ) ? 'Configured' : 'Missing' ); ?></td>
								<td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => self::BILLING_ALLOWANCES_SLUG, 'product_id' => (int) $item['product_id'], 'variation_id' => (int) $item['variation_id'] ), admin_url( 'admin.php' ) ) ); ?>">Edit</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php foreach ( array( 'error', 'success' ) as $notice_type ) : ?>
				<?php foreach ( $messages[ $notice_type ] as $message ) : ?>
					<div class="notice <?php echo esc_attr( 'error' === $notice_type ? 'notice-error' : 'notice-success' ); ?>"><p><?php echo esc_html( (string) $message ); ?></p></div>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<?php if ( $product_id > 0 ) : ?>
				<h2>Guided Allowances Editor</h2>
				<p style="margin-top:0;color:#646970;">Edit the allowance contract for <?php echo esc_html( $selected_label ); ?> without touching raw billing tables.</p>
				<form method="post" style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px 16px;margin:0 0 14px;">
					<?php wp_nonce_field( 'ecomcine_cp_allowances_action' ); ?>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />
					<h3 style="margin:0 0 12px;">Contract Scope</h3>
					<table class="form-table" role="presentation" style="margin-top:0;">
						<tr>
							<th scope="row"><label for="ecomcine-cp-variation-id">Variation</label></th>
							<td>
								<select id="ecomcine-cp-variation-id" name="variation_id" class="regular-text">
									<?php foreach ( $variation_options as $option ) : ?>
										<option value="<?php echo esc_attr( (string) $option['variation_id'] ); ?>" <?php selected( $variation_id, (int) $option['variation_id'] ); ?>><?php echo esc_html( (string) $option['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description" style="margin-top:6px;">
									<?php echo esc_html( 0 === $variation_id ? 'Default applies to the whole product unless a variation override is present.' : 'This variation override is merged on top of the default allowances at entitlement resolve time.' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<h3 style="margin:18px 0 12px;"><?php echo esc_html( 0 === $variation_id ? 'Default Limits' : 'Variation Override Limits' ); ?></h3>
					<table class="form-table" role="presentation" style="margin-top:0;">
						<?php foreach ( $form_values['limits'] as $key => $value ) : ?>
							<tr>
								<th scope="row"><label for="ecomcine-cp-<?php echo esc_attr( $key ); ?>"><code><?php echo esc_html( $key ); ?></code></label></th>
								<td><input id="ecomcine-cp-<?php echo esc_attr( $key ); ?>" name="allowance_defaults[<?php echo esc_attr( $key ); ?>]" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text" /></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row"><label for="ecomcine-cp-ai-mode"><code>ai_mode</code></label></th>
							<td>
								<select id="ecomcine-cp-ai-mode" name="allowance_ai_mode" class="regular-text">
									<option value="mutualized_ai" <?php selected( $form_values['ai_mode'], 'mutualized_ai' ); ?>>Mutualized AI</option>
									<option value="confidential_ai" <?php selected( $form_values['ai_mode'], 'confidential_ai' ); ?>>Confidential AI</option>
								</select>
							</td>
						</tr>
					</table>

					<p style="margin-top:10px;">
						<button class="button button-primary" type="submit" name="ecomcine_cp_allowances_action" value="save_form">Save Form</button>
					</p>
				</form>

				<h2>Stored Payload</h2>
				<textarea readonly rows="18" class="large-text code" style="max-width:1100px;"><?php echo esc_textarea( wp_json_encode( $current_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
			<?php endif; ?>

			<?php if ( ! empty( $messages['resolved'] ) ) : ?>
				<h2>Resolved Limits Preview</h2>
				<table class="widefat striped" style="max-width:760px;">
					<thead><tr><th>Limit</th><th>Value</th></tr></thead>
					<tbody>
						<?php foreach ( $messages['resolved'] as $key => $value ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $key ); ?></code></td>
								<td><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function discover_licensed_products() {
		$catalog = array();
		$product_ids = array();
		foreach ( EcomCine_CP_Plan_Registry::all() as $row ) {
			$product_id = (int) ( $row['product_id'] ?? 0 );
			if ( $product_id > 0 ) {
				$product_ids[] = $product_id;
			}
		}
		$product_ids = array_merge( $product_ids, EcomCine_CP_Billing_Allowance_Repository::discover_product_ids() );
		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
		sort( $product_ids );

		foreach ( $product_ids as $product_id ) {
			$payload = EcomCine_CP_Billing_Allowance_Repository::load_allowances_payload( $product_id );
			$settings = EcomCine_CP_Billing_Allowance_Repository::load_license_settings_payload( $product_id );
			$variation_options = self::build_variation_options( $product_id );
			foreach ( $variation_options as $variation_option ) {
				$current_variation_id = (int) $variation_option['variation_id'];
				$plan_row = EcomCine_CP_Plan_Registry::resolve_from_product_reference( $product_id, $current_variation_id );
				$plan = sanitize_key( (string) ( $plan_row['plan'] ?? '' ) );
			$label = '' !== $plan ? ucfirst( $plan ) : 'Product';
				$catalog[] = array(
					'product_id'       => $product_id,
					'variation_id'     => $current_variation_id,
					'activation_limit' => self::extract_activation_limit( $settings, $current_variation_id ),
					'has_allowances'   => ! empty( EcomCine_CP_Billing_Allowance_Repository::resolve_payload_limits( $payload, $current_variation_id ) ),
					'entry_label'      => sprintf( '%s (#%d)%s', $label, $product_id, 0 === $current_variation_id ? ' - Default' : ' - Variation #' . $current_variation_id ),
					'product_label'    => $label,
				);
			}
		}

		return $catalog;
	}

	/**
	 * @return array{limits:array<string,int>,ai_mode:string}
	 */
	private static function extract_allowance_form_values( $payload, $variation_id = 0 ) {
		$resolved = EcomCine_CP_Billing_Allowance_Repository::resolve_payload_limits( is_array( $payload ) ? $payload : array(), $variation_id );
		$defaults = array(
			'ai_queries_hour'  => 0,
			'ai_queries_day'   => 0,
			'ai_queries_month' => 0,
			'remixes_max'      => 0,
			'promotions_max'   => 0,
			'queue_max'        => 0,
		);
		foreach ( $defaults as $key => $seed ) {
			$defaults[ $key ] = max( 0, (int) ( $resolved[ $key ] ?? $seed ) );
		}

		$ai_mode = sanitize_key( (string) ( $resolved['ai_mode'] ?? 'mutualized_ai' ) );
		if ( ! in_array( $ai_mode, array( 'mutualized_ai', 'confidential_ai' ), true ) ) {
			$ai_mode = 'mutualized_ai';
		}

		return array(
			'limits'  => $defaults,
			'ai_mode' => $ai_mode,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function build_allowances_payload_from_request( $existing_payload = array(), $variation_id = 0 ) {
		$payload = is_array( $existing_payload ) ? $existing_payload : array();
		$defaults = array();
		$raw_defaults = isset( $_POST['allowance_defaults'] ) && is_array( $_POST['allowance_defaults'] ) ? (array) wp_unslash( $_POST['allowance_defaults'] ) : array();
		foreach ( array( 'ai_queries_hour', 'ai_queries_day', 'ai_queries_month', 'remixes_max', 'promotions_max', 'queue_max' ) as $key ) {
			$defaults[ $key ] = max( 0, (int) ( $raw_defaults[ $key ] ?? 0 ) );
		}

		$ai_mode = isset( $_POST['allowance_ai_mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['allowance_ai_mode'] ) ) : 'mutualized_ai';
		if ( ! in_array( $ai_mode, array( 'mutualized_ai', 'confidential_ai' ), true ) ) {
			$ai_mode = 'mutualized_ai';
		}
		$defaults['ai_mode'] = $ai_mode;

		if ( ! isset( $payload['defaults'] ) || ! is_array( $payload['defaults'] ) ) {
			$payload['defaults'] = array();
		}
		if ( ! isset( $payload['variations'] ) || ! is_array( $payload['variations'] ) ) {
			$payload['variations'] = array();
		}

		if ( (int) $variation_id <= 0 ) {
			$payload['defaults'] = $defaults;
		} else {
			$defaults['variation_id'] = (int) $variation_id;
			$payload['variations'][ (string) (int) $variation_id ] = $defaults;
		}

		return $payload;
	}

	private static function extract_activation_limit( $settings, $variation_id = 0 ) {
		if ( ! is_array( $settings ) ) {
			return 0;
		}

		$variation_id = (int) $variation_id;
		$limit = (int) ( $settings['activation_limit'] ?? $settings['activations_limit'] ?? 0 );
		if ( $variation_id <= 0 && $limit > 0 ) {
			return $limit;
		}

		if ( isset( $settings['variations'] ) && is_array( $settings['variations'] ) ) {
			foreach ( $settings['variations'] as $variation ) {
				if ( ! is_array( $variation ) ) {
					continue;
				}
				$row_variation_id = (int) ( $variation['variation_id'] ?? 0 );
				$candidate = (int) ( $variation['activation_limit'] ?? $variation['activations_limit'] ?? 0 );
				if ( $variation_id > 0 && $row_variation_id === $variation_id && $candidate > 0 ) {
					return $candidate;
				}
				if ( $candidate > $limit ) {
					$limit = $candidate;
				}
			}
		}

		return $limit;
	}

	/**
	 * @param array<int,array<string,mixed>> $catalog
	 */
	private static function resolve_catalog_product_label( $product_id, $variation_id, $catalog ) {
		$product_id = (int) $product_id;
		$variation_id = (int) $variation_id;
		foreach ( $catalog as $item ) {
			if ( $product_id !== (int) ( $item['product_id'] ?? 0 ) || $variation_id !== (int) ( $item['variation_id'] ?? 0 ) ) {
				continue;
			}
			return (string) ( $item['entry_label'] ?? ( 'Product #' . $product_id ) );
		}

		if ( $product_id <= 0 ) {
			return 'selected product';
		}

		return 0 === $variation_id ? 'Product #' . $product_id . ' - Default' : 'Product #' . $product_id . ' - Variation #' . $variation_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_variation_options( $product_id ) {
		$product_id = (int) $product_id;
		$options = array(
			array(
				'variation_id' => 0,
				'label'        => 'Default product contract',
			),
		);

		if ( $product_id <= 0 ) {
			return $options;
		}

		$seen = array( '0' => true );
		foreach ( EcomCine_CP_Plan_Registry::all() as $row ) {
			if ( $product_id !== (int) ( $row['product_id'] ?? 0 ) ) {
				continue;
			}
			$current_variation_id = (int) ( $row['variation_id'] ?? 0 );
			if ( $current_variation_id <= 0 || isset( $seen[ (string) $current_variation_id ] ) ) {
				continue;
			}
			$seen[ (string) $current_variation_id ] = true;
			$options[] = array(
				'variation_id' => $current_variation_id,
				'label'        => 'Variation #' . $current_variation_id,
			);
		}

		$settings = EcomCine_CP_Billing_Allowance_Repository::load_license_settings_payload( $product_id );
		if ( isset( $settings['variations'] ) && is_array( $settings['variations'] ) ) {
			foreach ( $settings['variations'] as $variation ) {
				if ( ! is_array( $variation ) ) {
					continue;
				}
				$current_variation_id = (int) ( $variation['variation_id'] ?? 0 );
				if ( $current_variation_id <= 0 || isset( $seen[ (string) $current_variation_id ] ) ) {
					continue;
				}
				$seen[ (string) $current_variation_id ] = true;
				$options[] = array(
					'variation_id' => $current_variation_id,
					'label'        => 'Variation #' . $current_variation_id,
				);
			}
		}

		usort(
			$options,
			static function( $left, $right ) {
				return (int) ( $left['variation_id'] ?? 0 ) <=> (int) ( $right['variation_id'] ?? 0 );
			}
		);

		return $options;
	}
}

EcomCine_Control_Plane::init();
