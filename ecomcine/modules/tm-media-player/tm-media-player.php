<?php
/**
 * Plugin Name: TM Media Player
 * Description: Standalone talent media player — playlist, A/B buffers, REST API, showcase shortcodes, and all enqueue logic extracted from the Astra child theme.
 * Version:     1.0.0
 * Author:      TM
 * Requires PHP: 8.2
 */

defined( 'ABSPATH' ) || exit;

define( 'TM_MEDIA_PLAYER_VERSION', '1.0.0' );
define( 'TM_MEDIA_PLAYER_DIR',     plugin_dir_path( __FILE__ ) );
define( 'TM_MEDIA_PLAYER_URL',     plugin_dir_url( __FILE__ ) );

// Adapter layer (Phase 2 — Default WP Pilot Adapter).
require_once TM_MEDIA_PLAYER_DIR . 'includes/contracts/interface-media-source-provider.php';
require_once TM_MEDIA_PLAYER_DIR . 'includes/adapters/compatibility/class-compat-media-source-provider.php';
require_once TM_MEDIA_PLAYER_DIR . 'includes/adapters/default-wp/class-wp-vendor-cpt.php';
require_once TM_MEDIA_PLAYER_DIR . 'includes/adapters/default-wp/class-wp-media-source-provider.php';
require_once TM_MEDIA_PLAYER_DIR . 'includes/adapters/class-adapter-registry.php';
require_once TM_MEDIA_PLAYER_DIR . 'includes/parity/class-parity-check.php';

// Register the tm_vendor CPT unconditionally (before Dokan guard).
add_action( 'init', array( 'TMP_WP_Vendor_CPT', 'register_post_type' ), 5 );

// =============================================================================
// 1. PLAYLIST — tm_get_vendor_media_playlist(), tm_vendor_has_video_playlist_media()
// =============================================================================

if ( ! function_exists( 'tm_get_vendor_media_playlist' ) ) :
function tm_get_vendor_media_playlist( $vendor_id ) {
	$payload = array(
		'items'         => array(),
		'fallbackImage' => '',
		'fallbackVideo' => '',
	);

	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id ) {
		return $payload;
	}

	// Use the adapter registry to retrieve vendor data sources.
	$source = TMP_Adapter_Registry::get_provider();
	$bio    = $source->get_biography( $vendor_id );

	$shortcode_pattern = get_shortcode_regex( array( 'gallery', 'playlist' ) );
	if ( is_string( $bio ) && $bio !== '' && $shortcode_pattern ) {
		if ( preg_match_all( '/'. $shortcode_pattern .'/s', $bio, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $shortcode ) {
				$tag      = isset( $shortcode[2] ) ? $shortcode[2] : '';
				$atts_raw = isset( $shortcode[3] ) ? $shortcode[3] : '';
				$atts     = shortcode_parse_atts( $atts_raw );
				$ids_raw  = isset( $atts['ids'] ) ? $atts['ids'] : '';
				if ( ! $ids_raw ) { continue; }
				$type = 'image';
				if ( $tag === 'playlist' ) {
					$type = ( isset( $atts['type'] ) && strtolower( $atts['type'] ) === 'video' ) ? 'video' : 'audio';
				}
				$ids = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );
				foreach ( $ids as $id ) {
					if ( ! $id ) { continue; }
					$src = wp_get_attachment_url( $id );
					if ( ! $src ) { continue; }
					$meta     = wp_get_attachment_metadata( $id );
					$duration = null;
					if ( is_array( $meta ) && isset( $meta['length'] ) ) {
						$duration = (int) $meta['length'];
					}
					$mime = get_post_mime_type( $id );
					if ( $type === 'image' && ( ! $mime || stripos( $mime, 'image/' ) !== 0 ) ) { continue; }
					$poster = '';
					if ( $type === 'video' ) {
						$poster = wp_get_attachment_image_url( $id, 'large' );
					}
					$title = '';
					if ( $type === 'image' ) {
						$title = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
						if ( $title === '' ) { $title = wp_basename( $src ); }
					} else {
						$title = trim( (string) get_the_title( $id ) );
						if ( $title === '' ) { $title = wp_basename( $src ); }
					}
					$payload['items'][] = array(
						'id'       => $id,
						'type'     => $type,
						'src'      => $src,
						'poster'   => $poster ? $poster : '',
						'title'    => $title,
						'duration' => $duration ?: null,
						'mime'     => $mime ? $mime : '',
					);
				}
			}
		}
	}

	if ( is_string( $bio ) && $bio !== '' ) {
		if ( preg_match_all( '/data-wp-media="([^"]+)"/i', $bio, $m ) && ! empty( $m[1] ) ) {
			foreach ( $m[1] as $raw_attr ) {
				$decoded = html_entity_decode( $raw_attr );
				$decoded = urldecode( $decoded );
				if ( stripos( $decoded, '[gallery' ) !== false ) {
					$atts    = shortcode_parse_atts( $decoded );
					$ids_raw = isset( $atts['ids'] ) ? $atts['ids'] : '';
					$ids     = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );
					foreach ( $ids as $id ) {
						$src = wp_get_attachment_url( $id );
						if ( ! $src ) { continue; }
						$mime = get_post_mime_type( $id );
						if ( ! $mime || stripos( $mime, 'image/' ) !== 0 ) { continue; }
						$title = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
						if ( $title === '' ) { $title = wp_basename( $src ); }
						$payload['items'][] = array(
							'id'       => $id,
							'type'     => 'image',
							'src'      => $src,
							'poster'   => '',
							'title'    => $title,
							'duration' => null,
							'mime'     => $mime ? $mime : '',
						);
					}
				}
			}
		}
	}

	$payload['fallbackVideo'] = $source->get_banner_video_url( $vendor_id );
	$payload['fallbackImage'] = $source->get_banner_image_url( $vendor_id );
	$payload['isFeatured']    = ( get_user_meta( $vendor_id, 'dokan_feature_seller', true ) === 'yes' );

	return $payload;
}
endif;

