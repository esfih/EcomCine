<?php
/**
 * EcomCine standalone stores listing shortcode.
 *
 * Provides a Dokan-independent listing renderer for the Talents page.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tm_store_ui_collect_person_ids_for_listing' ) ) {
	function tm_store_ui_is_person_live( int $user_id ): bool {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		// Gate on published/enabled status only. L1 completeness is a profile
		// quality indicator, not a listing visibility gate — existing vendors
		// may not have tm_l1_complete set, which would wrongly hide them.
		if ( function_exists( 'ecomcine_is_person_enabled' ) ) {
			return ecomcine_is_person_enabled( $user_id );
		}

		return 'yes' === strtolower( trim( (string) get_user_meta( $user_id, 'dokan_enable_selling', true ) ) );
	}

	function tm_store_ui_collect_person_ids_for_listing(): array {
		$ids = array();

		if ( function_exists( 'tm_get_showcase_vendor_ids' ) ) {
			$ids = array_map( 'intval', (array) tm_get_showcase_vendor_ids() );
		}

		if ( empty( $ids ) && function_exists( 'ecomcine_get_persons' ) ) {
			$users = ecomcine_get_persons( array( 'number' => -1 ) );
			foreach ( (array) $users as $user ) {
				if ( ! isset( $user->ID ) ) {
					continue;
				}
				$ids[] = (int) $user->ID;
			}
		}

		if ( empty( $ids ) ) {
			$users = get_users(
				array(
					'number'   => -1,
					'role__in' => array( 'ecomcine_person', 'seller', 'vendor' ),
				)
			);
			foreach ( (array) $users as $user ) {
				$ids[] = (int) $user->ID;
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		if ( function_exists( 'ecomcine_is_person_enabled' ) ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function( int $id ): bool {
						return ecomcine_is_person_enabled( $id );
					}
				)
			);
		}

		$ids = array_values(
			array_filter(
				$ids,
				static function( int $id ): bool {
					return tm_store_ui_is_person_live( $id );
				}
			)
		);

		if ( function_exists( 'ecomcine_get_person_url' ) ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function( int $id ): bool {
						return '' !== trim( (string) ecomcine_get_person_url( $id ) );
					}
				)
			);
		}

		usort(
			$ids,
			static function( int $a, int $b ): int {
				$ua = get_userdata( $a );
				$ub = get_userdata( $b );
				$na = $ua ? strtolower( (string) $ua->display_name ) : '';
				$nb = $ub ? strtolower( (string) $ub->display_name ) : '';
				return strcmp( $na, $nb );
			}
		);

		return $ids;
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_category_label' ) ) {
	function tm_store_ui_get_person_category_name_by_id( int $category_id ): string {
		$category_id = (int) $category_id;
		if ( $category_id < 1 ) {
			return '';
		}

		if ( class_exists( 'EcomCine_Person_Category_Registry' ) && method_exists( 'EcomCine_Person_Category_Registry', 'get_category' ) ) {
			$cat = EcomCine_Person_Category_Registry::get_category( $category_id );
			if ( is_array( $cat ) && ! empty( $cat['name'] ) ) {
				return trim( (string) $cat['name'] );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ecomcine_categories';
		$name  = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$table} WHERE id = %d", $category_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_string( $name ) ? trim( $name ) : '';
	}

	function tm_store_ui_resolve_legacy_category_token( string $token ): string {
		$token = trim( $token );
		if ( '' === $token ) {
			return '';
		}

		if ( ctype_digit( $token ) ) {
			$id   = (int) $token;
			$name = tm_store_ui_get_person_category_name_by_id( $id );
			if ( '' !== $name ) {
				return $name;
			}

			if ( taxonomy_exists( 'store_category' ) ) {
				$term = get_term( $id, 'store_category' );
				if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
					return trim( (string) $term->name );
				}
			}

			global $wpdb;
			$terms     = $wpdb->terms;
			$tt        = $wpdb->term_taxonomy;
			$term_name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.name
					 FROM {$terms} t
					 INNER JOIN {$tt} tt ON tt.term_id = t.term_id
					 WHERE t.term_id = %d
					 ORDER BY FIELD(tt.taxonomy, 'store_category', 'dokan_store_category', 'category') ASC
					 LIMIT 1",
					$id
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return is_string( $term_name ) ? trim( $term_name ) : '';
		}

		$slug = sanitize_title( $token );
		if ( '' === $slug ) {
			return '';
		}

		if ( class_exists( 'EcomCine_Person_Category_Registry' ) && method_exists( 'EcomCine_Person_Category_Registry', 'get_by_slug' ) ) {
			$cat = EcomCine_Person_Category_Registry::get_by_slug( $slug );
			if ( is_array( $cat ) && ! empty( $cat['name'] ) ) {
				return trim( (string) $cat['name'] );
			}
		}

		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	function tm_store_ui_get_person_category_label( int $user_id ): string {
		$labels = array();

		if ( class_exists( 'EcomCine_Person_Category_Registry' ) && method_exists( 'EcomCine_Person_Category_Registry', 'get_for_person' ) ) {
			$cats = (array) EcomCine_Person_Category_Registry::get_for_person( $user_id );
			foreach ( $cats as $cat ) {
				$name = isset( $cat['name'] ) ? trim( (string) $cat['name'] ) : '';
				if ( '' !== $name ) {
					$labels[] = $name;
				}
			}
		}

		if ( empty( $labels ) ) {
			global $wpdb;
			$cats = $wpdb->prefix . 'ecomcine_categories';
			$join = $wpdb->prefix . 'ecomcine_person_categories';
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT c.name
					 FROM {$cats} c
					 INNER JOIN {$join} j ON j.category_id = c.id
					 WHERE j.user_id = %d
					 ORDER BY c.sort_order ASC, c.name ASC",
					$user_id
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( (array) $rows as $name ) {
				$name = trim( (string) $name );
				if ( '' !== $name ) {
					$labels[] = $name;
				}
			}
		}

		if ( empty( $labels ) && taxonomy_exists( 'store_category' ) ) {
			$profile = get_user_meta( $user_id, 'dokan_profile_settings', true );
			$cat_ids = array();
			if ( is_array( $profile ) ) {
				if ( ! empty( $profile['dokan_category'] ) ) {
					$cat_ids = (array) $profile['dokan_category'];
				} elseif ( ! empty( $profile['categories'] ) ) {
					$cat_ids = (array) $profile['categories'];
				}
			}
			foreach ( $cat_ids as $cat_id ) {
				$term = get_term( (int) $cat_id, 'store_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$labels[] = (string) $term->name;
				}
			}
		}

		if ( empty( $labels ) ) {
			$legacy_slugs = get_user_meta( $user_id, 'dokan_store_categories', true );
			if ( is_array( $legacy_slugs ) ) {
				foreach ( $legacy_slugs as $slug ) {
					$resolved = tm_store_ui_resolve_legacy_category_token( (string) $slug );
					if ( '' !== $resolved ) {
						$labels[] = $resolved;
					}
				}
			}
		}

		if ( empty( $labels ) ) {
			$profile = get_user_meta( $user_id, 'dokan_profile_settings', true );
			$cat_ids = array();
			if ( is_array( $profile ) ) {
				if ( ! empty( $profile['store_categories'] ) ) {
					$cat_ids = (array) $profile['store_categories'];
				} elseif ( ! empty( $profile['ecomcine_person_categories'] ) ) {
					$cat_ids = (array) $profile['ecomcine_person_categories'];
				}
			}

			foreach ( $cat_ids as $cat_id ) {
				$resolved = tm_store_ui_resolve_legacy_category_token( (string) $cat_id );
				if ( '' !== $resolved ) {
					$labels[] = $resolved;
				}
			}
		}

		$labels = array_values( array_unique( array_filter( array_map( 'trim', $labels ) ) ) );
		return implode( ', ', $labels );
	}
}

if ( ! function_exists( 'tm_store_ui_meta_is_truthy' ) ) {
	function tm_store_ui_meta_is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( '1', 'yes', 'true', 'on' ), true );
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_status_badges' ) ) {
	function tm_store_ui_get_person_status_badges( int $user_id ): array {
		$featured = false;
		$verified = false;

		foreach ( array( 'ecomcine_is_featured', 'ecomcine_featured', 'dokan_feature_seller' ) as $key ) {
			if ( tm_store_ui_meta_is_truthy( get_user_meta( $user_id, $key, true ) ) ) {
				$featured = true;
				break;
			}
		}

		foreach ( array( 'ecomcine_is_verified', 'ecomcine_verified', 'dokan_store_verified' ) as $key ) {
			if ( tm_store_ui_meta_is_truthy( get_user_meta( $user_id, $key, true ) ) ) {
				$verified = true;
				break;
			}
		}

		return array(
			'featured' => $featured,
			'verified' => $verified,
		);
	}
}

if ( ! function_exists( 'tm_store_ui_compact_location_label' ) ) {
	function tm_store_ui_compact_location_label( string $location ): string {
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $location ) ), 'strlen' ) );

		if ( count( $parts ) > 2 ) {
			return $parts[0] . ', ' . $parts[ count( $parts ) - 1 ];
		}

		return implode( ', ', $parts );
	}
}

if ( ! function_exists( 'tm_store_ui_get_country_code_from_profile_or_geo' ) ) {
	function tm_store_ui_get_country_code_from_profile_or_geo( array $profile, string $raw_location ): string {
		$address_country = '';
		if ( isset( $profile['address'] ) && is_array( $profile['address'] ) ) {
			$address_country = strtoupper( trim( (string) ( $profile['address']['country'] ?? '' ) ) );
		}

		if ( strlen( $address_country ) === 2 ) {
			return $address_country;
		}

		if ( '' === $raw_location || ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->countries ) ) {
			return '';
		}

		$parts        = array_values( array_filter( array_map( 'trim', explode( ',', $raw_location ) ), 'strlen' ) );
		$country_name = ! empty( $parts ) ? $parts[ count( $parts ) - 1 ] : '';

		if ( '' === $country_name ) {
			return '';
		}

		foreach ( (array) WC()->countries->get_countries() as $code => $name ) {
			if ( stripos( (string) $name, $country_name ) !== false || stripos( $country_name, (string) $name ) !== false ) {
				$code = strtoupper( (string) $code );
				return strlen( $code ) === 2 ? $code : '';
			}
		}

		return '';
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_location_display_html' ) ) {
	function tm_store_ui_get_person_location_display_html( int $user_id, array $profile ): string {
		$raw_location = '';

		if ( function_exists( 'ecomcine_get_geo' ) ) {
			$geo          = ecomcine_get_geo( $user_id );
			$raw_location = trim( (string) ( $geo['address'] ?? '' ) );
		}

		if ( '' === $raw_location && isset( $profile['address'] ) && is_array( $profile['address'] ) ) {
			$city        = trim( (string) ( $profile['address']['city'] ?? '' ) );
			$country     = trim( (string) ( $profile['address']['country'] ?? '' ) );
			$state       = trim( (string) ( $profile['address']['state'] ?? '' ) );
			$parts       = array_values( array_filter( array( $city, $state, $country ), 'strlen' ) );
			$raw_location = implode( ', ', $parts );
		}

		$compact = tm_store_ui_compact_location_label( $raw_location );
		if ( '' === $compact ) {
			return '';
		}

		$country_code = tm_store_ui_get_country_code_from_profile_or_geo( $profile, $raw_location );
		$flag_html    = '';
		if ( strlen( $country_code ) === 2 ) {
			$flag_code = strtolower( $country_code );
			$flag_html = '<span class="country-flag" title="' . esc_attr( $country_code ) . '">' .
				'<img src="https://flagcdn.com/w40/' . esc_attr( $flag_code ) . '.png"' .
				' srcset="https://flagcdn.com/w80/' . esc_attr( $flag_code ) . '.png 2x"' .
				' width="18" height="18" loading="lazy" alt="" class="country-flag-img">' .
				'</span>';
		}

		return $flag_html . '<span class="geo-address">' . esc_html( $compact ) . '</span>';
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_location_label' ) ) {
	function tm_store_ui_get_person_location_label( int $user_id ): string {
		if ( function_exists( 'ecomcine_get_geo' ) ) {
			$geo = ecomcine_get_geo( $user_id );
			if ( ! empty( $geo['address'] ) ) {
				return (string) $geo['address'];
			}
		}

		if ( function_exists( 'ecomcine_get_person_info' ) ) {
			$info = ecomcine_get_person_info( $user_id );
			if ( isset( $info['address'] ) && is_array( $info['address'] ) ) {
				$parts = array();
				if ( ! empty( $info['address']['city'] ) ) {
					$parts[] = (string) $info['address']['city'];
				}
				if ( ! empty( $info['address']['state'] ) ) {
					$parts[] = (string) $info['address']['state'];
				}
				if ( ! empty( $parts ) ) {
					return implode( ', ', $parts );
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'tm_store_ui_get_listing_request_value' ) ) {
	function tm_store_ui_get_listing_request_value( array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $_GET[ $key ] ) ) {
				continue;
			}

			$value = trim( sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_category_slugs' ) ) {
	function tm_store_ui_get_person_category_slugs( int $user_id ): array {
		$slugs = array();

		if ( class_exists( 'EcomCine_Person_Category_Registry' ) && method_exists( 'EcomCine_Person_Category_Registry', 'get_for_person' ) ) {
			$cats = (array) EcomCine_Person_Category_Registry::get_for_person( $user_id );
			foreach ( $cats as $cat ) {
				$slug = isset( $cat['slug'] ) ? sanitize_title( (string) $cat['slug'] ) : '';
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		if ( empty( $slugs ) ) {
			global $wpdb;
			$cats = $wpdb->prefix . 'ecomcine_categories';
			$join = $wpdb->prefix . 'ecomcine_person_categories';
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT c.slug
					 FROM {$cats} c
					 INNER JOIN {$join} j ON j.category_id = c.id
					 WHERE j.user_id = %d",
					$user_id
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $slug ) {
				$slug = sanitize_title( (string) $slug );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}
}

if ( ! function_exists( 'tm_store_ui_get_active_listing_filters' ) ) {
	function tm_store_ui_get_active_listing_filters(): array {
		$meta_filters = array();
		foreach (
			array(
				'talent_height', 'talent_weight', 'talent_waist', 'talent_hip', 'talent_chest', 'talent_shoe_size',
				'talent_eye_color', 'talent_hair_color', 'camera_type', 'experience_level', 'editing_software',
				'specialization', 'years_experience', 'equipment_ownership', 'lighting_equipment', 'audio_equipment',
				'drone_capability', 'demo_ethnicity', 'demo_languages', 'demo_availability', 'demo_notice_time',
				'demo_can_travel', 'demo_daily_rate', 'demo_education',
			) as $meta_key
		) {
			$value = tm_store_ui_get_listing_request_value( array( $meta_key ) );
			if ( '' !== $value ) {
				$meta_filters[ $meta_key ] = $value;
			}
		}

		return array(
			'search'        => tm_store_ui_get_listing_request_value( array( 'ecomcine_person_search', 'dokan_seller_search' ) ),
			'category'      => sanitize_title( tm_store_ui_get_listing_request_value( array( 'ecomcine_person_category', 'dokan_seller_category' ) ) ),
			'verified'      => 'yes' === tm_store_ui_get_listing_request_value( array( 'verified' ) ),
			'featured'      => 'yes' === tm_store_ui_get_listing_request_value( array( 'featured' ) ),
			'profile_level' => tm_store_ui_get_listing_request_value( array( 'profile_level' ) ),
			'age_range'     => tm_store_ui_get_listing_request_value( array( 'demo_age' ) ),
			'tm_order'      => tm_store_ui_get_listing_request_value( array( 'tm_order' ) ) ?: 'newest',
			'meta_filters'  => $meta_filters,
		);
	}
}

if ( ! function_exists( 'tm_store_ui_person_matches_meta_filter' ) ) {
	function tm_store_ui_person_matches_meta_filter( int $user_id, string $meta_key, string $needle ): bool {
		$raw = get_user_meta( $user_id, $meta_key, true );
		if ( '' === $needle ) {
			return true;
		}

		if ( 'demo_languages' === $meta_key ) {
			$values = maybe_unserialize( $raw );
			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}

			$needle = strtolower( trim( $needle ) );
			foreach ( $values as $value ) {
				if ( strtolower( trim( (string) $value ) ) === $needle ) {
					return true;
				}
			}

			return false;
		}

		return strtolower( trim( (string) $raw ) ) === strtolower( trim( $needle ) );
	}
}

if ( ! function_exists( 'tm_store_ui_get_person_sort_name' ) ) {
	function tm_store_ui_get_person_sort_name( int $user_id ): string {
		$profile = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $user_id ) : array();
		$name    = isset( $profile['store_name'] ) ? trim( (string) $profile['store_name'] ) : '';
		if ( '' !== $name ) {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
		}

		$user         = get_userdata( $user_id );
		$display_name = $user ? trim( (string) $user->display_name ) : '';
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $display_name ) : strtolower( $display_name );
	}
}

if ( ! function_exists( 'tm_store_ui_sort_person_ids_for_listing' ) ) {
	function tm_store_ui_sort_person_ids_for_listing( array $ids, string $tm_order ): array {
		$tm_order = sanitize_key( $tm_order );
		if ( '' === $tm_order ) {
			return array_values( $ids );
		}

		usort(
			$ids,
			static function( int $a, int $b ) use ( $tm_order ): int {
				switch ( $tm_order ) {
					case 'oldest':
					case 'newest':
						$ua = get_userdata( $a );
						$ub = get_userdata( $b );
						$ta = $ua ? strtotime( (string) $ua->user_registered ) : 0;
						$tb = $ub ? strtotime( (string) $ub->user_registered ) : 0;
						if ( $ta === $tb ) {
							return strcmp( tm_store_ui_get_person_sort_name( $a ), tm_store_ui_get_person_sort_name( $b ) );
						}
						return ( 'oldest' === $tm_order ) ? ( $ta <=> $tb ) : ( $tb <=> $ta );
					case 'name_za':
						return strcmp( tm_store_ui_get_person_sort_name( $b ), tm_store_ui_get_person_sort_name( $a ) );
					case 'name_az':
					default:
						return strcmp( tm_store_ui_get_person_sort_name( $a ), tm_store_ui_get_person_sort_name( $b ) );
				}
			}
		);

		return array_values( $ids );
	}
}

if ( ! function_exists( 'tm_store_ui_get_filtered_person_ids_for_listing' ) ) {
	function tm_store_ui_get_filtered_person_ids_for_listing(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$filters = tm_store_ui_get_active_listing_filters();
		$ids     = tm_store_ui_collect_person_ids_for_listing();

		$ids = array_values(
			array_filter(
				$ids,
				static function( int $user_id ) use ( $filters ): bool {
					if ( ! empty( $filters['category'] ) ) {
						$slugs = tm_store_ui_get_person_category_slugs( $user_id );
						if ( ! in_array( (string) $filters['category'], $slugs, true ) ) {
							return false;
						}
					}

					if ( ! empty( $filters['search'] ) ) {
						$user       = get_userdata( $user_id );
						$profile    = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $user_id ) : array();
						$categories = tm_store_ui_get_person_category_label( $user_id );
						$haystack   = implode(
							' ',
							array_filter(
								array(
									$user ? (string) $user->display_name : '',
									(string) ( $profile['store_name'] ?? '' ),
									(string) ( $profile['bio'] ?? '' ),
									$categories,
								)
							)
						);

						if ( false === stripos( $haystack, (string) $filters['search'] ) ) {
							return false;
						}
					}

					if ( ! empty( $filters['verified'] ) || ! empty( $filters['featured'] ) ) {
						$badges = tm_store_ui_get_person_status_badges( $user_id );
						if ( ! empty( $filters['verified'] ) && empty( $badges['verified'] ) ) {
							return false;
						}
						if ( ! empty( $filters['featured'] ) && empty( $badges['featured'] ) ) {
							return false;
						}
					}

					if ( ! empty( $filters['profile_level'] ) ) {
						$l2_complete = '1' === trim( (string) get_user_meta( $user_id, 'tm_l2_complete', true ) );
						if ( 'mediatic' === $filters['profile_level'] && ! $l2_complete ) {
							return false;
						}
						if ( 'basic' === $filters['profile_level'] && $l2_complete ) {
							return false;
						}
					}

					if ( ! empty( $filters['age_range'] ) && function_exists( 'age_matches_range' ) ) {
						$birth_date = get_user_meta( $user_id, 'demo_birth_date', true );
						if ( ! age_matches_range( $birth_date, (string) $filters['age_range'] ) ) {
							return false;
						}
					}

					foreach ( (array) $filters['meta_filters'] as $meta_key => $value ) {
						if ( ! tm_store_ui_person_matches_meta_filter( $user_id, (string) $meta_key, (string) $value ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		$cache = tm_store_ui_sort_person_ids_for_listing( $ids, (string) $filters['tm_order'] );
		return $cache;
	}
}

if ( ! function_exists( 'tm_store_ui_render_stores_shortcode' ) ) {
	function tm_store_ui_get_stores_shortcode_atts_from_content( string $content ): array {
		if ( '' === $content ) {
			return array();
		}

		$pattern = get_shortcode_regex( array( 'ecomcine-stores', 'dokan-stores' ) );
		if ( ! preg_match( '/'. $pattern .'/s', $content, $matches ) ) {
			return array();
		}

		$tag = isset( $matches[2] ) ? (string) $matches[2] : '';
		if ( 'ecomcine-stores' !== $tag && 'dokan-stores' !== $tag ) {
			return array();
		}

		$atts = shortcode_parse_atts( $matches[3] ?? '' );
		return is_array( $atts ) ? $atts : array();
	}

	function tm_store_ui_get_stores_pagination_config( array $raw_atts = array() ): array {
		$grid_settings = tm_store_ui_get_persons_grid_settings();

		$atts = shortcode_atts(
			array(
				'per_page' => 0,
				'rows'     => $grid_settings['rows'],
				'columns'  => $grid_settings['columns'],
			),
			$raw_atts,
			'ecomcine-stores'
		);

		$rows    = max( 1, min( 12, (int) $atts['rows'] ) );
		$columns = max( 1, min( 6, (int) $atts['columns'] ) );

		$has_per_page_override = array_key_exists( 'per_page', $raw_atts ) && (int) $raw_atts['per_page'] > 0;
		$per_page              = $has_per_page_override ? max( 1, (int) $atts['per_page'] ) : max( 1, $rows * $columns );

		return array(
			'rows'     => $rows,
			'columns'  => $columns,
			'per_page' => $per_page,
		);
	}

	function tm_store_ui_maybe_redirect_out_of_range_store_page(): void {
		if ( is_admin() || ! is_page() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		if ( ! has_shortcode( $content, 'ecomcine-stores' ) && ! has_shortcode( $content, 'dokan-stores' ) ) {
			return;
		}

		$requested_page = max(
			1,
			(int) get_query_var( 'paged' ),
			(int) get_query_var( 'page' ),
			isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 0
		);

		if ( $requested_page <= 1 ) {
			return;
		}

		$shortcode_atts = tm_store_ui_get_stores_shortcode_atts_from_content( $content );
		$config         = tm_store_ui_get_stores_pagination_config( $shortcode_atts );
		$total_ids      = tm_store_ui_get_filtered_person_ids_for_listing();
		$total_items    = count( $total_ids );
		$max_page       = max( 1, (int) ceil( $total_items / max( 1, $config['per_page'] ) ) );

		if ( $requested_page <= $max_page ) {
			return;
		}

		$base_url = get_permalink( $post_id );
		if ( ! $base_url ) {
			return;
		}

		$target_url = ( $max_page <= 1 ) ? $base_url : trailingslashit( $base_url ) . 'page/' . $max_page . '/';

		$query_args = $_GET;
		unset( $query_args['paged'], $query_args['page'] );
		if ( ! empty( $query_args ) ) {
			$target_url = add_query_arg( $query_args, $target_url );
		}

		wp_safe_redirect( $target_url, 302, 'EcomCine Grid Pager' );
		exit;
	}

	add_action( 'template_redirect', 'tm_store_ui_maybe_redirect_out_of_range_store_page', 20 );

	function tm_store_ui_get_persons_grid_settings(): array {
		$rows     = 2;
		$columns  = 4;
		$settings = get_option( 'ecomcine_settings', array() );

		if ( is_array( $settings ) && isset( $settings['persons_grid'] ) && is_array( $settings['persons_grid'] ) ) {
			if ( isset( $settings['persons_grid']['rows'] ) ) {
				$rows = (int) $settings['persons_grid']['rows'];
			}
			if ( isset( $settings['persons_grid']['columns'] ) ) {
				$columns = (int) $settings['persons_grid']['columns'];
			}
		}

		return array(
			'rows'    => max( 1, min( 12, $rows ) ),
			'columns' => max( 1, min( 6, $columns ) ),
		);
	}

	function tm_store_ui_render_stores_shortcode( $atts = array() ): string {
		$raw_atts = (array) $atts;
		$config   = tm_store_ui_get_stores_pagination_config( $raw_atts );

		$columns  = $config['columns'];
		$per_page = $config['per_page'];
		$paged    = max(
			1,
			(int) get_query_var( 'paged' ),
			(int) get_query_var( 'page' ),
			isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 0
		);

		$all_ids = tm_store_ui_get_filtered_person_ids_for_listing();
			// ── Country filter (from Locations map CTA: ?country=United+States) ────
			$country_filter = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
			if ( '' !== $country_filter ) {
				global $wpdb;
				$ids_in_country = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT user_id FROM {$wpdb->usermeta}
						 WHERE meta_key = 'ecomcine_geo_address'
						   AND ( meta_value = %s OR meta_value LIKE %s )",
						$country_filter,
						'%' . $wpdb->esc_like( ', ' . $country_filter )
					)
				);
				$ids_in_country = ! empty( $ids_in_country )
					? array_map( 'intval', $ids_in_country )
					: array();
				$all_ids = ! empty( $ids_in_country )
					? array_values( array_intersect( $all_ids, $ids_in_country ) )
					: array();
			}
		$total   = count( $all_ids );

		if ( 0 === $total ) {
			return '<p class="dokan-error">No talent found!</p>';
		}

		$offset   = ( $paged - 1 ) * $per_page;
		$page_ids = array_slice( $all_ids, $offset, $per_page );
		$filter_markup = function_exists( 'tm_store_ui_render_person_listing_filters' ) ? tm_store_ui_render_person_listing_filters() : '';

		ob_start();
		?>
		<?php echo $filter_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div id="dokan-seller-listing-wrap" class="grid-view ecomcine-store-listing-wrap ecomcine-store-grid" data-grid-columns="<?php echo esc_attr( (string) $columns ); ?>" style="--tm-grid-columns: <?php echo esc_attr( (string) $columns ); ?>;">
			<div class="seller-listing-content">
				<ul class="dokan-seller-wrap">
					<?php foreach ( $page_ids as $vendor_id ) : ?>
						<?php
						$user = get_userdata( $vendor_id );
						if ( ! $user ) {
							continue;
						}

						$profile = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();
						$name    = ! empty( $profile['store_name'] ) ? (string) $profile['store_name'] : (string) $user->display_name;
						$url     = function_exists( 'ecomcine_get_person_url' ) ? ecomcine_get_person_url( $vendor_id ) : get_author_posts_url( $vendor_id, $user->user_nicename );
						if ( '' === trim( (string) $url ) ) {
							continue;
						}

						$store_user = function_exists( 'tm_store_ui_get_store_user' ) ? tm_store_ui_get_store_user( $vendor_id ) : null;
						$banner     = ( $store_user && method_exists( $store_user, 'get_banner' ) ) ? (string) $store_user->get_banner() : '';
						$avatar     = ( $store_user && method_exists( $store_user, 'get_avatar' ) ) ? (string) $store_user->get_avatar() : '';

						if ( '' === $banner && ! empty( $profile['banner_id'] ) ) {
							$banner = (string) wp_get_attachment_image_url( (int) $profile['banner_id'], 'full' );
						}
						if ( '' === $avatar && ! empty( $profile['avatar_id'] ) ) {
							$avatar = (string) wp_get_attachment_image_url( (int) $profile['avatar_id'], 'thumbnail' );
						}
						if ( '' === $avatar ) {
							$avatar = (string) get_avatar_url( $vendor_id, array( 'size' => 300 ) );
						}

						$categories    = tm_store_ui_get_person_category_label( $vendor_id );
						$location_html = tm_store_ui_get_person_location_display_html( $vendor_id, (array) $profile );
						$badges        = tm_store_ui_get_person_status_badges( $vendor_id );
						?>
						<li class="dokan-single-seller woocommerce coloum-3">
							<div class="store-wrapper">
								<a class="tm-store-card-link" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open profile: %s', 'tm-store-ui' ), $name ) ); ?>"></a>
								<div class="store-header">
									<div class="store-banner">
										<?php if ( '' !== $banner ) : ?>
											<img src="<?php echo esc_url( $banner ); ?>" alt="<?php echo esc_attr( $name ); ?>">
										<?php else : ?>
											<div class="tm-store-fallback-banner" aria-hidden="true"></div>
										<?php endif; ?>
									</div>
								</div>
								<div class="store-content">
									<div class="store-data-container">
										<div class="store-data">
											<div class="vendor-name-featured">
												<h2>
													<span class="vendor-name-pill"><?php echo esc_html( $name ); ?></span>
												</h2>
												<div class="tm-card-status-row <?php echo ( ! $badges['featured'] && ! $badges['verified'] ) ? 'tm-card-status-row--empty' : ''; ?>" aria-hidden="<?php echo ( ! $badges['featured'] && ! $badges['verified'] ) ? 'true' : 'false'; ?>">
													<?php if ( $badges['featured'] ) : ?>
														<span class="featured-label"><?php esc_html_e( 'Featured', 'tm-store-ui' ); ?></span>
													<?php endif; ?>
													<?php if ( $badges['verified'] ) : ?>
														<span class="verified-label"><?php esc_html_e( 'Verified', 'tm-store-ui' ); ?></span>
													<?php endif; ?>
												</div>
											</div>
											<?php if ( '' !== $categories ) : ?>
												<div class="store-categories-wrapper editable-field" style="text-align:left; margin-top:6px;">
													<div class="field-display" style="text-align:left;">
														<span class="store-categories-display" style="text-align:left;"><span class="tm-card-category-value"><?php echo esc_html( $categories ); ?></span></span>
													</div>
												</div>
											<?php endif; ?>
											<?php if ( '' !== $location_html ) : ?>
												<div class="location-wrapper editable-field" style="text-align:left; margin-top:6px;">
													<div class="field-display" style="text-align:left;">
														<span class="location-display" style="text-align:left;"><?php echo wp_kses_post( $location_html ); ?></span>
													</div>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</div>
								<div class="store-footer">
									<div class="seller-avatar">
										<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>" size="150">
									</div>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
					<div class="dokan-clearfix"></div>
				</ul>
				<?php
				$total_pages = (int) ceil( $total / $per_page );
				if ( $total_pages > 1 ) :
					$current_url = get_permalink( get_queried_object_id() );
					if ( ! $current_url ) {
						$current_url = home_url( '/' );
					}
					$base_url = trailingslashit( $current_url ) . '%_%';
					$links = paginate_links(
						array(
							'base'      => $base_url,
							'format'    => 'page/%#%/',
							'current'   => $paged,
							'total'     => $total_pages,
							'type'      => 'array',
							'prev_text' => __( '&larr; Previous', 'tm-store-ui' ),
							'next_text' => __( 'Next &rarr;', 'tm-store-ui' ),
						)
					);
					if ( ! empty( $links ) ) :
						?>
						<div class="pagination-container clearfix">
							<div class="pagination-wrap">
								<ul class="pagination"><li><?php echo wp_kses_post( implode( "</li>\n<li>", $links ) ); ?></li></ul>
							</div>
						</div>
						<?php
					endif;
				endif;
				?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! shortcode_exists( 'ecomcine-stores' ) ) {
	add_shortcode( 'ecomcine-stores', 'tm_store_ui_render_stores_shortcode' );
}

if ( ! function_exists( 'dokan' ) && ! shortcode_exists( 'dokan-stores' ) ) {
	add_shortcode( 'dokan-stores', 'tm_store_ui_render_stores_shortcode' );
}