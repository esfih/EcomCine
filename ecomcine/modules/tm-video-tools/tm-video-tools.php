<?php
/**
 * TM Video Tools — Browser-side WebM converter powered by ffmpeg.wasm.
 *
 * Registers the /converter page template and handles:
 *  - File-size limits passed to JS (guests: 20 MB, authenticated: 200 MB)
 *  - Asset enqueue scoped to converter pages only
 *  - COOP/COEP headers on the converter page (required for SharedArrayBuffer
 *    multi-threaded wasm via @ffmpeg/core-mt)
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

define( 'TM_VIDEO_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TM_VIDEO_TOOLS_URL', plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// 0. COOP / COEP headers
// ---------------------------------------------------------------------------
// Headers are sent directly inside templates/page-converter.php before any
// output, which is simpler and more reliable than a WP hook approach.

// ---------------------------------------------------------------------------
// 1. Page template registration
// ---------------------------------------------------------------------------

add_filter( 'theme_page_templates', function ( array $templates ): array {
	$templates['tm-video-tools/converter'] = __( 'Video Converter (EcomCine)', 'ecomcine' );
	return $templates;
} );

add_filter( 'template_include', function ( string $template ): string {
	if ( ! is_page() ) {
		return $template;
	}

	$post_id    = (int) get_the_ID();
	$stored_tpl = $post_id ? (string) get_post_meta( $post_id, '_wp_page_template', true ) : '';

	// Also match page slug "converter" as auto-detection fallback.
	$is_converter = 'tm-video-tools/converter' === $stored_tpl
		|| ( is_page( 'converter' ) && '' === $stored_tpl );

	if ( $is_converter ) {
		$tpl = TM_VIDEO_TOOLS_DIR . 'templates/page-converter.php';
		if ( file_exists( $tpl ) ) {
			return $tpl;
		}
	}

	return $template;
} );

// ---------------------------------------------------------------------------
// 2. Asset enqueue
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	if ( ! tm_video_tools_is_converter_page() ) {
		return;
	}

	wp_enqueue_style(
		'tm-video-tools-css',
		TM_VIDEO_TOOLS_URL . 'assets/css/converter.css',
		array(),
		ECOMCINE_VERSION
	);

	wp_enqueue_script(
		'tm-video-tools-js',
		TM_VIDEO_TOOLS_URL . 'assets/js/converter.js',
		array( 'jquery' ),
		ECOMCINE_VERSION,
		true
	);

	// ESM modules require type="module" on the script tag.
	add_filter( 'script_loader_tag', function ( string $tag, string $handle ): string {
		if ( 'tm-video-tools-js' === $handle ) {
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}, 10, 2 );

	// File-size cap: guests 20 MB, authenticated users 200 MB.
	$max_mb   = is_user_logged_in() ? 200 : 20;
	$ffmpeg_base = TM_VIDEO_TOOLS_URL . 'assets/ffmpeg/';

	wp_localize_script(
		'tm-video-tools-js',
		'tmVideoTools',
		array(
			'maxFileSizeMB' => $max_mb,
			'isLoggedIn'    => is_user_logged_in(),
			'loginUrl'      => wp_login_url( get_permalink() ),
			// Self-hosted ffmpeg.wasm assets (MT build) — same origin, no cross-origin Worker issues.
			'ffmpegUrl'       => $ffmpeg_base . 'index.js',
			'ffmpegCoreUrl'   => $ffmpeg_base . 'ffmpeg-core.js',
			'ffmpegWasmUrl'   => $ffmpeg_base . 'ffmpeg-core.wasm',
			'ffmpegWorkerUrl' => $ffmpeg_base . 'ffmpeg-core.worker.js',
		)
	);
} );

// ---------------------------------------------------------------------------
// 3. Helper
// ---------------------------------------------------------------------------

function tm_video_tools_is_converter_page(): bool {
	if ( ! is_page() ) {
		return false;
	}
	$post_id    = (int) get_the_ID();
	$stored_tpl = $post_id ? (string) get_post_meta( $post_id, '_wp_page_template', true ) : '';
	return 'tm-video-tools/converter' === $stored_tpl
		|| ( is_page( 'converter' ) && '' === $stored_tpl );
}
