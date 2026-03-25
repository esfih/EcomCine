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
	wp_enqueue_script(
		'vendor-store-js',
		get_stylesheet_directory_uri() . '/assets/js/vendor-store.js',
		array( 'jquery' ),
		'1.0.0',
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

	wp_localize_script( 'vendor-store-js', 'vendorStoreData', array(
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

/**
 * Bright Data API key (set in wp-config.php or environment)
 */
function tm_get_brightdata_api_key() {
	if ( defined( 'TM_BRIGHTDATA_API_KEY' ) && TM_BRIGHTDATA_API_KEY ) {
		return TM_BRIGHTDATA_API_KEY;
	}
	if ( defined( 'BRIGHTDATA_API_KEY' ) && BRIGHTDATA_API_KEY ) {
		return BRIGHTDATA_API_KEY;
	}
	$option_key = get_option( 'tm_brightdata_api_key' );
	return $option_key ? $option_key : '';
}

/**
 * Monthly social snapshot + growth helpers
 */
function tm_get_monthly_snapshot_key( $timestamp = null ) {
	$timestamp = $timestamp ? (int) $timestamp : current_time( 'timestamp' );
	return 'tm_social_monthly_snapshot_' . gmdate( 'Y_m', $timestamp );
}

function tm_collect_social_totals( $vendor_id ) {
	$totals = [
		'followers' => 0,
		'views'     => 0,
		'reactions' => 0,
	];
	$platforms = [];

	$youtube = get_user_meta( $vendor_id, 'tm_social_metrics_youtube', true );
	if ( is_array( $youtube ) ) {
		$platforms['youtube'] = [
			'followers' => isset( $youtube['subscribers'] ) ? (int) $youtube['subscribers'] : 0,
			'views'     => isset( $youtube['avg_views'] ) ? (int) $youtube['avg_views'] : 0,
			'reactions' => isset( $youtube['avg_reactions'] ) ? (int) $youtube['avg_reactions'] : 0,
		];
		$totals['followers'] += $platforms['youtube']['followers'];
		$totals['views']     += $platforms['youtube']['views'];
		$totals['reactions'] += $platforms['youtube']['reactions'];
	}

	$instagram = get_user_meta( $vendor_id, 'tm_social_metrics_instagram', true );
	if ( is_array( $instagram ) ) {
		$platforms['instagram'] = [
			'followers' => isset( $instagram['followers'] ) ? (int) $instagram['followers'] : 0,
			'views'     => 0,
			'reactions' => isset( $instagram['avg_reactions'] ) ? (int) $instagram['avg_reactions'] : 0,
		];
		$totals['followers'] += $platforms['instagram']['followers'];
		$totals['reactions'] += $platforms['instagram']['reactions'];
	}

	$facebook = get_user_meta( $vendor_id, 'tm_social_metrics_facebook', true );
	if ( is_array( $facebook ) ) {
		$platforms['facebook'] = [
			'followers' => isset( $facebook['page_followers'] ) ? tm_parse_social_number( $facebook['page_followers'] ) : 0,
			'views'     => isset( $facebook['avg_views'] ) ? tm_parse_social_number( $facebook['avg_views'] ) : 0,
			'reactions' => isset( $facebook['avg_reactions'] ) ? tm_parse_social_number( $facebook['avg_reactions'] ) : 0,
		];
		$totals['followers'] += $platforms['facebook']['followers'];
		$totals['views']     += $platforms['facebook']['views'];
		$totals['reactions'] += $platforms['facebook']['reactions'];
	}

	$linkedin = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin', true );
	if ( is_array( $linkedin ) ) {
		$linkedin_followers = isset( $linkedin['followers'] ) ? (int) $linkedin['followers'] : 0;
		$linkedin_connections = isset( $linkedin['connections'] ) ? (int) $linkedin['connections'] : 0;
		$followers_total = $linkedin_followers ? $linkedin_followers : $linkedin_connections;
		$platforms['linkedin'] = [
			'followers' => $followers_total,
			'views'     => isset( $linkedin['avg_views'] ) ? (int) $linkedin['avg_views'] : 0,
			'reactions' => isset( $linkedin['avg_reactions'] ) ? (int) $linkedin['avg_reactions'] : 0,
		];
		$totals['followers'] += $platforms['linkedin']['followers'];
		$totals['views']     += $platforms['linkedin']['views'];
		$totals['reactions'] += $platforms['linkedin']['reactions'];
	}

	return [
		'totals'    => $totals,
		'platforms' => $platforms,
	];
}

function tm_update_monthly_snapshot( $vendor_id ) {
	if ( ! $vendor_id ) {
		return null;
	}
	$payload = tm_collect_social_totals( $vendor_id );
	$payload['captured_at'] = current_time( 'mysql' );
	$key = tm_get_monthly_snapshot_key();
	update_user_meta( $vendor_id, $key, $payload );
	update_user_meta( $vendor_id, 'tm_social_monthly_snapshot_latest', $key );
	return $payload;
}

function tm_build_growth_metric( $current, $previous ) {
	$current = (int) $current;
	$previous = (int) $previous;
	$pct = null;
	if ( $previous > 0 ) {
		$pct = round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}
	return [
		'current'  => $current,
		'previous' => $previous,
		'pct'      => $pct,
	];
}

function tm_parse_social_number( $value ) {
	if ( is_int( $value ) || is_float( $value ) ) {
		return (int) round( $value );
	}
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return 0;
	}
	$value = str_replace( [ ',', ' ' ], '', $value );
	if ( preg_match( '/^([0-9]*\.?[0-9]+)([kKmMbB])$/', $value, $matches ) ) {
		$number = (float) $matches[1];
		switch ( strtolower( $matches[2] ) ) {
			case 'k':
				return (int) round( $number * 1000 );
			case 'm':
				return (int) round( $number * 1000000 );
			case 'b':
				return (int) round( $number * 1000000000 );
		}
	}
	if ( is_numeric( $value ) ) {
		return (int) round( (float) $value );
	}
	return 0;
}

function tm_get_monthly_growth( $vendor_id ) {
	$current_key = tm_get_monthly_snapshot_key();
	$current_snapshot = get_user_meta( $vendor_id, $current_key, true );
	if ( ! is_array( $current_snapshot ) ) {
		$current_snapshot = tm_update_monthly_snapshot( $vendor_id );
	}

	$previous_key = tm_get_monthly_snapshot_key( strtotime( '-1 month', current_time( 'timestamp' ) ) );
	$previous_snapshot = get_user_meta( $vendor_id, $previous_key, true );

	$current_totals = is_array( $current_snapshot ) && isset( $current_snapshot['totals'] ) ? $current_snapshot['totals'] : [ 'followers' => 0, 'views' => 0, 'reactions' => 0 ];
	$previous_totals = is_array( $previous_snapshot ) && isset( $previous_snapshot['totals'] ) ? $previous_snapshot['totals'] : [ 'followers' => 0, 'views' => 0, 'reactions' => 0 ];

	$metrics = [
		'followship' => tm_build_growth_metric( $current_totals['followers'], $previous_totals['followers'] ),
		'viewship'   => tm_build_growth_metric( $current_totals['views'], $previous_totals['views'] ),
		'reactions'  => tm_build_growth_metric( $current_totals['reactions'], $previous_totals['reactions'] ),
	];

	$has_growth = false;
	foreach ( $metrics as $metric ) {
		if ( $metric['pct'] !== null ) {
			$has_growth = true;
			break;
		}
	}

	return [
		'has_previous' => is_array( $previous_snapshot ) && ! empty( $previous_snapshot ),
		'has_growth'   => $has_growth,
		'metrics'      => $metrics,
	];
}

function tm_get_growth_snapshot_meta_key( $cadence ) {
	return 'tm_social_growth_' . $cadence . '_snapshots';
}

function tm_get_growth_due_meta_key( $cadence ) {
	return 'tm_social_growth_due_' . $cadence;
}

function tm_get_growth_snapshots( $vendor_id, $cadence ) {
	$snapshots = get_user_meta( $vendor_id, tm_get_growth_snapshot_meta_key( $cadence ), true );
	return is_array( $snapshots ) ? $snapshots : [];
}

function tm_store_growth_snapshot( $vendor_id, $cadence, $payload, $max ) {
	$snapshots = tm_get_growth_snapshots( $vendor_id, $cadence );
	$snapshots[] = $payload;
	usort( $snapshots, function( $a, $b ) {
		$at = isset( $a['captured_at'] ) ? strtotime( $a['captured_at'] ) : 0;
		$bt = isset( $b['captured_at'] ) ? strtotime( $b['captured_at'] ) : 0;
		return $at <=> $bt;
	} );
	if ( count( $snapshots ) > $max ) {
		$snapshots = array_slice( $snapshots, -1 * $max );
	}
	update_user_meta( $vendor_id, tm_get_growth_snapshot_meta_key( $cadence ), $snapshots );
}

function tm_compute_growth_from_snapshots( $snapshots ) {
	if ( ! is_array( $snapshots ) || count( $snapshots ) < 2 ) {
		return [ 'has_growth' => false, 'metrics' => [] ];
	}
	$last = $snapshots[ count( $snapshots ) - 1 ];
	$prev = $snapshots[ count( $snapshots ) - 2 ];
	$last_totals = isset( $last['totals'] ) && is_array( $last['totals'] ) ? $last['totals'] : [ 'followers' => 0, 'views' => 0, 'reactions' => 0 ];
	$prev_totals = isset( $prev['totals'] ) && is_array( $prev['totals'] ) ? $prev['totals'] : [ 'followers' => 0, 'views' => 0, 'reactions' => 0 ];
	$metrics = [
		'followship' => tm_build_growth_metric( $last_totals['followers'], $prev_totals['followers'] ),
		'viewship'   => tm_build_growth_metric( $last_totals['views'], $prev_totals['views'] ),
		'reactions'  => tm_build_growth_metric( $last_totals['reactions'], $prev_totals['reactions'] ),
	];
	$has_growth = false;
	foreach ( $metrics as $metric ) {
		if ( $metric['pct'] !== null ) {
			$has_growth = true;
			break;
		}
	}
	return [
		'has_growth' => $has_growth,
		'metrics'    => $metrics,
	];
}

function tm_get_growth_rollup( $vendor_id ) {
	$monthly = tm_get_monthly_growth( $vendor_id );
	if ( $monthly['has_growth'] ) {
		return [
			'label'      => 'Monthly Growth',
			'cadence'    => 'monthly',
			'has_growth' => true,
			'metrics'    => $monthly['metrics'],
			'message'    => '',
		];
	}

	$weekly_snapshots = tm_get_growth_snapshots( $vendor_id, 'weekly' );
	$weekly = tm_compute_growth_from_snapshots( $weekly_snapshots );
	if ( $weekly['has_growth'] ) {
		return [
			'label'      => 'Weekly Growth',
			'cadence'    => 'weekly',
			'has_growth' => true,
			'metrics'    => $weekly['metrics'],
			'message'    => '',
		];
	}

	$daily_snapshots = tm_get_growth_snapshots( $vendor_id, 'daily' );
	$daily = tm_compute_growth_from_snapshots( $daily_snapshots );
	if ( $daily['has_growth'] ) {
		return [
			'label'      => 'Daily Growth',
			'cadence'    => 'daily',
			'has_growth' => true,
			'metrics'    => $daily['metrics'],
			'message'    => '',
		];
	}

	$message = 'Not enough data yet (need another daily snapshot).';
	if ( count( $daily_snapshots ) >= 2 && count( $weekly_snapshots ) < 2 ) {
		$message = 'Not enough data yet (need weekly snapshot).';
	} elseif ( count( $weekly_snapshots ) >= 2 ) {
		$message = 'Not enough data yet (need previous month snapshot).';
	}

	return [
		'label'      => 'Daily Growth',
		'cadence'    => 'daily',
		'has_growth' => false,
		'metrics'    => $daily['metrics'],
		'message'    => $message,
	];
}

function tm_growth_plan_is_active( $vendor_id ) {
	$schedule = get_user_meta( $vendor_id, 'tm_social_growth_schedule', true );
	if ( ! is_array( $schedule ) ) {
		return false;
	}
	$now = time();
	foreach ( $schedule as $ts ) {
		if ( (int) $ts > $now ) {
			return true;
		}
	}
	return false;
}

function tm_clear_growth_plan( $vendor_id ) {
	$schedule = get_user_meta( $vendor_id, 'tm_social_growth_schedule', true );
	if ( is_array( $schedule ) ) {
		foreach ( $schedule as $ts ) {
			wp_unschedule_event( (int) $ts, 'tm_growth_refresh_event', [ $vendor_id, (int) $ts ] );
		}
	}
	delete_user_meta( $vendor_id, 'tm_social_growth_schedule' );
	delete_user_meta( $vendor_id, tm_get_growth_due_meta_key( 'daily' ) );
	delete_user_meta( $vendor_id, tm_get_growth_due_meta_key( 'weekly' ) );
}

function tm_schedule_growth_plan( $vendor_id ) {
	if ( ! $vendor_id || tm_growth_plan_is_active( $vendor_id ) ) {
		return;
	}
	$start = time();
	$daily_due = [];
	$weekly_due = [];
	$schedule = [];

	for ( $i = 1; $i <= 7; $i++ ) {
		$ts = $start + ( $i * DAY_IN_SECONDS );
		$daily_due[] = $ts;
		$schedule[] = $ts;
		if ( ! wp_next_scheduled( 'tm_growth_refresh_event', [ $vendor_id, $ts ] ) ) {
			wp_schedule_single_event( $ts, 'tm_growth_refresh_event', [ $vendor_id, $ts ] );
		}
	}

	$weekly_offsets = [ 14, 21 ];
	foreach ( $weekly_offsets as $offset ) {
		$ts = $start + ( $offset * DAY_IN_SECONDS );
		$weekly_due[] = $ts;
		$schedule[] = $ts;
		if ( ! wp_next_scheduled( 'tm_growth_refresh_event', [ $vendor_id, $ts ] ) ) {
			wp_schedule_single_event( $ts, 'tm_growth_refresh_event', [ $vendor_id, $ts ] );
		}
	}

	update_user_meta( $vendor_id, tm_get_growth_due_meta_key( 'daily' ), $daily_due );
	update_user_meta( $vendor_id, tm_get_growth_due_meta_key( 'weekly' ), $weekly_due );
	update_user_meta( $vendor_id, 'tm_social_growth_schedule', $schedule );
}

function tm_trigger_vendor_social_refresh( $vendor_id ) {
	if ( ! $vendor_id ) {
		return;
	}
	$profiles = tm_get_vendor_social_profiles( $vendor_id );
	if ( empty( $profiles ) || ! is_array( $profiles ) ) {
		return;
	}
	if ( ! empty( $profiles['instagram'] ) && function_exists( 'tm_queue_instagram_metrics_refresh' ) ) {
		tm_queue_instagram_metrics_refresh( $vendor_id, $profiles['instagram'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
	}
	if ( ! empty( $profiles['youtube'] ) && function_exists( 'tm_queue_youtube_metrics_refresh' ) ) {
		tm_queue_youtube_metrics_refresh( $vendor_id, $profiles['youtube'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
	}
	if ( ! empty( $profiles['fb'] ) && function_exists( 'tm_queue_facebook_metrics_refresh' ) ) {
		tm_queue_facebook_metrics_refresh( $vendor_id, $profiles['fb'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_last_fetch', current_time( 'mysql' ) );
	} elseif ( ! empty( $profiles['facebook'] ) && function_exists( 'tm_queue_facebook_metrics_refresh' ) ) {
		tm_queue_facebook_metrics_refresh( $vendor_id, $profiles['facebook'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_last_fetch', current_time( 'mysql' ) );
	}
	if ( ! empty( $profiles['linkedin'] ) && function_exists( 'tm_queue_linkedin_metrics_refresh' ) ) {
		tm_queue_linkedin_metrics_refresh( $vendor_id, $profiles['linkedin'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_last_fetch', current_time( 'mysql' ) );
	} elseif ( ! empty( $profiles['linked_in'] ) && function_exists( 'tm_queue_linkedin_metrics_refresh' ) ) {
		tm_queue_linkedin_metrics_refresh( $vendor_id, $profiles['linked_in'] );
		update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_last_fetch', current_time( 'mysql' ) );
	}
}

function tm_record_due_growth_snapshots( $vendor_id ) {
	if ( ! $vendor_id ) {
		return;
	}
	$profiles = tm_get_vendor_social_profiles( $vendor_id );
	if ( empty( $profiles ) || ! is_array( $profiles ) ) {
		return;
	}
	if ( empty( tm_get_growth_snapshots( $vendor_id, 'daily' ) ) ) {
		$monthly_key = get_user_meta( $vendor_id, 'tm_social_monthly_snapshot_latest', true );
		if ( $monthly_key ) {
			$monthly_snapshot = get_user_meta( $vendor_id, $monthly_key, true );
			if ( is_array( $monthly_snapshot ) && ! empty( $monthly_snapshot['totals'] ) && ! empty( $monthly_snapshot['captured_at'] ) ) {
				$bootstrap = [
					'totals'      => $monthly_snapshot['totals'],
					'captured_at' => $monthly_snapshot['captured_at'],
					'bootstrap'   => true,
				];
				tm_store_growth_snapshot( $vendor_id, 'daily', $bootstrap, 7 );
			}
		}
	}
	$totals = tm_collect_social_totals( $vendor_id )['totals'];
	$total_sum = (int) $totals['followers'] + (int) $totals['views'] + (int) $totals['reactions'];
	if ( $total_sum <= 0 ) {
		return;
	}

	$now = time();
	$payload = [
		'totals'      => $totals,
		'captured_at' => current_time( 'mysql' ),
	];

	$daily_snapshots = tm_get_growth_snapshots( $vendor_id, 'daily' );
	if ( empty( $daily_snapshots ) ) {
		tm_store_growth_snapshot( $vendor_id, 'daily', $payload, 7 );
	}

	foreach ( [ 'daily' => 7, 'weekly' => 2 ] as $cadence => $max ) {
		$due = get_user_meta( $vendor_id, tm_get_growth_due_meta_key( $cadence ), true );
		$due = is_array( $due ) ? $due : [];
		$has_due = false;
		$remaining = [];
		foreach ( $due as $ts ) {
			$ts = (int) $ts;
			if ( $ts <= $now ) {
				$has_due = true;
				continue;
			}
			$remaining[] = $ts;
		}
		if ( $has_due ) {
			tm_store_growth_snapshot( $vendor_id, $cadence, $payload, $max );
		}
		update_user_meta( $vendor_id, tm_get_growth_due_meta_key( $cadence ), $remaining );
	}
}

function tm_after_social_metrics_update( $vendor_id ) {
	tm_update_monthly_snapshot( $vendor_id );
	tm_record_due_growth_snapshots( $vendor_id );
}

function tm_handle_growth_refresh_event( $vendor_id, $ts ) {
	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id ) {
		return;
	}
	tm_trigger_vendor_social_refresh( $vendor_id );
	$schedule = get_user_meta( $vendor_id, 'tm_social_growth_schedule', true );
	if ( is_array( $schedule ) ) {
		$remaining = [];
		foreach ( $schedule as $item ) {
			if ( (int) $item !== (int) $ts ) {
				$remaining[] = (int) $item;
			}
		}
		update_user_meta( $vendor_id, 'tm_social_growth_schedule', $remaining );
	}
}

add_action( 'tm_growth_refresh_event', 'tm_handle_growth_refresh_event', 10, 2 );

add_filter( 'cron_schedules', function( $schedules ) {
	if ( ! isset( $schedules['tm_monthly'] ) ) {
		$schedules['tm_monthly'] = [
			'interval' => defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 30 * DAY_IN_SECONDS,
			'display'  => __( 'Every 30 days (Talent Marketplace)', 'astra-child' ),
		];
	}
	return $schedules;
} );

add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'tm_monthly_social_refresh' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'tm_monthly', 'tm_monthly_social_refresh' );
	}
} );

function tm_run_monthly_social_refresh() {
	$vendors = get_users( [
		'role__in' => [ 'seller', 'vendor' ],
		'fields'   => 'ID',
		'number'   => 500,
	] );
	if ( empty( $vendors ) ) {
		return;
	}
	foreach ( $vendors as $vendor_id ) {
		$profiles = tm_get_vendor_social_profiles( $vendor_id );
		if ( ! empty( $profiles['instagram'] ) ) {
			tm_queue_instagram_metrics_refresh( $vendor_id, $profiles['instagram'] );
		}
		if ( ! empty( $profiles['youtube'] ) ) {
			tm_queue_youtube_metrics_refresh( $vendor_id, $profiles['youtube'] );
		}
		if ( ! empty( $profiles['fb'] ) ) {
			tm_queue_facebook_metrics_refresh( $vendor_id, $profiles['fb'] );
		}
		if ( ! empty( $profiles['linkedin'] ) ) {
			tm_queue_linkedin_metrics_refresh( $vendor_id, $profiles['linkedin'] );
		} elseif ( ! empty( $profiles['linked_in'] ) ) {
			tm_queue_linkedin_metrics_refresh( $vendor_id, $profiles['linked_in'] );
		}
		tm_update_monthly_snapshot( $vendor_id );
	}
}
add_action( 'tm_monthly_social_refresh', 'tm_run_monthly_social_refresh' );

/**
 * Normalize vendor social profiles
 */
function tm_get_vendor_social_profiles( $vendor_id ) {
	if ( function_exists( 'dokan' ) ) {
		$vendor = dokan()->vendor->get( $vendor_id );
		if ( $vendor && method_exists( $vendor, 'get_social_profiles' ) ) {
			return $vendor->get_social_profiles();
		}
	}
	$profile_settings = get_user_meta( $vendor_id, 'dokan_profile_settings', true );
	return is_array( $profile_settings ) && isset( $profile_settings['social'] ) ? $profile_settings['social'] : [];
}

/**
 * Parse LinkedIn "interaction" strings (e.g., "Liked by Name1, Name2") into a numeric count.
 * Falls back to the largest digit found if present; otherwise counts comma/"and" separated names.
 */
function tm_linkedin_interaction_count( $interaction ) {
	if ( ! is_string( $interaction ) || $interaction === '' ) {
		return 0;
	}
	if ( preg_match_all( '/\d+/', $interaction, $matches ) && ! empty( $matches[0] ) ) {
		return (int) max( array_map( 'intval', $matches[0] ) );
	}
	$parts = preg_split( '/,|\band\b/i', $interaction );
	$parts = array_filter( array_map( 'trim', (array) $parts ) );
	return count( $parts );
}

/**
 * Normalize and store Instagram metrics from a Bright Data response payload
 */
function tm_process_instagram_payload( $vendor_id, $data, $raw_body, $debug_base, $save_debug, $instagram_url ) {
	// Handle explicit API errors
	if ( isset( $data['error'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Bright Data error',
			'error_code' => $data['error'],
			'raw'        => $data,
			'raw_body'   => $raw_body,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
		return false;
	}
	if ( isset( $data[0]['error'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Bright Data error',
			'error_code' => $data[0]['error'],
			'raw'        => $data,
			'raw_body'   => $raw_body,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	// Some dataset responses may wrap records under a "data" key
	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		$data = $data['data'];
	}

	// Allow both associative single-profile responses and array-of-profile responses
	$profile = null;
	if ( is_array( $data ) && isset( $data['posts'] ) ) {
		$profile = $data;
	} elseif ( is_array( $data ) && isset( $data['followers'] ) && ! isset( $data[0] ) ) {
		$profile = $data;
	} elseif ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
		$profile = $data[0];
	}

	if ( ! $profile ) {
		$save_debug( array_merge( $debug_base, [
			'error'       => 'Unexpected response payload',
			'raw'         => $data,
			'raw_body'    => $raw_body,
			'data_keys'   => is_array( $data ) ? array_keys( $data ) : 'not_array',
			'data_sample' => is_array( $data ) ? json_encode( array_slice( $data, 0, 2 ), JSON_PRETTY_PRINT ) : null,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	$posts = isset( $profile['posts'] ) && is_array( $profile['posts'] ) ? $profile['posts'] : [];
	$post_count = count( $posts );
	$total_likes = 0;
	$total_comments = 0;

	if ( $post_count === 0 && empty( $profile['followers'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'No Instagram data returned (posts/followers empty)',
			'raw'   => $data,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	if ( $post_count > 0 ) {
		foreach ( $posts as $post ) {
			if ( isset( $post['likes'] ) ) {
				$total_likes += (int) $post['likes'];
			}
			if ( isset( $post['comments'] ) ) {
				$total_comments += (int) $post['comments'];
			}
		}
	}

	$avg_reactions = $post_count > 0 ? round( $total_likes / $post_count ) : 0;
	$avg_comments = $post_count > 0 ? round( $total_comments / $post_count ) : 0;

	$metrics = [
		'followers'      => isset( $profile['followers'] ) ? (int) $profile['followers'] : 0,
		'avg_reactions'  => $avg_reactions,
		'avg_comments'   => $avg_comments,
		'profile_name'   => isset( $profile['profile_name'] ) ? $profile['profile_name'] : '',
		'profile_image'  => isset( $profile['profile_image_link'] ) ? $profile['profile_image_link'] : '',
		'url'            => isset( $profile['profile_url'] ) ? $profile['profile_url'] : $instagram_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	update_user_meta( $vendor_id, 'tm_social_metrics_instagram', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', $data );
	update_user_meta( $vendor_id, 'tm_social_instagram_url', $instagram_url );
	update_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
	delete_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );

	return true;
}

/**
 * Normalize and store YouTube channel metrics from Bright Data response payload
 */
function tm_process_youtube_payload( $vendor_id, $data, $raw_body, $debug_base, $save_debug, $youtube_url ) {
	// Handle explicit API errors
	if ( isset( $data['error'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Bright Data error',
			'error_code' => $data['error'],
			'raw'        => $data,
			'raw_body'   => $raw_body,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
		return false;
	}
	if ( isset( $data[0]['error'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Bright Data error',
			'error_code' => $data[0]['error'],
			'raw'        => $data,
			'raw_body'   => $raw_body,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		$data = $data['data'];
	}

	$profile = null;
	if ( is_array( $data ) && isset( $data['subscribers'] ) ) {
		$profile = $data;
	} elseif ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
		$profile = $data[0];
	}

	if ( ! $profile ) {
		$save_debug( array_merge( $debug_base, [
			'error'       => 'Unexpected response payload',
			'raw'         => $data,
			'raw_body'    => $raw_body,
			'data_keys'   => is_array( $data ) ? array_keys( $data ) : 'not_array',
			'data_sample' => is_array( $data ) ? json_encode( array_slice( $data, 0, 2 ), JSON_PRETTY_PRINT ) : null,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	$top_videos = isset( $profile['top_videos'] ) && is_array( $profile['top_videos'] ) ? $profile['top_videos'] : [];
	$video_sample_count = 0;
	$total_views_sample = 0;
	$like_samples = 0;
	$total_likes_sample = 0;
	if ( $top_videos ) {
		foreach ( $top_videos as $video ) {
			if ( isset( $video['views'] ) && is_numeric( $video['views'] ) ) {
				$total_views_sample += (int) $video['views'];
				$video_sample_count++;
			}
			$likes_value = null;
			if ( isset( $video['likes'] ) && is_numeric( $video['likes'] ) ) {
				$likes_value = (int) $video['likes'];
			} elseif ( isset( $video['like_count'] ) && is_numeric( $video['like_count'] ) ) {
				$likes_value = (int) $video['like_count'];
			}
			if ( $likes_value !== null ) {
				$total_likes_sample += $likes_value;
				$like_samples++;
			}
		}
	}

	$avg_views = $video_sample_count > 0 ? round( $total_views_sample / $video_sample_count ) : 0;
	$avg_reactions = $like_samples > 0 ? round( $total_likes_sample / $like_samples ) : 0;

	if ( empty( $profile['subscribers'] ) && $avg_views === 0 && empty( $profile['views'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'No YouTube data returned (subscribers/views empty)',
			'raw'   => $data,
		] ) );
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
		return false;
	}

	$metrics = [
		'subscribers'    => isset( $profile['subscribers'] ) ? (int) $profile['subscribers'] : 0,
		'total_views'    => isset( $profile['views'] ) ? (int) $profile['views'] : 0,
		'videos_count'   => isset( $profile['videos_count'] ) ? (int) $profile['videos_count'] : 0,
		'avg_views'      => $avg_views,
		'avg_reactions'  => $avg_reactions,
		'top_samples'    => $video_sample_count,
		'profile_name'   => isset( $profile['name'] ) ? $profile['name'] : '',
		'profile_image'  => isset( $profile['profile_image'] ) ? $profile['profile_image'] : '',
		'url'            => isset( $profile['url'] ) ? $profile['url'] : $youtube_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	update_user_meta( $vendor_id, 'tm_social_metrics_youtube', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_youtube_raw', $data );
	update_user_meta( $vendor_id, 'tm_social_youtube_url', $youtube_url );
	update_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
	delete_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );

	return true;
}

/**
 * Fetch Instagram profile metrics from Bright Data posts dataset
 */
function tm_fetch_instagram_metrics( $vendor_id, $instagram_url ) {
	$api_key = tm_get_brightdata_api_key();
	$debug_base = [
		'requested_url' => $instagram_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', $payload );
		}
	};
	if ( ! $api_key || ! $instagram_url ) {
		$save_debug( array_merge( $debug_base, [
			'error' => ! $api_key ? 'Missing Bright Data API key.' : 'Missing Instagram URL.',
		] ) );
		return;
	}

	$dataset_id = 'gd_l1vikfch901nx3by4'; // Instagram posts dataset
	$endpoint = add_query_arg( [
		'dataset_id'     => $dataset_id,
		'notify'         => 'false',
		'include_errors' => 'true',
	], 'https://api.brightdata.com/datasets/v3/scrape' );

	$response = wp_remote_post( $endpoint, [
		'timeout' => 60,
		'connect_timeout' => 20,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'input' => [
				[ 'url' => $instagram_url ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$raw_body = $body;
	$data = json_decode( $body, true );

	// Snapshot flow: the dataset may respond with a snapshot_id instead of immediate records
	if ( is_array( $data ) && isset( $data['snapshot_id'] ) ) {
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id', $data['snapshot_id'] );
		$save_debug( array_merge( $debug_base, $data, [
			'note' => 'Snapshot created; awaiting download.',
		] ) );
		tm_queue_instagram_snapshot_fetch( $vendor_id, $data['snapshot_id'], $instagram_url );
		return;
	}

	// Immediate data path
	tm_process_instagram_payload( $vendor_id, $data, $raw_body, $debug_base, $save_debug, $instagram_url );
}

/**
 * Queue Instagram snapshot fetch
 */
function tm_queue_instagram_snapshot_fetch( $vendor_id, $snapshot_id, $instagram_url ) {
	if ( ! $vendor_id || ! $snapshot_id ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_instagram_snapshot_event', [ $vendor_id, $snapshot_id, $instagram_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 30, 'tm_fetch_instagram_snapshot_event', [ $vendor_id, $snapshot_id, $instagram_url ] );
	}
}

/**
 * Fetch Instagram snapshot data from Bright Data
 */
function tm_fetch_instagram_snapshot( $vendor_id, $snapshot_id, $instagram_url = '' ) {
	$api_key = tm_get_brightdata_api_key();
	if ( ! $api_key || ! $snapshot_id ) {
		return;
	}

	$debug_base = [
		'snapshot_id'   => $snapshot_id,
		'requested_url' => $instagram_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', $payload );
		}
	};

	$endpoint = 'https://api.brightdata.com/datasets/v3/snapshot/' . rawurlencode( $snapshot_id ) . '?format=json';
	$response = wp_remote_get( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
		],
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( 404 === (int) $code ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_instagram_snapshot_fetch( $vendor_id, $snapshot_id, $instagram_url );
		}
		return;
	}
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	if ( ! empty( $data['error'] ) || ! empty( $data['error_code'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => ! empty( $data['error'] ) ? $data['error'] : 'Bright Data error',
			'error_code' => ! empty( $data['error_code'] ) ? $data['error_code'] : '',
			'raw'        => $data,
		] ) );
		return;
	}

	if ( isset( $data['status'] ) && strtolower( (string) $data['status'] ) === 'running' ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Snapshot not ready',
			'raw'   => $data,
		] ) );
		tm_queue_facebook_metrics_refresh( $vendor_id, $facebook_url );
		return;
	}

	if ( isset( $data['message'] ) && is_string( $data['message'] )
		&& stripos( $data['message'], 'snapshot is not ready' ) !== false
	) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Snapshot not ready',
			'raw'   => $data,
		] ) );
		tm_queue_facebook_metrics_refresh( $vendor_id, $facebook_url );
		return;
	}

	if ( isset( $data['status'] ) && strtolower( (string) $data['status'] ) === 'running' ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
			'raw'       => $data,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url );
		}
		return;
	}

	if ( isset( $data['message'] ) && is_string( $data['message'] )
		&& stripos( $data['message'], 'snapshot is not ready' ) !== false
	) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
			'raw'       => $data,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url );
		}
		return;
	}

	if ( ! empty( $data['error'] ) || ! empty( $data['error_code'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => ! empty( $data['error'] ) ? $data['error'] : 'Bright Data error',
			'error_code' => ! empty( $data['error_code'] ) ? $data['error_code'] : '',
			'raw'        => $data,
		] ) );
		return;
	}

	if ( ! empty( $data['error'] ) || ! empty( $data['error_code'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => ! empty( $data['error'] ) ? $data['error'] : 'Bright Data error',
			'error_code' => ! empty( $data['error_code'] ) ? $data['error_code'] : '',
			'raw'        => $data,
		] ) );
		return;
	}

	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		$data = $data['data'];
	} elseif ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
		$data = $data['results'];
	}

	$processed = tm_process_instagram_payload( $vendor_id, $data, $body, $debug_base, $save_debug, $instagram_url );
	if ( $processed ) {
		delete_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id' );
		delete_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_attempts' );
	}
}

/**
 * Fetch YouTube channel metrics from Bright Data
 */
function tm_fetch_youtube_metrics( $vendor_id, $youtube_url ) {
	$api_key = tm_get_brightdata_api_key();
	$debug_base = [
		'requested_url' => $youtube_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_youtube_raw', $payload );
		}
	};
	if ( ! $api_key || ! $youtube_url ) {
		$save_debug( array_merge( $debug_base, [
			'error' => ! $api_key ? 'Missing Bright Data API key.' : 'Missing YouTube URL.',
		] ) );
		return;
	}

	$dataset_id = 'gd_lk538t2k2p1k3oos71'; // YouTube channel dataset
	$endpoint = add_query_arg( [
		'dataset_id'     => $dataset_id,
		'notify'         => 'false',
		'include_errors' => 'true',
	], 'https://api.brightdata.com/datasets/v3/scrape' );

	$response = wp_remote_post( $endpoint, [
		'timeout' => 60,
		'connect_timeout' => 20,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'input' => [
				[ 'url' => $youtube_url ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$raw_body = $body;
	$data = json_decode( $body, true );

	// Snapshot flow
	if ( is_array( $data ) && isset( $data['snapshot_id'] ) ) {
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id', $data['snapshot_id'] );
		$save_debug( array_merge( $debug_base, $data, [
			'note' => 'Snapshot created; awaiting download.',
		] ) );
		tm_queue_youtube_snapshot_fetch( $vendor_id, $data['snapshot_id'], $youtube_url );
		return;
	}

	tm_process_youtube_payload( $vendor_id, $data, $raw_body, $debug_base, $save_debug, $youtube_url );
}

/**
 * Queue YouTube snapshot fetch
 */
function tm_queue_youtube_snapshot_fetch( $vendor_id, $snapshot_id, $youtube_url ) {
	if ( ! $vendor_id || ! $snapshot_id ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_youtube_snapshot_event', [ $vendor_id, $snapshot_id, $youtube_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 30, 'tm_fetch_youtube_snapshot_event', [ $vendor_id, $snapshot_id, $youtube_url ] );
	}
}

/**
 * Fetch YouTube snapshot data from Bright Data
 */
function tm_fetch_youtube_snapshot( $vendor_id, $snapshot_id, $youtube_url = '' ) {
	$api_key = tm_get_brightdata_api_key();
	if ( ! $api_key || ! $snapshot_id ) {
		return;
	}

	$debug_base = [
		'snapshot_id'   => $snapshot_id,
		'requested_url' => $youtube_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_youtube_raw', $payload );
		}
	};

	$endpoint = 'https://api.brightdata.com/datasets/v3/snapshot/' . rawurlencode( $snapshot_id ) . '?format=json';
	$response = wp_remote_get( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
		],
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( 404 === (int) $code ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_youtube_snapshot_fetch( $vendor_id, $snapshot_id, $youtube_url );
		}
		return;
	}
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$processed = tm_process_youtube_payload( $vendor_id, $data, $body, $debug_base, $save_debug, $youtube_url );
	if ( $processed ) {
		delete_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id' );
		delete_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_attempts' );
	}
}

/**
 * Fetch LinkedIn profile metrics from Bright Data
 */
function tm_fetch_linkedin_metrics( $vendor_id, $linkedin_url ) {
	$api_key = tm_get_brightdata_api_key();
	$debug_base = [
		'requested_url' => $linkedin_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', $payload );
		}
	};
	if ( ! $api_key || ! $linkedin_url ) {
		$save_debug( array_merge( $debug_base, [
			'error' => ! $api_key ? 'Missing Bright Data API key.' : 'Missing LinkedIn URL.',
		] ) );
		return;
	}

	$dataset_id = 'gd_l1viktl72bvl7bjuj0';
	$endpoint = add_query_arg( [
		'dataset_id'     => $dataset_id,
		'notify'         => 'false',
		'include_errors' => 'true',
	], 'https://api.brightdata.com/datasets/v3/scrape' );

	$response = wp_remote_post( $endpoint, [
		'timeout' => 60,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'input' => [
				[ 'url' => $linkedin_url ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Non-200 response',
			'http_code'  => $code,
			'raw_body'   => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	if ( isset( $data['snapshot_id'] ) ) {
		update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id', $data['snapshot_id'] );
		$save_debug( array_merge( $debug_base, $data, [
			'note' => 'Snapshot created; awaiting download.',
		] ) );
		tm_queue_linkedin_snapshot_fetch( $vendor_id, $data['snapshot_id'], $linkedin_url );
		return;
	}

	$profile = [];
	if ( is_array( $data ) && ( isset( $data['followers'] ) || isset( $data['connections'] ) || isset( $data['name'] ) ) ) {
		$profile = $data;
	} elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
		$profile = $data[0];
	}
	if ( empty( $profile ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Unexpected response payload',
			'raw'   => $data,
		] ) );
		return;
	}

	// Derive engagement from activity list when available
	$avg_reactions = null;
	if ( isset( $profile['activity'] ) && is_array( $profile['activity'] ) ) {
		$total_interactions = 0;
		$post_count = 0;
		foreach ( $profile['activity'] as $item ) {
			if ( isset( $item['interaction'] ) ) {
				$total_interactions += tm_linkedin_interaction_count( $item['interaction'] );
				$post_count++;
			}
		}
		if ( $post_count > 0 ) {
			$avg_reactions = round( $total_interactions / $post_count );
		}
	}

	$metrics = [
		'followers'      => isset( $profile['followers'] ) ? (int) $profile['followers'] : null,
		'connections'    => isset( $profile['connections'] ) ? (int) $profile['connections'] : null,
		'avg_reactions'  => $avg_reactions,
		'avg_views'      => null, // LinkedIn dataset does not expose view/impression counts
		'name'           => isset( $profile['name'] ) ? $profile['name'] : null,
		'avatar'         => isset( $profile['avatar'] ) ? $profile['avatar'] : null,
		'url'            => isset( $profile['url'] ) ? $profile['url'] : $linkedin_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	update_user_meta( $vendor_id, 'tm_social_metrics_linkedin', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', $data );
	update_user_meta( $vendor_id, 'tm_social_linkedin_url', $linkedin_url );
	delete_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );
}

/**
 * Queue Instagram metrics refresh (background)
 */
function tm_queue_instagram_metrics_refresh( $vendor_id, $instagram_url ) {
	if ( ! $vendor_id || ! $instagram_url ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_instagram_metrics_event', [ $vendor_id, $instagram_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 10, 'tm_fetch_instagram_metrics_event', [ $vendor_id, $instagram_url ] );
	}
}

add_action( 'tm_fetch_instagram_metrics_event', 'tm_fetch_instagram_metrics', 10, 2 );
add_action( 'tm_fetch_instagram_snapshot_event', 'tm_fetch_instagram_snapshot', 10, 3 );

/**
 * Queue YouTube metrics refresh (background)
 */
function tm_queue_youtube_metrics_refresh( $vendor_id, $youtube_url ) {
	if ( ! $vendor_id || ! $youtube_url ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_youtube_metrics_event', [ $vendor_id, $youtube_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 10, 'tm_fetch_youtube_metrics_event', [ $vendor_id, $youtube_url ] );
	}
}

add_action( 'tm_fetch_youtube_metrics_event', 'tm_fetch_youtube_metrics', 10, 2 );
add_action( 'tm_fetch_youtube_snapshot_event', 'tm_fetch_youtube_snapshot', 10, 3 );

/**
 * Queue LinkedIn snapshot fetch
 */
function tm_queue_linkedin_snapshot_fetch( $vendor_id, $snapshot_id, $linkedin_url ) {
	if ( ! $vendor_id || ! $snapshot_id ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_linkedin_snapshot_event', [ $vendor_id, $snapshot_id, $linkedin_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 30, 'tm_fetch_linkedin_snapshot_event', [ $vendor_id, $snapshot_id, $linkedin_url ] );
	}
}

/**
 * Fetch LinkedIn snapshot data from Bright Data
 */
function tm_fetch_linkedin_snapshot( $vendor_id, $snapshot_id, $linkedin_url = '' ) {
	$api_key = tm_get_brightdata_api_key();
	if ( ! $api_key || ! $snapshot_id ) {
		return;
	}

	$debug_base = [
		'snapshot_id'   => $snapshot_id,
		'requested_url' => $linkedin_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', $payload );
		}
	};

	$endpoint = 'https://api.brightdata.com/datasets/v3/snapshot/' . rawurlencode( $snapshot_id ) . '?format=json';
	$response = wp_remote_get( $endpoint, [
		'timeout' => 60,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
		],
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( 404 === (int) $code ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_linkedin_snapshot_fetch( $vendor_id, $snapshot_id, $linkedin_url );
		}
		return;
	}
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
		$data = $data['data'];
	} elseif ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
		$data = $data['results'];
	}

	$profile = [];
	if ( is_array( $data ) && ( isset( $data['followers'] ) || isset( $data['connections'] ) || isset( $data['name'] ) ) ) {
		$profile = $data;
	} elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
		$profile = $data[0];
	}
	if ( empty( $profile ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Empty snapshot payload',
			'raw'   => $data,
		] ) );
		return;
	}

	// Derive engagement from activity list when available
	$avg_reactions = null;
	if ( isset( $profile['activity'] ) && is_array( $profile['activity'] ) ) {
		$total_interactions = 0;
		$post_count = 0;
		foreach ( $profile['activity'] as $item ) {
			if ( isset( $item['interaction'] ) ) {
				$total_interactions += tm_linkedin_interaction_count( $item['interaction'] );
				$post_count++;
			}
		}
		if ( $post_count > 0 ) {
			$avg_reactions = round( $total_interactions / $post_count );
		}
	}

	$metrics = [
		'followers'      => isset( $profile['followers'] ) ? (int) $profile['followers'] : null,
		'connections'    => isset( $profile['connections'] ) ? (int) $profile['connections'] : null,
		'avg_reactions'  => $avg_reactions,
		'avg_views'      => null, // LinkedIn dataset does not expose view/impression counts
		'name'           => isset( $profile['name'] ) ? $profile['name'] : null,
		'avatar'         => isset( $profile['avatar'] ) ? $profile['avatar'] : null,
		'url'            => isset( $profile['url'] ) ? $profile['url'] : $linkedin_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	update_user_meta( $vendor_id, 'tm_social_metrics_linkedin', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', $data );
	if ( $linkedin_url ) {
		update_user_meta( $vendor_id, 'tm_social_linkedin_url', $linkedin_url );
	}
	delete_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );
}

/**
 * Queue LinkedIn metrics refresh
 */
function tm_queue_linkedin_metrics_refresh( $vendor_id, $linkedin_url ) {
	if ( ! $vendor_id || ! $linkedin_url ) {
		return;
	}
	// Only schedule background event - don't run immediately to avoid blocking page load
	$next = wp_next_scheduled( 'tm_fetch_linkedin_metrics_event', [ $vendor_id, $linkedin_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 10, 'tm_fetch_linkedin_metrics_event', [ $vendor_id, $linkedin_url ] );
	}
}

add_action( 'tm_fetch_linkedin_metrics_event', 'tm_fetch_linkedin_metrics', 10, 2 );
add_action( 'tm_fetch_linkedin_snapshot_event', 'tm_fetch_linkedin_snapshot', 10, 3 );

/**
 * Fetch Facebook profile metrics from Bright Data (Posts dataset with follower counts)
 */
function tm_fetch_facebook_metrics( $vendor_id, $facebook_url ) {
	$api_key = tm_get_brightdata_api_key();
	$debug_base = [
		'requested_url' => $facebook_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', $payload );
		}
	};
	if ( ! $api_key || ! $facebook_url ) {
		$save_debug( array_merge( $debug_base, [
			'error' => ! $api_key ? 'Missing Bright Data API key.' : 'Missing Facebook URL.',
		] ) );
		return;
	}

	$dataset_id = 'gd_lkaxegm826bjpoo9m5'; // Facebook Posts dataset with follower counts
	$endpoint = add_query_arg( [
		'dataset_id'     => $dataset_id,
		'notify'         => 'false',
		'include_errors' => 'true',
	], 'https://api.brightdata.com/datasets/v3/scrape' );

	$response = wp_remote_post( $endpoint, [
		'timeout' => 120, // Extended timeout for slow Facebook dataset creation
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'input' => [
				[
					'url'          => $facebook_url,
					'num_of_posts' => 10, // Get last 10 posts for engagement averages
				],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => 'Non-200 response',
			'http_code'  => $code,
			'raw_body'   => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	if ( isset( $data['snapshot_id'] ) ) {
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id', $data['snapshot_id'] );
		$save_debug( array_merge( $debug_base, $data, [
			'note' => 'Snapshot created; awaiting download.',
		] ) );
		tm_queue_facebook_snapshot_fetch( $vendor_id, $data['snapshot_id'], $facebook_url );
		return;
	}

	// Process posts array to calculate averages
	if ( empty( $data ) || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Unexpected response payload',
			'raw'   => $data,
			'data_keys' => is_array( $data ) ? array_keys( $data ) : 'not_array',
			'data_structure' => json_encode( $data, JSON_PRETTY_PRINT ),
		] ) );
		return;
	}

	// Extract metrics from posts
	$page_name = '';
	$page_followers = null;
	$page_logo = '';
	$total_likes = 0;
	$total_reactions = 0;
	$total_comments = 0;
	$total_shares = 0;
	$total_views = 0;
	$view_samples = 0;
	$post_count = count( $data );
	$extract_like_count = function( $post ) {
		if ( isset( $post['likes'] ) ) {
			return tm_parse_social_number( $post['likes'] );
		}
		if ( isset( $post['num_likes_type']['num'] ) ) {
			return tm_parse_social_number( $post['num_likes_type']['num'] );
		}
		if ( isset( $post['num_likes'] ) ) {
			return tm_parse_social_number( $post['num_likes'] );
		}
		return null;
	};
	$extract_reaction_count = function( $post ) use ( $extract_like_count ) {
		if ( ! empty( $post['count_reactions_type'] ) && is_array( $post['count_reactions_type'] ) ) {
			$sum = 0;
			$has = false;
			foreach ( $post['count_reactions_type'] as $reaction ) {
				if ( isset( $reaction['reaction_count'] ) ) {
					$sum += tm_parse_social_number( $reaction['reaction_count'] );
					$has = true;
				}
			}
			if ( $has ) {
				return $sum;
			}
		}
		return $extract_like_count( $post );
	};
	$extract_view_count = function( $post ) {
		$keys = [ 'video_view_count', 'play_count', 'views', 'video_views' ];
		foreach ( $keys as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$views = tm_parse_social_number( $post[ $key ] );
				return $views > 0 ? $views : null;
			}
		}
		return null;
	};

	foreach ( $data as $post ) {
		if ( empty( $page_name ) && ! empty( $post['page_name'] ) ) {
			$page_name = $post['page_name'];
		}
		if ( empty( $page_name ) && ! empty( $post['user_username_raw'] ) ) {
			$page_name = $post['user_username_raw'];
		}
		if ( $page_followers === null && ! empty( $post['page_followers'] ) ) {
			$page_followers = tm_parse_social_number( $post['page_followers'] );
		} elseif ( $page_followers === null && ! empty( $post['page_likes'] ) ) {
			$page_followers = tm_parse_social_number( $post['page_likes'] );
		} elseif ( $page_followers === null && ! empty( $post['followers'] ) ) {
			$page_followers = tm_parse_social_number( $post['followers'] );
		}
		if ( empty( $page_logo ) && ! empty( $post['page_logo'] ) ) {
			$page_logo = $post['page_logo'];
		} elseif ( empty( $page_logo ) && ! empty( $post['avatar_image_url'] ) ) {
			$page_logo = $post['avatar_image_url'];
		}
		
		// Sum engagement metrics
		$like_count = $extract_like_count( $post );
		if ( $like_count !== null ) {
			$total_likes += $like_count;
		}
		$reaction_count = $extract_reaction_count( $post );
		if ( $reaction_count !== null ) {
			$total_reactions += $reaction_count;
		}
		if ( isset( $post['num_comments'] ) ) {
			$total_comments += tm_parse_social_number( $post['num_comments'] );
		} elseif ( isset( $post['comments'] ) ) {
			$total_comments += tm_parse_social_number( $post['comments'] );
		}
		if ( isset( $post['num_shares'] ) ) {
			$total_shares += tm_parse_social_number( $post['num_shares'] );
		} elseif ( isset( $post['shares'] ) ) {
			$total_shares += tm_parse_social_number( $post['shares'] );
		}
		$views = $extract_view_count( $post );
		if ( $views !== null ) {
			$total_views += $views;
			$view_samples++;
		}
	}

	// Calculate averages
	$avg_likes = $post_count > 0 ? round( $total_likes / $post_count ) : 0;
	$avg_comments = $post_count > 0 ? round( $total_comments / $post_count ) : 0;
	$avg_shares = $post_count > 0 ? round( $total_shares / $post_count ) : 0;
	$avg_reactions = $total_reactions > 0 ? round( $total_reactions / $post_count ) : $avg_likes;
	$avg_views = $view_samples > 0 ? round( $total_views / $view_samples ) : 0;

	$metrics = [
		'page_name'      => $page_name,
		'page_followers' => $page_followers,
		'page_logo'      => $page_logo,
		'avg_likes'      => $avg_likes,
		'avg_comments'   => $avg_comments,
		'avg_shares'     => $avg_shares,
		'avg_reactions'  => $avg_reactions,
		'avg_views'      => $avg_views,
		'post_count'     => $post_count,
		'url'            => $facebook_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	error_log( '🔵 Saving Facebook metrics for user ' . $vendor_id . ' - Page: ' . $page_name . ', Followers: ' . $page_followers );
	
	update_user_meta( $vendor_id, 'tm_social_metrics_facebook', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', $data );
	update_user_meta( $vendor_id, 'tm_social_facebook_url', $facebook_url );
	delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );
	
	error_log( '✅ Facebook metrics saved successfully for user ' . $vendor_id );
}

/**
 * Queue Facebook snapshot fetch
 */
function tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url ) {
	if ( ! $vendor_id || ! $snapshot_id ) {
		return;
	}
	$next = wp_next_scheduled( 'tm_fetch_facebook_snapshot_event', [ $vendor_id, $snapshot_id, $facebook_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 30, 'tm_fetch_facebook_snapshot_event', [ $vendor_id, $snapshot_id, $facebook_url ] );
	}
}

/**
 * Fetch Facebook snapshot data from Bright Data
 */
function tm_fetch_facebook_snapshot( $vendor_id, $snapshot_id, $facebook_url = '' ) {
	$api_key = tm_get_brightdata_api_key();
	if ( ! $api_key || ! $snapshot_id ) {
		return;
	}

	$debug_base = [
		'snapshot_id'   => $snapshot_id,
		'requested_url' => $facebook_url,
		'timestamp'     => current_time( 'mysql' ),
	];
	$save_debug = function( $payload ) use ( $vendor_id ) {
		if ( $vendor_id ) {
			update_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', $payload );
		}
	};

	$endpoint = 'https://api.brightdata.com/datasets/v3/snapshot/' . rawurlencode( $snapshot_id ) . '?format=json';
	$response = wp_remote_get( $endpoint, [
		'timeout' => 120, // Extended timeout for Facebook snapshot download
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
		],
	] );

	if ( is_wp_error( $response ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'   => 'WP HTTP error',
			'message' => $response->get_error_message(),
		] ) );
		return;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	if ( 404 === (int) $code ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url );
		}
		return;
	}
	if ( $code < 200 || $code >= 300 || ! $body ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Non-200 response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Invalid JSON response',
			'http_code' => $code,
			'raw_body'  => $body,
		] ) );
		return;
	}
	if ( isset( $data['status'] ) && strtolower( (string) $data['status'] ) === 'running' ) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
			'raw'       => $data,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url );
		}
		return;
	}
	if ( isset( $data['message'] ) && is_string( $data['message'] )
		&& stripos( $data['message'], 'snapshot is not ready' ) !== false
	) {
		$attempts = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', true );
		$attempts++;
		update_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts', $attempts );
		$save_debug( array_merge( $debug_base, [
			'error'     => 'Snapshot not ready',
			'http_code' => $code,
			'attempts'  => $attempts,
			'raw'       => $data,
		] ) );
		if ( $attempts < 8 ) {
			tm_queue_facebook_snapshot_fetch( $vendor_id, $snapshot_id, $facebook_url );
		}
		return;
	}
	if ( ! empty( $data['error'] ) || ! empty( $data['error_code'] ) ) {
		$save_debug( array_merge( $debug_base, [
			'error'      => ! empty( $data['error'] ) ? $data['error'] : 'Bright Data error',
			'error_code' => ! empty( $data['error_code'] ) ? $data['error_code'] : '',
			'raw'        => $data,
		] ) );
		return;
	}

	// Process posts array to calculate averages
	if ( empty( $data ) || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
		$save_debug( array_merge( $debug_base, [
			'error' => 'Empty snapshot payload',
			'raw'   => $data,
			'data_keys' => is_array( $data ) ? array_keys( $data ) : 'not_array',
			'data_structure' => json_encode( $data, JSON_PRETTY_PRINT ),
		] ) );
		return;
	}

	// Extract metrics from posts
	$page_name = '';
	$page_followers = null;
	$page_logo = '';
	$total_likes = 0;
	$total_reactions = 0;
	$total_comments = 0;
	$total_shares = 0;
	$total_views = 0;
	$view_samples = 0;
	$post_count = count( $data );
	$extract_like_count = function( $post ) {
		if ( isset( $post['likes'] ) ) {
			return tm_parse_social_number( $post['likes'] );
		}
		if ( isset( $post['num_likes_type']['num'] ) ) {
			return tm_parse_social_number( $post['num_likes_type']['num'] );
		}
		if ( isset( $post['num_likes'] ) ) {
			return tm_parse_social_number( $post['num_likes'] );
		}
		return null;
	};
	$extract_reaction_count = function( $post ) use ( $extract_like_count ) {
		if ( ! empty( $post['count_reactions_type'] ) && is_array( $post['count_reactions_type'] ) ) {
			$sum = 0;
			$has = false;
			foreach ( $post['count_reactions_type'] as $reaction ) {
				if ( isset( $reaction['reaction_count'] ) ) {
					$sum += tm_parse_social_number( $reaction['reaction_count'] );
					$has = true;
				}
			}
			if ( $has ) {
				return $sum;
			}
		}
		return $extract_like_count( $post );
	};
	$extract_view_count = function( $post ) {
		$keys = [ 'video_view_count', 'play_count', 'views', 'video_views' ];
		foreach ( $keys as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$views = tm_parse_social_number( $post[ $key ] );
				return $views > 0 ? $views : null;
			}
		}
		return null;
	};

	foreach ( $data as $post ) {
		if ( empty( $page_name ) && ! empty( $post['page_name'] ) ) {
			$page_name = $post['page_name'];
		}
		if ( empty( $page_name ) && ! empty( $post['user_username_raw'] ) ) {
			$page_name = $post['user_username_raw'];
		}
		if ( $page_followers === null && ! empty( $post['page_followers'] ) ) {
			$page_followers = tm_parse_social_number( $post['page_followers'] );
		} elseif ( $page_followers === null && ! empty( $post['page_likes'] ) ) {
			$page_followers = tm_parse_social_number( $post['page_likes'] );
		} elseif ( $page_followers === null && ! empty( $post['followers'] ) ) {
			$page_followers = tm_parse_social_number( $post['followers'] );
		}
		if ( empty( $page_logo ) && ! empty( $post['page_logo'] ) ) {
			$page_logo = $post['page_logo'];
		} elseif ( empty( $page_logo ) && ! empty( $post['avatar_image_url'] ) ) {
			$page_logo = $post['avatar_image_url'];
		}
		
		// Sum engagement metrics
		$like_count = $extract_like_count( $post );
		if ( $like_count !== null ) {
			$total_likes += $like_count;
		}
		$reaction_count = $extract_reaction_count( $post );
		if ( $reaction_count !== null ) {
			$total_reactions += $reaction_count;
		}
		if ( isset( $post['num_comments'] ) ) {
			$total_comments += tm_parse_social_number( $post['num_comments'] );
		} elseif ( isset( $post['comments'] ) ) {
			$total_comments += tm_parse_social_number( $post['comments'] );
		}
		if ( isset( $post['num_shares'] ) ) {
			$total_shares += tm_parse_social_number( $post['num_shares'] );
		} elseif ( isset( $post['shares'] ) ) {
			$total_shares += tm_parse_social_number( $post['shares'] );
		}
		$views = $extract_view_count( $post );
		if ( $views !== null ) {
			$total_views += $views;
			$view_samples++;
		}
	}

	// Calculate averages
	$avg_likes = $post_count > 0 ? round( $total_likes / $post_count ) : 0;
	$avg_comments = $post_count > 0 ? round( $total_comments / $post_count ) : 0;
	$avg_shares = $post_count > 0 ? round( $total_shares / $post_count ) : 0;
	$avg_reactions = $total_reactions > 0 ? round( $total_reactions / $post_count ) : $avg_likes;
	$avg_views = $view_samples > 0 ? round( $total_views / $view_samples ) : 0;

	$metrics = [
		'page_name'      => $page_name,
		'page_followers' => $page_followers,
		'page_logo'      => $page_logo,
		'avg_likes'      => $avg_likes,
		'avg_comments'   => $avg_comments,
		'avg_shares'     => $avg_shares,
		'avg_reactions'  => $avg_reactions,
		'avg_views'      => $avg_views,
		'post_count'     => $post_count,
		'url'            => $facebook_url,
		'updated_at'     => current_time( 'mysql' ),
	];

	update_user_meta( $vendor_id, 'tm_social_metrics_facebook', $metrics );
	update_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', $data );
	if ( $facebook_url ) {
		update_user_meta( $vendor_id, 'tm_social_facebook_url', $facebook_url );
	}
	delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id' );
	delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_attempts' );

	tm_after_social_metrics_update( $vendor_id );
}

