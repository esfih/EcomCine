<?php
/**
 * Shared vendor utility functions extracted from theme for Phase 1 consolidation.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tm_get_vendor_public_profile_url' ) ) {
	/**
	 * Get the public profile URL for a vendor.
	 */
	function tm_get_vendor_public_profile_url( $vendor_id ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || ! function_exists( 'dokan_get_store_url' ) ) {
			return '';
		}

		$canonical_url = dokan_get_store_url( $vendor_id );
		if ( empty( $canonical_url ) ) {
			return '';
		}

		$current_url = '';
		if ( ! is_admin() ) {
			global $wp;
			if ( isset( $wp ) && is_object( $wp ) && isset( $wp->request ) ) {
				$current_url = home_url( '/' . ltrim( (string) $wp->request, '/' ) . '/' );
			}
		}

		if ( $current_url ) {
			$canonical_path = untrailingslashit( (string) wp_parse_url( $canonical_url, PHP_URL_PATH ) );
			$current_path   = untrailingslashit( (string) wp_parse_url( $current_url, PHP_URL_PATH ) );
			if ( $canonical_path && 0 === strpos( $current_path, $canonical_path ) ) {
				return $current_url;
			}
		}

		return $canonical_url;
	}
}

if ( ! function_exists( 'tm_load_qr_library' ) ) {
	/**
	 * Try to load the QR code library from known locations.
	 */
	function tm_load_qr_library() {
		if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
			return true;
		}

		$autoload_paths = array(
			// Plugin-bundled vendor — highest priority; works regardless of active theme or server config.
			defined( 'ECOMCINE_DIR' ) ? ECOMCINE_DIR . 'vendor/autoload.php' : '',
			// Standalone tm-store-ui plugin vendor (if present).
			defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'vendor/autoload.php' : '',
			// Theme-provided vendor (legacy fallback — astra-child or custom theme).
			get_stylesheet_directory() . '/vendor/autoload.php',
			get_stylesheet_directory() . '/lib/php-qrcode/vendor/autoload.php',
			get_template_directory() . '/vendor/autoload.php',
			get_template_directory() . '/lib/php-qrcode/vendor/autoload.php',
			// Hardcoded astra-child path (legacy fallback when astra-child is installed but not active).
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes/astra-child/vendor/autoload.php' : '',
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes/astra-child/lib/php-qrcode/vendor/autoload.php' : '',
		);

		foreach ( $autoload_paths as $autoload ) {
			if ( ! $autoload || ! file_exists( $autoload ) ) {
				continue;
			}

			require_once $autoload;
			if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
				return true;
			}
		}

		return class_exists( '\\chillerlan\\QRCode\\QRCode' );
	}
}

