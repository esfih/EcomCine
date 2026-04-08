<?php
/**
 * Template helper functions previously living in the legacy theme layer.
 *
 * These are utility functions called from templates and from hooks.php.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Fatal logger — forwards PHP fatals to the browser console in WP_DEBUG mode.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'tm_register_fatal_logger' ) ) {
	function tm_register_fatal_logger() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		register_shutdown_function( function() {
			$err = error_get_last();
			if ( ! $err ) { return; }
			$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];
			if ( ! in_array( $err['type'], $fatal_types, true ) ) { return; }
			if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { return; }
			$line    = '[' . gmdate( 'c' ) . '] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
			$payload = json_encode( $line );
			echo '<script>console.error(' . $payload . ');</script>';
		} );
	}
	tm_register_fatal_logger();
}

// ---------------------------------------------------------------------------
// HTML cleanup — strips inert Astra skeleton nodes from frontend HTML.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'tm_cleanup_frontend_shell_markup' ) ) {
	function tm_cleanup_frontend_shell_markup( $html ) {
		if ( ! is_string( $html ) || '' === $html ) { return $html; }
		$html = preg_replace(
			'#<a[^>]*class="skip-link\s+screen-reader-text"[^>]*>\s*Skip\s+to\s+content\s*</a>\s*#i',
			'',
			$html
		);
		$html = preg_replace(
			'#<div\s+class="site-header-primary-section-[^"]*\s+site-header-section\s+ast-flex\s+ast-grid-[^"]*section"\s*>\s*</div>\s*#i',
			'',
			$html
		);
		return $html;
	}
}

// ---------------------------------------------------------------------------
// Age helpers
// ---------------------------------------------------------------------------
if ( ! function_exists( 'calculate_age_from_birth_date' ) ) {
	function calculate_age_from_birth_date( $birth_date ) {
		if ( empty( $birth_date ) ) { return null; }
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
	function age_matches_range( $birth_date, $range ) {
		$age = calculate_age_from_birth_date( $birth_date );
		if ( $age === null ) { return false; }
		switch ( $range ) {
			case '18-25': return $age >= 18 && $age <= 25;
			case '26-35': return $age >= 26 && $age <= 35;
			case '36-45': return $age >= 36 && $age <= 45;
			case '46-55': return $age >= 46 && $age <= 55;
			case '56-65': return $age >= 56 && $age <= 65;
			case '66+':   return $age >= 66;
			default:      return false;
		}
	}
}

// ---------------------------------------------------------------------------
// Vendor URL + QR helpers
// ---------------------------------------------------------------------------
if ( ! function_exists( 'tm_store_ui_bootstrap_vendor_qr_helpers' ) ) {
	/**
	 * Load QR helpers from one canonical source to avoid cross-plugin collisions.
	 */
	function tm_store_ui_bootstrap_vendor_qr_helpers() {
		if (
			function_exists( 'tm_get_vendor_public_profile_url' )
			&& function_exists( 'tm_load_qr_library' )
			&& function_exists( 'tm_get_vendor_qr_svg_markup' )
		) {
			return;
		}

		$candidates = array(
			defined( 'ECOMCINE_DIR' ) ? ECOMCINE_DIR . 'includes/compat/vendor-utilities.php' : '',
			defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR . '/ecomcine/includes/compat/vendor-utilities.php' : '',
			dirname( TM_STORE_UI_DIR ) . '/ecomcine/includes/compat/vendor-utilities.php',
		);

		foreach ( $candidates as $path ) {
			if ( ! $path || ! file_exists( $path ) ) {
				continue;
			}
			require_once $path;
			if (
				function_exists( 'tm_get_vendor_public_profile_url' )
				&& function_exists( 'tm_load_qr_library' )
				&& function_exists( 'tm_get_vendor_qr_svg_markup' )
			) {
				return;
			}
		}

		$fallback = TM_STORE_UI_DIR . 'includes/compat/vendor-utilities-fallback.php';
		if ( file_exists( $fallback ) ) {
			require_once $fallback;
		}
	}
}
tm_store_ui_bootstrap_vendor_qr_helpers();