if ( ! function_exists( 'tm_vendor_has_video_playlist_media' ) ) :
function tm_vendor_has_video_playlist_media( $vendor_id ) {
	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id ) { return false; }

	$source = TMP_Adapter_Registry::get_provider();
	$bio    = $source->get_biography( $vendor_id );
	if ( $bio === '' ) { return false; }

	$pattern = get_shortcode_regex( array( 'playlist' ) );
	if ( $pattern && preg_match_all( '/'. $pattern .'/s', $bio, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $shortcode ) {
			$tag      = isset( $shortcode[2] ) ? (string) $shortcode[2] : '';
			$atts_raw = isset( $shortcode[3] ) ? (string) $shortcode[3] : '';
			if ( $tag !== 'playlist' ) { continue; }
			$atts = shortcode_parse_atts( $atts_raw );
			$ids  = isset( $atts['ids'] ) ? (string) $atts['ids'] : '';
			if ( $ids === '' ) { continue; }
			$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : '';
			if ( $type === 'video' ) { return true; }
		}
	}

	if ( preg_match_all( '/data-wp-media="([^"]+)"/i', $bio, $m ) && ! empty( $m[1] ) ) {
		foreach ( $m[1] as $raw_attr ) {
			$decoded = html_entity_decode( (string) $raw_attr );
			$decoded = urldecode( $decoded );
			if ( stripos( $decoded, '[playlist' ) === false ) { continue; }
			$atts = shortcode_parse_atts( $decoded );
			$ids  = isset( $atts['ids'] ) ? (string) $atts['ids'] : '';
			if ( $ids === '' ) { continue; }
			$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : '';
			if ( $type === 'video' ) { return true; }
		}
	}

	return false;
}
endif;

// =============================================================================
// 2. REST & AJAX — vendor store content + navigation list
// =============================================================================

add_action( 'rest_api_init', function() {
	register_rest_route( 'tm/v1', '/vendor-store-content', array(
		'methods'             => 'GET',
		'callback'            => 'tm_rest_get_vendor_store_content',
		'permission_callback' => '__return_true',
	) );
} );