/**
 * Queue Facebook metrics refresh
 */
function tm_queue_facebook_metrics_refresh( $vendor_id, $facebook_url ) {
	if ( ! $vendor_id || ! $facebook_url ) {
		return;
	}
	// Only schedule background event - don't run immediately to avoid blocking page load
	$next = wp_next_scheduled( 'tm_fetch_facebook_metrics_event', [ $vendor_id, $facebook_url ] );
	if ( ! $next ) {
		wp_schedule_single_event( time() + 10, 'tm_fetch_facebook_metrics_event', [ $vendor_id, $facebook_url ] );
	}
}

add_action( 'tm_fetch_facebook_metrics_event', 'tm_fetch_facebook_metrics', 10, 2 );
add_action( 'tm_fetch_facebook_snapshot_event', 'tm_fetch_facebook_snapshot', 10, 3 );

/**
 * Detect social URL changes and refresh LinkedIn metrics
 */
function tm_handle_social_profile_update( $meta_id, $user_id, $meta_key, $meta_value ) {
	if ( 'dokan_profile_settings' !== $meta_key ) {
		return;
	}
	if ( ! is_array( $meta_value ) ) {
		return;
	}
	$social = isset( $meta_value['social'] ) ? $meta_value['social'] : [];
	if ( ! is_array( $social ) ) {
		return;
	}
	$linkedin_url = '';
	if ( ! empty( $social['linkedin'] ) ) {
		$linkedin_url = $social['linkedin'];
	} elseif ( ! empty( $social['linked_in'] ) ) {
		$linkedin_url = $social['linked_in'];
	}
	if ( $linkedin_url ) {
		tm_queue_linkedin_metrics_refresh( $user_id, $linkedin_url );
	}
}

