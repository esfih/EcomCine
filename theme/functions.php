<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

// TEMP: capture fatal errors when WP_DEBUG is on and normal logging fails.
if ( ! function_exists( 'tm_register_fatal_logger' ) ) {
	function tm_register_fatal_logger() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		register_shutdown_function( function() {
			$err = error_get_last();
			if ( ! $err ) {
				return;
			}

			$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];
			if ( ! in_array( $err['type'], $fatal_types, true ) ) {
				return;
			}

			// Never echo HTML into REST API or AJAX responses — it corrupts the JSON.
			if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				return;
			}

			$line = '[' . gmdate( 'c' ) . '] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
			// Output to browser console so it can be copied when file logging is unavailable.
			$payload = json_encode( $line );
			echo '<script>console.error(' . $payload . ');</script>';
		} );
	}

	tm_register_fatal_logger();
}

add_filter( 'show_admin_bar', function( $show ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return $show;
	}
	if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
		return $show;
	}
	$vendor_id = absint( get_query_var( 'author' ) );
	if ( ! $vendor_id ) {
		return $show;
	}
	$is_preonboard = (bool) get_user_meta( $vendor_id, 'tm_preonboard', true );
	$is_admin_editing = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id )
		: false;
	if ( $is_preonboard || $is_admin_editing ) {
		return false;
	}
	return $show;
} );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {
	// Enqueue responsive config FIRST - provides CSS variables for all other styles
	wp_enqueue_style(
		'responsive-config-css',
		get_stylesheet_directory_uri() . '/assets/css/responsive-config.css',
		array( 'astra-theme-css' ),
		'1.0.0',
		'all'
	);
	
	wp_enqueue_style(
		'astra-child-theme-css',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-theme-css', 'responsive-config-css' ),
		CHILD_THEME_ASTRA_CHILD_VERSION,
		'all'
	);

	// Non-player store UI: listing page filters, editing UI.
	// Complements the plugin's player.css which covers the cinematic store page.
	wp_enqueue_style(
		'vendor-store-css',
		get_stylesheet_directory_uri() . '/assets/css/vendor-store.css',
		array( 'astra-child-theme-css', 'responsive-config-css' ),
		'1.0.0',
		'all'
	);

	// Vendor store JS — biography lightbox, profile inline editing,
	// social metrics polling UI, location map modal, onboard share link.
	// Uses vendorStoreUiData (not vendorStoreData) to avoid colliding with the
	// tm-media-player plugin's localization object.
	wp_enqueue_script(
		'vendor-store-js',
		get_stylesheet_directory_uri() . '/assets/js/vendor-store.js',
		array( 'jquery' ),
		'1.1.0',
		true // load in footer
	);

	// Resolve vendor context — only meaningful on a dokan store page.
	$_vs_vendor_id       = 0;
	$_vs_is_owner        = false;
	$_vs_can_edit        = false;
	$_vs_mapbox          = '';

	if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
		$_vs_vendor_id   = absint( get_query_var( 'author' ) );
		$_vs_current_uid = get_current_user_id();
		if ( $_vs_vendor_id ) {
			$_vs_is_owner = ( (int) $_vs_current_uid === (int) $_vs_vendor_id );
			$_vs_can_edit = function_exists( 'tm_can_edit_vendor_profile' )
				? (bool) tm_can_edit_vendor_profile( $_vs_vendor_id )
				: $_vs_is_owner;
		}
		$_vs_mapbox = function_exists( 'dokan_get_option' )
			? (string) dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' )
			: '';
	}

	// Use a distinct variable name (vendorStoreUiData, not vendorStoreData) so this
	// localization never collides with / overwrites the plugin's vendorStoreData object
	// which carries playerMode, vendorStoreRestUrl and other player-critical fields.
	wp_localize_script( 'vendor-store-js', 'vendorStoreUiData', array(
		'ajaxurl'              => admin_url( 'admin-ajax.php' ),
		'ajax_url'             => admin_url( 'admin-ajax.php' ),
		'nonce'                => wp_create_nonce( 'tm_social_fetch' ),
		'editNonce'            => wp_create_nonce( 'vendor_inline_edit' ),
		'onboardNonce'         => wp_create_nonce( 'tm_onboard_share_link' ),
		'userId'               => $_vs_vendor_id,
		'isOwner'              => $_vs_is_owner,
		'canEdit'              => $_vs_can_edit,
		'mapbox_token'         => $_vs_mapbox,
		'jqueryUiCssUrl'       => WP_CONTENT_URL . '/plugins/woocommerce-bookings/dist/jquery-ui-styles.css',
		'jqueryUiCoreUrl'      => includes_url( 'js/jquery/ui/core.min.js' ),
		'jqueryUiDatepickerUrl' => includes_url( 'js/jquery/ui/datepicker.min.js' ),
		'jqueryUiWidgetUrl'    => includes_url( 'js/jquery/ui/widget.min.js' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

/**
 * Remove Dokan Mapbox assets on store pages (we load Mapbox on-demand in the modal).
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
add_action( 'dokan_enqueue_scripts', 'tm_remove_dokan_mapbox_on_store_page', 20 );

function tm_strip_mapbox_resource_hints( $urls, $relation_type ) {
	if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
		return $urls;
	}
	if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) {
		return $urls;
	}

	return array_values( array_filter( $urls, function( $url ) {
		return false === strpos( $url, 'api.mapbox.com' );
	} ) );
}
add_filter( 'wp_resource_hints', 'tm_strip_mapbox_resource_hints', 10, 2 );

/**
 * Remove Google-hosted assets on the frontend.
 * Keep registrations so booking modal can load assets on-demand.
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
add_action( 'wp_enqueue_scripts', 'tm_remove_google_assets', 30 );

function tm_strip_google_resource_hints( $urls, $relation_type ) {
	if ( is_admin() ) {
		return $urls;
	}
	if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) {
		return $urls;
	}

	return array_values( array_filter( $urls, function( $url ) {
		return false === strpos( $url, 'fonts.googleapis.com' )
			&& false === strpos( $url, 'fonts.gstatic.com' )
			&& false === strpos( $url, 'ajax.googleapis.com' );
	} ) );
}
add_filter( 'wp_resource_hints', 'tm_strip_google_resource_hints', 10, 2 );

// Minimal emoji cleanup: remove frontend emoji script + styles.
add_action( 'init', function() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
} );

/**
 * Remove WooCommerce + block assets on vendor store pages.
 * Keep registrations so booking modal can load assets on-demand.
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
add_action( 'wp_enqueue_scripts', 'tm_remove_woocommerce_assets_on_store_page', 100 );

/**
 * Remove Gutenberg block-editor scripts from the Dokan store-listing page.
 *
 * WordPress's @wordpress/preferences package is pulled in by Gutenberg and fires
 * a REST API call to /wp/v2/users/me on every page load. On a public front-end
 * page that call returns 401 (visitor not logged in), which then triggers an
 * "invalid_json" error because the 401 body is an HTML login redirect rather than
 * JSON. None of these packages are needed on the store-listing front-end.
 */
function tm_remove_editor_assets_on_store_listing() {
	if ( is_admin() ) {
		return;
	}
	if ( ! function_exists( 'dokan_is_store_listing' ) || ! dokan_is_store_listing() ) {
		return;
	}

	$script_handles = [
		// These packages fire a REST /wp/v2/users/me call on every page load to
		// hydrate user preferences. On a public front-end page that call returns
		// 401 → "invalid_json" error. They have no functional role here.
		'wp-preferences',
		'wp-preferences-persistence',
	];

	foreach ( $script_handles as $handle ) {
		wp_dequeue_script( $handle );
		wp_deregister_script( $handle );
	}

	$style_handles = [
		'wp-block-library',
		'global-styles',
		'global-styles-inline',
	];

	foreach ( $style_handles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'tm_remove_editor_assets_on_store_listing', 100 );

/**
 * Hide default WooCommerce terms/privacy output in the modal checkout.
 */
function tm_modal_hide_woo_terms( $show_terms ) {
	if ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) {
		return false;
	}

	return $show_terms;
}
add_filter( 'woocommerce_checkout_show_terms', 'tm_modal_hide_woo_terms', 20 );

function tm_modal_privacy_text_filter( $text ) {
	if ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) {
		return '';
	}

	return $text;
}
add_filter( 'woocommerce_get_privacy_policy_text', 'tm_modal_privacy_text_filter', 20 );
add_filter( 'woocommerce_checkout_privacy_policy_text', 'tm_modal_privacy_text_filter', 20 );

/**
 * REMOVED: All text replacement code (vendor/store -> talent)
 * This was breaking admin functionality and causing issues
 */

/**
 * Helper function to calculate age from birth date
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

/**
 * Helper function to check if age falls within a range
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

/**
 * Get the public profile URL for a vendor.
 */
function tm_get_vendor_public_profile_url( $vendor_id ) {
	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id || ! function_exists( 'dokan_get_store_url' ) ) {
		return '';
	}

	$url = dokan_get_store_url( $vendor_id );
	return $url ? $url : '';
}

/**
 * Try to load the QR code library if it exists in the theme.
 */
function tm_load_qr_library() {
	if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
		return true;
	}

	$autoload_paths = [
		get_stylesheet_directory() . '/vendor/autoload.php',
		get_stylesheet_directory() . '/lib/php-qrcode/vendor/autoload.php',
	];

	foreach ( $autoload_paths as $autoload ) {
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
			break;
		}
	}

	return class_exists( '\\chillerlan\\QRCode\\QRCode' );
}