if ( ! has_action( 'wp_ajax_get_vendor_store_content', 'get_vendor_store_content' ) ) {
	add_action( 'wp_ajax_get_vendor_store_content',        'get_vendor_store_content' );
	add_action( 'wp_ajax_nopriv_get_vendor_store_content', 'get_vendor_store_content' );
}
if ( ! has_action( 'wp_ajax_get_vendor_navigation_list', 'get_vendor_navigation_list' ) ) {
	add_action( 'wp_ajax_get_vendor_navigation_list',        'get_vendor_navigation_list' );
	add_action( 'wp_ajax_nopriv_get_vendor_navigation_list', 'get_vendor_navigation_list' );
}

if ( ! function_exists( 'tm_get_vendor_store_content_payload' ) ) :
function tm_get_vendor_store_content_payload( $vendor_id ) {
	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id || ! function_exists( 'dokan' ) ) {
		return new WP_Error( 'invalid_vendor', 'Invalid vendor', array( 'vendor_id' => $vendor_id ) );
	}
	$store_user = dokan()->vendor->get( $vendor_id );
	if ( ! $store_user ) {
		return new WP_Error( 'vendor_not_found', 'Vendor not found', array( 'vendor_id' => $vendor_id ) );
	}
	try {
		set_query_var( 'author', $vendor_id );
		ob_start();
		include locate_template( 'dokan/store-header.php' );
		$html = ob_get_clean();
		// Strip UTF-8 BOM (EF BB BF) that some template files prepend to output.
		// Without this jQuery treats the response as a parse error and fires the AJAX
		// error callback even though success:true is in the body.
		if ( substr( $html, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$html = substr( $html, 3 );
		}
	} catch ( Throwable $e ) {
		while ( ob_get_level() ) { ob_end_clean(); }
		$payload = array( 'message' => 'Failed to load vendor content.', 'vendor_id' => $vendor_id );
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			$payload['debug'] = array( 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() );
		}
		return new WP_Error( 'vendor_render_failed', $payload['message'], $payload );
	}
	$start_marker = '<div class="profile-info-box';
	$start_pos    = stripos( $html, $start_marker );
	if ( $start_pos !== false ) {
		$tag_end = strpos( $html, '>', $start_pos );
		if ( $tag_end !== false ) {
			$tag_end++;
			$rest        = substr( $html, $tag_end );
			$div_count   = 1;
			$pos         = 0;
			$content_end = false;
			while ( $div_count > 0 && $pos < strlen( $rest ) ) {
				$next_open  = stripos( $rest, '<div', $pos );
				$next_close = stripos( $rest, '</div>', $pos );
				if ( $next_open  === false ) { $next_open  = PHP_INT_MAX; }
				if ( $next_close === false ) { $next_close = PHP_INT_MAX; }
				if ( $next_open < $next_close ) { $div_count++; $pos = $next_open + 4; }
				elseif ( $next_close < PHP_INT_MAX ) {
					$div_count--;
					if ( $div_count === 0 ) { $content_end = $next_close; break; }
					$pos = $next_close + 6;
				} else { break; }
			}
			$content_html = ( $content_end !== false )
				? substr( $html, $start_pos, $tag_end - $start_pos + $content_end + 6 )
				: $html;
		} else {
			$content_html = $html;
		}
	} else {
		$content_html = $html;
	}
	try {
		$vendor_media = tm_get_vendor_media_playlist( $vendor_id );
	} catch ( Throwable $e ) {
		$vendor_media = array( 'items' => array(), 'fallbackImage' => '', 'fallbackVideo' => '' );
	}
	return array(
		'html'        => $content_html,
		'vendor_id'   => $vendor_id,
		'store_name'  => $store_user->get_shop_name(),
		'vendorMedia' => $vendor_media,
	);
}
endif;

if ( ! function_exists( 'get_vendor_store_content' ) ) :
function get_vendor_store_content() {
	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	$result    = tm_get_vendor_store_content_payload( $vendor_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_data() ?: array( 'message' => $result->get_error_message() ) );
		return;
	}
	wp_send_json_success( $result );
}
endif;

