<?php
/**
 * [vendors_map] shortcode
 *
 * Registers the shortcode and handles all PHP-side work:
 *   - Validates Dokan Geolocation / Mapbox config
 *   - Builds vendor marker data
 *   - Enqueues CSS + JS (WP deduplicates on multi-shortcode pages)
 *   - Passes per-instance data to JS via wp_add_inline_script
 *   - Returns the HTML scaffold (one bare <div> the JS fills)
 *
 * Assets:
 *   assets/css/vendors-map.css  — all map styles
 *   assets/js/vendors-map.js    — Mapbox GL initialisation
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Admin-only geocoding repair ─────────────────────────────────────────────
// Called from browser console: tmVendorsMapFixCoords(userId)
// Re-geocodes dokan_geo_address via Mapbox and writes back correct lat/lng.
add_action( 'wp_ajax_tm_fix_vendor_geocoords', function() {
	// Discard any BOM / whitespace / notices buffered before this handler ran.
	// Without this, a BOM in any included file corrupts the JSON response.
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	check_ajax_referer( 'tm_fix_vendor_geocoords', 'nonce' );

	$user_id = intval( $_POST['user_id'] ?? 0 );
	if ( ! $user_id ) {
		wp_send_json_error( 'Missing user_id' );
	}

	$address = get_user_meta( $user_id, 'dokan_geo_address', true );
	if ( empty( $address ) ) {
		wp_send_json_error( 'No dokan_geo_address set for user ' . $user_id );
	}

	$token = dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' );
	if ( empty( $token ) ) {
		wp_send_json_error( 'Mapbox token not configured' );
	}

	$url = add_query_arg(
		array(
			'access_token' => $token,
			'limit'        => 1,
			'types'        => 'address,place,locality,region,country',
		),
		'https://api.mapbox.com/geocoding/v5/mapbox.places/' . rawurlencode( $address ) . '.json'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'Mapbox API error: ' . $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['features'][0]['center'] ) ) {
		wp_send_json_error( 'No geocoding result for: ' . $address );
	}

	$lng_new = floatval( $body['features'][0]['center'][0] );
	$lat_new = floatval( $body['features'][0]['center'][1] );
	$place   = $body['features'][0]['place_name'] ?? $address;

	// Capture old values before overwriting
	$lat_old = floatval( get_user_meta( $user_id, 'dokan_geo_latitude',  true ) );
	$lng_old = floatval( get_user_meta( $user_id, 'dokan_geo_longitude', true ) );

	update_user_meta( $user_id, 'dokan_geo_latitude',  $lat_new );
	update_user_meta( $user_id, 'dokan_geo_longitude', $lng_new );

	wp_send_json_success( array(
		'user_id'    => $user_id,
		'address'    => $address,
		'place_name' => $place,
		'old_lat'    => $lat_old,
		'old_lng'    => $lng_old,
		'new_lat'    => $lat_new,
		'new_lng'    => $lng_new,
	) );
} );

/**
 * Register (but do not enqueue) Mapbox GL and our own map assets.
 * Enqueuing happens inside the shortcode callback so assets are only loaded
 * on pages that actually use [vendors_map].
 */
function tm_vendors_map_register_assets() {
	$theme_uri = get_stylesheet_directory_uri();
	$theme_dir = get_stylesheet_directory();

	wp_register_style(
		'tm-mapbox-gl',
		'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
		array(),
		'2.15.0'
	);

	wp_register_script(
		'tm-mapbox-gl-js',
		'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
		array(),
		'2.15.0',
		true // footer
	);

	wp_register_style(
		'tm-vendors-map',
		$theme_uri . '/assets/css/vendors-map.css',
		array( 'tm-mapbox-gl' ),
		filemtime( $theme_dir . '/assets/css/vendors-map.css' )
	);

	wp_register_script(
		'tm-vendors-map-js',
		$theme_uri . '/assets/js/vendors-map.js',
		array( 'tm-mapbox-gl-js' ),
		filemtime( $theme_dir . '/assets/js/vendors-map.js' ),
		true // footer
	);
}
add_action( 'wp_loaded', 'tm_vendors_map_register_assets' );

// ─── Shortcode ────────────────────────────────────────────────────────────────