// ---------------------------------------------------------------------------
// Vendor identity helpers
// ---------------------------------------------------------------------------
if ( ! function_exists( 'get_vendor_id_from_store_user' ) ) {
	function get_vendor_id_from_store_user( $store_user ) {
		if ( is_object( $store_user ) ) {
			if ( method_exists( $store_user, 'get_id' ) ) { return $store_user->get_id(); }
			if ( isset( $store_user->ID ) )                { return $store_user->ID; }
		}
		if ( is_array( $store_user ) ) {
			if ( isset( $store_user['ID'] ) ) { return $store_user['ID']; }
			if ( isset( $store_user['id'] ) ) { return $store_user['id']; }
		}
		return null;
	}
}

if ( ! function_exists( 'render_editable_attribute' ) ) {
	function render_editable_attribute( $args ) {
		$field_name = $args['name'];
		$label      = $args['label'];
		$icon       = $args['icon'] ?? '';
		$user_id    = $args['user_id'];
		$is_owner   = $args['is_owner'];
		$options    = $args['options'] ?? [];
		$editable   = array_key_exists( 'editable', $args ) ? (bool) $args['editable'] : true;
		$is_multi   = ! empty( $args['multi'] );
		$input_type = $args['type'] ?? 'select';
		$edit_label = $args['edit_label'] ?? $label;
		$help_text  = $args['help_text'] ?? '';
		$value      = array_key_exists( 'value', $args ) ? $args['value'] : get_user_meta( $user_id, $field_name, true );
		$raw_value  = $args['raw_value'] ?? $value;

		$display_text = '';
		if ( $is_multi && is_array( $value ) ) {
			$labels = [];
			foreach ( $value as $val ) {
				if ( isset( $options[ $val ] ) ) { $labels[] = $options[ $val ]; }
				elseif ( ! empty( $val ) )        { $labels[] = $val; }
			}
			$display_text = implode( ', ', $labels );
		} elseif ( ! empty( $value ) && isset( $options[ $value ] ) ) {
			$display_text = $options[ $value ];
		} elseif ( ! empty( $value ) ) {
			$display_text = $value;
		}

		if ( empty( $display_text ) && ! $is_owner ) { return; }
		if ( empty( $display_text ) ) { $display_text = 'Not set'; }

		$wrapper_class = $is_owner ? 'editable-field' : '';
		$data_attrs = [
			'data-field'      => esc_attr( $field_name ),
			'data-label'      => esc_attr( $label ),
			'data-edit-label' => esc_attr( $edit_label ),
			'data-input-type' => esc_attr( $input_type ),
			'data-multi'      => $is_multi ? '1' : '0',
			'data-help'       => esc_attr( $help_text ),
			'data-editor'     => 'attribute',
		];
		if ( ! empty( $options ) ) { $data_attrs['data-options'] = esc_attr( wp_json_encode( $options ) ); }
		if ( 'date' === $input_type ) { $data_attrs['data-raw-value'] = esc_attr( $raw_value ); }
		if ( $is_multi ) {
			$data_attrs['data-values'] = esc_attr( wp_json_encode( array_values( (array) $value ) ) );
		} else {
			$data_attrs['data-value'] = esc_attr( is_array( $value ) ? '' : (string) $value );
		}
		$data_attr_string = '';
		foreach ( $data_attrs as $attr_key => $attr_value ) {
			if ( '' === $attr_value ) { continue; }
			$data_attr_string .= ' ' . $attr_key . '="' . $attr_value . '"';
		}
		echo '<div class="stat-item ' . $wrapper_class . '"' . $data_attr_string . '>';
		echo '<div class="field-display">';
		if ( is_string( $icon ) && false !== strpos( $icon, '<' ) ) {
			echo '<span class="stat-icon--attribute stat-icon--attribute-html">' . wp_kses_post( $icon ) . '</span>';
		} else {
			echo '<span class="stat-icon--attribute">' . esc_html( (string) $icon ) . '</span>';
		}
		echo esc_html( $label ) . ': ';
		echo '<strong class="field-value stat-value--gold">' . esc_html( $display_text ) . '</strong>';
		if ( $is_owner && $editable ) {
			echo '<button class="edit-field-btn" type="button" title="Edit ' . esc_attr( $label ) . '"><i class="fas fa-pencil-alt"></i></button>';
		}
		echo '</div>';
		echo '</div>';
	}
}