if ( ! function_exists( 'tm_rest_get_vendor_store_content' ) ) :
function tm_rest_get_vendor_store_content( WP_REST_Request $request ) {
	$vendor_id = absint( $request->get_param( 'vendor_id' ) );
	$result    = tm_get_vendor_store_content_payload( $vendor_id );
	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( array( 'success' => false, 'data' => $result->get_error_data() ), 400 );
	}
	$response = new WP_REST_Response( array( 'success' => true, 'data' => $result ), 200 );
	$response->header( 'X-TM-Cache', 'BYPASS' );
	return $response;
}
endif;

if ( ! function_exists( 'get_vendor_navigation_list' ) ) :
function get_vendor_navigation_list() {
	$current_vendor_id = isset( $_POST['current_vendor_id'] ) ? absint( $_POST['current_vendor_id'] ) : 0;
	$vendor_query = new WP_User_Query( array(
		'role__in' => array( 'seller', 'vendor' ),
		'orderby'  => 'registered',
		'order'    => 'ASC',
		'fields'   => array( 'ID' ),
		'number'   => 500,
	) );
	$vendors = $vendor_query->get_results();
	if ( empty( $vendors ) ) {
		wp_send_json_error( array( 'message' => 'No vendors found' ) );
		return;
	}
	$vendor_list   = array();
	$current_index = 0;
	$index         = 0;
	foreach ( $vendors as $vendor ) {
		$vendor_id = 0;
		if ( is_object( $vendor ) && isset( $vendor->ID ) ) { $vendor_id = absint( $vendor->ID ); }
		elseif ( is_numeric( $vendor ) ) { $vendor_id = absint( $vendor ); }
		if ( ! $vendor_id ) { continue; }
		if ( ! tm_vendor_has_video_playlist_media( $vendor_id ) ) { continue; }
		if ( function_exists( 'dokan_get_store_url' ) ) {
			$store_url = dokan_get_store_url( $vendor_id );
		} else {
			$user_data = get_userdata( $vendor_id );
			$store_url = home_url( '/store/' . $user_data->user_nicename );
		}
		$store_name = get_user_meta( $vendor_id, 'dokan_store_name', true );
		if ( empty( $store_name ) ) {
			$user_data  = get_userdata( $vendor_id );
			$store_name = $user_data->display_name;
		}
		$vendor_list[] = array( 'id' => $vendor_id, 'name' => $store_name, 'url' => $store_url );
		if ( $vendor_id == $current_vendor_id ) { $current_index = $index; }
		$index++;
	}
	if ( empty( $vendor_list ) ) {
		wp_send_json_error( array( 'message' => 'No vendors with video playlists found' ) );
		return;
	}
	wp_send_json_success( array( 'vendors' => $vendor_list, 'current_index' => $current_index, 'total' => count( $vendor_list ) ) );
}
endif;

// =============================================================================
// 3. SHOWCASE — shortcodes, page detection, WP filters
// =============================================================================

if ( ! function_exists( 'tm_get_showcase_vendor_ids' ) ) :
function tm_get_showcase_vendor_ids() {
	$vendor_query = new WP_User_Query( array(
		'role__in' => array( 'seller', 'vendor' ),
		'orderby'  => 'registered',
		'order'    => 'ASC',
		'fields'   => array( 'ID' ),
		'number'   => 500,
	) );
	$vendors    = $vendor_query->get_results();
	$vendor_ids = array();
	if ( empty( $vendors ) ) { return $vendor_ids; }
	foreach ( $vendors as $vendor ) {
		$vendor_id = 0;
		if ( is_object( $vendor ) && isset( $vendor->ID ) ) { $vendor_id = absint( $vendor->ID ); }
		elseif ( is_numeric( $vendor ) ) { $vendor_id = absint( $vendor ); }
		if ( ! $vendor_id ) { continue; }
		if ( ! tm_vendor_has_video_playlist_media( $vendor_id ) ) { continue; }
		$vendor_ids[] = $vendor_id;
	}
	return $vendor_ids;
}
endif;