// DISABLED: This was causing infinite loops and page stalling
// Auto-fetch is now manual only via Fetch Metrics buttons
// add_action( 'updated_user_meta', 'tm_handle_social_profile_update', 10, 4 );
// add_action( 'added_user_meta', 'tm_handle_social_profile_update', 10, 4 );


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
 * Manual social metrics fetch (vendor dashboard)
 */
add_action( 'wp_ajax_tm_social_manual_fetch', function() {
	check_ajax_referer( 'tm_social_fetch', 'nonce' );
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not authenticated.' ], 403 );
	}
	if ( function_exists( 'dokan_is_user_seller' ) && ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ], 403 );
	}

	$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	if ( ! $platform || ! $url ) {
		wp_send_json_error( [ 'message' => 'Missing platform or URL.' ], 400 );
	}

	if ( 'linkedin' === $platform ) {
		// Immediate fetch when user clicks button
		tm_fetch_linkedin_metrics( $user_id, $url );
		// Also queue background refresh
		tm_queue_linkedin_metrics_refresh( $user_id, $url );
		update_user_meta( $user_id, 'tm_social_metrics_linkedin_last_fetch', current_time( 'mysql' ) );
		$snapshot_id = get_user_meta( $user_id, 'tm_social_metrics_linkedin_snapshot_id', true );
		$metrics = get_user_meta( $user_id, 'tm_social_metrics_linkedin', true );
		$message = $snapshot_id ? 'Snapshot queued. Check back in ~1 minute.' : 'Fetch triggered.';
		if ( $snapshot_id ) {
			$message .= ' Snapshot ID: ' . $snapshot_id;
		}
		wp_send_json_success( [
			'status'      => $snapshot_id ? 'queued' : 'ok',
			'snapshot_id' => $snapshot_id ? $snapshot_id : null,
			'updated_at'  => is_array( $metrics ) && ! empty( $metrics['updated_at'] ) ? $metrics['updated_at'] : null,
			'message'     => $message,
		] );
	}

	if ( 'facebook' === $platform ) {
		// Queue background fetch to avoid AJAX timeout; background will handle snapshot polling
		tm_queue_facebook_metrics_refresh( $user_id, $url );
		update_user_meta( $user_id, 'tm_social_metrics_facebook_last_fetch', current_time( 'mysql' ) );
		$snapshot_id = get_user_meta( $user_id, 'tm_social_metrics_facebook_snapshot_id', true );
		$metrics = get_user_meta( $user_id, 'tm_social_metrics_facebook', true );
		$raw = get_user_meta( $user_id, 'tm_social_metrics_facebook_raw', true );
		$message = 'Fetch queued. Check back in ~1-2 minutes.';
		$last_error = null;
		if ( is_array( $raw ) && isset( $raw['error'] ) ) {
			$last_error = [
				'error' => $raw['error'],
				'error_code' => $raw['error_code'] ?? null,
			];
		}
		if ( $snapshot_id ) {
			$message .= ' Snapshot ID: ' . $snapshot_id;
		}
		wp_send_json_success( [
			'status'      => 'queued',
			'snapshot_id' => $snapshot_id ? $snapshot_id : null,
			'updated_at'  => is_array( $metrics ) && ! empty( $metrics['updated_at'] ) ? $metrics['updated_at'] : null,
			'message'     => $message,
			'last_error'  => $last_error,
		] );
	}

	if ( 'instagram' === $platform ) {
		// Do immediate fetch so the user sees results without waiting on cron
		tm_fetch_instagram_metrics( $user_id, $url );
		// Also queue a background fetch as a retry/refresh safeguard
		tm_queue_instagram_metrics_refresh( $user_id, $url );
		update_user_meta( $user_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
		$metrics = get_user_meta( $user_id, 'tm_social_metrics_instagram', true );
		$raw = get_user_meta( $user_id, 'tm_social_metrics_instagram_raw', true );
		$snapshot_id = get_user_meta( $user_id, 'tm_social_metrics_instagram_snapshot_id', true );
		$last_error = null;
		if ( is_array( $raw ) ) {
			if ( isset( $raw['error'] ) ) {
				$last_error = $raw['error'];
			} elseif ( isset( $raw[0]['error'] ) ) {
				$last_error = $raw[0]['error'];
			}
		}
		$last_error_detail = null;
		if ( is_array( $raw ) && isset( $raw['raw_body'] ) && is_string( $raw['raw_body'] ) ) {
			$last_error_detail = substr( $raw['raw_body'], 0, 500 );
		}
		$message = 'Fetch triggered.';
		$status = 'ok';
		if ( $snapshot_id ) {
			$message = 'Snapshot queued. Check back in ~1 minute.';
			$status = 'queued';
			$message .= ' Snapshot ID: ' . $snapshot_id;
		}
		if ( $last_error ) {
			$message = 'Fetch completed with error: ' . $last_error;
			$status = 'error';
		}
		wp_send_json_success( [
			'platform'   => 'instagram',
			'status'     => $status,
			'snapshot_id'=> $snapshot_id ? $snapshot_id : null,
			'updated_at' => is_array( $metrics ) && ! empty( $metrics['updated_at'] ) ? $metrics['updated_at'] : null,
			'message'    => $message,
			'last_error' => $last_error,
			'error_body' => $last_error_detail,
		] );
	}

	if ( 'youtube' === $platform ) {
		// Immediate fetch to give user feedback; background queue adds resiliency
		tm_fetch_youtube_metrics( $user_id, $url );
		tm_queue_youtube_metrics_refresh( $user_id, $url );
		update_user_meta( $user_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
		$metrics = get_user_meta( $user_id, 'tm_social_metrics_youtube', true );
		$raw = get_user_meta( $user_id, 'tm_social_metrics_youtube_raw', true );
		$snapshot_id = get_user_meta( $user_id, 'tm_social_metrics_youtube_snapshot_id', true );
		$last_error = null;
		if ( is_array( $raw ) ) {
			if ( isset( $raw['error'] ) ) {
				$last_error = $raw['error'];
			} elseif ( isset( $raw[0]['error'] ) ) {
				$last_error = $raw[0]['error'];
			}
		}
		$last_error_detail = null;
		if ( is_array( $raw ) && isset( $raw['raw_body'] ) && is_string( $raw['raw_body'] ) ) {
			$last_error_detail = substr( $raw['raw_body'], 0, 500 );
		}
		$message = 'Fetch triggered.';
		$status = 'ok';
		if ( $snapshot_id ) {
			$message = 'Snapshot queued. Check back in ~1 minute.';
			$status = 'queued';
			$message .= ' Snapshot ID: ' . $snapshot_id;
		}
		if ( $last_error ) {
			$message = 'Fetch completed with error: ' . $last_error;
			$status = 'error';
		}
		wp_send_json_success( [
			'platform'    => 'youtube',
			'status'      => $status,
			'snapshot_id' => $snapshot_id ? $snapshot_id : null,
			'updated_at'  => is_array( $metrics ) && ! empty( $metrics['updated_at'] ) ? $metrics['updated_at'] : null,
			'message'     => $message,
			'last_error'  => $last_error,
			'error_body'  => $last_error_detail,
		] );
	}

	wp_send_json_error( [ 'message' => 'Platform not supported yet.' ], 400 );
} );

/**
 * Get LinkedIn raw data for debugging (vendor dashboard)
 */
add_action( 'wp_ajax_tm_get_linkedin_raw', function() {
	check_ajax_referer( 'tm_social_fetch', 'nonce' );
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Not authenticated.' ], 403 );
	}
	if ( function_exists( 'dokan_is_user_seller' ) && ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ], 403 );
	}

	$linkedin_raw = get_user_meta( $user_id, 'tm_social_metrics_linkedin_raw', true );
	$linkedin_metrics = get_user_meta( $user_id, 'tm_social_metrics_linkedin', true );
	$facebook_raw = get_user_meta( $user_id, 'tm_social_metrics_facebook_raw', true );
	$facebook_metrics = get_user_meta( $user_id, 'tm_social_metrics_facebook', true );
	$instagram_raw = get_user_meta( $user_id, 'tm_social_metrics_instagram_raw', true );
	$instagram_metrics = get_user_meta( $user_id, 'tm_social_metrics_instagram', true );
	$youtube_raw = get_user_meta( $user_id, 'tm_social_metrics_youtube_raw', true );
	$youtube_metrics = get_user_meta( $user_id, 'tm_social_metrics_youtube', true );
	
	$has_data = ( is_array( $linkedin_raw ) && ! empty( $linkedin_raw ) ) || ( is_array( $facebook_raw ) && ! empty( $facebook_raw ) ) || ( is_array( $instagram_raw ) && ! empty( $instagram_raw ) ) || ( is_array( $youtube_raw ) && ! empty( $youtube_raw ) );
	
	if ( ! $has_data ) {
		wp_send_json_error( [ 'message' => 'No raw data available yet.' ], 404 );
	}

	$result = [];
	
	if ( is_array( $linkedin_raw ) && ! empty( $linkedin_raw ) ) {
		$result['linkedin'] = [
			'raw_response' => $linkedin_raw,
			'extracted_metrics' => $linkedin_metrics,
		];
	}
	
	if ( is_array( $facebook_raw ) && ! empty( $facebook_raw ) ) {
		$result['facebook'] = [
			'raw_response' => $facebook_raw,
			'extracted_metrics' => $facebook_metrics,
		];
	}

	if ( is_array( $instagram_raw ) && ! empty( $instagram_raw ) ) {
		$result['instagram'] = [
			'raw_response' => $instagram_raw,
			'extracted_metrics' => $instagram_metrics,
		];
	}

	if ( is_array( $youtube_raw ) && ! empty( $youtube_raw ) ) {
		$result['youtube'] = [
			'raw_response' => $youtube_raw,
			'extracted_metrics' => $youtube_metrics,
		];
	}

	wp_send_json_success( [
		'platforms' => $result,
		'note' => 'raw_response shows the full API data. extracted_metrics shows what we currently save.',
	] );
} );

/**
 * Add manual fetch buttons on vendor social settings page
 */