/**
 * Build a sanitized SVG QR code markup for a vendor URL.
 */
function tm_get_vendor_qr_svg_markup( $vendor_id, $args = [] ) {
	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id ) {
		return '';
	}

	$url = tm_get_vendor_public_profile_url( $vendor_id );
	if ( empty( $url ) ) {
		return '';
	}

	$defaults = [
		'size'    => 320,
		'context' => 'default',
	];
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
	$svg_error = '';
	try {
		$qr_output_options = [
			'eccLevel'            => 3,
			'addQuietzone'        => true,
			'quietzoneSize'       => 4,
			'scale'               => 8,
			'outputBase64'        => false,
			'svgAddXmlHeader'     => false,
			'svgPreserveAspectRatio' => 'xMidYMid meet',
		];

		if ( class_exists( '\\chillerlan\\QRCode\\Output\\QRMarkupSVG' ) ) {
			$qr_output_options['outputInterface'] = \chillerlan\QRCode\Output\QRMarkupSVG::class;
		} else {
			$qr_output_options['outputType'] = 'svg';
		}

		$options = new \chillerlan\QRCode\QROptions( $qr_output_options );
		$svg_markup = ( new \chillerlan\QRCode\QRCode( $options ) )->render( $url );
	} catch ( Throwable $e ) {
		$svg_markup = '';
		$svg_error = $e->getMessage();
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

	$allowed = [
		'svg'  => [
			'xmlns' => true,
			'width' => true,
			'height' => true,
			'viewBox' => true,
			'preserveAspectRatio' => true,
			'class' => true,
			'role' => true,
			'aria-hidden' => true,
			'focusable' => true,
			'fill' => true,
		],
		'path' => [
			'd' => true,
			'fill' => true,
			'class' => true,
		],
		'rect' => [
			'x' => true,
			'y' => true,
			'width' => true,
			'height' => true,
			'rx' => true,
			'ry' => true,
			'fill' => true,
			'class' => true,
		],
		'g' => [
			'fill' => true,
			'class' => true,
		],
		'defs' => [
			'class' => true,
		],
		'title' => [
		],
	];

	if ( ! empty( $svg_markup ) ) {
		$svg_markup = wp_kses( $svg_markup, $allowed );
		if ( ! empty( $svg_markup ) ) {
			$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile">' . $svg_markup . '</div>';
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			return $output;
		}
	}

	// Fallback to PNG data URI if SVG is unavailable.
	$png_markup = '';
	$png_error = '';
	try {
		$png_options = new \chillerlan\QRCode\QROptions( [
			'eccLevel'     => 3,
			'outputType'   => 'png',
			'outputBase64' => true,
			'scale'        => 8,
		] );
		$png_markup = ( new \chillerlan\QRCode\QRCode( $png_options ) )->render( $url );
	} catch ( Throwable $e ) {
		$png_markup = '';
		$png_error = $e->getMessage();
	}

	$trimmed_png = ltrim( (string) $png_markup );
	if ( 0 === strpos( $trimmed_png, 'data:image/' ) ) {
		$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $trimmed_png ) . '" alt="Scan to open profile" /></div>';
		set_transient( $cache_key, $output, DAY_IN_SECONDS );
		return $output;
	}

	return '';
}