if ( ! function_exists( 'tm_is_showcase_page' ) ) :
function tm_is_showcase_page() {
	if ( ! empty( $GLOBALS['tm_showcase_page'] ) ) { return true; }
	if ( ! is_page() ) { return false; }
	$queried = get_queried_object();
	if ( ! $queried || empty( $queried->post_content ) ) { return false; }
	return has_shortcode( $queried->post_content, 'tm_talent_showcase' )
		|| has_shortcode( $queried->post_content, 'tm_talent_player' );
}
endif;

if ( ! function_exists( 'tm_talent_showcase_shortcode' ) ) :
function tm_talent_showcase_shortcode( $atts = array() ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return '<div class="tm-talent-showcase-placeholder">[tm_talent_showcase]</div>';
	}
	$atts      = shortcode_atts( array( 'mode' => 'showcase' ), $atts, 'tm_talent_showcase' );
	$mode      = strtolower( (string) $atts['mode'] );
	if ( '' === $mode ) { $mode = 'showcase'; }
	$vendor_ids = tm_get_showcase_vendor_ids();
	if ( empty( $vendor_ids ) ) { return '<div class="tm-talent-showcase-empty">No talent available.</div>'; }
	$vendor_id  = (int) $vendor_ids[0];
	$payload    = tm_get_vendor_store_content_payload( $vendor_id );
	if ( is_wp_error( $payload ) ) { return '<div class="tm-talent-showcase-empty">Unable to load talent showcase.</div>'; }
	TM_Media_Player_Assets::enqueue_for_showcase( $vendor_id, $mode );
	set_query_var( 'author', $vendor_id );
	ob_start();
	?>
	<style>
		.tm-showcase-takeover { width: 100vw; margin-left: calc(50% - 50vw); margin-right: calc(50% - 50vw); }
		.tm-showcase-takeover .dokan-store-wrap { width: 100%; margin: 0; }
		.tm-showcase-takeover #dokan-primary { width: 100%; }
		.tm-showcase-takeover .dokan-single-store { width: 100%; }
		.tm-showcase-takeover .profile-frame { min-height: 100vh; }
	</style>
	<div class="tm-showcase-takeover">
		<div class="dokan-store-wrap layout-full">
			<div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
			<?php dokan_get_template_part( 'store-header' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
endif;

if ( ! shortcode_exists( 'tm_talent_showcase' ) ) {
	add_shortcode( 'tm_talent_showcase', 'tm_talent_showcase_shortcode' );
}

if ( ! function_exists( 'tm_talent_player_shortcode' ) ) :
function tm_talent_player_shortcode( $atts = array() ) {
	$atts = shortcode_atts( array( 'mode' => 'showcase' ), $atts, 'tm_talent_player' );
	return tm_talent_showcase_shortcode( $atts );
}
endif;

if ( ! shortcode_exists( 'tm_talent_player' ) ) {
	add_shortcode( 'tm_talent_player', 'tm_talent_player_shortcode' );
}

add_filter( 'body_class', function( $classes ) {
	if ( tm_is_showcase_page() ) {
		if ( ! in_array( 'dokan-store', $classes, true ) )    { $classes[] = 'dokan-store'; }
		if ( ! in_array( 'tm-showcase-page', $classes, true ) ) { $classes[] = 'tm-showcase-page'; }
	}
	return $classes;
}, 20 );

add_filter( 'astra_header_display', function( $display ) {
	return tm_is_showcase_page() ? false : $display;
}, 20 );

add_filter( 'astra_footer_display', function( $display ) {
	return tm_is_showcase_page() ? false : $display;
}, 20 );

add_filter( 'template_include', function( $template ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return $template;
	}
	if ( ! is_page() ) { return $template; }
	$queried = get_queried_object();
	if ( ! $queried || empty( $queried->post_content ) ) { return $template; }
	$has_showcase = has_shortcode( $queried->post_content, 'tm_talent_showcase' )
		|| has_shortcode( $queried->post_content, 'tm_talent_player' );
	if ( ! $has_showcase ) { return $template; }
	$GLOBALS['tm_showcase_page'] = true;
	$forced = locate_template( 'template-talent-showcase-full.php' );
	return $forced ? $forced : $template;
}, 99 );