add_action( 'wp_footer', function() {
	if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
		return;
	}
	$nonce = wp_create_nonce( 'tm_social_fetch' );
	$user_id = get_current_user_id();
	$linkedin_status = [
		'snapshot_id' => '',
		'updated_at'  => '',
		'last_fetch'  => '',
		'error'       => '',
	];
	$facebook_status = [
		'snapshot_id' => '',
		'updated_at'  => '',
		'last_fetch'  => '',
		'error'       => '',
	];
	$instagram_status = [
		'updated_at'  => '',
		'last_fetch'  => '',
		'error'       => '',
	];
	$youtube_status = [
		'snapshot_id' => '',
		'updated_at'  => '',
		'last_fetch'  => '',
		'error'       => '',
	];
	if ( $user_id ) {
		$linkedin_status['snapshot_id'] = (string) get_user_meta( $user_id, 'tm_social_metrics_linkedin_snapshot_id', true );
		$linkedin_status['last_fetch'] = (string) get_user_meta( $user_id, 'tm_social_metrics_linkedin_last_fetch', true );
		$linkedin_metrics = get_user_meta( $user_id, 'tm_social_metrics_linkedin', true );
		if ( is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ) {
			$linkedin_status['updated_at'] = $linkedin_metrics['updated_at'];
		}
		$linkedin_raw = get_user_meta( $user_id, 'tm_social_metrics_linkedin_raw', true );
		if ( is_array( $linkedin_raw ) && ! empty( $linkedin_raw['error'] ) ) {
			$linkedin_status['error'] = $linkedin_raw['error'];
		}
		
		$facebook_status['snapshot_id'] = (string) get_user_meta( $user_id, 'tm_social_metrics_facebook_snapshot_id', true );
		$facebook_status['last_fetch'] = (string) get_user_meta( $user_id, 'tm_social_metrics_facebook_last_fetch', true );
		$facebook_metrics = get_user_meta( $user_id, 'tm_social_metrics_facebook', true );
		if ( is_array( $facebook_metrics ) && ! empty( $facebook_metrics['updated_at'] ) ) {
			$facebook_status['updated_at'] = $facebook_metrics['updated_at'];
		}
		$facebook_raw = get_user_meta( $user_id, 'tm_social_metrics_facebook_raw', true );
		$has_facebook_data = is_array( $facebook_metrics ) && ! empty( $facebook_metrics['page_followers'] );
		if ( ! $has_facebook_data && is_array( $facebook_raw ) && ! empty( $facebook_raw['error'] ) ) {
			$facebook_status['error'] = $facebook_raw['error'];
		}

		$instagram_status['last_fetch'] = (string) get_user_meta( $user_id, 'tm_social_metrics_instagram_last_fetch', true );
		$instagram_metrics = get_user_meta( $user_id, 'tm_social_metrics_instagram', true );
		if ( is_array( $instagram_metrics ) && ! empty( $instagram_metrics['updated_at'] ) ) {
			$instagram_status['updated_at'] = $instagram_metrics['updated_at'];
		}
		$instagram_raw = get_user_meta( $user_id, 'tm_social_metrics_instagram_raw', true );
		if ( is_array( $instagram_raw ) && ! empty( $instagram_raw['error'] ) ) {
			$instagram_status['error'] = $instagram_raw['error'];
		}

		$youtube_status['snapshot_id'] = (string) get_user_meta( $user_id, 'tm_social_metrics_youtube_snapshot_id', true );
		$youtube_status['last_fetch'] = (string) get_user_meta( $user_id, 'tm_social_metrics_youtube_last_fetch', true );
		$youtube_metrics = get_user_meta( $user_id, 'tm_social_metrics_youtube', true );
		if ( is_array( $youtube_metrics ) && ! empty( $youtube_metrics['updated_at'] ) ) {
			$youtube_status['updated_at'] = $youtube_metrics['updated_at'];
		}
		$youtube_raw = get_user_meta( $user_id, 'tm_social_metrics_youtube_raw', true );
		if ( is_array( $youtube_raw ) && ! empty( $youtube_raw['error'] ) ) {
			$youtube_status['error'] = $youtube_raw['error'];
		}
	}
	?>
	<script type="text/javascript">
	(function() {
		var config = {
			ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce: <?php echo wp_json_encode( $nonce ); ?>,
			linkedinStatus: <?php echo wp_json_encode( $linkedin_status ); ?>,
			facebookStatus: <?php echo wp_json_encode( $facebook_status ); ?>,
			instagramStatus: <?php echo wp_json_encode( $instagram_status ); ?>,
			youtubeStatus: <?php echo wp_json_encode( $youtube_status ); ?>
		};

		function isSocialSettingsPage() {
			return (window.location.hash && window.location.hash.indexOf('settings/social') !== -1);
		}

		function getPlatformFromInput(input) {
			var name = (input.getAttribute('name') || '').toLowerCase();
			var placeholder = (input.getAttribute('placeholder') || '').toLowerCase();
			var value = (input.value || '').toLowerCase();
			var hay = name + ' ' + placeholder + ' ' + value;
			if (hay.indexOf('linkedin') !== -1) return 'linkedin';
			if (hay.indexOf('instagram') !== -1) return 'instagram';
			if (hay.indexOf('facebook') !== -1 || hay.indexOf('fb.com') !== -1) return 'facebook';
			if (hay.indexOf('youtube') !== -1) return 'youtube';
			if (hay.indexOf('tiktok') !== -1) return 'tiktok';
			if (hay.indexOf('twitter') !== -1 || hay.indexOf('x.com') !== -1) return 'twitter';
			if (hay.indexOf('pinterest') !== -1) return 'pinterest';
			if (hay.indexOf('flickr') !== -1) return 'flickr';
			if (hay.indexOf('threads') !== -1) return 'threads';
			return '';
		}

		function formatStatus(platform) {
			var status = null;
			if (platform === 'linkedin') {
				status = config.linkedinStatus || {};
			} else if (platform === 'facebook') {
				status = config.facebookStatus || {};
			} else if (platform === 'instagram') {
				status = config.instagramStatus || {};
			} else if (platform === 'youtube') {
				status = config.youtubeStatus || {};
			}
			if (!status) return '';
			
			var parts = [];
			if (status.updated_at) {
				parts.push('Updated: ' + status.updated_at);
			} else if (status.last_fetch) {
				parts.push('Last fetch: ' + status.last_fetch);
			}
			if (status.error) {
				parts.push('Error: ' + status.error);
			}
			return parts.join(' | ');
		}

		function addFetchButtons() {
			if (!isSocialSettingsPage()) return;
			var inputs = document.querySelectorAll('input[type="url"], input[type="text"]');
			inputs.forEach(function(input) {
				var platform = getPlatformFromInput(input);
				if (!platform) return;
				if (!input.value || !input.value.trim()) return;
				if (input.parentElement && input.parentElement.querySelector('.tm-social-fetch-btn')) return;

				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'tm-social-fetch-btn dokan-btn dokan-btn-theme';
				btn.style.cssText = 'margin-left:8px;padding:6px 10px;font-size:11px;line-height:1;';
				btn.textContent = 'Fetch Metrics';
				btn.dataset.platform = platform;
				btn.dataset.url = input.value.trim();

				var status = document.createElement('span');
				status.className = 'tm-social-fetch-status';
				status.style.cssText = 'display:block;margin-top:6px;font-size:11px;color:#D4AF37;';
				var existingStatus = formatStatus(platform);
				if (existingStatus) {
					status.textContent = existingStatus;
				}

				btn.addEventListener('click', function() {
					btn.disabled = true;
					btn.textContent = 'Fetching...';
					status.textContent = '';
					var body = new URLSearchParams();
					body.append('action', 'tm_social_manual_fetch');
					body.append('nonce', config.nonce);
					body.append('platform', platform);
					body.append('url', input.value.trim());
					fetch(config.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: body.toString()
					}).then(function(resp) { return resp.json(); }).then(function(data) {
						if (data && data.success) {
							// Build status message from response data
							var parts = [];
							if (data.data.snapshot_id) {
								parts.push('Snapshot ID: ' + data.data.snapshot_id);
							}
							if (data.data.updated_at) {
								parts.push('Updated: ' + data.data.updated_at);
							}
							if (data.data.message) {
								parts.push(data.data.message);
							}
							status.textContent = parts.length > 0 ? parts.join(' | ') : 'Fetch triggered.';
							status.style.color = '#D4AF37';
						} else {
							var msg = data && data.data && data.data.message ? data.data.message : 'Fetch failed.';
							status.textContent = msg;
							status.style.color = '#ff6b6b';
						}
					}).catch(function() {
						status.textContent = 'Fetch failed.';
						status.style.color = '#ff6b6b';
					}).finally(function() {
						btn.disabled = false;
						btn.textContent = 'Fetch Metrics';
					});
				});

				if (input.parentElement) {
					input.parentElement.appendChild(btn);
					input.parentElement.appendChild(status);
				}
			});
		}

		setTimeout(addFetchButtons, 1200);
		window.addEventListener('hashchange', function() { setTimeout(addFetchButtons, 800); });
		var observer = new MutationObserver(function() { setTimeout(addFetchButtons, 400); });
		observer.observe(document.body, { childList: true, subtree: true });
		
		// Display full social media raw data for debugging
		function displayRawDataSection() {
			if (!isSocialSettingsPage()) return;
			var existing = document.querySelector('.tm-linkedin-raw-data-display');
			if (existing) return;
			
			var containers = document.querySelectorAll('.dokan-social-fields-wrapper, .dokan-form-group, .dokan-settings-content');
			var container = null;
			for (var i = 0; i < containers.length; i++) {
				if (containers[i].querySelector('input[name*="social"]')) {
					container = containers[i];
					break;
				}
			}
			if (!container) {
				container = document.querySelector('.dokan-dashboard-content');
			}
			if (!container) return;
			
			var wrapper = document.createElement('div');
			wrapper.className = 'tm-linkedin-raw-data-display';
			wrapper.style.cssText = 'margin:30px 0;padding:20px;background:#1a1a1a;border:2px solid #D4AF37;border-radius:8px;max-width:100%;overflow:hidden;';
			
			var titleRow = document.createElement('div');
			titleRow.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:10px;';
			
			var title = document.createElement('h3');
			title.textContent = 'Social Media Raw API Data (Debug)';
			title.style.cssText = 'color:#D4AF37;margin:0;font-size:16px;flex:1;min-width:200px;';
			
			var refreshBtn = document.createElement('button');
			refreshBtn.type = 'button';
			refreshBtn.className = 'dokan-btn dokan-btn-theme';
			refreshBtn.textContent = '🔄 Refresh Data';
			refreshBtn.style.cssText = 'padding:6px 12px;font-size:12px;flex-shrink:0;';
			
			var pre = document.createElement('pre');
			pre.style.cssText = 'background:#0a0a0a;color:#4CAF50;padding:15px;border-radius:4px;overflow:auto;max-height:500px;max-width:100%;font-size:11px;line-height:1.5;margin:0;white-space:pre-wrap;word-wrap:break-word;word-break:break-all;';
			pre.textContent = 'Loading...';
			
			function loadRawData() {
				pre.textContent = 'Loading...';
				pre.style.color = '#999';
				fetch(config.ajaxUrl + '?action=tm_get_linkedin_raw&nonce=' + config.nonce, {
					credentials: 'same-origin'
				}).then(function(resp) { return resp.json(); }).then(function(data) {
					if (data && data.success && data.data) {
						pre.textContent = JSON.stringify(data.data, null, 2);
						pre.style.color = '#4CAF50';
					} else {
						pre.textContent = 'No raw data available yet. Click "Fetch Metrics" first.';
						pre.style.color = '#999';
					}
				}).catch(function(err) {
					pre.textContent = 'Error loading raw data: ' + err.message;
					pre.style.color = '#ff6b6b';
				});
			}
			
			refreshBtn.addEventListener('click', loadRawData);
			
			titleRow.appendChild(title);
			titleRow.appendChild(refreshBtn);
			wrapper.appendChild(titleRow);
			wrapper.appendChild(pre);
			
			if (container.parentElement) {
				container.parentElement.appendChild(wrapper);
			} else {
				container.appendChild(wrapper);
			}
			
			loadRawData();
		}
		
		setTimeout(displayRawDataSection, 1500);
		window.addEventListener('hashchange', function() { setTimeout(displayRawDataSection, 1000); });
	})();
	</script>
	<?php
}, 99 );

/**
 * Queue LinkedIn refresh when Dokan profile is saved
 * DISABLED: Auto-fetch removed - users must click Fetch Metrics button manually
 */
/*
add_action( 'dokan_store_profile_saved', function ( $store_id, $dokan_settings ) {
	if ( empty( $store_id ) || ! is_array( $dokan_settings ) ) {
		return;
	}
	if ( empty( $dokan_settings['social'] ) || ! is_array( $dokan_settings['social'] ) ) {
		return;
	}
	$social = $dokan_settings['social'];
	$linkedin_url = '';
	if ( ! empty( $social['linkedin'] ) ) {
		$linkedin_url = $social['linkedin'];
	} elseif ( ! empty( $social['linked_in'] ) ) {
		$linkedin_url = $social['linked_in'];
	}
	if ( $linkedin_url ) {
		tm_queue_linkedin_metrics_refresh( $store_id, $linkedin_url );
	}
}, 20, 2 );
*/



/**
 * Display Social Influence Metrics on Vendor Store Page (Influencer category)
 * Priority 4 = appears after Demographics (priority 3) but before physical/cameraman (priority 5)
 * VISUAL MOCKUP ONLY - Data will be populated dynamically later
 */