// Vendor attribute-sets data layer.
require_once get_stylesheet_directory() . '/includes/vendor-attributes/vendor-attribute-sets.php';
// Vendor attribute display/save hooks.
require_once get_stylesheet_directory() . '/includes/vendor-attributes/vendor-attributes-hooks.php';
// [dokan-stores] shortcode hooks and filters → dokan/store-lists/store-lists-hooks.php
require_once get_stylesheet_directory() . '/dokan/store-lists/store-lists-hooks.php';

/**
 * Apply the Platform Page template to any page that either:
 *   (a) has "Platform Page" manually selected as its page template, or
 *   (b) contains the [dokan-stores] shortcode.
 * Gives it the cinematic dark header/footer instead of Astra's defaults.
 */
add_filter( 'template_include', function( $template ) {
	if ( ! is_page() ) {
		return $template;
	}
	$platform_tpl = get_stylesheet_directory() . '/page-platform.php';
	if ( ! file_exists( $platform_tpl ) ) {
		return $template;
	}
	$post_id        = get_the_ID();
	$manual_tpl     = $post_id ? get_post_meta( $post_id, '_wp_page_template', true ) : '';
	$has_shortcode  = $post_id && has_shortcode( get_post_field( 'post_content', $post_id ), 'dokan-stores' );
	if ( 'page-platform.php' === $manual_tpl || $has_shortcode ) {
		return $platform_tpl;
	}
	return $template;
} );

/**
 * Get vendor ID from Dokan store user object/array
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

/**
 * Render a single editable attribute field for vendor profiles
 * Displays value in read mode, and edit controls when vendor is viewing their own profile
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
	
	// Get display text
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
	
	// Only render if there's a value OR owner is viewing (so they can add values)
	if ( empty( $display_text ) && ! $is_owner ) {
		return;
	}
	
	if ( empty( $display_text ) ) {
		$display_text = 'Not set';
	}
	
	// Use unified modal editing pattern
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
	
	// Display Mode (System A pattern)
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
// Social Metrics Engine (Bright Data, fetch/snapshot, dashboard JS, store panel)
//  includes/social-metrics/social-metrics.php
require_once get_stylesheet_directory() . '/includes/social-metrics/social-metrics.php';
/**
 * Normalize store categories on save to keep all schemas in sync.
 * Maps dokan_store_categories[] -> categories/dokan_category + dedicated meta key.
 */
add_action( 'dokan_store_profile_saved', function( $store_id, $dokan_settings ) {
	// Gather categories from POST first, then fallback to settings payload
	$posted = isset( $_POST['dokan_store_categories'] ) ? wp_unslash( $_POST['dokan_store_categories'] ) : [];
	if ( ! is_array( $posted ) ) {
		$posted = $posted ? [ $posted ] : [];
	}

	$settings_categories = [];
	if ( isset( $dokan_settings['categories'] ) ) {
		$settings_categories = $dokan_settings['categories'];
	} elseif ( isset( $dokan_settings['dokan_category'] ) ) {
		$settings_categories = $dokan_settings['dokan_category'];
	}
	if ( ! is_array( $settings_categories ) ) {
		$settings_categories = $settings_categories ? [ $settings_categories ] : [];
	}

	$categories = ! empty( $posted ) ? $posted : $settings_categories;

	// Sanitize to numeric term IDs and dedupe
	$categories = array_filter( array_unique( array_map( 'intval', $categories ) ) );

	// If still empty, do nothing
	if ( empty( $categories ) ) {
		return;
	}

	// Update dedicated meta
	update_user_meta( $store_id, 'dokan_store_categories', $categories );

	// Update serialized profile settings array with both keys for compatibility
	$profile_settings = get_user_meta( $store_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}
	$profile_settings['categories'] = $categories;
	$profile_settings['dokan_category'] = $categories;
	update_user_meta( $store_id, 'dokan_profile_settings', $profile_settings );
}, 20, 2 );





/**
 * Add CSS for Physical Attributes section in Vendor Dashboard
 */