if ( ! function_exists( 'tm_get_vendor_qr_svg_markup' ) ) {
	/**
	 * Build a sanitized SVG QR code markup for a vendor URL.
	 */
	function tm_get_vendor_qr_svg_markup( $vendor_id, $args = array() ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id ) {
			return '';
		}

		$url = tm_get_vendor_public_profile_url( $vendor_id );
		if ( empty( $url ) ) {
			return '';
		}

		$defaults = array(
			'size'    => 320,
			'context' => 'default',
		);
		$args = wp_parse_args( $args, $defaults );

		$cache_key = 'tm_vendor_qr_svg_' . md5( $url . '|' . (string) $args['size'] . '|' . (string) $args['context'] );
		$cached = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		if ( ! tm_load_qr_library() ) {
			return '';
		}

		if ( ! class_exists( '\\chillerlan\\QRCode\\QROptions' ) || ! class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
			return '';
		}

		$svg_markup = '';
		try {
			$qr_output_options = array(
				'eccLevel'               => 3,
				'addQuietzone'           => true,
				'quietzoneSize'          => 4,
				'scale'                  => 8,
				'outputBase64'           => false,
				'svgAddXmlHeader'        => false,
				'svgPreserveAspectRatio' => 'xMidYMid meet',
			);

			if ( class_exists( '\\chillerlan\\QRCode\\Output\\QRMarkupSVG' ) ) {
				$qr_output_options['outputInterface'] = \chillerlan\QRCode\Output\QRMarkupSVG::class;
			} else {
				$qr_output_options['outputType'] = 'svg';
			}

			$options = new \chillerlan\QRCode\QROptions( $qr_output_options );
			$svg_markup = ( new \chillerlan\QRCode\QRCode( $options ) )->render( $url );
		} catch ( Throwable $e ) {
			$svg_markup = '';
		}

		if ( ! empty( $svg_markup ) && is_string( $svg_markup ) ) {
			$trimmed_markup = ltrim( $svg_markup );
			if ( 0 === strpos( $trimmed_markup, 'data:image/' ) ) {
				$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $trimmed_markup ) . '" alt="Scan to open profile" /></div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}

			if ( false !== strpos( $svg_markup, '<svg' ) ) {
				$svg_data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg_markup );
				$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $svg_data_uri ) . '" alt="Scan to open profile" /></div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}
		}

		$allowed = array(
			'svg'  => array(
				'xmlns'               => true,
				'width'               => true,
				'height'              => true,
				'viewBox'             => true,
				'preserveAspectRatio' => true,
				'class'               => true,
				'role'                => true,
				'aria-hidden'         => true,
				'focusable'           => true,
				'fill'                => true,
			),
			'path' => array(
				'd'     => true,
				'fill'  => true,
				'class' => true,
			),
			'rect' => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'class'  => true,
			),
			'g' => array(
				'fill'  => true,
				'class' => true,
			),
			'defs' => array(
				'class' => true,
			),
			'title' => array(),
		);

		if ( ! empty( $svg_markup ) ) {
			$svg_markup = wp_kses( $svg_markup, $allowed );
			if ( ! empty( $svg_markup ) ) {
				$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile">' . $svg_markup . '</div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}
		}

		$png_markup = '';
		try {
			$png_options = new \chillerlan\QRCode\QROptions(
				array(
					'eccLevel'     => 3,
					'outputType'   => 'png',
					'outputBase64' => true,
					'scale'        => 8,
				)
			);
			$png_markup = ( new \chillerlan\QRCode\QRCode( $png_options ) )->render( $url );
		} catch ( Throwable $e ) {
			$png_markup = '';
		}

		$trimmed_png = ltrim( (string) $png_markup );
		if ( 0 === strpos( $trimmed_png, 'data:image/' ) ) {
			$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $trimmed_png ) . '" alt="Scan to open profile" /></div>';
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			return $output;
		}

		return '';
	}
}

if ( ! function_exists( 'tm_get_vendor_geo_location_display' ) ) {
	/**
	 * Build vendor location display using Dokan geolocation data.
	 */
	function tm_get_vendor_geo_location_display( $vendor_id, $store_info = array(), $store_address = array() ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || ! function_exists( 'WC' ) ) {
			return '';
		}

		$geo_address = get_user_meta( $vendor_id, 'dokan_geo_address', true );
		if ( empty( $geo_address ) && ! empty( $store_info['location'] ) ) {
			$geo_address = is_string( $store_info['location'] ) ? $store_info['location'] : '';
		}
		if ( empty( $geo_address ) ) {
			return '';
		}

		$address_parts = array_map( 'trim', explode( ',', $geo_address ) );
		$address_parts = array_values( array_filter( $address_parts, 'strlen' ) );
		$country_name = end( $address_parts );

		$countries = WC()->countries->get_countries();
		$country_code = '';
		foreach ( $countries as $code => $name ) {
			if ( stripos( $name, $country_name ) !== false || stripos( $country_name, $name ) !== false ) {
				$country_code = $code;
				break;
			}
		}

		if ( ! $country_code && ! empty( $store_address['country'] ) ) {
			$country_code = $store_address['country'];
		}

		$flag = '';
		if ( $country_code && strlen( $country_code ) === 2 ) {
			$country_code = strtoupper( $country_code );
			$flag_code = strtolower( $country_code );
			$flag = '<img src="https://flagcdn.com/w40/' . esc_attr( $flag_code ) . '.png"'
				. ' srcset="https://flagcdn.com/w80/' . esc_attr( $flag_code ) . '.png 2x"'
				. ' width="35" height="35"'
				. ' loading="lazy"'
				. ' alt=""'
				. ' class="country-flag-img">';
		}

		if ( count( $address_parts ) >= 2 ) {
			$geo_address_without_country = implode( ', ', array_slice( $address_parts, 0, 2 ) );
		} else {
			$geo_address_without_country = $geo_address;
		}

		$display_parts = array();
		if ( ! empty( $flag ) ) {
			$country_full_name = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_name;
			$display_parts[] = '<span class="country-flag" title="' . esc_attr( $country_full_name ) . '">' . $flag . '</span>';
		}
		$display_parts[] = '<span class="geo-address">' . $geo_address_without_country . '</span>';

		return implode( '', $display_parts );
	}
}