// ---------------------------------------------------------------------------
// Vendor avatar helpers
// ---------------------------------------------------------------------------
if ( ! function_exists( 'mp_get_vendor_avatar_url' ) ) {
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
	function mp_print_vendor_avatar_badge( $product_id ) {
		if ( ! function_exists( 'dokan_get_store_url' ) ) { return; }
		$vendor_id = (int) get_post_field( 'post_author', $product_id );
		if ( ! $vendor_id ) { return; }
		$avatar = mp_get_vendor_avatar_url( $vendor_id, 200 );
		if ( ! $avatar ) { return; }
		$store_url  = function_exists( 'tm_get_vendor_public_profile_url' )
			? tm_get_vendor_public_profile_url( $vendor_id )
			: '';
		if ( ! $store_url && function_exists( 'ecomcine_get_person_route_url' ) ) {
			$store_url = ecomcine_get_person_route_url( $vendor_id );
		}
		if ( ! $store_url ) {
			$store_url = dokan_get_store_url( $vendor_id );
		}
		$store_info = dokan_get_store_info( $vendor_id );
		$store_name = ! empty( $store_info['store_name'] )
			? $store_info['store_name']
			: get_the_author_meta( 'display_name', $vendor_id );
		echo '<a class="mp-vendor-avatar-badge" href="' . esc_url( $store_url ) . '" aria-label="View vendor: ' . esc_attr( $store_name ) . '">'
			. '<img src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $store_name ) . '" loading="lazy" />'
			. '</a>';
	}
}

// ---------------------------------------------------------------------------
// Geo-location display helper
// ---------------------------------------------------------------------------
if ( ! function_exists( 'tm_get_vendor_geo_location_display' ) ) {
function tm_get_vendor_geo_location_display( $vendor_id, $store_info = array(), $store_address = array() ) {
	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id || ! function_exists( 'WC' ) ) { return ''; }
	$geo = function_exists( 'ecomcine_get_geo' ) ? ecomcine_get_geo( $vendor_id ) : array();
	$geo_address = isset( $geo['address'] ) ? (string) $geo['address'] : '';
	if ( empty( $geo_address ) && ! empty( $store_info['location'] ) ) {
		$geo_address = is_string( $store_info['location'] ) ? $store_info['location'] : '';
	}
	if ( empty( $geo_address ) ) { return ''; }
	$address_parts = array_values( array_filter( array_map( 'trim', explode( ',', $geo_address ) ), 'strlen' ) );
	$country_name  = end( $address_parts );
	$countries     = WC()->countries->get_countries();
	$country_code  = '';
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
		$flag_code    = strtolower( $country_code );
		$flag = '<img src="https://flagcdn.com/w40/' . esc_attr( $flag_code ) . '.png"'
		      . ' srcset="https://flagcdn.com/w80/' . esc_attr( $flag_code ) . '.png 2x"'
		      . ' width="35" height="35" loading="lazy" alt="" class="country-flag-img">';
	}
	$geo_address_without_country = count( $address_parts ) >= 2
		? implode( ', ', array_slice( $address_parts, 0, 2 ) )
		: $geo_address;
	$display_parts = [];
	if ( ! empty( $flag ) ) {
		$country_full_name = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : $country_name;
		$display_parts[] = '<span class="country-flag" title="' . esc_attr( $country_full_name ) . '">' . $flag . '</span>';
	}
	$display_parts[] = '<span class="geo-address">' . esc_html( $geo_address_without_country ) . '</span>';
	return implode( '', $display_parts );
}
}