add_action( 'admin_enqueue_scripts', function() {
	wp_add_inline_style( 'wp-admin', '
		/* Physical Attributes section styling in vendor dashboard */
		.physical-attributes-section h3 {
			color: #D4AF37 !important;
		}
		
		.physical-attributes-section + .dokan-form-group label {
			color: #C0C0C0 !important;
		}
		
		/* Target all Physical Attributes labels */
		label[for^="talent_"] {
			color: #C0C0C0 !important;
		}
		
		/* Cameraman section labels */
		label[for="camera_type"],
		label[for="experience_level"],
		label[for="editing_software"],
		label[for="specialization"],
		label[for="years_experience"],
		label[for="equipment_ownership"],
		label[for="lighting_equipment"],
		label[for="audio_equipment"],
		label[for="drone_capability"] {
			color: #333333 !important;
		}
	' );
} );

/**
 * Add CSS for Select2 category tags on vendor dashboard
 */
/**
 * Add JavaScript to vendor dashboard to show/hide category-specific fields
 */
add_action( 'wp_footer', function() {
	// Only on vendor dashboard settings page
	if ( ! dokan_is_seller_dashboard() ) {
		return;
	}
	// Only load on settings page
	global $wp;
	$current_url = home_url( $wp->request );
	if ( strpos( $current_url, '/dashboard/settings' ) === false ) {
		return;
	}

	// Pull stored categories to force-select in UI (prevents fallback to Uncategorized)
	$user_id = get_current_user_id();
	$stored_categories = [];
	$settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( is_array( $settings ) ) {
		if ( ! empty( $settings['dokan_category'] ) ) {
			$stored_categories = $settings['dokan_category'];
		} elseif ( ! empty( $settings['categories'] ) ) {
			$stored_categories = $settings['categories'];
		}
	}
	if ( empty( $stored_categories ) ) {
		$meta_cats = get_user_meta( $user_id, 'dokan_store_categories', true );
		if ( is_array( $meta_cats ) ) {
			$stored_categories = $meta_cats;
		}
	}
	if ( ! is_array( $stored_categories ) ) {
		$stored_categories = $stored_categories ? [ $stored_categories ] : [];
	}
	$stored_categories = array_values( array_filter( array_map( 'intval', $stored_categories ) ) );
	?>
	<script type="text/javascript">
	(function($) {
		'use strict';
		
		
		// Function to update visible fields based on selected categories
		function updateCategoryFields() {
			
			var selectedCategories = [];
			
			// First, let's debug and find all select elements
			$('select').each(function(index) {
				var $select = $(this);
				var name = $select.attr('name');
				var id = $select.attr('id');
				var classes = $select.attr('class');
			});
			
			// Look for Select2 elements
			$('.select2-selection__choice').each(function(index) {
				var title = $(this).attr('title');
				var text = $(this).text().trim();
			});
			
			// Try to find the category select - common Dokan patterns
			var $categorySelect = $('select[name*="categor"]').first();
			if (!$categorySelect.length) {
				$categorySelect = $('#store_category, #dokan_category, select.dokan_category').first();
			}
			
			if ($categorySelect.length) {
				
				// Get selected values from the select element
				var selectedValues = $categorySelect.val();
				
				if (selectedValues && selectedValues.length > 0) {
					// Get the text labels for selected categories
					$categorySelect.find('option:selected').each(function() {
						var categoryLabel = $(this).text().trim().toLowerCase();
						selectedCategories.push(categoryLabel);
					});
				}
			}
			
			// Fallback: read from Select2 UI if select element doesn't work
			if (selectedCategories.length === 0) {
				$('.select2-selection__choice').each(function() {
					var categoryText = $(this).attr('title') || $(this).text();
					categoryText = categoryText.replace('×', '').trim().toLowerCase();
					if (categoryText) {
						selectedCategories.push(categoryText);
					}
				});
			}
			
			
			// Find all elements with data-category attribute
			var $categoryFields = $('[data-category]');
			
			$categoryFields.each(function(index) {
				var $field = $(this);
				var fieldCategory = $field.attr('data-category');
				var fieldClasses = $field.attr('class');
				var currentDisplay = $field.css('display');
			});
			
			// Hide all category-specific fields by default
			$categoryFields.css('display', 'none');
			
			// If no categories selected, keep everything hidden
			if ( selectedCategories.length === 0 ) {
				return;
			}
			
			// Show fields matching selected categories
			var shownCount = 0;
			$categoryFields.each(function() {
				var $field = $(this);
				var fieldCategories = $field.attr('data-category').split(',');
				var shouldShow = false;
				
				
				// Check if any selected category matches this field's categories
				for (var i = 0; i < selectedCategories.length; i++) {
					for (var j = 0; j < fieldCategories.length; j++) {
						if (selectedCategories[i] === fieldCategories[j].trim()) {
							shouldShow = true;
							break;
						}
					}
					if (shouldShow) break;
				}
				
				if (shouldShow) {
					$field.css('display', 'block');
					shownCount++;
				}
			});
			
		}
		
		// Run on page load
		$(document).ready(function() {
			
			// Force-select stored categories to avoid Dokan defaulting to Uncategorized
			var storedCategories = <?php echo wp_json_encode( $stored_categories ); ?>;
			if ( storedCategories && storedCategories.length ) {
				setTimeout(function() {
					var $cat = $('#dokan_store_categories');
					if ( $cat.length ) {
						$cat.val(storedCategories).trigger('change');
					}
				}, 400);
			}

			setTimeout(function() {
				updateCategoryFields();
				
				// Update when categories change - listen to Select2 change event
				$('select[name*="categor"]').on('change', function() {
					updateCategoryFields();
				});
				
				// Also listen to select2:select and select2:unselect events
				$('select[name*="categor"]').on('select2:select select2:unselect', function() {
					setTimeout(updateCategoryFields, 100);
				});
			}, 500);
		});
		
		
	})(jQuery);
	</script>
	<?php
}, 100 ); // Higher priority to run after Dokan


// Admin vendor edit logs + permission helpers  includes/admin/vendor-edit-logs.php
require_once get_stylesheet_directory() . '/includes/admin/vendor-edit-logs.php';
require_once get_stylesheet_directory() . '/includes/admin/vendor-completeness-admin.php';

// Vendor profile completeness engine  includes/vendor-profile/vendor-completeness.php
require_once get_stylesheet_directory() . '/includes/vendor-profile/vendor-completeness.php';

// Vendor profile AJAX handlers  includes/vendor-profile/vendor-profile-ajax.php
require_once get_stylesheet_directory() . '/includes/vendor-profile/vendor-profile-ajax.php';


/**
 * Show Dokan vendor name + link on single product page.
 */
add_action( 'woocommerce_single_product_summary', function () {

	if ( ! function_exists( 'dokan_get_store_url' ) ) return;
	if ( ! is_product() ) return;

	global $product;
	if ( ! $product ) return;

	$vendor_id = get_post_field( 'post_author', $product->get_id() );
	if ( ! $vendor_id ) return;

	$store_url  = dokan_get_store_url( $vendor_id );
	$store_info = dokan_get_store_info( $vendor_id );
	$store_name = ! empty( $store_info['store_name'] )
		? $store_info['store_name']
		: get_the_author_meta( 'display_name', $vendor_id );

	echo '<div class="dokan-vendor-on-product" style="margin:8px 0;">'
		. 'Offered by: <a href="' . esc_url( $store_url ) . '">' . esc_html( $store_name ) . '</a>'
		. '</div>';

}, 6 ); // 6 puts it near the title; try 11 to place after price


/**
 * Get vendor avatar URL (Dokan store gravatar if set, else WP avatar).
 */
function mp_get_vendor_avatar_url( $vendor_id, $size = 240 ) {
	$url = '';

	if ( function_exists( 'dokan_get_store_info' ) ) {
		$store_info = dokan_get_store_info( $vendor_id );

		// Dokan often stores avatar as attachment ID in 'gravatar'
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

/**
 * Print the overlay badge HTML.
 */
function mp_print_vendor_avatar_badge( $product_id ) {
	if ( ! function_exists( 'dokan_get_store_url' ) ) return;

	$vendor_id = (int) get_post_field( 'post_author', $product_id );
	if ( ! $vendor_id ) return;

	$avatar = mp_get_vendor_avatar_url( $vendor_id, 200 );
	if ( ! $avatar ) return;

	$store_url  = dokan_get_store_url( $vendor_id );
	$store_info = dokan_get_store_info( $vendor_id );
	$store_name = ! empty( $store_info['store_name'] )
		? $store_info['store_name']
		: get_the_author_meta( 'display_name', $vendor_id );

	echo '<a class="mp-vendor-avatar-badge" href="' . esc_url( $store_url ) . '" aria-label="View vendor: ' . esc_attr( $store_name ) . '">'
		. '<img src="' . esc_url( $avatar ) . '" alt="' . esc_attr( $store_name ) . '" loading="lazy" />'
		. '</a>';
}

add_action( 'woocommerce_before_single_product_summary', function () {
	if ( ! is_product() ) return;

	global $product;
	if ( ! $product ) return;

	// Output before gallery so it can overlay; CSS will position it
	mp_print_vendor_avatar_badge( $product->get_id() );
}, 19 );

add_action( 'woocommerce_before_shop_loop_item_title', function () {
	global $product;
	if ( ! $product ) return;

	mp_print_vendor_avatar_badge( $product->get_id() );
}, 9 );






/**
 * Save Banner Video for Vendor Profile
 * Store in separate meta keys to avoid conflicts with dokan_profile_settings
 */
add_action( 'dokan_store_profile_saved', function ( $store_id, $dokan_settings ) {
	// Only proceed if banner video fields are being saved
	if ( ! isset( $_POST['dokan_banner_video'] ) && ! isset( $_POST['banner_video_position'] ) ) {
		return;
	}
	
	// Save as separate meta keys instead of inside dokan_profile_settings
	if ( isset( $_POST['dokan_banner_video'] ) ) {
		$video_id = absint( $_POST['dokan_banner_video'] );
		update_user_meta( $store_id, 'dokan_banner_video', $video_id );
	}
	
	if ( isset( $_POST['banner_video_position'] ) ) {
		$position = sanitize_text_field( $_POST['banner_video_position'] );
		update_user_meta( $store_id, 'dokan_banner_video_position', $position );
	}
}, 10, 2 );


/**
 * Add CSS and JavaScript for banner video upload in vendor settings
 */
add_action( 'wp_footer', function() {
	// Only load on Dokan dashboard/settings pages
	if ( ! is_admin() && ( dokan_is_seller_dashboard() || ( isset($_GET['page']) && strpos($_GET['page'], 'dokan') !== false ) || is_page('dashboard') ) ) {
		?>
		<style>
			.dokan-banner-video {
				border: 4px dashed #d8d8d8;
				margin: 0 auto 35px;
				max-width: 850px;
				text-align: center;
				overflow: hidden;
				position: relative;
				min-height: 150px;
				padding: 20px;
			}
			.dokan-banner-video .video-wrap {
				position: relative;
			}
			.dokan-banner-video .dokan-remove-banner-video {
				position: absolute;
				top: 10px;
				right: 10px;
				width: 40px;
				height: 40px;
				background: #000;
				color: #f00;
				font-size: 30px;
				line-height: 40px;
				text-align: center;
				cursor: pointer;
				border-radius: 50%;
				opacity: 0.7;
			}
			.dokan-banner-video .dokan-remove-banner-video:hover {
				opacity: 1;
			}
			.dokan-banner-video .button-area i {
				font-size: 80px;
				color: #d8d8d8;
				display: block;
				margin-bottom: 10px;
			}
		</style>
		<script>
		jQuery(document).ready(function($) {
			
			// Video upload using Dokan's pattern
			var videoFrame;
			$('body').on('click', 'a.dokan-banner-video-drag', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var uploadBtn = $(this);
				
				if (videoFrame) {
					videoFrame.open();
					return;
				}
				
				videoFrame = wp.media({
					title: 'Select Banner Video',
					button: { text: 'Use this video' },
					multiple: false,
					library: { type: 'video' }
				});
				
				videoFrame.on('select', function() {
					var attachment = videoFrame.state().get('selection').first().toJSON();
					var wrapper = uploadBtn.closest('.dokan-banner-video');
					
					wrapper.find('input.dokan-video-field').val(attachment.id);
					wrapper.find('video.dokan-banner-video-preview').attr('src', attachment.url);
					uploadBtn.parent().siblings('.video-wrap', wrapper).removeClass('dokan-hide');
					uploadBtn.parent('.button-area').addClass('dokan-hide');
				});
				
				videoFrame.open();
			});
			
			// Remove video using Dokan's pattern
			$('body').on('click', 'a.dokan-remove-banner-video', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var imageWrap = $(this).closest('.video-wrap');
				var buttonArea = imageWrap.siblings('.button-area');
				
				imageWrap.find('input.dokan-video-field').val('0');
				imageWrap.addClass('dokan-hide');
				buttonArea.removeClass('dokan-hide');
			});
		});
		</script>
		<?php
	}
}, 9999);

// Removed legacy all-store-content output and tab filtering to slim markup.

/**
 * Remove all Dokan store content sections we don't need
 */
add_action( 'init', function() {
	if ( ! function_exists( 'dokan_is_store_page' ) ) {
		return;
	}
	
	// Remove products listing filter
	remove_action( 'woocommerce_before_shop_loop', 'dokan_product_listing_filter', 10 );
	
	// Remove products filter from store page
	remove_action( 'dokan_store_profile_frame_after', 'dokan_after_store_content', 10 );
	
	// Remove any WooCommerce content hooks on store pages
	if ( dokan_is_store_page() ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}
}, 20 );

/**
 * Prevent Astra account login popup from rendering on store pages
 */
add_filter( 'astra_hb_account_popup_output', function( $output ) {
	if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
		return false;
	}
	return $output;
}, 10 );