add_action( 'dokan_store_profile_bottom_drawer', function( $store_user, $store_info ) {
	$vendor_id = get_vendor_id_from_store_user( $store_user );
	if ( ! $vendor_id ) {
		return;
	}
	
	$current_user_id = get_current_user_id();
	$is_owner = function_exists( 'tm_can_edit_vendor_profile' ) 
		? tm_can_edit_vendor_profile( $vendor_id )
		: ( $current_user_id && $current_user_id == $vendor_id );
	
	// Get vendor's store categories
	$store_categories = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $store_categories ) ) {
		$store_categories = array();
	}
	
	// Only show for Influencer category (when it exists)
	// For now, we'll show it for ALL vendors as a visual demo
	// Change this line to: if ( ! in_array( 'influencer', $store_categories ) ) { return; }
	// when the influencer category is created
	
	$social_profiles = tm_get_vendor_social_profiles( $vendor_id );
	$linkedin_url = '';
	if ( ! empty( $social_profiles['linkedin'] ) ) {
		$linkedin_url = $social_profiles['linkedin'];
	} elseif ( ! empty( $social_profiles['linked_in'] ) ) {
		$linkedin_url = $social_profiles['linked_in'];
	}
	$linkedin_metrics = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin', true );
	$linkedin_updated_at = is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ? $linkedin_metrics['updated_at'] : '';
	
	// Extract LinkedIn data for display
	$linkedin_display_url = '';
	$linkedin_followers = 0;
	// Connections and per-post engagement are not shown; set placeholders
	$linkedin_avg_reactions = null;
	$linkedin_avg_views = null;
	if ( is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['followers'] ) ) {
		$linkedin_followers = intval( $linkedin_metrics['followers'] );
		$linkedin_display_url = ! empty( $linkedin_metrics['profile_url'] ) ? $linkedin_metrics['profile_url'] : $linkedin_url;
	}
	
	if ( $linkedin_url ) {
		$needs_refresh = true;
		if ( is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ) {
			$last = strtotime( $linkedin_metrics['updated_at'] );
			$needs_refresh = $last ? ( time() - $last ) > ( defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 30 * DAY_IN_SECONDS ) : true;
		}
		if ( $needs_refresh ) {
			tm_queue_linkedin_metrics_refresh( $vendor_id, $linkedin_url );
		}
	}
	
	// Extract Facebook data for display
	$facebook_url = ! empty( $social_profiles['fb'] ) ? $social_profiles['fb'] : ( $social_profiles['facebook'] ?? '' );
	$facebook_metrics = get_user_meta( $vendor_id, 'tm_social_metrics_facebook', true );
	$facebook_raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', true );
	$facebook_display_url = $facebook_url;
	$facebook_name = '';
	$facebook_followers = 0;
	$facebook_avg_reactions = 0;
	$facebook_avg_comments = 0;
	$facebook_avg_views = 0;
	$facebook_updated_at = '';
	$extract_facebook_identifier = function( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}
		$parsed = wp_parse_url( $value );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parsed['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		if ( $host !== 'facebook.com' ) {
			return '';
		}
		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( strtolower( $path ) === 'profile.php' && ! empty( $parsed['query'] ) ) {
			parse_str( (string) $parsed['query'], $query_args );
			return ! empty( $query_args['id'] ) ? 'id:' . (string) $query_args['id'] : '';
		}
		$segments = array_values( array_filter( explode( '/', $path ) ) );
		return isset( $segments[0] ) ? strtolower( (string) $segments[0] ) : '';
	};
	$current_facebook_identifier = $extract_facebook_identifier( $facebook_url );
	if ( is_array( $facebook_metrics ) ) {
		$metrics_identifier = $extract_facebook_identifier( isset( $facebook_metrics['url'] ) ? $facebook_metrics['url'] : '' );
		if ( $current_facebook_identifier && $metrics_identifier && $current_facebook_identifier === $metrics_identifier ) {
			$facebook_name = ! empty( $facebook_metrics['page_name'] ) ? $facebook_metrics['page_name'] : '';
			$facebook_followers = ! empty( $facebook_metrics['page_followers'] ) ? tm_parse_social_number( $facebook_metrics['page_followers'] ) : 0;
			$facebook_avg_reactions = ! empty( $facebook_metrics['avg_reactions'] ) ? tm_parse_social_number( $facebook_metrics['avg_reactions'] ) : 0;
			$facebook_avg_comments = ! empty( $facebook_metrics['avg_comments'] ) ? tm_parse_social_number( $facebook_metrics['avg_comments'] ) : 0;
			$facebook_avg_views = ! empty( $facebook_metrics['avg_views'] ) ? tm_parse_social_number( $facebook_metrics['avg_views'] ) : 0;
			$facebook_display_url = ! empty( $facebook_metrics['url'] ) ? $facebook_metrics['url'] : $facebook_url;
			$facebook_updated_at = ! empty( $facebook_metrics['updated_at'] ) ? $facebook_metrics['updated_at'] : '';
		}
	}

	// Fallback: if metrics are missing but raw posts exist, compute lightweight stats so UI does not show failure despite data
	if ( $facebook_followers === 0 && is_array( $facebook_raw ) && empty( $facebook_raw['error'] ) && $current_facebook_identifier ) {
		$raw_posts = isset( $facebook_raw['raw_response'] ) && is_array( $facebook_raw['raw_response'] ) ? $facebook_raw['raw_response'] : $facebook_raw;
		$raw_page_url = '';
		if ( ! empty( $raw_posts ) && is_array( $raw_posts ) && ! empty( $raw_posts[0]['page_url'] ) ) {
			$raw_page_url = $raw_posts[0]['page_url'];
		}
		$raw_identifier = $raw_page_url ? $extract_facebook_identifier( $raw_page_url ) : '';
		if ( ! empty( $raw_posts ) && is_array( $raw_posts ) && $raw_identifier && $raw_identifier === $current_facebook_identifier ) {
			$page_followers = 0;
			$total_likes = 0;
			$total_comments = 0;
			$total_views = 0;
			$view_samples = 0;
			$post_count = count( $raw_posts );
			foreach ( $raw_posts as $post ) {
				if ( $page_followers === 0 && ! empty( $post['page_followers'] ) ) {
					$page_followers = tm_parse_social_number( $post['page_followers'] );
				}
				if ( isset( $post['likes'] ) ) {
					$total_likes += tm_parse_social_number( $post['likes'] );
				}
				if ( isset( $post['num_comments'] ) ) {
					$total_comments += tm_parse_social_number( $post['num_comments'] );
				}
				if ( isset( $post['video_view_count'] ) ) {
					$views = tm_parse_social_number( $post['video_view_count'] );
					if ( $views > 0 ) {
						$total_views += $views;
						$view_samples++;
					}
				} elseif ( isset( $post['play_count'] ) ) {
					$views = tm_parse_social_number( $post['play_count'] );
					if ( $views > 0 ) {
						$total_views += $views;
						$view_samples++;
					}
				}
			}
			if ( $page_followers > 0 ) {
				$facebook_followers = $page_followers;
				$facebook_avg_reactions = $post_count > 0 ? round( $total_likes / $post_count ) : 0;
				$facebook_avg_comments = $post_count > 0 ? round( $total_comments / $post_count ) : 0;
				$facebook_avg_views = $view_samples > 0 ? round( $total_views / $view_samples ) : 0;
				if ( empty( $facebook_display_url ) && ! empty( $raw_posts[0]['page_url'] ) ) {
					$facebook_display_url = $raw_posts[0]['page_url'];
				}
				if ( empty( $facebook_updated_at ) && ! empty( $raw_posts[0]['timestamp'] ) ) {
					$facebook_updated_at = $raw_posts[0]['timestamp'];
				}
			}
		}
	}

	// Ensure these are defined before the "latest update" scan below;
	// they are fully populated later in the function but must have a safe default here.
	if ( ! isset( $instagram_metrics ) ) { $instagram_metrics = null; }
	if ( ! isset( $youtube_updated_at ) ) { $youtube_updated_at = ''; }

	// Determine the most recent update across social sources for tooltip display only
	$latest_updated_at = '';
	$latest_timestamp = 0;
	if ( $facebook_updated_at ) {
		$ts = strtotime( $facebook_updated_at );
		if ( $ts && $ts > $latest_timestamp ) {
			$latest_timestamp = $ts;
			$latest_updated_at = $facebook_updated_at;
		}
	}
	if ( $linkedin_updated_at ) {
		$ts = strtotime( $linkedin_updated_at );
		if ( $ts && $ts > $latest_timestamp ) {
			$latest_timestamp = $ts;
			$latest_updated_at = $linkedin_updated_at;
		}
	}
	if ( is_array( $instagram_metrics ) && ! empty( $instagram_metrics['updated_at'] ) ) {
		$ts = strtotime( $instagram_metrics['updated_at'] );
		if ( $ts && $ts > $latest_timestamp ) {
			$latest_timestamp = $ts;
			$latest_updated_at = $instagram_metrics['updated_at'];
		}
	}
	if ( $youtube_updated_at ) {
		$ts = strtotime( $youtube_updated_at );
		if ( $ts && $ts > $latest_timestamp ) {
			$latest_timestamp = $ts;
			$latest_updated_at = $youtube_updated_at;
		}
	}
	
	if ( $facebook_url ) {
		$needs_refresh = true;
		if ( is_array( $facebook_metrics ) && ! empty( $facebook_metrics['updated_at'] ) ) {
			$last = strtotime( $facebook_metrics['updated_at'] );
			$needs_refresh = $last ? ( time() - $last ) > ( defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 30 * DAY_IN_SECONDS ) : true;
		}
		$active_platform = get_user_meta( $vendor_id, 'tm_social_active_fetch_platform', true );
		$active_until = (int) get_user_meta( $vendor_id, 'tm_social_active_fetch_until', true );
		if ( $active_platform && $active_until && time() > $active_until ) {
			delete_user_meta( $vendor_id, 'tm_social_active_fetch_platform' );
			delete_user_meta( $vendor_id, 'tm_social_active_fetch_until' );
			$active_platform = '';
			$active_until = 0;
		}
		if ( $active_platform && $active_platform !== 'facebook' ) {
			$needs_refresh = false;
		}
		if ( $needs_refresh ) {
			tm_queue_facebook_metrics_refresh( $vendor_id, $facebook_url );
		}
	}

	// Extract YouTube data for display
	$youtube_url = ! empty( $social_profiles['youtube'] ) ? $social_profiles['youtube'] : '';
	$youtube_metrics = get_user_meta( $vendor_id, 'tm_social_metrics_youtube', true );
	$youtube_display_url = '';
	$youtube_name = '';
	$youtube_subscribers = null;
	$youtube_avg_views = null;
	$youtube_avg_reactions = null;
	$youtube_updated_at = '';
	if ( is_array( $youtube_metrics ) ) {
		$youtube_name = ! empty( $youtube_metrics['channel_name'] ) ? $youtube_metrics['channel_name'] : '';
		$youtube_subscribers = isset( $youtube_metrics['subscribers'] ) ? (int) $youtube_metrics['subscribers'] : null;
		$youtube_avg_views = array_key_exists( 'avg_views', $youtube_metrics ) ? (int) $youtube_metrics['avg_views'] : null;
		$youtube_avg_reactions = array_key_exists( 'avg_reactions', $youtube_metrics ) ? (int) $youtube_metrics['avg_reactions'] : null;
		$youtube_display_url = ! empty( $youtube_metrics['url'] ) ? $youtube_metrics['url'] : $youtube_url;
		$youtube_updated_at = ! empty( $youtube_metrics['updated_at'] ) ? $youtube_metrics['updated_at'] : '';
	}
	if ( $youtube_url ) {
		$needs_refresh = true;
		if ( $youtube_updated_at ) {
			$last = strtotime( $youtube_updated_at );
			$needs_refresh = $last ? ( time() - $last ) > ( defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 30 * DAY_IN_SECONDS ) : true;
		}
		$active_platform = get_user_meta( $vendor_id, 'tm_social_active_fetch_platform', true );
		$active_until = (int) get_user_meta( $vendor_id, 'tm_social_active_fetch_until', true );
		if ( $active_platform && $active_until && time() > $active_until ) {
			delete_user_meta( $vendor_id, 'tm_social_active_fetch_platform' );
			delete_user_meta( $vendor_id, 'tm_social_active_fetch_until' );
			$active_platform = '';
			$active_until = 0;
		}
		if ( $active_platform && $active_platform !== 'youtube' ) {
			$needs_refresh = false;
		}
		if ( $needs_refresh ) {
			tm_queue_youtube_metrics_refresh( $vendor_id, $youtube_url );
		}
	}
	
	// Mock data for visual demonstration
	$instagram_url = ! empty( $social_profiles['instagram'] ) ? $social_profiles['instagram'] : '';
	$instagram_metrics = get_user_meta( $vendor_id, 'tm_social_metrics_instagram', true );
	$instagram_followers = 0;
	$instagram_avg_reactions = 0;
	$instagram_avg_comments = 0;
	$instagram_display_url = '';
	$instagram_updated_at = '';
	if ( is_array( $instagram_metrics ) ) {
		$extract_instagram_handle = function( $value ) {
			$value = trim( (string) $value );
			if ( $value === '' ) {
				return '';
			}
			if ( ! preg_match( '#^https?://#i', $value ) ) {
				$value = 'https://' . ltrim( $value, '/' );
			}
			$parsed = wp_parse_url( $value );
			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return '';
			}
			$host = strtolower( (string) $parsed['host'] );
			$host = preg_replace( '/^www\./', '', $host );
			if ( $host !== 'instagram.com' ) {
				return '';
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( $path === '' ) {
				return '';
			}
			$segments = array_values( array_filter( explode( '/', $path ) ) );
			return isset( $segments[0] ) ? strtolower( (string) $segments[0] ) : '';
		};
		$current_handle = $extract_instagram_handle( $instagram_url );
		$metrics_handle = $extract_instagram_handle( isset( $instagram_metrics['url'] ) ? $instagram_metrics['url'] : '' );
		$instagram_updated_at = ! empty( $instagram_metrics['updated_at'] ) ? $instagram_metrics['updated_at'] : '';
		$instagram_matches = $current_handle && $metrics_handle && $current_handle === $metrics_handle;
		if ( $instagram_matches ) {
			$instagram_followers = isset( $instagram_metrics['followers'] ) ? (int) $instagram_metrics['followers'] : 0;
			$instagram_avg_reactions = isset( $instagram_metrics['avg_reactions'] ) ? (int) $instagram_metrics['avg_reactions'] : 0;
			$instagram_avg_comments = isset( $instagram_metrics['avg_comments'] ) ? (int) $instagram_metrics['avg_comments'] : 0;
			$instagram_display_url = ! empty( $instagram_metrics['url'] ) ? $instagram_metrics['url'] : $instagram_url;
		}
	}

	$growth_payload = tm_get_growth_rollup( $vendor_id );
	
	// Helper function to format large numbers
	$format_social_number = function( $number ) {
		if ( $number >= 1000000 ) {
			return round( $number / 1000000, 1 ) . 'M';
		} elseif ( $number >= 1000 ) {
			return round( $number / 1000, 1 ) . 'K';
		}
		return number_format( $number );
	};

	// Safe defaults for fetch-state flags — assigned inside the $is_owner block but
	// consumed by the metrics display template which renders for ALL visitors.
	// Without these, PHP 8 throws "Undefined variable" notices for non-owners.
	$youtube_fetching      = false;
	$youtube_error         = false;
	$instagram_fetching    = false;
	$instagram_error       = false;
	$facebook_fetching     = false;
	$facebook_error        = false;
	$facebook_stats_hidden = false;
	$linkedin_fetching     = false;
	$linkedin_error        = false;
	?>
	
	<div id="social-section" class="talent-physical-attributes attribute-slide-section social-section">
		<h3 class="section-title">
			<i class="fas fa-chart-line section-title-icon"></i> Social Influence Metrics
			<span class="help-icon-wrapper">
				<button type="button" class="help-toggle-btn help-toggle-btn--social" aria-label="Show help" data-help-text="Statistics based on last 10 posts average. Growth metrics compare the latest period against the previous one (daily, then weekly, then monthly).">
					<i class="fas fa-question-circle" aria-hidden="true"></i>
				</button>
			</span>
		</h3>
		
		<?php if ( $is_owner ) : ?>
			<!-- Social Media URLs - Inline editing for Owner -->
			<div class="social-urls-section">
				<h4 class="social-urls-title">
					<i class="fas fa-link social-urls-title-icon"></i> Your Social Media URLs
					<span class="social-urls-note">(Click and edit, auto-saves)</span>
				</h4>
				<div class="social-urls-grid">
					<?php
					$map_social_error = function( $error ) {
						$error_lower = strtolower( (string) $error );
						if ( strpos( $error_lower, 'bright data' ) !== false ) {
							return 'error';
						}
						if ( strpos( $error_lower, 'unexpected response payload' ) !== false ) {
							return 'profile not found or invalid URL';
						}
						if ( strpos( $error_lower, 'no instagram data returned' ) !== false
							|| strpos( $error_lower, 'no youtube data returned' ) !== false
							|| strpos( $error_lower, 'no facebook data returned' ) !== false
						) {
							return 'profile not found or invalid URL';
						}
						if ( strpos( $error_lower, 'missing' ) !== false && strpos( $error_lower, 'url' ) !== false ) {
							return 'missing URL';
						}
						if ( strpos( $error_lower, 'wp http error' ) !== false
							|| strpos( $error_lower, 'non-200 response' ) !== false
							|| strpos( $error_lower, 'invalid json response' ) !== false
						) {
							return 'network error. please try again';
						}
						return $error;
					};

					$extract_fetch_error = function( $raw ) use ( $map_social_error ) {
						if ( ! is_array( $raw ) ) {
							return '';
						}
						if ( ! empty( $raw['error'] ) ) {
							$error = is_string( $raw['error'] ) ? $raw['error'] : 'request error';
							if ( ! empty( $raw['message'] ) ) {
								$error .= ': ' . (string) $raw['message'];
							}
							return $map_social_error( $error );
						}
						if ( ! empty( $raw['error_code'] ) ) {
							return $map_social_error( (string) $raw['error_code'] );
						}
						if ( ! empty( $raw['message'] ) ) {
							return $map_social_error( (string) $raw['message'] );
						}
						return '';
					};

					$format_fetch_status = function( $last_fetch, $updated_at, $snapshot_id, $raw ) use ( $extract_fetch_error ) {
						if ( $snapshot_id ) {
							return 'fetching data... may take few minutes';
						}
						$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
						$updated_ts = $updated_at ? strtotime( $updated_at ) : 0;
						if ( $last_fetch_ts && ( ! $updated_ts || $updated_ts < $last_fetch_ts ) ) {
							$error = $extract_fetch_error( $raw );
							if ( $error !== '' ) {
								return 'fetch failed: ' . $error;
							}
							return 'fetching data... may take few minutes';
						}
						if ( $updated_ts ) {
							return 'last fetched ' . date_i18n( 'M j, Y g:ia', $updated_ts );
						}
						return '';
					};

					$get_fetch_state = function( $last_fetch, $updated_at, $snapshot_id, $raw ) use ( $extract_fetch_error ) {
						if ( $snapshot_id ) {
							return [ 'fetching' => true, 'error' => false ];
						}
						$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
						$updated_ts = $updated_at ? strtotime( $updated_at ) : 0;
						if ( $last_fetch_ts && ( ! $updated_ts || $updated_ts < $last_fetch_ts ) ) {
							$error = $extract_fetch_error( $raw );
							if ( $error !== '' ) {
								return [ 'fetching' => false, 'error' => true ];
							}
							return [ 'fetching' => true, 'error' => false ];
						}
						return [ 'fetching' => false, 'error' => false ];
					};

					$youtube_last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', true );
					$youtube_snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id', true );
					$youtube_raw = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_raw', true );
					$youtube_status = $format_fetch_status( $youtube_last_fetch, $youtube_updated_at, $youtube_snapshot_id, $youtube_raw );
					$youtube_state = $get_fetch_state( $youtube_last_fetch, $youtube_updated_at, $youtube_snapshot_id, $youtube_raw );
					$youtube_fetching = $youtube_state['fetching'];
					$youtube_error = $youtube_state['error'];

					$instagram_last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', true );
					$instagram_snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id', true );
					$instagram_raw = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', true );
					$instagram_status = $format_fetch_status( $instagram_last_fetch, $instagram_updated_at, $instagram_snapshot_id, $instagram_raw );
					$instagram_state = $get_fetch_state( $instagram_last_fetch, $instagram_updated_at, $instagram_snapshot_id, $instagram_raw );
					$instagram_fetching = $instagram_state['fetching'];
					$instagram_error = $instagram_state['error'];

					$facebook_last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_last_fetch', true );
					$facebook_snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id', true );
					$facebook_raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', true );
					$facebook_status = $format_fetch_status( $facebook_last_fetch, $facebook_updated_at, $facebook_snapshot_id, $facebook_raw );
					$facebook_state = $get_fetch_state( $facebook_last_fetch, $facebook_updated_at, $facebook_snapshot_id, $facebook_raw );
					$facebook_fetching = $facebook_state['fetching'];
					$facebook_error = $facebook_state['error'];
					$facebook_raw_error = $extract_fetch_error( $facebook_raw );
					if ( $facebook_raw_error ) {
						$facebook_status = 'fetch failed: ' . $facebook_raw_error;
						$facebook_fetching = false;
						$facebook_error = true;
					} elseif ( ! empty( $facebook_stats_hidden ) ) {
						$facebook_status = 'stats are hidden on this page';
						$facebook_fetching = false;
						$facebook_error = false;
					}

					$linkedin_last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_last_fetch', true );
					$linkedin_snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id', true );
					$linkedin_raw = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', true );
					$linkedin_status = $format_fetch_status( $linkedin_last_fetch, $linkedin_updated_at, $linkedin_snapshot_id, $linkedin_raw );
					$linkedin_state = $get_fetch_state( $linkedin_last_fetch, $linkedin_updated_at, $linkedin_snapshot_id, $linkedin_raw );
					$linkedin_fetching = $linkedin_state['fetching'];
					$linkedin_error = $linkedin_state['error'];

					function render_social_url_field( $platform, $icon_class, $current_url, $status_text = '' ) {
						$field_name = 'social_' . strtolower( $platform );
						$help_text = sprintf(
							'Enter your full %s profile URL (e.g., https://%s.com/yourprofile)',
							$platform,
							strtolower( $platform )
						);
						?>
						<div class="social-url-field" data-field="<?php echo esc_attr( $field_name ); ?>" data-platform="<?php echo esc_attr( strtolower( $platform ) ); ?>" data-help="<?php echo esc_attr( $help_text ); ?>">
							<label class="social-url-label">
								<i class="fab <?php echo esc_attr( $icon_class ); ?> social-url-icon"></i>
								<span class="social-url-name"><?php echo esc_html( $platform ); ?></span>
								<?php if ( $status_text !== '' ) : ?>
									<span class="social-url-status">(<?php echo esc_html( $status_text ); ?>)</span>
								<?php endif; ?>
							</label>
							<input
								type="url"
								class="social-url-input"
								data-field="<?php echo esc_attr( $field_name ); ?>"
								data-original="<?php echo esc_attr( (string) $current_url ); ?>"
								value="<?php echo esc_attr( (string) $current_url ); ?>"
								placeholder="https://<?php echo esc_attr( strtolower( $platform ) ); ?>.com/yourprofile"
							/>
						</div>
						<?php
					}
					
					render_social_url_field( 'YouTube', 'fa-youtube', $youtube_url, $youtube_status );
					render_social_url_field( 'Instagram', 'fa-instagram', $instagram_url, $instagram_status );
					render_social_url_field( 'Facebook', 'fa-facebook-square', $facebook_url, $facebook_status );
					render_social_url_field( 'LinkedIn', 'fa-linkedin', $linkedin_url, $linkedin_status );
					?>
				</div>
			</div>
		<?php endif; ?>
		
		<div class="attribute-grid">
			
			<!-- YouTube Metrics -->
			<div class="social-metric-column" data-platform="youtube">
				<div class="social-header">
					<i class="fab fa-youtube social-header-icon"></i>
					<div>
						<h4 class="social-title">YouTube</h4>
						<?php if ( $youtube_display_url ) : ?>
							<a href="<?php echo esc_url( $youtube_display_url ); ?>" target="_blank" class="social-profile-link">
								<i class="fas fa-external-link-alt social-profile-link-icon"></i> View Profile
							</a>
						<?php else : ?>
							<span class="social-not-connected">Not connected</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="social-stats">
					<?php if ( $youtube_fetching ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Subscribers: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-chart-bar stat-icon"></i> Avg Views: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $youtube_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $youtube_subscribers !== null || $youtube_avg_views !== null || $youtube_avg_reactions !== null ) : ?>
						<?php if ( $youtube_subscribers !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-users stat-icon"></i> Subscribers: <strong class="stat-value--gold"><?php echo $format_social_number( $youtube_subscribers ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $youtube_avg_views !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-chart-bar stat-icon"></i> Avg Views: <strong class="stat-value--rose"><?php echo $format_social_number( $youtube_avg_views ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $youtube_avg_reactions !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose"><?php echo $format_social_number( $youtube_avg_reactions ); ?></strong>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="stat-item stat-item--muted">
							<?php 
							if ( ! $youtube_url ) {
								echo 'No YouTube URL provided';
							} else {
								$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id', true );
								if ( $snapshot_id ) {
									echo 'Processing data (Snapshot: ' . esc_html( substr( $snapshot_id, 0, 12 ) ) . '...)';
								} elseif ( $youtube_updated_at ) {
									echo 'Last fetch failed. Try clicking Fetch Metrics again.';
								} else {
									echo 'Click Fetch Metrics to load data';
								}
							}
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Instagram Metrics -->
			<div class="social-metric-column" data-platform="instagram">
				<div class="social-header">
					<i class="fab fa-instagram social-header-icon"></i>
					<div>
						<h4 class="social-title">Instagram</h4>
						<?php if ( $instagram_display_url ) : ?>
							<a href="<?php echo esc_url( $instagram_display_url ); ?>" target="_blank" class="social-profile-link">
								<i class="fas fa-external-link-alt social-profile-link-icon"></i> View Profile
							</a>
						<?php else : ?>
							<span class="social-not-connected">Not connected</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="social-stats">
					<?php if ( $instagram_fetching ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-comment-dots stat-icon"></i> Avg Comments: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $instagram_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $instagram_followers > 0 ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold"><?php echo $format_social_number( $instagram_followers ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose"><?php echo $format_social_number( $instagram_avg_reactions ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-comment-dots stat-icon"></i> Avg Comments: <strong class="stat-value--rose"><?php echo $format_social_number( $instagram_avg_comments ); ?></strong>
						</div>
					<?php else : ?>
						<div class="stat-item stat-item--muted">
							<?php 
							if ( ! $instagram_url ) {
								echo 'No Instagram URL provided';
							} else {
								echo 'Click Fetch Metrics to load data';
							}
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Facebook Metrics -->
			<div class="social-metric-column" data-platform="facebook">
				<div class="social-header">
					<i class="fab fa-facebook-square social-header-icon"></i>
					<div>
						<h4 class="social-title">Facebook</h4>
						<?php if ( $facebook_display_url ) : ?>
							<a href="<?php echo esc_url( $facebook_display_url ); ?>" target="_blank" class="social-profile-link">
								<i class="fas fa-external-link-alt social-profile-link-icon"></i> View Profile
							</a>
						<?php else : ?>
							<span class="social-not-connected">Not connected</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="social-stats">
					<?php if ( $facebook_fetching ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> Avg Views: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $facebook_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $facebook_stats_hidden ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold">N/A</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> Avg Views: <strong class="stat-value--rose">N/A</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">N/A</strong>
						</div>
					<?php elseif ( $facebook_followers > 0 ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold"><?php echo $format_social_number( $facebook_followers ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> Avg Views: <strong class="stat-value--rose"><?php echo $format_social_number( $facebook_avg_views ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> Avg Reactions: <strong class="stat-value--rose"><?php echo $format_social_number( $facebook_avg_reactions ); ?></strong>
						</div>
					<?php else : ?>
						<div class="stat-item stat-item--muted">
							<?php 
							if ( ! $facebook_url ) {
								echo 'No Facebook URL provided';
							} else {
								$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id', true );
								if ( $snapshot_id ) {
									echo 'Processing data (Snapshot: ' . esc_html( substr( $snapshot_id, 0, 12 ) ) . '...)';
								} elseif ( $facebook_updated_at ) {
									echo 'Last fetch failed. Try clicking Fetch Metrics again.';
								} else {
									echo 'Click Fetch Metrics to load data';
								}
							}
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- LinkedIn Metrics -->
			<div class="social-metric-column" data-platform="linkedin">
				<div class="social-header">
					<i class="fab fa-linkedin social-header-icon"></i>
					<div>
						<h4 class="social-title">LinkedIn</h4>
						<?php if ( $linkedin_display_url ) : ?>
							<a href="<?php echo esc_url( $linkedin_display_url ); ?>" target="_blank" class="social-profile-link">
								<i class="fas fa-external-link-alt social-profile-link-icon"></i> View Profile
							</a>
						<?php else : ?>
							<span class="social-not-connected">Not connected</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="social-stats">
					<?php if ( $linkedin_fetching ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-user-friends stat-icon"></i> Connections: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $linkedin_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php else : ?>
						<?php if ( $linkedin_followers > 0 ) : ?>
							<div class="stat-item">
								<i class="fas fa-users stat-icon"></i> Followers: <strong class="stat-value--gold">&nbsp;<?php echo $format_social_number( $linkedin_followers ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( isset( $linkedin_metrics['avg_reactions'] ) && $linkedin_metrics['avg_reactions'] !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-heart stat-icon"></i> Avg Reactions: <strong class="stat-value--rose">&nbsp;<?php echo $format_social_number( (int) $linkedin_metrics['avg_reactions'] ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( isset( $linkedin_metrics['connections'] ) && $linkedin_metrics['connections'] ) : ?>
							<div class="stat-item">
								<i class="fas fa-user-friends stat-icon"></i> Connections: <strong class="stat-value--rose">&nbsp;<?php echo $format_social_number( (int) $linkedin_metrics['connections'] ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( ! $linkedin_followers && ( ! isset( $linkedin_metrics['avg_reactions'] ) || $linkedin_metrics['avg_reactions'] === null ) ) : ?>
							<div class="stat-item stat-item--muted">
								<?php 
								if ( ! $linkedin_url ) {
									echo 'No LinkedIn URL provided';
								} else {
									$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id', true );
									if ( $snapshot_id ) {
										echo 'Processing data (Snapshot: ' . esc_html( substr( $snapshot_id, 0, 12 ) ) . '...)';
									} elseif ( $linkedin_updated_at ) {
										echo 'Last fetch failed. Try clicking Fetch Metrics again.';
									} else {
										echo 'Click Fetch Metrics to load data';
									}
								}
								?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Growth Metrics -->
			<div class="social-metric-column social-metric-column--growth">
				<div class="social-header social-header--center">
					<h4 class="social-title">
						<i class="fas fa-chart-line section-title-icon"></i> <?php echo esc_html( $growth_payload['label'] ); ?>
					</h4>
				</div>
				<div class="social-stats">
					<?php
					$growth_palette = [
						'followship' => '#4CAF50',
						'viewship'   => '#2196F3',
						'reactions'  => '#FF9800',
					];
					$growth_icons = [
						'followship' => 'fa-arrow-up',
						'viewship'   => 'fa-arrow-up',
						'reactions'  => 'fa-arrow-up',
					];
					$growth_labels = [
						'followship' => 'Followship',
						'viewship'   => 'Viewship',
						'reactions'  => 'Reactions',
					];
					$rendered_growth = false;
					foreach ( $growth_payload['metrics'] as $key => $metric ) {
						if ( $metric['pct'] === null ) {
							continue;
						}
						$rendered_growth = true;
						$color = isset( $growth_palette[ $key ] ) ? $growth_palette[ $key ] : '#D4AF37';
						$icon  = isset( $growth_icons[ $key ] ) ? $growth_icons[ $key ] : 'fa-arrow-up';
						$label = isset( $growth_labels[ $key ] ) ? $growth_labels[ $key ] : ucfirst( $key );
						$formatted = ( $metric['pct'] > 0 ? '+' : '' ) . number_format( $metric['pct'], 1 ) . '%';
						?>
						<div class="stat-item stat-item--pill">
							<i class="fas <?php echo esc_attr( $icon ); ?> stat-icon stat-icon--<?php echo esc_attr( $key ); ?>"></i> <?php echo esc_html( $label ); ?>: <strong class="stat-value--<?php echo esc_attr( $key ); ?> stat-value--growth"><?php echo esc_html( $formatted ); ?></strong>
						</div>
						<?php
					}
					if ( ! $rendered_growth ) :
						?>
						<div class="stat-item stat-item--pill stat-item--pill-muted">
							<i class="fas fa-info-circle stat-icon stat-icon--info"></i> <?php echo esc_html( $growth_payload['message'] ); ?>
						</div>
						<?php
					endif;
					?>
				</div>
			</div>
			
		</div>
	</div>
	
	<?php
}, 4, 2 );

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
		
		console.log('=== VENDOR DASHBOARD CONDITIONAL FIELDS DEBUG ===');
		console.log('Script loaded and running');
		console.log('Current URL:', window.location.href);
		
		// Function to update visible fields based on selected categories
		function updateCategoryFields() {
			console.log('--- updateCategoryFields() called ---');
			
			var selectedCategories = [];
			
			// First, let's debug and find all select elements
			console.log('=== DEBUGGING: Finding category selector ===');
			$('select').each(function(index) {
				var $select = $(this);
				var name = $select.attr('name');
				var id = $select.attr('id');
				var classes = $select.attr('class');
				console.log('Select ' + index + ': name="' + name + '", id="' + id + '", classes="' + classes + '"');
			});
			
			// Look for Select2 elements
			console.log('Select2 elements:');
			$('.select2-selection__choice').each(function(index) {
				var title = $(this).attr('title');
				var text = $(this).text().trim();
				console.log('Select2 choice ' + index + ': title="' + title + '", text="' + text + '"');
			});
			
			// Try to find the category select - common Dokan patterns
			var $categorySelect = $('select[name*="categor"]').first();
			if (!$categorySelect.length) {
				$categorySelect = $('#store_category, #dokan_category, select.dokan_category').first();
			}
			
			console.log('Category select found:', $categorySelect.length);
			if ($categorySelect.length) {
				console.log('Category select name:', $categorySelect.attr('name'));
				console.log('Category select id:', $categorySelect.attr('id'));
				
				// Get selected values from the select element
				var selectedValues = $categorySelect.val();
				console.log('Selected values from select:', selectedValues);
				
				if (selectedValues && selectedValues.length > 0) {
					// Get the text labels for selected categories
					$categorySelect.find('option:selected').each(function() {
						var categoryLabel = $(this).text().trim().toLowerCase();
						selectedCategories.push(categoryLabel);
						console.log('Added category:', categoryLabel);
					});
				}
			}
			
			// Fallback: read from Select2 UI if select element doesn't work
			if (selectedCategories.length === 0) {
				console.log('No categories from select, trying Select2 UI elements');
				$('.select2-selection__choice').each(function() {
					var categoryText = $(this).attr('title') || $(this).text();
					categoryText = categoryText.replace('×', '').trim().toLowerCase();
					if (categoryText) {
						selectedCategories.push(categoryText);
						console.log('Added from Select2 UI:', categoryText);
					}
				});
			}
			
			console.log('FINAL selected categories:', selectedCategories);
			
			// Find all elements with data-category attribute
			var $categoryFields = $('[data-category]');
			console.log('Found elements with data-category:', $categoryFields.length);
			
			$categoryFields.each(function(index) {
				var $field = $(this);
				var fieldCategory = $field.attr('data-category');
				var fieldClasses = $field.attr('class');
				var currentDisplay = $field.css('display');
				console.log('Field ' + index + ': data-category="' + fieldCategory + '", classes="' + fieldClasses + '", current display="' + currentDisplay + '"');
			});
			
			// Hide all category-specific fields by default
			$categoryFields.css('display', 'none');
			console.log('Hidden all category-specific fields');
			
			// If no categories selected, keep everything hidden
			if ( selectedCategories.length === 0 ) {
				console.log('NO CATEGORIES SELECTED - all fields remain hidden');
				return;
			}
			
			// Show fields matching selected categories
			var shownCount = 0;
			$categoryFields.each(function() {
				var $field = $(this);
				var fieldCategories = $field.attr('data-category').split(',');
				var shouldShow = false;
				
				console.log('Checking field with categories:', fieldCategories);
				
				// Check if any selected category matches this field's categories
				for (var i = 0; i < selectedCategories.length; i++) {
					for (var j = 0; j < fieldCategories.length; j++) {
						if (selectedCategories[i] === fieldCategories[j].trim()) {
							shouldShow = true;
							console.log('MATCH FOUND: selected="' + selectedCategories[i] + '" matches field category="' + fieldCategories[j].trim() + '"');
							break;
						}
					}
					if (shouldShow) break;
				}
				
				if (shouldShow) {
					$field.css('display', 'block');
					shownCount++;
					console.log('SHOWING field:', $field.attr('class') || $field.find('label').first().text());
				}
			});
			
			console.log('Total fields shown:', shownCount);
			console.log('--- updateCategoryFields() complete ---');
		}
		
		// Run on page load
		$(document).ready(function() {
			console.log('Document ready - initializing');
			
			// Force-select stored categories to avoid Dokan defaulting to Uncategorized
			var storedCategories = <?php echo wp_json_encode( $stored_categories ); ?>;
			if ( storedCategories && storedCategories.length ) {
				setTimeout(function() {
					var $cat = $('#dokan_store_categories');
					if ( $cat.length ) {
						$cat.val(storedCategories).trigger('change');
						console.log('Applied stored categories to select:', storedCategories);
					}
				}, 400);
			}

			setTimeout(function() {
				console.log('Running after 500ms delay');
				updateCategoryFields();
				
				// Update when categories change - listen to Select2 change event
				$('select[name*="categor"]').on('change', function() {
					console.log('Category select changed!');
					updateCategoryFields();
				});
				
				// Also listen to select2:select and select2:unselect events
				$('select[name*="categor"]').on('select2:select select2:unselect', function() {
					console.log('Select2 event triggered!');
					setTimeout(updateCategoryFields, 100);
				});
			}, 500);
		});
		
		console.log('=== END SCRIPT INITIALIZATION ===');
		
	})(jQuery);
	</script>
	<?php
}, 100 ); // Higher priority to run after Dokan

// Store-listing verified badge + footer JS → dokan/store-lists/store-lists-hooks.php
add_action( 'wp_footer', function() {
	// Removed – all store-listing JS is now loaded by store-lists-hooks.php at priority 1000.
	return;
	?>
	<script type="text/javascript">
	(function($) {
		'use strict';
		
		$(document).ready(function() {
			console.log('DEBUG: Verified filter JS loaded');
			
			// Add search field to the filter form at the top
			var filterWrap = $('.store-lists-other-filter-wrap').first();
			if (filterWrap.length) {
				// Check if search field doesn't already exist
				if (!$('#dokan_seller_search').length) {
					var searchHtml = '<div class="store-search-field item">' +
						'<label for="dokan_seller_search">🔍 Search:</label>' +
						'<input type="search" id="dokan_seller_search" name="dokan_seller_search" placeholder="Search by name or keyword" value="' + (new URLSearchParams(window.location.search).get('dokan_seller_search') || '') + '">' +
						'</div>';
					filterWrap.prepend(searchHtml);
					console.log('DEBUG: Search field added to filter form');
				}
			}
			
			// Intercept Dokan's apply filter button click
			$('#apply-filter-btn').on('click', function(e) {
				console.log('DEBUG: Apply filter clicked');
				
				// Prevent default to handle the redirect ourselves
				e.preventDefault();
				e.stopPropagation();
				
				var verifiedCheckbox = $('#verified');
				var isChecked = verifiedCheckbox.is(':checked');
				
				console.log('DEBUG: Verified checkbox is checked:', isChecked);
				
				// Get current URL
				var currentUrl = new URL(window.location.href);
				var params = new URLSearchParams(currentUrl.search);
				
				// Handle verified parameter
				if (isChecked) {
					params.set('verified', 'yes');
					console.log('DEBUG: Adding verified=yes to URL');
				} else {
					params.delete('verified');
					console.log('DEBUG: Removing verified from URL');
				}
				
			// Handle search parameter
			var searchValue = $('#dokan_seller_search').val();
			if (searchValue && searchValue.trim() !== '') {
				params.set('dokan_seller_search', searchValue.trim());
			} else {
				params.delete('dokan_seller_search');
			}
			
			// Handle category selection
			var selectedCategory = $('.store-lists-category .category-box ul li.selected').data('slug');
			if (selectedCategory && selectedCategory !== '') {
				params.set('dokan_seller_category', selectedCategory);
				console.log('DEBUG: Adding dokan_seller_category=' + selectedCategory);
			} else {
				params.delete('dokan_seller_category');
				console.log('DEBUG: No category selected, removing from URL');
			}
			
			// Handle featured filter
			var featuredCheckbox = $('#featured');
			if (featuredCheckbox.is(':checked')) {
				params.set('featured', 'yes');
				console.log('DEBUG: Adding featured=yes to URL');
			} else {
				params.delete('featured');
			}
			
			// Handle physical attributes filters - all 9 attributes
			var physicalFilters = [
				'talent_height',
				'talent_weight',
				'talent_waist',
				'talent_hip',
				'talent_chest',
				'talent_shoe_size',
				'talent_eye_color',
				'talent_hair_color',
				'talent_hair_style'
			];
			
			physicalFilters.forEach(function(filterName) {
				var filterValue = $('#' + filterName).val();
				if (filterValue && filterValue !== '') {
					params.set(filterName, filterValue);
					console.log('DEBUG: Adding ' + filterName + '=' + filterValue);
				} else {
					params.delete(filterName);
					console.log('DEBUG: Removing ' + filterName);
				}
			});
			
			// Build new URL
			var newUrl = currentUrl.pathname + '?' + params.toString();
			
			console.log('DEBUG: Final URL:', newUrl);
			console.log('DEBUG: Redirecting now...');
			
			// Redirect
			window.location.href = newUrl;
			
			return false;
		});
			
	// Monitor checkbox state
	$('#verified').on('change', function() {
		console.log('DEBUG: Verified checkbox changed to:', $(this).is(':checked'));
	});
	
	// Function to show/hide category-specific filter groups
	function updateCategorySpecificFilters(categorySlug) {
		console.log('DEBUG updateCategorySpecificFilters called with:', categorySlug);
		
		// Hide all custom filter groups EXCEPT always-visible ones
		$('.custom-filter-group').not('.always-visible').css('display', 'none');
		console.log('DEBUG: Found', $('.custom-filter-group').length, 'custom filter groups');
		
		// Always show the always-visible filter groups (e.g., demographic filters)
		$('.custom-filter-group.always-visible').css('display', 'block');
		console.log('DEBUG: Always showing', $('.custom-filter-group.always-visible').length, 'always-visible filter groups');
		
		if (categorySlug && categorySlug !== '') {
			// Show filter groups that match this category
			$('.custom-filter-group').not('.always-visible').each(function() {
				var $group = $(this);
				var categories = $group.data('category');
				console.log('DEBUG: Filter group data-category:', categories, 'Type:', typeof categories);
				
				if (categories) {
					// Convert to string and split by comma
					var categoryArray = categories.toString().split(',').map(function(cat) {
						return cat.trim();
					});
					console.log('DEBUG: Category array:', categoryArray);
					console.log('DEBUG: Looking for slug:', categorySlug);
					
					// Show if the selected category is in the list
					if (categoryArray.indexOf(categorySlug) !== -1) {
						// Use .css() with !important to override CSS
						$group.css('display', 'block');
						console.log('DEBUG: ✓ SHOWING filter group for category:', categorySlug);
					} else {
						console.log('DEBUG: ✗ NOT showing - slug not in array');
					}
				}
			});
		} else {
			console.log('DEBUG: No category selected, hiding all custom filter groups (except always-visible)');
		}
	}
		
		// Override Dokan's category selection to make it single-select only
		$('.store-lists-category .category-box ul li').off('click').on('click', function(e) {
			e.preventDefault();
			var $this = $(this);
			var categoryName = $this.text().trim();
			var categorySlug = $this.data('slug');
			
			// Check if this item is already selected BEFORE removing classes
			var wasSelected = $this.hasClass('selected');
			
			// Remove 'selected' class from all items and 'dokan-btn-theme' class
			$('.store-lists-category .category-box ul li').removeClass('selected dokan-btn-theme');
			
			// Toggle the clicked item
			if (wasSelected) {
				// If it was already selected, deselect it (don't add class back)
				$('.store-lists-category .category-items').text('Select a category');
				console.log('DEBUG: Category deselected');
				// Hide all category-specific filters
				updateCategorySpecificFilters(null);
			} else {
				// If it wasn't selected, select it now
				$this.addClass('selected');
				$('.store-lists-category .category-items').text(categoryName);
				console.log('DEBUG: Category selected:', categoryName);
				// Show category-specific filters for this category
				updateCategorySpecificFilters(categorySlug);
			}
		});
		
		// Restore category selection from URL on page load
		var urlParams = new URLSearchParams(window.location.search);
		var selectedCategorySlug = urlParams.get('dokan_seller_category');
		if (selectedCategorySlug) {
			var $categoryItem = $('.store-lists-category .category-box ul li[data-slug="' + selectedCategorySlug + '"]');
			if ($categoryItem.length) {
				$categoryItem.addClass('selected');
				$('.store-lists-category .category-items').text($categoryItem.text().trim());
				console.log('DEBUG: Restored category selection:', selectedCategorySlug);
				// Show category-specific filters for this category
				updateCategorySpecificFilters(selectedCategorySlug);
			}
		} else {
			// No category selected, make sure all custom filters are hidden
			updateCategorySpecificFilters(null);
		}
		
		// Update category display text on page load
		if ($('.store-lists-category .category-items').text().trim() === 'All Categories') {
			$('.store-lists-category .category-items').text('Select a category');
		}
	});
})(jQuery);
</script>
	<?php
}, 1000 );  // Run after Dokan's scripts

/**
 * Add Admin Menu for Vendor Edit Logs
 */
add_action( 'admin_menu', function() {
	add_submenu_page(
		'users.php',                    // Parent menu (Users)
		'Vendor Edit Logs',             // Page title
		'Vendor Edit Logs',             // Menu title
		'manage_options',               // Capability
		'vendor-edit-logs',             // Menu slug
		'tm_vendor_edit_logs_page'      // Callback function
	);
});

/**
 * Admin page content for vendor edit logs
 */
function tm_vendor_edit_logs_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions.' );
	}
	
	// Handle log clearing
	if ( isset( $_POST['clear_logs'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'clear_vendor_logs' ) ) {
		delete_option( 'tm_admin_vendor_edit_logs' );
		echo '<div class="notice notice-success is-dismissible"><p>Logs cleared successfully.</p></div>';
	}
	
	// Get logs
	$logs = get_option( 'tm_admin_vendor_edit_logs', [] );
	$logs = array_reverse( $logs ); // Show newest first
	
	// Handle search/filtering
	$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
	$filter_admin = isset( $_GET['filter_admin'] ) ? sanitize_text_field( $_GET['filter_admin'] ) : '';
	$filter_vendor = isset( $_GET['filter_vendor'] ) ? sanitize_text_field( $_GET['filter_vendor'] ) : '';
	
	if ( $search || $filter_admin || $filter_vendor ) {
		$logs = array_filter( $logs, function( $log ) use ( $search, $filter_admin, $filter_vendor ) {
			if ( $search && stripos( $log['field'] . ' ' . $log['admin_name'] . ' ' . $log['vendor_name'], $search ) === false ) {
				return false;
			}
			if ( $filter_admin && $log['admin_id'] != $filter_admin ) {
				return false;
			}
			if ( $filter_vendor && $log['vendor_id'] != $filter_vendor ) {
				return false;
			}
			return true;
		});
	}
	
	// Get unique admins and vendors for filter dropdowns
	$all_logs = get_option( 'tm_admin_vendor_edit_logs', [] );
	$admins = [];
	$vendors = [];
	foreach ( $all_logs as $log ) {
		$admins[ $log['admin_id'] ] = $log['admin_name'];
		$vendors[ $log['vendor_id'] ] = $log['vendor_name'];
	}
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Vendor Edit Logs</h1>
		<p class="description">Track admin modifications to vendor profiles for security and audit purposes.</p>
		
		<!-- Filters -->
		<div class="tablenav top">
			<form method="get" class="search-form" style="float: right; display: flex; gap: 10px; align-items: center;">
				<input type="hidden" name="page" value="vendor-edit-logs">
				
				<select name="filter_admin" style="min-width: 150px;">
					<option value="">All Admins</option>
					<?php foreach ( $admins as $admin_id => $admin_name ) : ?>
						<option value="<?php echo esc_attr( $admin_id ); ?>" <?php selected( $filter_admin, $admin_id ); ?>>
							<?php echo esc_html( $admin_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<select name="filter_vendor" style="min-width: 150px;">
					<option value="">All Vendors</option>
					<?php foreach ( $vendors as $vendor_id => $vendor_name ) : ?>
						<option value="<?php echo esc_attr( $vendor_id ); ?>" <?php selected( $filter_vendor, $vendor_id ); ?>>
							<?php echo esc_html( $vendor_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" 
					   placeholder="Search fields, names..." style="min-width: 200px;">
				
				<input type="submit" class="button" value="Filter">
				
				<?php if ( $search || $filter_admin || $filter_vendor ) : ?>
					<a href="?page=vendor-edit-logs" class="button button-secondary">Clear</a>
				<?php endif; ?>
			</form>
			
			<div class="alignleft actions">
				<form method="post" style="display: inline;" 
					  onsubmit="return confirm('Are you sure you want to clear all logs? This cannot be undone.');">
					<?php wp_nonce_field( 'clear_vendor_logs' ); ?>
					<input type="submit" name="clear_logs" class="button button-secondary" value="Clear All Logs">
				</form>
				
				<button type="button" class="button button-secondary" onclick="exportVendorLogs()">Export CSV</button>
			</div>
		</div>
		
		<div class="clear"></div>
		
		<!-- Stats -->
		<div class="vendor-logs-stats" style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<div><strong>Total Logs:</strong> <?php echo count( $all_logs ); ?></div>
			<div><strong>Showing:</strong> <?php echo count( $logs ); ?></div>
			<div><strong>Admins Active:</strong> <?php echo count( array_unique( array_column( $all_logs, 'admin_id' ) ) ); ?></div>
			<div><strong>Vendors Edited:</strong> <?php echo count( array_unique( array_column( $all_logs, 'vendor_id' ) ) ); ?></div>
		</div>
		
		<!-- Logs Table -->
		<?php if ( empty( $logs ) ) : ?>
			<div class="notice notice-info">
				<p><?php echo $search ? 'No logs found matching your search criteria.' : 'No admin vendor edits recorded yet.'; ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" id="vendor-logs-table">
				<thead>
					<tr>
						<th scope="col" style="width: 140px;">Timestamp</th>
						<th scope="col">Admin User</th>
						<th scope="col">Vendor</th>
						<th scope="col">Field Edited</th>
						<th scope="col" style="width: 200px;">Changes</th>
						<th scope="col">Action</th>
						<th scope="col" style="width: 100px;">Links</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<?php 
						$has_values = !empty( $log['old_value'] ) || !empty( $log['new_value'] );
						$old_display = isset( $log['old_value'] ) ? $log['old_value'] : 'null';
						$new_display = isset( $log['new_value'] ) ? $log['new_value'] : 'null';
						?>
						<tr>
							<td title="<?php echo esc_attr( $log['timestamp'] ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $log['timestamp'] ) ) ); ?> ago
							</td>
							<td>
								<strong><?php echo esc_html( $log['admin_name'] ); ?></strong>
								<br><small>ID: <?php echo esc_html( $log['admin_id'] ); ?></small>
							</td>
							<td>
								<strong><?php echo esc_html( $log['vendor_name'] ); ?></strong>
								<br><small>ID: <?php echo esc_html( $log['vendor_id'] ); ?></small>
							</td>
							<td>
								<code><?php echo esc_html( $log['field'] ); ?></code>
							</td>
							<td>
								<?php if ( $has_values && ($old_display !== 'null' || $new_display !== 'null') ) : ?>
									<div class="field-changes">
										<div class="old-value" title="Old Value">
											<strong>From:</strong> <code><?php echo esc_html( $old_display ); ?></code>
										</div>
										<div class="new-value" title="New Value">
											<strong>To:</strong> <code><?php echo esc_html( $new_display ); ?></code>
										</div>
									</div>
								<?php else : ?>
									<em>Values not recorded</em>
								<?php endif; ?>
							</td>
							<td>
								<span class="vendor-log-action vendor-log-<?php echo esc_attr( $log['action'] ); ?>">
									<?php echo esc_html( ucfirst( $log['action'] ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( get_edit_user_link( $log['vendor_id'] ) ); ?>" 
								   class="button button-small" target="_blank" title="Edit Vendor in Admin">Admin</a>
								<a href="<?php echo esc_url( dokan_get_store_url( $log['vendor_id'] ) ); ?>" 
								   class="button button-small" target="_blank" title="View Vendor Store">Store</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	
	<style>
		.vendor-log-action {
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: 600;
		}
		.vendor-log-updated {
			background: #d1ecf1;
			color: #0c5460;
		}
		.vendor-log-created {
			background: #d4edda;
			color: #155724;
		}
		.vendor-log-deleted {
			background: #f8d7da;
			color: #721c24;
		}
		.search-form {
			margin-bottom: 10px;
		}
		.field-changes {
			font-size: 11px;
			line-height: 1.3;
		}
		.field-changes .old-value,
		.field-changes .new-value {
			margin-bottom: 3px;
		}
		.field-changes code {
			color: #666;
			font-size: 10px;
			background: #f7f7f7;
			padding: 1px 4px;
			max-width: 150px;
			display: inline-block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			vertical-align: top;
			border-radius: 2px;
		}
		.old-value code { 
			background-color: #fef7f7; 
			border-left: 2px solid #dc3232;
		}
		.new-value code { 
			background-color: #f0f8f0; 
			border-left: 2px solid #46b450;
		}
		@media (max-width: 782px) {
			.search-form {
				flex-direction: column;
				align-items: stretch;
			}
			.search-form > * {
				margin-bottom: 5px;
			}
		}
	</style>
	
	<script>
	function exportVendorLogs() {
		const table = document.getElementById('vendor-logs-table');
		if (!table) {
			alert('No logs to export.');
			return;
		}
		
		let csv = 'Timestamp,Admin User,Admin ID,Vendor,Vendor ID,Field,Old Value,New Value,Action\n';
		
		const rows = table.querySelectorAll('tbody tr');
		rows.forEach(row => {
			const cells = row.querySelectorAll('td');
			const timestamp = cells[0].getAttribute('title');
			const adminName = cells[1].querySelector('strong').textContent;
			const adminId = cells[1].querySelector('small').textContent.replace('ID: ', '');
			const vendorName = cells[2].querySelector('strong').textContent;
			const vendorId = cells[2].querySelector('small').textContent.replace('ID: ', '');
			const field = cells[3].querySelector('code').textContent;
			
			// Extract old and new values from changes column (5th column with new layout)
			const changesCell = cells[4];
			let oldValue = 'N/A';
			let newValue = 'N/A';
			
			const oldValueEl = changesCell.querySelector('.old-value code');
			const newValueEl = changesCell.querySelector('.new-value code');
			if (oldValueEl && newValueEl) {
				oldValue = oldValueEl.textContent;
				newValue = newValueEl.textContent;
			}
			
			const action = cells[5].querySelector('span').textContent;
			
			csv += `"${timestamp}","${adminName}","${adminId}","${vendorName}","${vendorId}","${field}","${oldValue}","${newValue}","${action}"\n`;
		});
		
		const blob = new Blob([csv], { type: 'text/csv' });
		const url = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `vendor-edit-logs-${new Date().toISOString().split('T')[0]}.csv`;
		a.click();
		window.URL.revokeObjectURL(url);
	}
	</script>
	<?php
}

/**
 * Enhanced permission check for vendor profile editing
 * Allows both vendor owners and WordPress admins to edit profiles
 */
function tm_can_edit_vendor_profile( $vendor_id, $current_user_id = null ) {
	if ( !$current_user_id ) {
		$current_user_id = get_current_user_id();
	}
	
	// Original owner check
	if ( $current_user_id == $vendor_id ) {
		return true;
	}
	
	// Admin capability check - manage_options is core admin capability
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	
	// Edit users capability - for user managers
	if ( current_user_can( 'edit_users' ) ) {
		return true;
	}
	
	// Optional: Custom capability for vendor management (can be added later)
	if ( current_user_can( 'manage_vendors' ) ) {
		return true;
	}
	
	return false;
}

/**
 * Log admin edits to vendor profiles for audit purposes
 * Enhanced to capture old and new values
 */
function tm_log_admin_vendor_edit( $vendor_id, $field, $action = 'updated', $old_value = null, $new_value = null ) {
	$current_user_id = get_current_user_id();
	
	// Only log if this is an admin editing someone else's profile
	if ( $current_user_id != $vendor_id && current_user_can( 'manage_options' ) ) {
		$admin_user = get_userdata( $current_user_id );
		$vendor_user = get_userdata( $vendor_id );
		
		// Sanitize values for logging (truncate if too long)
		$old_display = $old_value !== null ? tm_sanitize_log_value( $old_value ) : null;
		$new_display = $new_value !== null ? tm_sanitize_log_value( $new_value ) : null;
		
		$log_entry = sprintf(
			'[ADMIN EDIT] %s (%s) %s field "%s" for vendor %s (%s)',
			$admin_user->display_name,
			$admin_user->user_login,
			$action,
			$field,
			$vendor_user->display_name,
			$vendor_user->user_login
		);
		
		if ( $old_display !== null || $new_display !== null ) {
			$log_entry .= sprintf( ' [%s → %s]', $old_display ?? 'null', $new_display ?? 'null' );
		}
		
		// Log to WordPress error log
		error_log( $log_entry );
		
		// Optional: Store in database for dashboard viewing
		$logs = get_option( 'tm_admin_vendor_edit_logs', [] );
		$logs[] = [
			'timestamp' => current_time( 'mysql' ),
			'admin_id' => $current_user_id,
			'admin_name' => $admin_user->display_name,
			'vendor_id' => $vendor_id,
			'vendor_name' => $vendor_user->display_name,
			'field' => $field,
			'action' => $action,
			'old_value' => $old_display,
			'new_value' => $new_display
		];
		
		// Keep only last 100 entries
		$logs = array_slice( $logs, -100, 100 );
		update_option( 'tm_admin_vendor_edit_logs', $logs );
	}
}

/**
 * Sanitize values for log display (handle arrays, long strings, sensitive data)
 */
function tm_sanitize_log_value( $value ) {
	if ( is_array( $value ) ) {
		// Handle arrays (like contact lists)
		if ( empty( $value ) ) {
			return '[]';
		}
		return '[' . implode( ', ', array_slice( $value, 0, 3 ) ) . ( count( $value ) > 3 ? '...' : '' ) . ']';
	}
	
	if ( is_string( $value ) ) {
		// Truncate long strings
		if ( strlen( $value ) > 100 ) {
			return substr( $value, 0, 97 ) . '...';
		}
		return $value;
	}
	
	if ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	}
	
	if ( is_null( $value ) ) {
		return 'null';
	}
	
	return (string) $value;
}

/**
 * AJAX Handler: Save vendor attribute inline edit
 */
add_action( 'wp_ajax_vendor_save_attribute', function() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	
	// Verify nonce
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );
	
	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	$field = isset( $_POST['field'] ) ? sanitize_text_field( $_POST['field'] ) : '';
	$value_raw = isset( $_POST['value'] ) ? $_POST['value'] : '';
	
	// Capture old value for logging before making changes
	$old_value = null;
	if ( strpos( $field, 'social_' ) === 0 ) {
		$social_key = str_replace( 'social_', '', $field );
		$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
		$old_value = isset( $profile_settings['social'][ $social_key ] ) ? $profile_settings['social'][ $social_key ] : '';
	} elseif ( $field === 'store_categories' ) {
		$old_value = wp_get_object_terms( $user_id, 'store_category', [ 'fields' => 'ids' ] );
	} else {
		$old_value = get_user_meta( $user_id, $field, true );
	}
	
	// Whitelist allowed fields
	$allowed_fields = [
		'talent_height', 'talent_weight', 'talent_waist', 'talent_hip',
		'talent_chest', 'talent_shoe_size', 'talent_eye_color',
		'talent_hair_color', 'talent_hair_style',
		'camera_type', 'experience_level', 'editing_software',
		'specialization', 'years_experience', 'equipment_ownership',
		'lighting_equipment', 'audio_equipment', 'drone_capability',
		'demo_ethnicity', 'demo_availability', 'demo_notice_time', 'demo_languages',
		'demo_daily_rate', 'demo_education', 'demo_birth_date', 'demo_can_travel',
		'social_youtube', 'social_instagram', 'social_facebook', 'social_linkedin',
		'store_categories'
	];
	
	if ( ! in_array( $field, $allowed_fields ) ) {
		wp_send_json_error( ['message' => 'Invalid field'], 400 );
	}
	
	// Special handling for social URLs - save to dokan_profile_settings['social']
	if ( strpos( $field, 'social_' ) === 0 ) {
		$social_key = str_replace( 'social_', '', $field );
		$normalize_social_url = function( $value ) use ( $social_key ) {
			$value = trim( (string) $value );
			if ( $value === '' ) {
				return '';
			}
			if ( ! preg_match( '#^https?://#i', $value ) ) {
				$value = 'https://' . ltrim( $value, '/' );
			}
			$parsed = wp_parse_url( $value );
			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return rtrim( $value, "/ \t\n\r\0\x0B" );
			}
			$scheme = ! empty( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
			$path = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '';
			$query = isset( $parsed['query'] ) ? $parsed['query'] : '';
			$host = isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
			if ( $social_key === 'facebook' ) {
				$host = preg_replace( '/^www\./i', '', strtolower( $host ) );
			}
			$normalized = $scheme . '://' . $host . $path;
			if ( $social_key === 'facebook' && strtolower( $path ) === '/profile.php' && $query ) {
				parse_str( $query, $query_args );
				if ( ! empty( $query_args['id'] ) ) {
					$normalized .= '?id=' . rawurlencode( (string) $query_args['id'] );
				}
			}
			return $normalized;
		};
				$is_valid_facebook_url = function( $value ) {
					$parsed = wp_parse_url( $value );
					if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
						return false;
					}
					$host = strtolower( (string) $parsed['host'] );
					$host = preg_replace( '/^www\./', '', $host );
					if ( $host !== 'facebook.com' ) {
						return false;
					}
					$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
					if ( $path === '' ) {
						return false;
					}
					if ( strtolower( $path ) === 'profile.php' ) {
						if ( empty( $parsed['query'] ) ) {
							return false;
						}
						parse_str( (string) $parsed['query'], $query_args );
						return ! empty( $query_args['id'] );
					}
					$segments = array_filter( explode( '/', $path ) );
					if ( count( $segments ) !== 1 ) {
						return false;
					}
					$handle = strtolower( (string) $segments[0] );
					$reserved = [ 'pages', 'profile.php', 'home.php', 'people', 'groups', 'events', 'watch', 'marketplace', 'login', 'settings', 'help', 'plugins', 'privacy' ];
					return ! in_array( $handle, $reserved, true );
				};
				if ( $social_key === 'facebook' && $new_url && ! $is_valid_facebook_url( $new_url ) ) {
					wp_send_json_error( [ 'message' => 'Please enter a valid Facebook profile or page URL.' ], 400 );
				}
		$url = $normalize_social_url( esc_url_raw( $value_raw ) );
		$old_url = $normalize_social_url( $old_value );
		$new_url = $normalize_social_url( $url );
		$did_change = $new_url !== $old_url;
		$is_valid_instagram_url = function( $value ) {
			$parsed = wp_parse_url( $value );
			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return false;
			}
			$host = strtolower( (string) $parsed['host'] );
			$host = preg_replace( '/^www\./', '', $host );
			if ( $host !== 'instagram.com' ) {
				return false;
			}
			$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
			if ( $path === '' ) {
				return false;
			}
			$segments = array_filter( explode( '/', $path ) );
			if ( count( $segments ) !== 1 ) {
				return false;
			}
			$handle = strtolower( (string) $segments[0] );
			$reserved = [ 'p', 'reel', 'reels', 'stories', 'explore', 'tv', 'accounts', 'about', 'developer', 'directory', 'tags', 'locations' ];
			if ( in_array( $handle, $reserved, true ) ) {
				return false;
			}
			return true;
		};
		if ( $social_key === 'instagram' && $new_url && ! $is_valid_instagram_url( $new_url ) ) {
			wp_send_json_error( [ 'message' => 'Please enter a valid Instagram profile URL.' ], 400 );
		}
		
		// Get current profile settings
		$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
		if ( ! is_array( $profile_settings ) ) {
			$profile_settings = [];
		}
		if ( ! isset( $profile_settings['social'] ) || ! is_array( $profile_settings['social'] ) ) {
			$profile_settings['social'] = [];
		}
		
		// Save or delete URL
		if ( empty( $url ) ) {
			unset( $profile_settings['social'][ $social_key ] );
		} else {
			$profile_settings['social'][ $social_key ] = $url;
		}
		
		update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );

		if ( $did_change ) {
			update_user_meta( $user_id, 'tm_social_active_fetch_platform', $social_key );
			update_user_meta( $user_id, 'tm_social_active_fetch_until', time() + ( 20 * MINUTE_IN_SECONDS ) );
			update_user_meta( $user_id, 'tm_social_fetch_pending_' . $social_key, time() );
			switch ( $social_key ) {
				case 'instagram':
					delete_user_meta( $user_id, 'tm_social_metrics_instagram' );
					delete_user_meta( $user_id, 'tm_social_metrics_instagram_raw' );
					delete_user_meta( $user_id, 'tm_social_metrics_instagram_snapshot_id' );
					delete_user_meta( $user_id, 'tm_social_metrics_instagram_snapshot_attempts' );
					delete_user_meta( $user_id, 'tm_social_metrics_instagram_last_fetch' );
					delete_user_meta( $user_id, 'tm_social_fetch_started_instagram' );
					if ( ! empty( $new_url ) && function_exists( 'tm_queue_instagram_metrics_refresh' ) ) {
						tm_queue_instagram_metrics_refresh( $user_id, $new_url );
						update_user_meta( $user_id, 'tm_social_metrics_instagram_last_fetch', current_time( 'mysql' ) );
						update_user_meta( $user_id, 'tm_social_fetch_started_instagram', time() );
					}
					break;
				case 'youtube':
					delete_user_meta( $user_id, 'tm_social_metrics_youtube_snapshot_id' );
					delete_user_meta( $user_id, 'tm_social_metrics_youtube_snapshot_attempts' );
					delete_user_meta( $user_id, 'tm_social_metrics_youtube_last_fetch' );
					if ( ! empty( $new_url ) && function_exists( 'tm_queue_youtube_metrics_refresh' ) ) {
						tm_queue_youtube_metrics_refresh( $user_id, $new_url );
						update_user_meta( $user_id, 'tm_social_metrics_youtube_last_fetch', current_time( 'mysql' ) );
					}
					break;
				case 'facebook':
					delete_user_meta( $user_id, 'tm_social_metrics_facebook' );
					delete_user_meta( $user_id, 'tm_social_metrics_facebook_raw' );
					delete_user_meta( $user_id, 'tm_social_metrics_facebook_snapshot_id' );
					delete_user_meta( $user_id, 'tm_social_metrics_facebook_snapshot_attempts' );
					delete_user_meta( $user_id, 'tm_social_metrics_facebook_last_fetch' );
					if ( ! empty( $new_url ) && function_exists( 'tm_queue_facebook_metrics_refresh' ) ) {
						tm_queue_facebook_metrics_refresh( $user_id, $new_url );
						update_user_meta( $user_id, 'tm_social_metrics_facebook_last_fetch', current_time( 'mysql' ) );
					}
					break;
				case 'linkedin':
					delete_user_meta( $user_id, 'tm_social_metrics_linkedin_snapshot_id' );
					delete_user_meta( $user_id, 'tm_social_metrics_linkedin_snapshot_attempts' );
					delete_user_meta( $user_id, 'tm_social_metrics_linkedin_last_fetch' );
					if ( ! empty( $new_url ) && function_exists( 'tm_queue_linkedin_metrics_refresh' ) ) {
						tm_queue_linkedin_metrics_refresh( $user_id, $new_url );
						update_user_meta( $user_id, 'tm_social_metrics_linkedin_last_fetch', current_time( 'mysql' ) );
					}
					break;
			}
				if ( ! empty( $new_url ) ) {
					tm_schedule_growth_plan( $user_id );
				}
		}
		
		// Log admin changes
		tm_log_admin_vendor_edit( $user_id, $field, 'updated', $old_value, $url );
		
		wp_send_json_success( [
			'field' => $field,
			'value' => $url,
			'message' => 'Social URL saved successfully'
		] );
		return;
	}

	// Store categories are taxonomy terms on the vendor
	if ( $field === 'store_categories' ) {
		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $value_raw ) ) ) );

		if ( ! taxonomy_exists( 'store_category' ) ) {
			wp_send_json_error( [ 'message' => 'Store categories taxonomy missing' ], 500 );
		}

		$result = wp_set_object_terms( $user_id, $term_ids, 'store_category', false );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
		if ( ! is_array( $profile_settings ) ) {
			$profile_settings = [];
		}
		$profile_settings['categories'] = $term_ids;
		$profile_settings['dokan_category'] = $term_ids;
		update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );

		update_user_meta( $user_id, 'dokan_store_categories', $term_ids );

		// Log admin changes
		tm_log_admin_vendor_edit( $user_id, $field, 'updated', $old_value, $term_ids );

		wp_send_json_success( [
			'field' => $field,
			'value' => $term_ids,
			'message' => 'Categories updated successfully'
		] );
		return;
	}
	
	// Save regular fields
	if ( is_array( $value_raw ) ) {
		$value = array_map( 'sanitize_text_field', $value_raw );
		$value = array_values( array_filter( $value, 'strlen' ) );
		if ( empty( $value ) ) {
			delete_user_meta( $user_id, $field );
		} else {
			update_user_meta( $user_id, $field, $value );
		}
	} else {
		$value = sanitize_text_field( $value_raw );
		update_user_meta( $user_id, $field, $value );
	}
	
	// Log admin changes
	tm_log_admin_vendor_edit( $user_id, $field, 'updated', $old_value, is_array( $value_raw ) ? $value : $value );
	
	wp_send_json_success( [
		'field' => $field,
		'value' => is_array( $value_raw ) ? $value : $value,
		'message' => 'Saved successfully'
	] );
} );

