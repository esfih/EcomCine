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

	$geo     = function_exists( 'ecomcine_get_geo' ) ? ecomcine_get_geo( $user_id ) : array();
	$address = isset( $geo['address'] ) ? (string) $geo['address'] : '';
	if ( empty( $address ) ) {
		wp_send_json_error( 'No canonical geo address set for user ' . $user_id );
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
	$lat_old = isset( $geo['lat'] ) ? floatval( $geo['lat'] ) : 0.0;
	$lng_old = isset( $geo['lng'] ) ? floatval( $geo['lng'] ) : 0.0;

	update_user_meta( $user_id, 'ecomcine_geo_lat', $lat_new );
	update_user_meta( $user_id, 'ecomcine_geo_lng', $lng_new );

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
	$plugin_uri = defined( 'TM_STORE_UI_URL' ) ? TM_STORE_UI_URL : get_stylesheet_directory_uri();
	$plugin_dir = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR : get_stylesheet_directory() . '/';

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
		$plugin_uri . 'assets/css/vendors-map.css',
		array( 'tm-mapbox-gl' ),
		filemtime( $plugin_dir . 'assets/css/vendors-map.css' )
	);

	wp_register_script(
		'tm-vendors-map-js',
		$plugin_uri . 'assets/js/vendors-map.js',
		array( 'tm-mapbox-gl-js' ),
		filemtime( $plugin_dir . 'assets/js/vendors-map.js' ),
		true // footer
	);
}
add_action( 'wp_loaded', 'tm_vendors_map_register_assets' );

// ─── Shortcode ────────────────────────────────────────────────────────────────

add_shortcode( 'vendors_map', function() {

	// ── Pre-flight checks ─────────────────────────────────────────────────────

	// Use EcomCine canonical token getter (reads own settings, falls back to Dokan).
	$mapbox_access_token = function_exists( 'ecomcine_get_mapbox_token' )
		? ecomcine_get_mapbox_token()
		: ( function_exists( 'dokan_get_option' ) ? (string) dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' ) : '' );

	if ( empty( $mapbox_access_token ) ) {
		return '<p>Please configure the Mapbox Access Token in EcomCine → Settings.</p>';
	}

	// ── Build vendor data ─────────────────────────────────────────────────────

	// Use ecomcine_get_persons() for role portability (seller ↔ ecomcine_person).
	$users = function_exists( 'ecomcine_get_persons' )
		? ecomcine_get_persons( array(
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => 'ecomcine_geo_lat',  'compare' => 'EXISTS' ),
				array( 'key' => 'ecomcine_geo_lng', 'compare' => 'EXISTS' ),
				array( 'key' => 'ecomcine_enabled', 'value' => '1', 'compare' => '=' ),
				array( 'key' => 'tm_l1_complete',      'value'   => '1', 'compare' => '=' ),
			),
		) )
		: get_users( array(
			'role'       => 'seller',
			'number'     => -1,
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => 'ecomcine_geo_lat',   'compare' => 'EXISTS' ),
				array( 'key' => 'ecomcine_geo_lng',  'compare' => 'EXISTS' ),
				array( 'key' => 'ecomcine_enabled', 'value'   => '1', 'compare' => '=' ),
				array( 'key' => 'tm_l1_complete',       'value'   => '1',   'compare' => '=' ),
			),
		) );

	// Portability: filter to enabled persons only using EcomCine canonical check.
	if ( function_exists( 'ecomcine_is_person_enabled' ) ) {
		$users = array_values( array_filter( $users, static function ( $u ) {
			return ecomcine_is_person_enabled( $u->ID );
		} ) );
	}

	if ( empty( $users ) ) {
		return '<p>No vendors with location data found.</p>';
	}

	$vendor_markers = array();
	foreach ( $users as $user ) {
		$user_geo = function_exists( 'ecomcine_get_geo' ) ? ecomcine_get_geo( $user->ID ) : array();
		$lat_raw = isset( $user_geo['lat'] ) ? $user_geo['lat'] : '';
		$lng_raw = isset( $user_geo['lng'] ) ? $user_geo['lng'] : '';

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

		// Use EcomCine native categories — avoids Dokan store_category taxonomy.
		$v_cats = array();
		if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			foreach ( EcomCine_Person_Category_Registry::get_for_person( $user->ID ) as $row ) {
				$v_cats[] = $row['slug'];
			}
		}

		$address_str  = isset( $user_geo['address'] ) ? (string) $user_geo['address'] : '';
		$address_parts = array_map( 'trim', explode( ',', $address_str ) );
		$country_str   = ! empty( $address_parts ) ? end( $address_parts ) : '';

		$store_info       = function_exists( 'ecomcine_get_person_info' )
			? ecomcine_get_person_info( $user->ID )
			: ( function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $user->ID ) : array() );
		$vendor_markers[] = array(
			'id'         => $user->ID,
			'name'       => $store_info['store_name'] ?? $user->display_name,
			'url'        => function_exists( 'ecomcine_get_person_url' )
				? ecomcine_get_person_url( $user->ID )
				: ( function_exists( 'dokan_get_store_url' ) ? dokan_get_store_url( $user->ID ) : get_author_posts_url( $user->ID ) ),
			'lat'        => $lat,
			'lng'        => $lng,
			'address'    => $address_str,
			'country'    => $country_str,
			'avatar'     => get_avatar_url( $user->ID, array( 'size' => 150 ) ),
			'cats'       => implode( ',', $v_cats ),
			'registered' => (int) strtotime( $user->user_registered ),
		);
	}

	if ( empty( $vendor_markers ) ) {
		return '<p>No vendors with location data found.</p>';
	}

	// Build the full EcomCine categories list (all categories, not only those
	// present on map vendors, so the panel is always complete).
	$map_categories = array();
	if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
		foreach ( EcomCine_Person_Category_Registry::get_all() as $row ) {
			$map_categories[] = array( 'slug' => $row['slug'], 'name' => $row['name'] );
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

	// Resolve showcase page URL — use get_page_by_path so we get the canonical
	// permalink regardless of which page template is assigned.
	$_sc_page       = get_page_by_path( 'showcase', OBJECT, 'page' );
	$_showcase_url  = $_sc_page instanceof WP_Post
		? esc_url( get_permalink( $_sc_page ) )
		: esc_url( home_url( '/showcase/' ) );

	// Resolve talents page URL via slug — avoids relying on Dokan's store-listing page.
	$_talents_page  = get_page_by_path( 'talents', OBJECT, 'page' );
	$_talents_url   = $_talents_page instanceof WP_Post
		? esc_url( get_permalink( $_talents_page ) )
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