/**
 * Render cinematic header overlay for vendor store pages.
 */
add_action( 'wp_body_open', function() {
	$on_store_page    = function_exists( 'dokan_is_store_page' ) && dokan_is_store_page();
	$on_store_listing = function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing();
	// Fire on any page using the Platform Page template (manually assigned or auto-applied
	// via the template_include filter). is_page_template() is reliable at wp_body_open time.
	$on_platform      = is_page_template( 'page-platform.php' );
	if ( ! $on_store_page && ! $on_store_listing && ! tm_is_showcase_page() && ! $on_platform ) {
		return;
	}

	$menu_html = wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'menu_class'     => 'tm-header-menu',
		'fallback_cb'    => false,
		'echo'           => false,
		'depth'          => 1,
	) );

	// Prepend a Home icon as the first item inside the <ul>.
	if ( $menu_html ) {
		$home_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
		$home_li = '<li class="menu-item tm-header-home-item">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '" class="menu-link tm-header-home-link" aria-label="Home">'
			. $home_icon_svg
			. '</a>'
			. '</li>';
		$menu_html = preg_replace( '/<ul([^>]*)>/', '<ul$1>' . $home_li, $menu_html, 1 );
	}

	$cart_count = 0;
	if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->cart ) {
		$cart_count = (int) WC()->cart->get_cart_contents_count();
	}

	$favorites_count = 0;
	$notifications_count = 0;
	?>
	<div class="tm-cinematic-header" role="banner">
		<div class="tm-cinematic-header__inner">
			<?php $wp_root = home_url( '/' ); ?>
			<div class="tm-header-left">
				<?php echo get_custom_logo(); ?>
				<div class="tm-header-platforms" aria-label="Watch on TV platforms">
					<span class="tm-header-platforms__label">Watch on</span>
					<span class="tm-header-platforms__icons" aria-hidden="true">
						<img class="platform-logo" src="<?php echo esc_url( $wp_root . 'FireTV.svg' ); ?>" alt="" title="Fire TV" loading="lazy" />
						<img class="platform-logo" src="<?php echo esc_url( $wp_root . 'ROKU.svg' ); ?>" alt="" title="Roku" loading="lazy" />
						<img class="platform-logo" src="<?php echo esc_url( $wp_root . 'AppleTV.svg' ); ?>" alt="" title="Apple TV" loading="lazy" />
						<img class="platform-logo" src="<?php echo esc_url( $wp_root . 'AndroidTV.svg' ); ?>" alt="" title="Android TV" loading="lazy" />
					</span>
				</div>
			</div>
			<?php if ( $menu_html ) { ?>
				<nav class="tm-header-nav" aria-label="Primary">
					<?php echo $menu_html; ?>
				</nav>
			<?php } ?>
			<div class="tm-header-actions" aria-label="Header actions">
				<button class="tm-header-icon tm-header-toggle" type="button" aria-label="Menu" aria-expanded="false">
					<i class="fas fa-bars" aria-hidden="true"></i>
				</button>
				<button class="tm-header-icon" type="button" aria-label="Favorites">
					<i class="fas fa-heart" aria-hidden="true"></i>
					<span class="tm-header-count"><?php echo (int) $favorites_count; ?></span>
				</button>
				<button class="tm-header-icon" type="button" aria-label="Shopping cart">
					<i class="fas fa-shopping-cart" aria-hidden="true"></i>
					<span class="tm-header-count"><?php echo (int) $cart_count; ?></span>
				</button>
				<button class="tm-header-icon" type="button" aria-label="Notifications">
					<i class="fas fa-bell" aria-hidden="true"></i>
					<span class="tm-header-count"><?php echo (int) $notifications_count; ?></span>
				</button>
			</div>
		</div>
	</div>
	<?php
}, 5 );