// =============================================================================
// 4. ASSETS — TM_Media_Player_Assets class + enqueue logic
// =============================================================================

if ( ! class_exists( 'TM_Media_Player_Assets' ) ) :

class TM_Media_Player_Assets {

	/** JS + CSS version strings — bump to bust browser caches. */
	const JS_VERSION  = '2.0.2';
	const CSS_VERSION = '1.8.1';

	/** CDN URLs for Mapbox (loaded on-demand for editable profile pages). */
	const MAPBOX_VERSION   = '2.15.0';
	const GEOCODER_VERSION = '4.7.2';

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue' ), 20 );
		add_action( 'wp', array( __CLASS__, 'strip_global_styles' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_global_styles_scripts' ), 1 );
	}

	// -----------------------------------------------------------------------
	// Public context entry-points
	// -----------------------------------------------------------------------

	public static function enqueue_for_showcase( $vendor_id, $mode = 'showcase' ) {
		if ( is_admin() ) { return; }
		self::enqueue_css();
		self::enqueue_js( array( 'jquery' ) );
		self::bootstrap_media( $vendor_id );
		self::localize_showcase( $vendor_id, $mode );
	}

	public static function enqueue_for_profile( $vendor_id ) {
		if ( is_admin() ) { return; }
		$can_edit = function_exists( 'tm_can_edit_vendor_profile' )
			? tm_can_edit_vendor_profile( $vendor_id )
			: false;
		$deps = array( 'jquery' );
		if ( $can_edit ) {
			$deps = self::maybe_enqueue_mapbox( $deps );
		}
		self::enqueue_css();
		self::enqueue_js( $deps );
		self::bootstrap_media( $vendor_id );
		self::localize_profile( $vendor_id, $can_edit );
		if ( $can_edit ) {
			self::enqueue_media_library();
		}
	}

	// -----------------------------------------------------------------------
	// wp hooks
	// -----------------------------------------------------------------------

	public static function handle_enqueue() {
		if ( self::is_dashboard() ) { return; }
		// Showcase pages call enqueue_for_showcase() directly from the template before
		// get_header(). If we let handle_enqueue() also run here it would call
		// enqueue_for_profile() (because set_query_var('author',...) makes dokan_is_store_page()
		// return true) and overwrite playerMode:'showcase' with playerMode:'default'.
		if ( ! empty( $GLOBALS['tm_showcase_page'] ) ) {
			self::enqueue_css();
			return;
		}
		self::enqueue_css();
		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			$vendor_id = self::get_current_vendor_id();
			if ( $vendor_id ) {
				self::enqueue_for_profile( $vendor_id );
			}
		}
	}