/**
 * AJAX Handler: Fetch social metrics status for live updates on store page
 */
add_action( 'wp_ajax_tm_social_metrics_status', function() {
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );

	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	$platform = isset( $_POST['platform'] ) ? sanitize_text_field( $_POST['platform'] ) : '';
	if ( ! $vendor_id || ! $platform ) {
		wp_send_json_error( [ 'message' => 'Missing vendor or platform.' ], 400 );
	}
	if ( ! tm_can_edit_vendor_profile( $vendor_id ) ) {
		wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
	}

	$profiles = tm_get_vendor_social_profiles( $vendor_id );
	$url = '';
	$platform_key = strtolower( $platform );
	if ( 'facebook' === $platform_key ) {
		$url = ! empty( $profiles['fb'] ) ? $profiles['fb'] : ( $profiles['facebook'] ?? '' );
	} elseif ( 'linkedin' === $platform_key ) {
		$url = ! empty( $profiles['linkedin'] ) ? $profiles['linkedin'] : ( $profiles['linked_in'] ?? '' );
	} else {
		$url = $profiles[ $platform_key ] ?? '';
	}

	$map_social_error = function( $error ) {
		$error_lower = strtolower( (string) $error );
		if ( strpos( $error_lower, 'bright data' ) !== false ) {
			return 'error';
		}
		if ( strpos( $error_lower, 'dead_page' ) !== false
			|| strpos( $error_lower, 'content isn\'t available' ) !== false
			|| strpos( $error_lower, 'content isn\'t available right now' ) !== false
		) {
			return 'profile not public';
		}
		if ( strpos( $error_lower, 'request is still in progress' ) !== false
			|| strpos( $error_lower, 'monitor snapshot endpoint' ) !== false
			|| strpos( $error_lower, 'download snapshot endpoint' ) !== false
			|| strpos( $error_lower, 'snapshot is not ready yet' ) !== false
		) {
			return 'in progress';
		}
		if ( strpos( $error_lower, 'snapshot not ready' ) !== false ) {
			return 'in progress';
		}
		if ( strpos( $error_lower, 'unexpected response payload' ) !== false ) {
			return 'profile not found or invalid URL';
		}
		if ( strpos( $error_lower, 'no instagram data returned' ) !== false
			|| strpos( $error_lower, 'no youtube data returned' ) !== false
			|| strpos( $error_lower, 'no facebook data returned' ) !== false
		) {
			return 'profile not found or invalid URL';
		}
		if ( strpos( $error_lower, 'missing' ) !== false && strpos( $error_lower, 'url' ) !== false ) {
			return 'missing URL';
		}
		if ( strpos( $error_lower, 'wp http error' ) !== false
			|| strpos( $error_lower, 'non-200 response' ) !== false
			|| strpos( $error_lower, 'invalid json response' ) !== false
		) {
			return 'network error. please try again';
		}
		return $error;
	};

	$extract_error = function( $raw ) use ( $map_social_error ) {
		if ( ! is_array( $raw ) ) {
			return '';
		}
		if ( ! empty( $raw['error'] ) ) {
			$error = is_string( $raw['error'] ) ? $raw['error'] : 'request error';
			if ( ! empty( $raw['message'] ) ) {
				$error .= ': ' . (string) $raw['message'];
			}
			return $map_social_error( $error );
		}
		if ( ! empty( $raw['error_code'] ) ) {
			return $map_social_error( (string) $raw['error_code'] );
		}
		if ( ! empty( $raw['message'] ) ) {
			return $map_social_error( (string) $raw['message'] );
		}
		return '';
	};

	$format_status = function( $last_fetch, $updated_at, $snapshot_id, $raw ) use ( $extract_error ) {
		if ( $snapshot_id ) {
			return 'fetching data... may take few minutes';
		}
		$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
		$updated_ts = $updated_at ? strtotime( $updated_at ) : 0;
		if ( $last_fetch_ts && ( ! $updated_ts || $updated_ts < $last_fetch_ts ) ) {
			$error = $extract_error( $raw );
			if ( $error !== '' && $error !== 'in progress' ) {
				return 'fetch failed: ' . $error;
			}
			return 'fetching data... may take few minutes';
		}
		if ( $updated_ts ) {
			return 'last fetched ' . date_i18n( 'M j, Y g:ia', $updated_ts );
		}
		return '';
	};

	$get_state = function( $last_fetch, $updated_at, $snapshot_id, $raw ) use ( $extract_error ) {
		if ( $snapshot_id ) {
			return [ 'fetching' => true, 'error' => false ];
		}
		$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
		$updated_ts = $updated_at ? strtotime( $updated_at ) : 0;
		if ( $last_fetch_ts && ( ! $updated_ts || $updated_ts < $last_fetch_ts ) ) {
			$error = $extract_error( $raw );
			if ( $error !== '' && $error !== 'in progress' ) {
				return [ 'fetching' => false, 'error' => true ];
			}
			return [ 'fetching' => true, 'error' => false ];
		}
		return [ 'fetching' => false, 'error' => false ];
	};

	$metrics = [];
	$updated_at = '';
	$last_fetch = '';
	$snapshot_id = '';
	$raw = null;
	$stats_hidden = false;
	$override_status_text = null;
	$override_state = null;
	$pending_key = 'tm_social_fetch_pending_' . $platform_key;
	$pending_at = (int) get_user_meta( $vendor_id, $pending_key, true );
	if ( $pending_at ) {
		delete_user_meta( $vendor_id, $pending_key );
		if ( $url ) {
			switch ( $platform_key ) {
				case 'youtube':
					if ( function_exists( 'tm_fetch_youtube_metrics' ) ) {
						tm_fetch_youtube_metrics( $vendor_id, $url );
					}
					break;
				case 'instagram':
					if ( function_exists( 'tm_fetch_instagram_metrics' ) ) {
						tm_fetch_instagram_metrics( $vendor_id, $url );
					}
					break;
				case 'linkedin':
					if ( function_exists( 'tm_fetch_linkedin_metrics' ) ) {
						tm_fetch_linkedin_metrics( $vendor_id, $url );
					}
					break;
				case 'facebook':
					// Facebook fetch can be slow; rely on background queue.
					break;
			}
		}
	}

	switch ( $platform_key ) {
		case 'youtube':
			$metrics_raw = get_user_meta( $vendor_id, 'tm_social_metrics_youtube', true );
			$last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_last_fetch', true );
			$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_snapshot_id', true );
			$raw = get_user_meta( $vendor_id, 'tm_social_metrics_youtube_raw', true );
			if ( is_array( $metrics_raw ) ) {
				$updated_at = ! empty( $metrics_raw['updated_at'] ) ? $metrics_raw['updated_at'] : '';
				$metrics = [
					'subscribers'  => array_key_exists( 'subscribers', $metrics_raw ) ? (int) $metrics_raw['subscribers'] : null,
					'avg_views'    => array_key_exists( 'avg_views', $metrics_raw ) ? (int) $metrics_raw['avg_views'] : null,
					'avg_reactions'=> array_key_exists( 'avg_reactions', $metrics_raw ) ? (int) $metrics_raw['avg_reactions'] : null,
				];
			}
			break;
		case 'instagram':
			$metrics_raw = get_user_meta( $vendor_id, 'tm_social_metrics_instagram', true );
			$last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_last_fetch', true );
			$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id', true );
			$raw = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', true );
			if ( $snapshot_id ) {
				$poll_last = (int) get_user_meta( $vendor_id, 'tm_social_snapshot_poll_instagram_last', true );
				$started_at = (int) get_user_meta( $vendor_id, 'tm_social_fetch_started_instagram', true );
				if ( ! $started_at ) {
					$started_at = time();
					update_user_meta( $vendor_id, 'tm_social_fetch_started_instagram', $started_at );
				}
				if ( time() - $poll_last > 20 && function_exists( 'tm_fetch_instagram_snapshot' ) ) {
					update_user_meta( $vendor_id, 'tm_social_snapshot_poll_instagram_last', time() );
					tm_fetch_instagram_snapshot( $vendor_id, $snapshot_id, $url );
					$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_snapshot_id', true );
					$raw = get_user_meta( $vendor_id, 'tm_social_metrics_instagram_raw', true );
				}
				if ( $started_at && ( time() - $started_at ) > 180 ) {
					$override_status_text = 'fetch failed: timeout';
					$override_state = [ 'fetching' => false, 'error' => true ];
				}
			}
			if ( is_array( $metrics_raw ) ) {
				$extract_instagram_handle = function( $value ) {
					$value = trim( (string) $value );
					if ( $value === '' ) {
						return '';
					}
					if ( ! preg_match( '#^https?://#i', $value ) ) {
						$value = 'https://' . ltrim( $value, '/' );
					}
					$parsed = wp_parse_url( $value );
					if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
						return '';
					}
					$host = strtolower( (string) $parsed['host'] );
					$host = preg_replace( '/^www\./', '', $host );
					if ( $host !== 'instagram.com' ) {
						return '';
					}
					$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
					if ( $path === '' ) {
						return '';
					}
					$segments = array_values( array_filter( explode( '/', $path ) ) );
					return isset( $segments[0] ) ? strtolower( (string) $segments[0] ) : '';
				};
				$current_handle = $extract_instagram_handle( $url );
				$metrics_handle = $extract_instagram_handle( isset( $metrics_raw['url'] ) ? $metrics_raw['url'] : '' );
				$updated_at = ! empty( $metrics_raw['updated_at'] ) ? $metrics_raw['updated_at'] : '';
				if ( $current_handle && $metrics_handle && $current_handle === $metrics_handle ) {
					$metrics = [
						'followers'    => array_key_exists( 'followers', $metrics_raw ) ? (int) $metrics_raw['followers'] : null,
						'avg_reactions'=> array_key_exists( 'avg_reactions', $metrics_raw ) ? (int) $metrics_raw['avg_reactions'] : null,
						'avg_comments' => array_key_exists( 'avg_comments', $metrics_raw ) ? (int) $metrics_raw['avg_comments'] : null,
					];
				}
			}
			break;
		case 'facebook':
			$metrics_raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook', true );
			$last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_last_fetch', true );
			$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id', true );
			$raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', true );
			if ( empty( $url ) ) {
				$last_fetch = '';
				$snapshot_id = '';
				$raw = null;
				break;
			}
			if ( $snapshot_id ) {
				$poll_last = (int) get_user_meta( $vendor_id, 'tm_social_snapshot_poll_facebook_last', true );
				$started_at = (int) get_user_meta( $vendor_id, 'tm_social_fetch_started_facebook', true );
				if ( ! $started_at ) {
					$started_at = time();
					update_user_meta( $vendor_id, 'tm_social_fetch_started_facebook', $started_at );
				}
				if ( time() - $poll_last > 20 && function_exists( 'tm_fetch_facebook_snapshot' ) ) {
					update_user_meta( $vendor_id, 'tm_social_snapshot_poll_facebook_last', time() );
					tm_fetch_facebook_snapshot( $vendor_id, $snapshot_id, $url );
					$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_snapshot_id', true );
					$raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook_raw', true );
					$metrics_raw = get_user_meta( $vendor_id, 'tm_social_metrics_facebook', true );
				}
				if ( $started_at && ( time() - $started_at ) > 300 ) {
					$override_status_text = 'fetch failed: timeout';
					$override_state = [ 'fetching' => false, 'error' => true ];
				}
			}
			$extract_facebook_identifier = function( $value ) {
				$value = trim( (string) $value );
				if ( $value === '' ) {
					return '';
				}
				if ( ! preg_match( '#^https?://#i', $value ) ) {
					$value = 'https://' . ltrim( $value, '/' );
				}
				$parsed = wp_parse_url( $value );
				if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
					return '';
				}
				$host = strtolower( (string) $parsed['host'] );
				$host = preg_replace( '/^www\./', '', $host );
				if ( $host !== 'facebook.com' ) {
					return '';
				}
				$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
				if ( strtolower( $path ) === 'profile.php' && ! empty( $parsed['query'] ) ) {
					parse_str( (string) $parsed['query'], $query_args );
					return ! empty( $query_args['id'] ) ? 'id:' . (string) $query_args['id'] : '';
				}
				$segments = array_values( array_filter( explode( '/', $path ) ) );
				return isset( $segments[0] ) ? strtolower( (string) $segments[0] ) : '';
			};
			$current_identifier = $extract_facebook_identifier( $url );
			if ( is_array( $metrics_raw ) ) {
				$metrics_identifier = $extract_facebook_identifier( isset( $metrics_raw['url'] ) ? $metrics_raw['url'] : '' );
				if ( $current_identifier && $metrics_identifier && $current_identifier === $metrics_identifier ) {
					$updated_at = ! empty( $metrics_raw['updated_at'] ) ? $metrics_raw['updated_at'] : '';
					$metrics = [
						'followers'    => array_key_exists( 'page_followers', $metrics_raw ) ? tm_parse_social_number( $metrics_raw['page_followers'] ) : null,
						'avg_views'    => array_key_exists( 'avg_views', $metrics_raw ) ? tm_parse_social_number( $metrics_raw['avg_views'] ) : null,
						'avg_reactions'=> array_key_exists( 'avg_reactions', $metrics_raw ) ? tm_parse_social_number( $metrics_raw['avg_reactions'] ) : null,
					];
					$raw_error = $extract_error( $raw );
					if ( $updated_at && ! $raw_error
						&& ( $metrics['followers'] === 0 || $metrics['followers'] === null )
						&& ( $metrics['avg_views'] === 0 || $metrics['avg_views'] === null )
						&& ( $metrics['avg_reactions'] === 0 || $metrics['avg_reactions'] === null )
					) {
						$stats_hidden = true;
					}
				}
			}
			break;
		case 'linkedin':
			$metrics_raw = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin', true );
			$last_fetch = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_last_fetch', true );
			$snapshot_id = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_snapshot_id', true );
			$raw = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin_raw', true );
			if ( is_array( $metrics_raw ) ) {
				$updated_at = ! empty( $metrics_raw['updated_at'] ) ? $metrics_raw['updated_at'] : '';
				$followers = array_key_exists( 'followers', $metrics_raw ) ? (int) $metrics_raw['followers'] : null;
				$connections = array_key_exists( 'connections', $metrics_raw ) ? (int) $metrics_raw['connections'] : null;
				$metrics = [
					'followers'    => $followers ? $followers : $connections,
					'connections'  => $connections,
					'avg_reactions'=> array_key_exists( 'avg_reactions', $metrics_raw ) ? (int) $metrics_raw['avg_reactions'] : null,
				];
			}
			break;
		default:
			wp_send_json_error( [ 'message' => 'Unsupported platform.' ], 400 );
	}

	$status_text = $format_status( $last_fetch, $updated_at, $snapshot_id, $raw );
	$state = $get_state( $last_fetch, $updated_at, $snapshot_id, $raw );
	if ( $platform_key === 'instagram' && ! $snapshot_id && $updated_at ) {
		$state = [ 'fetching' => false, 'error' => false ];
	}
	if ( $platform_key === 'facebook' && $state['error'] ) {
		$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
		if ( $last_fetch_ts && ( time() - $last_fetch_ts ) < 180 ) {
			$state = [ 'fetching' => true, 'error' => false ];
			$status_text = 'fetching data... may take few minutes';
		}
	}
	if ( $platform_key === 'facebook' ) {
		$fb_error = $extract_error( $raw );
		$last_fetch_ts = $last_fetch ? strtotime( $last_fetch ) : 0;
		$retry_count = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_count', true );
		$retry_last = (int) get_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_last', true );
		if ( $fb_error === 'network error. please try again' && $url && $last_fetch_ts ) {
			$retry_window = 10 * MINUTE_IN_SECONDS;
			if ( ( time() - $last_fetch_ts ) < $retry_window ) {
				if ( $retry_count < 5 && ( time() - $retry_last ) > 30 ) {
					$retry_count++;
					update_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_count', $retry_count );
					update_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_last', time() );
					if ( function_exists( 'tm_queue_facebook_metrics_refresh' ) ) {
						tm_queue_facebook_metrics_refresh( $vendor_id, $url );
					}
				}
				$state = [ 'fetching' => true, 'error' => false ];
				$status_text = 'fetching data... may take few minutes';
			}
		} elseif ( $fb_error === '' && $updated_at ) {
			delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_count' );
			delete_user_meta( $vendor_id, 'tm_social_metrics_facebook_retry_last' );
		}
		if ( $fb_error && $fb_error !== 'network error. please try again' && $fb_error !== 'in progress' ) {
			$override_status_text = 'fetch failed: ' . $fb_error;
			$override_state = [ 'fetching' => false, 'error' => true ];
			$stats_hidden = false;
			$metrics = [];
		} elseif ( $fb_error === 'in progress' ) {
			$override_status_text = 'fetching data... may take few minutes';
			$override_state = [ 'fetching' => true, 'error' => false ];
		} elseif ( $stats_hidden ) {
			$override_status_text = 'stats are hidden on this page';
			$override_state = [ 'fetching' => false, 'error' => false ];
		}
	}
	if ( $override_status_text !== null ) {
		$status_text = $override_status_text;
	}
	if ( $override_state !== null ) {
		$state = $override_state;
	}

	wp_send_json_success( [
		'platform'    => $platform_key,
		'has_url'     => ! empty( $url ),
		'status_text' => $status_text,
		'fetching'    => $state['fetching'],
		'error'       => $state['error'],
		'metrics'     => $metrics,
		'stats_hidden'=> $stats_hidden,
	] );
} );