/**
 * ============================================================================
 * VENDOR LOCATION CUSTOMIZATION (Simplified to Country, City, ZIP only)
 * ============================================================================
 */

/**
 * Hide manual address fields - use map widget instead
 * The Dokan geolocation map widget with autocomplete is more user-friendly
 * than manual Country/City/ZIP entry and automatically generates coordinates
 */
add_action( 'wp_head', function() {
	if ( ! dokan_is_seller_dashboard() ) {
		return;
	}
	?>
	<style>
		/* Hide manual address input fields - replaced by map widget */
		.dokan-form-group.dokan-address-fields,
		#dokan_address_country,
		input[name="dokan_address[city]"],
		input[name="dokan_address[zip]"],
		label[for="dokan_address[country]"],
		label[for="dokan_address[city]"],
		label[for="dokan_address[zip]"],
		.dokan-form-group:has(#dokan_address_country),
		.dokan-form-group:has(input[name="dokan_address[city]"]),
		.dokan-form-group:has(input[name="dokan_address[zip]"]) {
			display: none !important;
		}
		
		/* Move map widget to top of Address section */
		.dokan-form-group:has(#setting_map) {
			order: -1;
			margin-bottom: 30px;
		}
		
		/* Style the map container */
		.dokan-map-wrap {
			border: 2px solid #D4AF37;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 4px 6px rgba(0,0,0,0.1);
		}
		
		/* Map search bar styling */
		.dokan-map-search-bar {
			background: #f5f5f5;
			padding: 15px;
			border-bottom: 1px solid #ddd;
		}
		
		/* Map geocoder input */
		.mapboxgl-ctrl-geocoder--input {
			background: white !important;
			border: 1px solid #D4AF37 !important;
			border-radius: 4px !important;
			padding: 10px 40px 10px 12px !important;
			font-size: 14px !important;
		}
		
		.mapboxgl-ctrl-geocoder--input:focus {
			outline: none !important;
			border-color: #b8941f !important;
			box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1) !important;
		}
		
		/* Gold marker for vendor location */
		.mapboxgl-marker {
			filter: hue-rotate(45deg) saturate(1.5);
		}
		
		/* Map label */
		label[for="setting_map"] {
			color: #D4AF37;
			font-weight: 600;
			font-size: 16px;
			display: flex;
			align-items: center;
		}
		
		/* Center the map container */
		.dokan-form-group:has(#setting_map) {
			display: flex;
			flex-direction: column;
			align-items: center;
			width: 100%;
			margin-bottom: 30px;
		}
		
		.dokan-form-group:has(#setting_map) .dokan-w3 {
			width: 100%;
			text-align: center;
			margin-bottom: 10px;
		}
		
		.dokan-form-group:has(#setting_map) .dokan-w6 {
			width: 100%;
			max-width: 800px;
		}
		
		/* Tooltip icon styling */
		.map-tooltip-icon {
			margin-left: 8px;
			font-size: 14px;
			color: #888;
			cursor: help;
		}
	</style>
	
	<!-- Hide Dokan Geolocation Filters on Store Listing Page -->
	<style>
		/* Hide geolocation filters on store listing page */
		.dokan-geolocation-location-filters,
		.store-lists-other-filter-wrap .dokan-geolocation-location-filters {
			display: none !important;
			visibility: hidden !important;
			height: 0 !important;
			overflow: hidden !important;
			opacity: 0 !important;
		}
	</style>
	
	<script>
		jQuery(document).ready(function($) {
			// Remove geolocation filters from DOM on store listing page
			$('.dokan-geolocation-location-filters').remove();
		});
	</script>
	
	<script>
		jQuery(document).ready(function($) {
			// Move map widget to appear right before Demographic & Availability section
			var $mapContainer = $('.dokan-form-group:has(#setting_map), .dokan-form-group:has(label[for="setting_map"])');
			var $demographicSection = $('.dokan-form-group.demographic-availability-section');
			
			if ($mapContainer.length && $demographicSection.length) {
				// Move map container to appear immediately before demographic section
				$mapContainer.insertBefore($demographicSection);
			}
			
			// Change map label to "Store Location" with icon and tooltip
			var tooltipText = 'Type your city name or address in the search box below, then click on the map to set your exact location.';
			$('label[for="setting_map"]').html(
				'📍 Store Location ' +
				'<i class="fas fa-question-circle map-tooltip-icon" title="' + tooltipText + '"></i>'
			);
			
			// Remove any existing help text paragraph
			$('.map-help-text').remove();
			
			// Ensure map container is visible
			$mapContainer.css('display', 'flex');
		});
	</script>
	<?php
}, 999 );