if ( ! function_exists( 'calculate_age_from_birth_date' ) ) {
	/**
	 * Helper function to calculate age from birth date.
	 */
	function calculate_age_from_birth_date( $birth_date ) {
		if ( empty( $birth_date ) ) {
			return null;
		}

		try {
			$birth = new DateTime( $birth_date );
			$today = new DateTime();
			return $today->diff( $birth )->y;
		} catch ( Exception $e ) {
			return null;
		}
	}
}

if ( ! function_exists( 'age_matches_range' ) ) {
	/**
	 * Helper function to check if age falls within a range.
	 */
	function age_matches_range( $birth_date, $range ) {
		$age = calculate_age_from_birth_date( $birth_date );
		if ( $age === null ) {
			return false;
		}

		switch ( $range ) {
			case '18-25':
				return $age >= 18 && $age <= 25;
			case '26-35':
				return $age >= 26 && $age <= 35;
			case '36-45':
				return $age >= 36 && $age <= 45;
			case '46-55':
				return $age >= 46 && $age <= 55;
			case '56-65':
				return $age >= 56 && $age <= 65;
			case '66+':
				return $age >= 66;
			default:
				return false;
		}
	}
}

if ( ! function_exists( 'get_vendor_id_from_store_user' ) ) {
	/**
	 * Get vendor ID from Dokan store user object/array.
	 */
	function get_vendor_id_from_store_user( $store_user ) {
		if ( is_object( $store_user ) ) {
			if ( method_exists( $store_user, 'get_id' ) ) {
				return $store_user->get_id();
			}
			if ( isset( $store_user->ID ) ) {
				return $store_user->ID;
			}
		}
		if ( is_array( $store_user ) ) {
			if ( isset( $store_user['ID'] ) ) {
				return $store_user['ID'];
			}
			if ( isset( $store_user['id'] ) ) {
				return $store_user['id'];
			}
		}
		return null;
	}
}

if ( ! function_exists( 'render_editable_attribute' ) ) {
	/**
	 * Render a single editable attribute field for vendor profiles.
	 */
	function render_editable_attribute( $args ) {
		$field_name = $args['name'];
		$label = $args['label'];
		$icon = $args['icon'] ?? '';
		$user_id = $args['user_id'];
		$is_owner = $args['is_owner'];
		$options = $args['options'] ?? [];
		$editable = array_key_exists( 'editable', $args ) ? (bool) $args['editable'] : true;
		$is_multi = ! empty( $args['multi'] );
		$input_type = $args['type'] ?? 'select'; // select, date, text
		$edit_label = $args['edit_label'] ?? $label; // Different label for edit mode
		$help_text = $args['help_text'] ?? ''; // Help tooltip text
		$value = array_key_exists( 'value', $args ) ? $args['value'] : get_user_meta( $user_id, $field_name, true );
		$raw_value = $args['raw_value'] ?? $value; // For date fields, store raw date separate from display

		$display_text = '';
		if ( $is_multi && is_array( $value ) ) {
			$labels = [];
			foreach ( $value as $val ) {
				if ( isset( $options[ $val ] ) ) {
					$labels[] = $options[ $val ];
				} elseif ( ! empty( $val ) ) {
					$labels[] = $val;
				}
			}
			$display_text = implode( ', ', $labels );
		} elseif ( ! empty( $value ) && isset( $options[ $value ] ) ) {
			$display_text = $options[ $value ];
		} elseif ( ! empty( $value ) ) {
			$display_text = $value;
		}

		if ( empty( $display_text ) && ! $is_owner ) {
			return;
		}

		if ( empty( $display_text ) ) {
			$display_text = 'Not set';
		}

		$wrapper_class = $is_owner ? 'editable-field' : '';
		$data_attrs = [
			'data-field' => esc_attr( $field_name ),
			'data-label' => esc_attr( $label ),
			'data-edit-label' => esc_attr( $edit_label ),
			'data-input-type' => esc_attr( $input_type ),
			'data-multi' => $is_multi ? '1' : '0',
			'data-help' => esc_attr( $help_text ),
			'data-editor' => 'attribute',
		];
		if ( ! empty( $options ) ) {
			$data_attrs['data-options'] = esc_attr( wp_json_encode( $options ) );
		}
		if ( $input_type === 'date' ) {
			$data_attrs['data-raw-value'] = esc_attr( $raw_value );
		}
		if ( $is_multi ) {
			$data_attrs['data-values'] = esc_attr( wp_json_encode( array_values( (array) $value ) ) );
		} else {
			$data_attrs['data-value'] = esc_attr( is_array( $value ) ? '' : (string) $value );
		}
		$data_attr_string = '';
		foreach ( $data_attrs as $attr_key => $attr_value ) {
			if ( $attr_value === '' ) {
				continue;
			}
			$data_attr_string .= ' ' . $attr_key . '="' . $attr_value . '"';
		}

		echo '<div class="stat-item ' . $wrapper_class . '"' . $data_attr_string . '>';
		echo '<div class="field-display">';
		echo '<span class="stat-icon--attribute">' . $icon . '</span>';
		echo esc_html( $label ) . ': ';
		echo '<strong class="field-value stat-value--gold">' . esc_html( $display_text ) . '</strong>';
		if ( $is_owner && $editable ) {
			echo '<button class="edit-field-btn" type="button" title="Edit ' . esc_attr( $label ) . '"><i class="fas fa-pencil-alt"></i></button>';
		}
		echo '</div>';
		echo '</div>';
	}
}