/**
 * AJAX Handler: Dump social debug data for console inspection
 */
add_action( 'wp_ajax_tm_social_debug_dump', function() {
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );

	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	$platform = isset( $_POST['platform'] ) ? sanitize_text_field( $_POST['platform'] ) : '';
	if ( ! $vendor_id || ! $platform ) {
		wp_send_json_error( [ 'message' => 'Missing vendor or platform.' ], 400 );
	}
	if ( ! tm_can_edit_vendor_profile( $vendor_id ) ) {
		wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
	}

	$profiles = tm_get_vendor_social_profiles( $vendor_id );
	$platform_key = strtolower( $platform );
	$url = '';
	if ( 'facebook' === $platform_key ) {
		$url = ! empty( $profiles['fb'] ) ? $profiles['fb'] : ( $profiles['facebook'] ?? '' );
	} elseif ( 'linkedin' === $platform_key ) {
		$url = ! empty( $profiles['linkedin'] ) ? $profiles['linkedin'] : ( $profiles['linked_in'] ?? '' );
	} else {
		$url = $profiles[ $platform_key ] ?? '';
	}

	$extract_facebook_identifier = function( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}
		$parsed = wp_parse_url( $value );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parsed['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		if ( $host !== 'facebook.com' ) {
			return '';
		}
		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( strtolower( $path ) === 'profile.php' && ! empty( $parsed['query'] ) ) {
			parse_str( (string) $parsed['query'], $query_args );
			return ! empty( $query_args['id'] ) ? 'id:' . (string) $query_args['id'] : '';
		}
		$segments = array_values( array_filter( explode( '/', $path ) ) );
		return isset( $segments[0] ) ? strtolower( (string) $segments[0] ) : '';
	};

	$response = [
		'platform' => $platform_key,
		'url' => $url,
		'profiles' => $profiles,
		'active_fetch_platform' => (string) get_user_meta( $vendor_id, 'tm_social_active_fetch_platform', true ),
		'active_fetch_until' => (string) get_user_meta( $vendor_id, 'tm_social_active_fetch_until', true ),
		'pending' => (string) get_user_meta( $vendor_id, 'tm_social_fetch_pending_' . $platform_key, true ),
		'last_fetch' => (string) get_user_meta( $vendor_id, 'tm_social_metrics_' . $platform_key . '_last_fetch', true ),
		'snapshot_id' => (string) get_user_meta( $vendor_id, 'tm_social_metrics_' . $platform_key . '_snapshot_id', true ),
		'snapshot_attempts' => (string) get_user_meta( $vendor_id, 'tm_social_metrics_' . $platform_key . '_snapshot_attempts', true ),
		'metrics' => get_user_meta( $vendor_id, 'tm_social_metrics_' . $platform_key, true ),
		'raw' => get_user_meta( $vendor_id, 'tm_social_metrics_' . $platform_key . '_raw', true ),
	];

	if ( $platform_key === 'facebook' ) {
		$metrics = is_array( $response['metrics'] ) ? $response['metrics'] : [];
		$raw = is_array( $response['raw'] ) ? $response['raw'] : [];
		$raw_posts = isset( $raw['raw_response'] ) && is_array( $raw['raw_response'] ) ? $raw['raw_response'] : $raw;
		$raw_url = '';
		if ( is_array( $raw_posts ) && ! empty( $raw_posts[0]['page_url'] ) ) {
			$raw_url = (string) $raw_posts[0]['page_url'];
		}
		$response['facebook_identifiers'] = [
			'input' => $extract_facebook_identifier( $url ),
			'metrics' => $extract_facebook_identifier( isset( $metrics['url'] ) ? $metrics['url'] : '' ),
			'raw' => $extract_facebook_identifier( $raw_url ),
			'raw_page_url' => $raw_url,
		];
	}

	wp_send_json_success( $response );
} );