add_shortcode( 'vendors_map', function() {

	// ── Pre-flight checks ─────────────────────────────────────────────────────

	if ( ! function_exists( 'dokan_pro' ) || ! dokan_pro()->module->is_active( 'geolocation' ) ) {
		return '<p>Geolocation module is not active.</p>';
	}

	if ( 'mapbox' !== dokan_get_option( 'map_api_source', 'dokan_appearance', 'google' ) ) {
		return '<p>Please set Map API Source to Mapbox in Dokan settings.</p>';
	}

	$mapbox_access_token = dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' );
	if ( empty( $mapbox_access_token ) ) {
		return '<p>Please configure Mapbox Access Token in Dokan → Settings → Appearance.</p>';
	}

	// ── Build vendor data ─────────────────────────────────────────────────────

	$users = get_users( array(
		'role'       => 'seller',
		'number'     => -1,
		'meta_query' => array(
			'relation' => 'AND',
			array( 'key' => 'dokan_geo_latitude',   'compare' => 'EXISTS' ),
			array( 'key' => 'dokan_geo_longitude',  'compare' => 'EXISTS' ),
			// Same two-gate criteria as the store listing: approved + L1-complete.
			array( 'key' => 'dokan_enable_selling', 'value'   => 'yes', 'compare' => '=' ),
			array( 'key' => 'tm_l1_complete',       'value'   => '1',   'compare' => '=' ),
		),
	) );

	if ( empty( $users ) ) {
		return '<p>No vendors with location data found.</p>';
	}

	$vendor_markers = array();
	foreach ( $users as $user ) {
		$lat_raw = get_user_meta( $user->ID, 'dokan_geo_latitude',  true );
		$lng_raw = get_user_meta( $user->ID, 'dokan_geo_longitude', true );

		// Reject empty, serialized, JSON or array meta — is_numeric accepts '51.5' but
		// not 'a:1:{...}', so this is the fastest guard against corrupted Dokan data.
		if ( ! is_numeric( $lat_raw ) || ! is_numeric( $lng_raw ) ) {
			continue;
		}

		$lat = floatval( $lat_raw );
		$lng = floatval( $lng_raw );

		// Hard bounds: valid latitude -90..90, valid longitude -180..180.
		// Values outside these ranges mean the meta fields are swapped or garbage.
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			continue;
		}

		// Skip Null Island (0, 0) -- almost always means location was never set.
		if ( $lat === 0.0 && $lng === 0.0 ) {
			continue;
		}

		// Dokan stores vendor-category associations using the user ID as object_id
		// in wp_term_relationships (non-standard but supported by WP core tables).
		$v_terms = wp_get_object_terms( $user->ID, 'store_category', array( 'fields' => 'slugs' ) );
		$v_cats  = ( is_array( $v_terms ) && ! is_wp_error( $v_terms ) ) ? $v_terms : array();

		$store_info       = dokan_get_store_info( $user->ID );
		$vendor_markers[] = array(
			'id'         => $user->ID,
			'name'       => $store_info['store_name'] ?? $user->display_name,
			'url'        => dokan_get_store_url( $user->ID ),
			'lat'        => $lat,
			'lng'        => $lng,
			'address'    => get_user_meta( $user->ID, 'dokan_geo_address', true ),
			'avatar'     => get_avatar_url( $user->ID, array( 'size' => 150 ) ),
			'cats'       => implode( ',', $v_cats ),
			'registered' => (int) strtotime( $user->user_registered ),
		);
	}

	if ( empty( $vendor_markers ) ) {
		return '<p>No vendors with location data found.</p>';
	}

	// Build the categories list from slugs that are actually present on map vendors.
	// Doing this after the vendor loop ensures empty categories are never shown.
	$present_slugs = array();
	foreach ( $vendor_markers as $vm ) {
		if ( ! empty( $vm['cats'] ) ) {
			foreach ( explode( ',', $vm['cats'] ) as $s ) {
				$present_slugs[ trim( $s ) ] = true;
			}
		}
	}
	$map_categories = array();
	if ( ! empty( $present_slugs ) ) {
		$cat_terms = get_terms( array(
			'taxonomy'   => 'store_category',
			'hide_empty' => false,
			'slug'       => array_keys( $present_slugs ),
			'orderby'    => 'name',
		) );
		if ( is_array( $cat_terms ) && ! is_wp_error( $cat_terms ) ) {
			foreach ( $cat_terms as $t ) {
				$map_categories[] = array( 'slug' => $t->slug, 'name' => $t->name );
			}
		}
	}

	// ── Enqueue assets ────────────────────────────────────────────────────────
	// Safe to call inside the shortcode callback; WP deduplicates automatically.

	wp_enqueue_style( 'tm-vendors-map' );
	wp_enqueue_script( 'tm-vendors-map-js' );

	// Pass per-instance config to vendors-map.js BEFORE the script runs.
	// Multiple [vendors_map] shortcodes on the same page each push their own
	// entry; vendors-map.js iterates the whole array.
	$map_id = 'vendors-map-' . uniqid();

	// Inject the AJAX config once — wp_add_inline_script deduplicates identical calls
	// so if there are multiple [vendors_map] on the same page this only fires once.
	$ajax_config = 'window.tmVendorsMapAjax = window.tmVendorsMapAjax || '
		. wp_json_encode( array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tm_fix_vendor_geocoords' ),
		) ) . ';';

	wp_add_inline_script( 'tm-vendors-map-js', $ajax_config, 'before' );

	// Resolve showcase page URL (same logic as store-lists-hooks.php).
	$_sc_pages      = get_pages( [
		'meta_key'   => '_wp_page_template',
		'meta_value' => 'template-talent-showcase-full.php',
		'number'     => 1,
	] );
	$_showcase_url  = $_sc_pages
		? esc_url( get_permalink( $_sc_pages[0]->ID ) )
		: esc_url( home_url( '/showcase/' ) );

	// Resolve talents (store-listing) page URL via Dokan's stored page ID.
	$_talents_page_id = (int) dokan_get_option( 'store_listing', 'dokan_pages', 0 );
	$_talents_url     = $_talents_page_id
		? esc_url( get_permalink( $_talents_page_id ) )
		: esc_url( home_url( '/talents/' ) );

	wp_add_inline_script(
		'tm-vendors-map-js',
		'window.tmVendorsMapInstances = window.tmVendorsMapInstances || [];'
		. 'window.tmVendorsMapInstances.push(' . wp_json_encode( array(
			'mapId'       => $map_id,
			'token'       => $mapbox_access_token,
			'vendors'     => $vendor_markers,
			'categories'  => $map_categories,
			'showcaseUrl' => $_showcase_url,
			'talentsUrl'  => $_talents_url,
		) ) . ');',
		'before' // runs before vendors-map.js so data is ready when the script executes
	);

	// ── HTML scaffold ─────────────────────────────────────────────────────────

	return '<div class="tm-vendors-map-wrap">'
		. '<div id="' . esc_attr( $map_id ) . '-cat-panel" class="tm-vmap-cat-panel"></div>'
		. '<div id="' . esc_attr( $map_id ) . '" class="tm-vendors-map-canvas"></div>'
		. '</div>';
} );