if ( ! function_exists( 'mp_get_vendor_avatar_url' ) ) {
	/**
	 * Get vendor avatar URL from Dokan profile or fallback to WordPress avatar.
	 */
	function mp_get_vendor_avatar_url( $vendor_id, $size = 240 ) {
		$url = '';

		if ( function_exists( 'dokan_get_store_info' ) ) {
			$store_info = dokan_get_store_info( $vendor_id );

			if ( ! empty( $store_info['gravatar'] ) ) {
				$img_id = absint( $store_info['gravatar'] );
				$url    = wp_get_attachment_image_url( $img_id, array( $size, $size ) );
			}
		}

		if ( ! $url ) {
			$url = get_avatar_url( $vendor_id, array( 'size' => $size ) );
		}

		return $url;
	}
}

if ( ! function_exists( 'mp_print_vendor_avatar_badge' ) ) {
	/**
	 * Print vendor avatar badge HTML linked to store page.
	 */
	function mp_print_vendor_avatar_badge( $product_id ) {
		if ( ! function_exists( 'dokan_get_store_url' ) ) {
			return;
		}

		$vendor_id = (int) get_post_field( 'post_author', $product_id );
		if ( ! $vendor_id ) {
			return;
		}

		$avatar = mp_get_vendor_avatar_url( $vendor_id, 200 );
		if ( ! $avatar ) {
			return;
		}

		$store_url  = dokan_get_store_url( $vendor_id );
		$store_info = dokan_get_store_info( $vendor_id );
		$store_name = ! empty( $store_info['store_name'] )
			? $store_info['store_name']
			: get_the_author_meta( 'display_name', $vendor_id );

		echo '<a class="mp-vendor-avatar-badge" href="' . esc_url( $store_url ) . '" aria-label="View vendor: ' . esc_attr( $store_name ) . '">'
			. '<img src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $store_name ) . '" loading="lazy" />'
			. '</a>';
	}
}

if ( ! function_exists( 'tm_modal_hide_woo_terms' ) ) {
	/**
	 * Hide default WooCommerce terms/privacy output in booking modal checkout context.
	 */
	function tm_modal_hide_woo_terms( $show_terms ) {
		if ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) {
			return false;
		}

		return $show_terms;
	}
}

if ( ! function_exists( 'tm_modal_privacy_text_filter' ) ) {
	/**
	 * Blank default privacy text for booking modal checkout context.
	 */
	function tm_modal_privacy_text_filter( $text ) {
		if ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) {
			return '';
		}

		return $text;
	}
}