	public static function strip_global_styles() {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return; }
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
		remove_action( 'wp_head', 'wp_global_styles_render', 10 );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	}

	public static function strip_global_styles_scripts() {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return; }
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles_custom_css' );
		wp_dequeue_style( 'astra-addon-megamenu-dynamic' );
		wp_deregister_style( 'astra-addon-megamenu-dynamic' );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	private static function enqueue_css() {
		wp_enqueue_style(
			'tm-player-css',
			TM_MEDIA_PLAYER_URL . 'assets/css/player.css',
			array( 'tm-store-ui-responsive', 'tm-store-ui-css' ),
			self::CSS_VERSION
		);
	}

	private static function enqueue_js( array $deps ) {
		wp_enqueue_script(
			'tm-player-js',
			TM_MEDIA_PLAYER_URL . 'assets/js/player.js',
			$deps,
			self::JS_VERSION,
			true
		);
	}

	private static function bootstrap_media( $vendor_id ) {
		$vendor_media    = tm_get_vendor_media_playlist( $vendor_id );
		$media_json      = wp_json_encode( $vendor_media );
		$media_bootstrap = 'window.vendorMedia = ' . ( $media_json ? $media_json : 'null' ) . ';';
		wp_add_inline_script( 'tm-player-js', $media_bootstrap, 'before' );
	}

	private static function localize_showcase( $vendor_id, $mode ) {
		$nonce = wp_create_nonce( 'vendor_inline_edit' );
		wp_localize_script( 'tm-player-js', 'vendorStoreData', array(
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'vendorStoreRestUrl'    => rest_url( 'tm/v1/vendor-store-content' ),
			'isOwner'               => false,
			'canEdit'               => false,
			'isPreonboard'          => false,
			'isOnboardingPage'      => false,
			'isAdminEditing'        => false,
			'isOnboardValid'        => false,
			'isOnboardClaimed'      => false,
			'userId'                => absint( $vendor_id ),
			'defaultAvatarUrl'      => 'https://marketplace.castingagency.co/talent_avatar_250x250.webp',
			'editNonce'             => $nonce,
			'nonce'                 => $nonce,
			'onboardNonce'          => $nonce,
			'mapbox_token'          => '',
			'jqueryUiCssUrl'        => '',
			'jqueryUiCoreUrl'       => '',
			'jqueryUiWidgetUrl'     => '',
			'jqueryUiDatepickerUrl' => '',
			'playerMode'            => $mode ? $mode : 'showcase',
		) );
		// Set dedicated globals that cannot be overwritten by any subsequent
		// wp_localize_script call (e.g. vendor-store-js from the theme).
		// These run as an inline <script> immediately BEFORE player.js loads.
		$safe_mode     = $mode ? $mode : 'showcase';
		$rest_url      = rest_url( 'tm/v1/vendor-store-content' );
		$inline_config = 'window.tmPlayerMode=' . json_encode( $safe_mode ) . ';'
			. 'window.tmVendorStoreRestUrl=' . json_encode( $rest_url ) . ';';
		wp_add_inline_script( 'tm-player-js', $inline_config, 'before' );
	}

	private static function localize_profile( $vendor_id, $can_edit ) {
		$current_user_id = get_current_user_id();
		$is_owner        = (bool) $can_edit;
		$mapbox_token    = function_exists( 'dokan_get_option' )
			? dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' )
			: '';

		$jquery_ui_css_url  = '';
		$jquery_ui_css_path = WP_CONTENT_DIR . '/plugins/woocommerce-bookings/dist/jquery-ui-styles.css';
		if ( file_exists( $jquery_ui_css_path ) ) {
			$jquery_ui_css_url = content_url( 'plugins/woocommerce-bookings/dist/jquery-ui-styles.css' );
		}

		$inline_edit_nonce = wp_create_nonce( 'vendor_inline_edit' );
		$onboard_token     = isset( $_GET['tm_onboard'] )
			? sanitize_text_field( wp_unslash( $_GET['tm_onboard'] ) )
			: '';
		$onboard_state     = function_exists( 'tm_account_panel_get_onboard_state' )
			? tm_account_panel_get_onboard_state( $vendor_id, $onboard_token )
			: array( 'valid' => false );
		$is_preonboard     = (bool) get_user_meta( $vendor_id, 'tm_preonboard', true );
		$is_admin_editing  = $is_owner && $current_user_id && $current_user_id !== $vendor_id
			&& current_user_can( 'manage_options' );
		$onboard_claimed   = isset( $_GET['tm_onboard_claimed'] )
			? sanitize_text_field( wp_unslash( $_GET['tm_onboard_claimed'] ) )
			: '';
		$is_onboarding_page = ! empty( $onboard_state['valid'] )
			|| ! empty( $onboard_claimed )
			|| ( $onboard_token && $is_preonboard );

		wp_localize_script( 'tm-player-js', 'vendorStoreData', array(
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'vendorStoreRestUrl'    => rest_url( 'tm/v1/vendor-store-content' ),
			'isOwner'               => $is_owner,
			'canEdit'               => $can_edit,
			'isPreonboard'          => $is_preonboard,
			'isOnboardingPage'      => $is_onboarding_page,
			'isAdminEditing'        => $is_admin_editing,
			'isOnboardValid'        => ! empty( $onboard_state['valid'] ),
			'isOnboardClaimed'      => ! empty( $onboard_claimed ),
			'userId'                => $vendor_id,
			'defaultAvatarUrl'      => 'https://marketplace.castingagency.co/talent_avatar_250x250.webp',
			'editNonce'             => $inline_edit_nonce,
			'nonce'                 => $inline_edit_nonce,
			'onboardNonce'          => $inline_edit_nonce,
			'mapbox_token'          => $mapbox_token,
			'jqueryUiCssUrl'        => $jquery_ui_css_url,
			'jqueryUiCoreUrl'       => includes_url( 'js/jquery/ui/core.min.js' ),
			'jqueryUiWidgetUrl'     => includes_url( 'js/jquery/ui/widget.min.js' ),
			'jqueryUiDatepickerUrl' => includes_url( 'js/jquery/ui/datepicker.min.js' ),
			'playerMode'            => 'default',
		) );
	}

	private static function maybe_enqueue_mapbox( array $deps ) {
		$mapbox_token = function_exists( 'dokan_get_option' )
			? dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' )
			: '';
		if ( empty( $mapbox_token ) ) { return $deps; }

		wp_enqueue_style(
			'mapbox-gl',
			'https://api.mapbox.com/mapbox-gl-js/v' . self::MAPBOX_VERSION . '/mapbox-gl.css',
			array(),
			self::MAPBOX_VERSION
		);
		wp_enqueue_style(
			'mapbox-geocoder',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v' . self::GEOCODER_VERSION . '/mapbox-gl-geocoder.css',
			array( 'mapbox-gl' ),
			self::GEOCODER_VERSION
		);
		wp_enqueue_script(
			'mapbox-gl',
			'https://api.mapbox.com/mapbox-gl-js/v' . self::MAPBOX_VERSION . '/mapbox-gl.js',
			array(),
			self::MAPBOX_VERSION,
			true
		);
		wp_enqueue_script(
			'mapbox-geocoder',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v' . self::GEOCODER_VERSION . '/mapbox-gl-geocoder.min.js',
			array( 'mapbox-gl' ),
			self::GEOCODER_VERSION,
			true
		);
		$deps[] = 'mapbox-gl';
		$deps[] = 'mapbox-geocoder';
		return $deps;
	}

	private static function enqueue_media_library() {
		$cap_filter = static function( $allcaps, $caps, $args ) {
			if ( isset( $args[0] ) && $args[0] === 'upload_files' ) {
				$allcaps['upload_files'] = true;
			}
			return $allcaps;
		};
		add_filter( 'user_has_cap', $cap_filter, 10, 3 );
		wp_enqueue_style( 'media-views' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_media();
		remove_filter( 'user_has_cap', $cap_filter, 10 );
	}

	private static function get_current_vendor_id() {
		if ( ! function_exists( 'dokan' ) ) { return 0; }
		$store_user = dokan()->vendor->get( get_query_var( 'author' ) );
		return $store_user ? (int) $store_user->get_id() : 0;
	}

	private static function is_dashboard() {
		if ( function_exists( 'dokan_is_dashboard' ) && dokan_is_dashboard() ) { return true; }
		return strpos( $_SERVER['REQUEST_URI'], '/dashboard/' ) !== false;
	}
}

endif; // class_exists TM_Media_Player_Assets

TM_Media_Player_Assets::init();

// ---------------------------------------------------------------------------
// Back-compat shim — keep tm_enqueue_talent_showcase_assets() callable from
// any code that still references it directly (theme, third-party code).
// ---------------------------------------------------------------------------
if ( ! function_exists( 'tm_enqueue_talent_showcase_assets' ) ) {
	function tm_enqueue_talent_showcase_assets( $vendor_id, $mode = 'showcase' ) {
		TM_Media_Player_Assets::enqueue_for_showcase( $vendor_id, $mode );
	}
}