/**
 * AJAX Handler: Update Vendor Avatar
 * Uses WordPress media library for upload
 */
add_action( 'wp_ajax_vendor_update_avatar', function() {
	// Verify nonce
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );
	
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$avatar_id = isset( $_POST['avatar_id'] ) ? absint( $_POST['avatar_id'] ) : 0;
	
	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	// Verify attachment exists and is an image
	if ( ! $avatar_id || ! wp_attachment_is_image( $avatar_id ) ) {
		wp_send_json_error( ['message' => 'Invalid image'], 400 );
	}
	
	// Save to dokan profile settings
	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}
	
	$profile_settings['gravatar'] = $avatar_id;
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );
	
	// Get new avatar URL
	$avatar_url = wp_get_attachment_image_url( $avatar_id, 'full' );
	
	wp_send_json_success( [
		'avatar_url' => $avatar_url,
		'message' => 'Avatar updated successfully'
	] );
} );

/**
 * AJAX Handler: Update Vendor Banner
 * Uses WordPress media library for upload
 */
add_action( 'wp_ajax_vendor_update_banner', function() {
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$banner_id = isset( $_POST['banner_id'] ) ? absint( $_POST['banner_id'] ) : 0;

	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
	}

	if ( ! $banner_id || ! wp_attachment_is_image( $banner_id ) ) {
		wp_send_json_error( [ 'message' => 'Invalid image' ], 400 );
	}

	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}

	$profile_settings['banner'] = $banner_id;
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );

	$banner_url = wp_get_attachment_image_url( $banner_id, 'full' );

	wp_send_json_success( [
		'banner_url' => $banner_url,
		'message' => 'Banner updated successfully'
	] );
} );

/**
 * AJAX Handler: Update Vendor Media Playlist
 * Persists media shortcodes into vendor biography (one playlist per type).
 */
add_action( 'wp_ajax_vendor_update_media_playlist', function() {
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$playlist_type = isset( $_POST['playlist_type'] ) ? strtolower( sanitize_text_field( $_POST['playlist_type'] ) ) : '';
	$ids_raw = isset( $_POST['ids'] ) ? $_POST['ids'] : [];
	$clear = ! empty( $_POST['clear'] );

	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
	}

	if ( ! in_array( $playlist_type, [ 'image', 'video', 'audio' ], true ) ) {
		wp_send_json_error( [ 'message' => 'Invalid playlist type' ], 400 );
	}

	$ids_list = [];
	if ( is_array( $ids_raw ) ) {
		$ids_list = $ids_raw;
	} elseif ( is_string( $ids_raw ) ) {
		$ids_list = explode( ',', $ids_raw );
	}

	$ids = array_values( array_filter( array_map( 'absint', $ids_list ) ) );
	if ( empty( $ids ) && ! $clear ) {
		wp_send_json_error( [ 'message' => 'No media selected' ], 400 );
	}

	$shortcode = '';
	if ( ! empty( $ids ) ) {
		$ids_csv = implode( ',', $ids );
		$shortcode = $playlist_type === 'image'
			? '[gallery ids="' . $ids_csv . '"]'
			: '[playlist type="' . $playlist_type . '" ids="' . $ids_csv . '"]';
	}

	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}

	$bio = '';
	if ( ! empty( $profile_settings['vendor_biography'] ) ) {
		$bio = (string) $profile_settings['vendor_biography'];
	} else {
		$bio = (string) get_user_meta( $user_id, 'vendor_biography', true );
	}

	$shortcode_pattern = get_shortcode_regex( [ 'gallery', 'playlist' ] );
	if ( $shortcode_pattern && $bio !== '' ) {
		$bio = preg_replace_callback(
			'/' . $shortcode_pattern . '/s',
			function( $match ) use ( $playlist_type ) {
				$tag = isset( $match[2] ) ? (string) $match[2] : '';
				$atts_raw = isset( $match[3] ) ? (string) $match[3] : '';
				$atts = shortcode_parse_atts( $atts_raw );

				if ( $playlist_type === 'image' && $tag === 'gallery' ) {
					return '';
				}

				if ( $tag === 'playlist' ) {
					$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : 'audio';
					if ( $playlist_type === $type ) {
						return '';
					}
				}

				return $match[0];
			},
			$bio
		);
	}

	if ( $bio !== '' ) {
		$bio = preg_replace_callback(
			'/\s*data-wp-media="([^"]*)"/i',
			function( $match ) use ( $playlist_type ) {
				$decoded = urldecode( html_entity_decode( (string) $match[1] ) );
				if ( $playlist_type === 'image' && stripos( $decoded, '[gallery' ) !== false ) {
					return '';
				}
				if ( stripos( $decoded, '[playlist' ) !== false ) {
					$atts = shortcode_parse_atts( $decoded );
					$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : 'audio';
					if ( $playlist_type === $type ) {
						return '';
					}
				}
				return $match[0];
			},
			$bio
		);
	}

	$bio = trim( (string) $bio );
	if ( $shortcode !== '' ) {
		$bio = $bio === '' ? $shortcode : $bio . "\n\n" . $shortcode;
	}

	$profile_settings['vendor_biography'] = $bio;
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );
	update_user_meta( $user_id, 'vendor_biography', $bio );

	wp_send_json_success( [
		'vendorMedia' => tm_get_vendor_media_playlist( $user_id ),
		'message' => $clear ? 'Playlist cleared successfully' : 'Playlist updated successfully',
	] );
} );

/**
 * AJAX Handler: Update Vendor Store Name
 */
add_action( 'wp_ajax_vendor_update_store_name', function() {
	// Verify nonce
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );
	
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$store_name = isset( $_POST['store_name'] ) ? sanitize_text_field( $_POST['store_name'] ) : '';
	
	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	if ( empty( $store_name ) ) {
		wp_send_json_error( ['message' => 'Name cannot be empty'], 400 );
	}
	
	// Capture old value for logging
	$old_profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	$old_store_name = isset( $old_profile_settings['store_name'] ) ? $old_profile_settings['store_name'] : get_user_meta( $user_id, 'dokan_store_name', true );
	
	// Update store name in dokan profile settings
	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}
	
	$profile_settings['store_name'] = $store_name;
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );
	
	// Also update dokan_store_name meta directly
	update_user_meta( $user_id, 'dokan_store_name', $store_name );
	
	// Log admin action if applicable
	tm_log_admin_vendor_edit( $user_id, 'store_name', 'updated', $old_store_name, $store_name );
	
	wp_send_json_success( [
		'store_name' => $store_name,
		'message' => 'Name updated successfully'
	] );
} );

/**
 * AJAX Handler: Update Vendor Contact Info
 */
if ( ! function_exists( 'tm_sanitize_phone_value' ) ) {
	function tm_sanitize_phone_value( $value ) {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/[^0-9+()\s.-]/', '', $value );
		return trim( $value );
	}
}

add_action( 'wp_ajax_vendor_update_contact_info', function() {
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$has_emails = array_key_exists( 'contact_emails', $_POST );
	$has_phones = array_key_exists( 'contact_phones', $_POST );
	$contact_emails_raw = $has_emails ? (array) wp_unslash( $_POST['contact_emails'] ) : [];
	$contact_phones_raw = $has_phones ? (array) wp_unslash( $_POST['contact_phones'] ) : [];
	$contact_email_main = $has_emails && isset( $_POST['contact_email_main'] ) ? sanitize_email( wp_unslash( $_POST['contact_email_main'] ) ) : '';
	$contact_phone_main = $has_phones && isset( $_POST['contact_phone_main'] ) ? tm_sanitize_phone_value( wp_unslash( $_POST['contact_phone_main'] ) ) : '';

	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	// Capture old values for logging before making changes
	$old_contact_emails = get_user_meta( $user_id, 'tm_contact_emails', true );
	$old_contact_phones = get_user_meta( $user_id, 'tm_contact_phones', true );
	$old_email_main = get_user_meta( $user_id, 'tm_contact_email_main', true );
	$old_phone_main = get_user_meta( $user_id, 'tm_contact_phone_main', true );

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
	}

	$contact_emails = [];
	if ( $has_emails ) {
		foreach ( $contact_emails_raw as $email ) {
			$email = sanitize_email( $email );
			if ( $email && is_email( $email ) ) {
				$contact_emails[] = $email;
			}
		}
		$contact_emails = array_values( array_unique( $contact_emails ) );
		$contact_emails = array_slice( $contact_emails, 0, 3 );
		if ( $contact_email_main && ! in_array( $contact_email_main, $contact_emails, true ) ) {
			$contact_email_main = '';
		}
		if ( ! $contact_email_main && ! empty( $contact_emails ) ) {
			$contact_email_main = $contact_emails[0];
		}
		if ( empty( $contact_emails ) ) {
			$contact_email_main = '';
		}
		update_user_meta( $user_id, 'tm_contact_emails', $contact_emails );
		update_user_meta( $user_id, 'tm_contact_email_main', $contact_email_main );
		update_user_meta( $user_id, 'tm_contact_email', $contact_email_main );
	}

	$contact_phones = [];
	if ( $has_phones ) {
		foreach ( $contact_phones_raw as $phone ) {
			$phone = tm_sanitize_phone_value( $phone );
			if ( $phone ) {
				$contact_phones[] = $phone;
			}
		}
		$contact_phones = array_values( array_unique( $contact_phones ) );
		$contact_phones = array_slice( $contact_phones, 0, 3 );
		if ( $contact_phone_main && ! in_array( $contact_phone_main, $contact_phones, true ) ) {
			$contact_phone_main = '';
		}
		if ( ! $contact_phone_main && ! empty( $contact_phones ) ) {
			$contact_phone_main = $contact_phones[0];
		}
		if ( empty( $contact_phones ) ) {
			$contact_phone_main = '';
		}
		update_user_meta( $user_id, 'tm_contact_phones', $contact_phones );
		update_user_meta( $user_id, 'tm_contact_phone_main', $contact_phone_main );
	}

	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}
	if ( $has_phones ) {
		$profile_settings['phone'] = $contact_phone_main;
		update_user_meta( $user_id, 'dokan_store_phone', $contact_phone_main );
	}
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );

	$contact_emails_saved = get_user_meta( $user_id, 'tm_contact_emails', true );
	$contact_email_main_saved = get_user_meta( $user_id, 'tm_contact_email_main', true );
	$contact_phones_saved = get_user_meta( $user_id, 'tm_contact_phones', true );
	$contact_phone_main_saved = get_user_meta( $user_id, 'tm_contact_phone_main', true );

	// Log admin changes for audit purposes
	if ( $has_emails ) {
		tm_log_admin_vendor_edit( $user_id, 'contact_emails', 'updated', $old_contact_emails, $contact_emails_saved );
		if ( $old_email_main !== $contact_email_main_saved ) {
			tm_log_admin_vendor_edit( $user_id, 'contact_email_main', 'updated', $old_email_main, $contact_email_main_saved );
		}
	}
	if ( $has_phones ) {
		tm_log_admin_vendor_edit( $user_id, 'contact_phones', 'updated', $old_contact_phones, $contact_phones_saved );
		if ( $old_phone_main !== $contact_phone_main_saved ) {
			tm_log_admin_vendor_edit( $user_id, 'contact_phone_main', 'updated', $old_phone_main, $contact_phone_main_saved );
		}
	}

	wp_send_json_success( [
		'contact_emails' => is_array( $contact_emails_saved ) ? array_values( $contact_emails_saved ) : [],
		'contact_email_main' => $contact_email_main_saved ? $contact_email_main_saved : '',
		'contact_phones' => is_array( $contact_phones_saved ) ? array_values( $contact_phones_saved ) : [],
		'contact_phone_main' => $contact_phone_main_saved ? $contact_phone_main_saved : '',
		'message' => 'Contact info updated successfully'
	] );
} );

/**
 * AJAX Handler: Update Vendor Location (Mapbox)
 */
add_action( 'wp_ajax_vendor_update_location', function() {
	// Verify nonce
	check_ajax_referer( 'vendor_inline_edit', 'nonce' );
	
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$geo_address = isset( $_POST['geo_address'] ) ? sanitize_text_field( $_POST['geo_address'] ) : '';
	$location_data = isset( $_POST['location_data'] ) ? $_POST['location_data'] : '';
	
	// Enhanced permission check - allows both owners and admins
	if ( ! tm_can_edit_vendor_profile( $user_id ) ) {
		wp_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		wp_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	if ( empty( $geo_address ) ) {
		wp_send_json_error( ['message' => 'Location cannot be empty'], 400 );
	}
	
	// Capture old value for admin change logging
	$old_location = get_user_meta( $user_id, 'dokan_geo_address', true );
	
	// Parse location data if provided (from Mapbox)
	$location_obj = ! empty( $location_data ) ? json_decode( stripslashes( $location_data ), true ) : null;
	
	// Update dokan_geo_address
	update_user_meta( $user_id, 'dokan_geo_address', $geo_address );
	
	// Update dokan profile settings location
	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}
	
	$profile_settings['location'] = $geo_address;
	
	// If we have coordinates from Mapbox, save them
	if ( $location_obj && isset( $location_obj['center'] ) ) {
		$profile_settings['geolocation'] = [
			'latitude' => floatval( $location_obj['center'][1] ),
			'longitude' => floatval( $location_obj['center'][0] )
		];
	}
	
	// Parse address components if available
	if ( $location_obj && isset( $location_obj['context'] ) ) {
		$address = [];
		foreach ( $location_obj['context'] as $component ) {
			if ( strpos( $component['id'], 'place' ) !== false ) {
				$address['city'] = $component['text'];
			} elseif ( strpos( $component['id'], 'region' ) !== false ) {
				$address['state'] = $component['text'];
			} elseif ( strpos( $component['id'], 'country' ) !== false ) {
				$address['country'] = $component['short_code'];
			} elseif ( strpos( $component['id'], 'postcode' ) !== false ) {
				$address['zip'] = $component['text'];
			}
		}
		if ( ! empty( $address ) ) {
			if ( ! isset( $profile_settings['address'] ) || ! is_array( $profile_settings['address'] ) ) {
				$profile_settings['address'] = [];
			}
			$profile_settings['address'] = array_merge( $profile_settings['address'], $address );
		}
	}
	
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );
	
	// Log admin changes
	tm_log_admin_vendor_edit( $user_id, 'geo_location', 'updated', $old_location, $geo_address );
	
	// Get formatted display
	$geo_display = '';
	if ( function_exists( 'tm_get_vendor_geo_location_display' ) ) {
		$geo_display = tm_get_vendor_geo_location_display( $user_id, $profile_settings, $profile_settings['address'] ?? [] );
	}
	
	wp_send_json_success( [
		'geo_address' => $geo_address,
		'geo_display' => $geo_display,
		'message' => 'Location updated successfully'
	] );
} );

/**
 * Scope media library to vendor-owned attachments for front-end media modal.
 * Includes attachments authored by the vendor and those tagged by Dokan meta.
 */
add_filter( 'ajax_query_attachments_args', function( $args ) {
	if ( ! is_user_logged_in() || ! function_exists( 'dokan_is_user_seller' ) ) {
		return $args;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id || ! dokan_is_user_seller( $user_id ) ) {
		return $args;
	}

	// Fetch attachments authored by vendor
	$author_ids = get_posts( [
		'post_type' => 'attachment',
		'fields' => 'ids',
		'posts_per_page' => -1,
		'author' => $user_id,
		'post_status' => 'inherit'
	] );

	// Fetch attachments tagged to vendor by Dokan meta (if used)
	$meta_ids = get_posts( [
		'post_type' => 'attachment',
		'fields' => 'ids',
		'posts_per_page' => -1,
		'post_status' => 'inherit',
		'meta_query' => [
			'relation' => 'OR',
			[ 'key' => '_dokan_vendor_id', 'value' => $user_id, 'compare' => '=' ],
			[ 'key' => 'dokan_vendor_id', 'value' => $user_id, 'compare' => '=' ],
			[ 'key' => '_vendor_id', 'value' => $user_id, 'compare' => '=' ]
		]
	] );

	// Include profile media IDs to ensure banner/avatar show up
	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	$profile_ids = [];
	if ( is_array( $profile_settings ) ) {
		foreach ( [ 'banner', 'gravatar', 'banner_video' ] as $key ) {
			if ( ! empty( $profile_settings[ $key ] ) ) {
				$profile_ids[] = (int) $profile_settings[ $key ];
			}
		}
	}

	$allowed_ids = array_values( array_unique( array_filter( array_merge( (array) $author_ids, (array) $meta_ids, $profile_ids ) ) ) );
	if ( ! empty( $allowed_ids ) ) {
		$args['post__in'] = isset( $args['post__in'] ) && is_array( $args['post__in'] )
			? array_values( array_unique( array_merge( $args['post__in'], $allowed_ids ) ) )
			: $allowed_ids;
		unset( $args['author'] );
	}

	return $args;
} );


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
			console.log('Video upload script loaded');
			
			// Video upload using Dokan's pattern
			var videoFrame;
			$('body').on('click', 'a.dokan-banner-video-drag', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('Video upload button clicked!');
				
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
	$on_store_page = function_exists( 'dokan_is_store_page' ) && dokan_is_store_page();
	if ( ! $on_store_page && ! tm_is_showcase_page() ) {
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

/**
 * Custom Shortcode: Display Vendors Map Only
 * Usage: [vendors_map]
 * Shows all vendors on a map centered on USA without filters or search
 */
add_shortcode( 'vendors_map', function() {
	// Check if Geolocation module is active
	if ( ! function_exists( 'dokan_pro' ) || ! dokan_pro()->module->is_active( 'geolocation' ) ) {
		return '<p>Geolocation module is not active.</p>';
	}
	
	// Get Mapbox API key
	$map_api_source = dokan_get_option( 'map_api_source', 'dokan_appearance', 'google' );
	
	if ( 'mapbox' !== $map_api_source ) {
		return '<p>Please set Map API Source to Mapbox in Dokan settings.</p>';
	}
	
	$mapbox_access_token = dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' );
	
	if ( empty( $mapbox_access_token ) ) {
		return '<p>Please configure Mapbox Access Token in Dokan → Settings → Appearance.</p>';
	}
	
	// Get all vendors with geolocation data
	$args = array(
		'role'       => 'seller',
		'number'     => -1,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key'     => 'dokan_geo_latitude',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'dokan_geo_longitude',
				'compare' => 'EXISTS',
			),
		),
	);
	
	$vendors = get_users( $args );
	
	if ( empty( $vendors ) ) {
		return '<p>No vendors with location data found. Please ensure vendors have set their store location.</p>';
	}
	
	// Build vendor data for map
	$vendor_markers = array();
	foreach ( $vendors as $vendor ) {
		$store_info = dokan_get_store_info( $vendor->ID );
		$latitude   = get_user_meta( $vendor->ID, 'dokan_geo_latitude', true );
		$longitude  = get_user_meta( $vendor->ID, 'dokan_geo_longitude', true );
		
		if ( empty( $latitude ) || empty( $longitude ) ) {
			continue;
		}
		
		$vendor_markers[] = array(
			'id'        => $vendor->ID,
			'name'      => $store_info['store_name'] ?? $vendor->display_name,
			'url'       => dokan_get_store_url( $vendor->ID ),
			'latitude'  => floatval( $latitude ),
			'longitude' => floatval( $longitude ),
			'address'   => get_user_meta( $vendor->ID, 'dokan_geo_address', true ),
			'avatar'    => get_avatar_url( $vendor->ID, array( 'size' => 150 ) ),
		);
	}
	
	// Default center: United States
	$default_lat  = 37.0902;
	$default_lng  = -95.7129;
	$default_zoom = 4;
	
	// Generate unique map ID
	$map_id = 'vendors-map-' . uniqid();
	
	ob_start();
	?>
	<div class="vendors-map-container">
		<div id="<?php echo esc_attr( $map_id ); ?>" style="width: 100%; height: 600px;"></div>
	</div>
	
	<link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
	<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
	
	<style>
		.vendors-map-container {
			margin: 20px 0;
		}
		.mapboxgl-popup-content {
			padding: 15px;
			min-width: 200px;
		}
		.vendor-popup h3 {
			margin: 0 0 10px 0;
			font-size: 16px;
		}
		.vendor-popup img {
			width: 60px;
			height: 60px;
			border-radius: 50%;
			margin-bottom: 10px;
		}
		.vendor-popup p {
			margin: 5px 0;
			font-size: 13px;
			color: #666;
		}
		.vendor-popup a {
			display: inline-block;
			margin-top: 10px;
			padding: 8px 15px;
			background: #D4AF37;
			color: white;
			text-decoration: none;
			border-radius: 3px;
			font-size: 13px;
		}
		.vendor-popup a:hover {
			background: #b8941f;
		}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		mapboxgl.accessToken = '<?php echo esc_js( $mapbox_access_token ); ?>';
		
		var map = new mapboxgl.Map({
			container: '<?php echo esc_js( $map_id ); ?>',
			style: 'mapbox://styles/mapbox/streets-v12',
			center: [<?php echo $default_lng; ?>, <?php echo $default_lat; ?>],
			zoom: <?php echo $default_zoom; ?>
		});
		
		// Add navigation controls
		map.addControl(new mapboxgl.NavigationControl(), 'top-right');
		
		// Add fullscreen control
		map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
		
		var vendors = <?php echo wp_json_encode( $vendor_markers ); ?>;
		
		// Add markers for each vendor
		vendors.forEach(function(vendor) {
			// Create custom marker element
			var el = document.createElement('div');
			el.className = 'vendor-marker';
			el.style.backgroundColor = '#D4AF37';
			el.style.width = '30px';
			el.style.height = '30px';
			el.style.borderRadius = '50%';
			el.style.border = '3px solid white';
			el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.3)';
			el.style.cursor = 'pointer';
			
			// Create popup content
			var popupHTML = '<div class="vendor-popup">' +
				'<img src="' + vendor.avatar + '" alt="' + vendor.name + '" />' +
				'<h3>' + vendor.name + '</h3>';
			
			if (vendor.address) {
				popupHTML += '<p>' + vendor.address + '</p>';
			}
			
			popupHTML += '<a href="' + vendor.url + '" target="_blank">View Profile</a>' +
				'</div>';
			
			var popup = new mapboxgl.Popup({ offset: 25 })
				.setHTML(popupHTML);
			
			// Add marker to map
			new mapboxgl.Marker(el)
				.setLngLat([vendor.longitude, vendor.latitude])
				.setPopup(popup)
				.addTo(map);
		});
		
		// Fit map to show all markers
		if (vendors.length > 0) {
			var bounds = new mapboxgl.LngLatBounds();
			
			vendors.forEach(function(vendor) {
				bounds.extend([vendor.longitude, vendor.latitude]);
			});
			
			map.fitBounds(bounds, {
				padding: 50,
				maxZoom: 10
			});
		}
	});
	</script>
	<?php
	
	return ob_get_clean();
} );

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