/**
 * Remove unused address fields from seller address fields filter
 * Keep basic structure but fields will be hidden via CSS
 */
add_filter( 'dokan_seller_address_fields', function( $fields ) {
	// Remove street fields and state
	unset( $fields['street_1'] );
	unset( $fields['street_2'] );
	unset( $fields['state'] );
	
	// Make ZIP code not required since it's hidden
	// Map widget will provide full address data
	if ( isset( $fields['zip'] ) ) {
		$fields['zip']['required'] = 0;
	}
	if ( isset( $fields['city'] ) ) {
		$fields['city']['required'] = 0;
	}
	if ( isset( $fields['country'] ) ) {
		$fields['country']['required'] = 0;
	}
	
	// IMPORTANT: Geolocation map fields will auto-populate location data
	// Map widget automatically generates lat/lng coordinates
	
	return $fields;
}, 10, 1 );

/**
 * Hide Dokan Geolocation Filters on Store Listing Page
 * Remove the location/radius filters added by geolocation module
 */
add_action( 'wp_footer', function() {
	?>
	<style>
		/* Hide geolocation filters on store listing page */
		.dokan-geolocation-location-filters {
			display: none !important;
			visibility: hidden !important;
			height: 0 !important;
			overflow: hidden !important;
			opacity: 0 !important;
		}
	</style>
	<script>
		jQuery(document).ready(function($) {
			// Remove geolocation filters from DOM
			$('.dokan-geolocation-location-filters').remove();
		});
	</script>
	<?php
}, 999 );