if ( ! function_exists( 'tm_remove_dokan_mapbox_on_store_page' ) ) {
	/**
	 * Remove Dokan Mapbox assets on store pages.
	 */
	function tm_remove_dokan_mapbox_on_store_page() {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
			return;
		}

		wp_dequeue_style( 'dokan-mapbox-gl' );
		wp_dequeue_style( 'dokan-mapbox-gl-geocoder' );
		wp_dequeue_script( 'dokan-mapbox-gl-geocoder' );
		wp_dequeue_script( 'dokan-maps' );
		wp_deregister_style( 'dokan-mapbox-gl' );
		wp_deregister_style( 'dokan-mapbox-gl-geocoder' );
		wp_deregister_script( 'dokan-mapbox-gl-geocoder' );
		wp_deregister_script( 'dokan-maps' );
	}
}

if ( ! function_exists( 'tm_strip_mapbox_resource_hints' ) ) {
	/**
	 * Remove Mapbox resource hints on store pages.
	 */
	function tm_strip_mapbox_resource_hints( $urls, $relation_type ) {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
			return $urls;
		}
		if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) {
			return $urls;
		}

		return array_values(
			array_filter(
				$urls,
				function( $url ) {
					return false === strpos( $url, 'api.mapbox.com' );
				}
			)
		);
	}
}

if ( ! function_exists( 'tm_remove_google_assets' ) ) {
	/**
	 * Remove Google-hosted assets on the frontend.
	 */
	function tm_remove_google_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_dequeue_style( 'astra-google-fonts' );
		wp_deregister_style( 'astra-google-fonts' );

		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			wp_dequeue_style( 'jquery-ui-style' );
		}
	}
}

if ( ! function_exists( 'tm_strip_google_resource_hints' ) ) {
	/**
	 * Remove Google resource hints.
	 */
	function tm_strip_google_resource_hints( $urls, $relation_type ) {
		if ( is_admin() ) {
			return $urls;
		}
		if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) {
			return $urls;
		}

		return array_values(
			array_filter(
				$urls,
				function( $url ) {
					return false === strpos( $url, 'fonts.googleapis.com' )
						&& false === strpos( $url, 'fonts.gstatic.com' )
						&& false === strpos( $url, 'ajax.googleapis.com' );
				}
			)
		);
	}
}

if ( ! function_exists( 'tm_remove_woocommerce_assets_on_store_page' ) ) {
	/**
	 * Remove WooCommerce + block assets on vendor store pages.
	 */
	function tm_remove_woocommerce_assets_on_store_page() {
		if ( is_admin() ) {
			return;
		}
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
			return;
		}
		$vendor_id = absint( get_query_var( 'author' ) );
		$can_edit = $vendor_id && function_exists( 'tm_can_edit_vendor_profile' )
			? tm_can_edit_vendor_profile( $vendor_id )
			: false;

		$style_handles = [
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce-general',
			'woocommerce-inline',
			'astra-wc-dokan-compatibility-css',
			'wc-blocks-style',
			'wp-block-library',
			'global-styles',
			'global-styles-inline',
			'dashicons',
		];

		if ( $can_edit ) {
			$style_handles = array_values( array_diff( $style_handles, [ 'dashicons' ] ) );
		}

		foreach ( $style_handles as $handle ) {
			wp_dequeue_style( $handle );
		}

		$script_handles = [
			'woocommerce',
			'wc-add-to-cart',
			'wc-cart-fragments',
			'wc-checkout',
			'wc-country-select',
			'wc-address-i18n',
			'wc-jquery-blockui',
			'js-cookie',
		];

		foreach ( $script_handles as $handle ) {
			wp_dequeue_script( $handle );
		}
	}
}

if ( ! function_exists( 'tm_remove_editor_assets_on_store_listing' ) ) {
	/**
	 * Remove Gutenberg editor scripts from Dokan store listing pages.
	 */
	function tm_remove_editor_assets_on_store_listing() {
		if ( is_admin() ) {
			return;
		}
		if ( ! function_exists( 'dokan_is_store_listing' ) || ! dokan_is_store_listing() ) {
			return;
		}

		$script_handles = [
			'wp-preferences',
			'wp-preferences-persistence',
		];

		foreach ( $script_handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