/**
 * Change "Address" label to "Location"
 */
add_filter( 'gettext', function( $translated_text, $text, $domain ) {
	if ( 'dokan-lite' === $domain || 'dokan' === $domain ) {
		if ( $text === 'Address' ) {
			return 'Location';
		}
		if ( $text === 'Store Address & Details' ) {
			return 'Store Location & Details';
		}
		if ( $text === 'Provide your store locations to be displayed on the site.' ) {
			return 'Provide your store location to be displayed on the site.';
		}
	}
	return $translated_text;
}, 10, 3 );

/**
 * Build vendor location display - Use Dokan Geolocation Data Only
 * Display: Country Flag + Full Address from map autocomplete
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
		// Use flagcdn.com image instead of Unicode regional-indicator emoji — Windows
		// does not render flag emoji sequences as flag images (shows "GB" text instead).
		$flag_code = strtolower( $country_code );
		$flag      = '<img src="https://flagcdn.com/w40/' . esc_attr( $flag_code ) . '.png"'
		           . ' srcset="https://flagcdn.com/w80/' . esc_attr( $flag_code ) . '.png 2x"'
		           . ' width="35" height="35"'
		           . ' loading="lazy"'
		           . ' alt=""'
		           . ' class="country-flag-img">';
	}

	if ( count( $address_parts ) >= 2 ) {
		// Show only the first two segments (city + region).
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

/**
 * Customize vendor store location display - Use Dokan Geolocation Data Only
 */
add_filter( 'dokan_store_header_adress', function( $formatted_address, $store_address, $short_address ) {
	$vendor_id = 0;
	if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
		$vendor_id = (int) get_query_var( 'author' );
	}
	if ( ! $vendor_id ) {
		global $post;
		$vendor_id = $post ? (int) get_post_field( 'post_author', $post->ID ) : 0;
	}
	$store_info = $vendor_id ? dokan_get_store_info( $vendor_id ) : array();
	$geo_display = tm_get_vendor_geo_location_display( $vendor_id, $store_info, $store_address );

	return $geo_display;
}, 10, 3 );

// [vendors_map] shortcode — asset registration + shortcode handler
require_once get_stylesheet_directory() . '/includes/vendors-map/vendors-map-shortcode.php';

// Bypass cache when vendor views their own profile
add_action( 'template_redirect', function() {
	// Only on Dokan store pages
	if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) {
		return;
	}
	
	// Get current user and vendor being viewed
	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) {
		return; // Not logged in
	}
	
	// Get the vendor/store user
	$store_user = dokan()->vendor->get( get_query_var( 'author' ) );
	if ( ! $store_user ) {
		return;
	}
	
	$vendor_id = method_exists( $store_user, 'get_id' ) ? $store_user->get_id() : ( $store_user->ID ?? 0 );
	
	$can_edit_vendor = false;
	if ( function_exists( 'tm_can_edit_vendor_profile' ) ) {
		$can_edit_vendor = tm_can_edit_vendor_profile( $vendor_id, $current_user_id );
	}
	if ( ! $can_edit_vendor ) {
		$can_edit_vendor = ( $current_user_id == $vendor_id ) || current_user_can( 'manage_options' );
	}

	// Bypass cache for vendors viewing themselves and admins editing vendors
	if ( $can_edit_vendor ) {
		// Bypass LiteSpeed Cache
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_control_set_nocache', 'vendor or admin editing profile' );
		}
	}
}, 5 ); // Early priority before template loads

/**
 * Showcase page: remove Astra's transparent-header body class so its JS
 * never measures #masthead height and injects an inline padding-top on #content.
 * (CSS !important cannot override inline styles set by JavaScript.)
 */
add_filter( 'body_class', function( $classes ) {
	if ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() ) {
		$classes = array_diff( $classes, [ 'ast-theme-transparent-header' ] );
	}
	return $classes;
} );

/**
 * Purge LiteSpeed cached page for the Showcase page (ID 968) automatically.
 * LiteSpeed serves cached HTML at the web-server level before PHP runs, so
 * new PHP fixes (template filters, wp_head hooks) won't fire until the cached
 * entry is cleared. This purges page 968 once on next admin_init, then stops.
 */
add_action( 'admin_init', function() {
	if ( ! defined( 'LSCWP_V' ) ) {
		return;
	}
	if ( get_transient( 'tm_showcase_cache_purged' ) ) {
		return;
	}
	// Purge the showcase page and all site cache to be safe.
	do_action( 'litespeed_purge_post', 968 );
	do_action( 'litespeed_purge_all' );
	set_transient( 'tm_showcase_cache_purged', 1, WEEK_IN_SECONDS );
} );





