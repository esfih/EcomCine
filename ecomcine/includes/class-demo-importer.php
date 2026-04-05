<?php
/**
 * EcomCine Demo Importer
 *
 * Imports demo vendor profiles into the current WordPress installation.
 *
 * Imports demo vendor profiles into the current WordPress installation.
 *
 * Demo packs are fetched from the remote manifest at ECOMCINE_DEMO_MANIFEST_URL
 * (https://raw.githubusercontent.com/esfih/EcomCine/main/demos/manifest.json), downloaded as a zip to a temporary
 * WP uploads sub-directory, used for import, then cleaned up.
 *
 * Upsert behaviour: if a vendor username already exists the profile meta and media
 * are updated in-place rather than skipped.
 *
 * Entry points:
 *   EcomCine_Demo_Importer::run_remote($url) → result array (remote zip URL)
 *   EcomCine_Demo_Importer::fetch_manifest() → decoded manifest array or null
 *
 * Catalog ID: data.vendors.import.demo
 */

defined( 'ABSPATH' ) || exit;

/** Manifest URL — override via wp-config.php define or filter. */
if ( ! defined( 'ECOMCINE_DEMO_MANIFEST_URL' ) ) {
	define( 'ECOMCINE_DEMO_MANIFEST_URL', 'https://raw.githubusercontent.com/esfih/EcomCine/master/demos/manifest.json' );
}

class EcomCine_Demo_Importer {

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Run from a remote demo pack zip URL.
	 *
	 * The zip must contain:
	 *   vendor-data.json   — vendor profiles
	 *   media/             — per-vendor media files (same layout as local demo/media/)
	 *
	 * @param  string $zip_url  Absolute URL to the demo pack zip.
	 * @return array  Result summary.
	 */
	public static function run_remote( string $zip_url ): array {
		$result = self::empty_result();

		if ( empty( $zip_url ) || ! filter_var( $zip_url, FILTER_VALIDATE_URL ) ) {
			$result['errors'][] = 'Invalid demo pack URL.';
			return $result;
		}

		// Debug: Log start of run_remote
		$log_file = ABSPATH . 'wp-content/uploads/ecomcine_importer_debug.log';
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - EcomCine_Demo_Importer::run_remote called with URL: " . $zip_url . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// ── Download ──────────────────────────────────────────────────────────
		$tmp_dir = self::make_tmp_dir( $result );
		if ( null === $tmp_dir ) {
			$result['errors'][] = 'Failed to create temp directory';
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Temp directory created: " . $tmp_dir . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );

		$zip_path = $tmp_dir . '/demo-pack.zip';
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Downloading from: " . $zip_url . " to: " . $zip_path . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		$download = self::download_file( $zip_url, $zip_path );
		if ( is_wp_error( $download ) ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - Download failed: " . $download->get_error_message() . "\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'Download failed: ' . $download->get_error_message();
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Download successful\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// ── Unzip ─────────────────────────────────────────────────────────────
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Attempting to unzip\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// WP_Filesystem must be initialised before unzip_file() is called.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Initializing WP_Filesystem\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		WP_Filesystem();
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - WP_Filesystem initialized\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );

		$unzip = unzip_file( $zip_path, $tmp_dir );
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - unzip_file called\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		if ( is_wp_error( $unzip ) ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - Unzip failed: " . $unzip->get_error_message() . "\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'Unzip failed: ' . $unzip->get_error_message();
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Unzip successful\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// Resolve vendor-data.json (may be directly in tmp_dir or in a sub-folder).
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Searching for vendor-data.json\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		$json_path = self::find_in_dir( $tmp_dir, 'vendor-data.json' );
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - json_path: " . ( $json_path ?? 'NULL' ) . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		if ( null === $json_path ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - ERROR: vendor-data.json not found\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'vendor-data.json not found inside demo pack zip.';
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$media_dir = dirname( $json_path );
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - media_dir: " . $media_dir . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// ── Import ────────────────────────────────────────────────────────────────
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Reading vendor-data.json\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// Set timeout for file_get_contents
		$timeout = 30;
		$context = stream_context_create( array( 'timeout' => $timeout ) );
		
		$raw = @file_get_contents( $json_path, false, $context );
		if ( $raw === false ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - ERROR: file_get_contents failed\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'Failed to read vendor-data.json: ' . error_get_last()['message'];
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - JSON content length: " . strlen( $raw ) . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		$payload = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - JSON decode error: " . json_last_error_msg() . "\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'JSON decode error: ' . json_last_error_msg();
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - JSON decoded: " . ( is_array( $payload ) ? 'yes' : 'no' ) . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		if ( ! is_array( $payload ) || empty( $payload['vendors'] ) ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - ERROR: No vendors found in JSON\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			$result['errors'][] = 'vendor-data.json inside demo pack is empty or malformed.';
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Found " . count( $payload['vendors'] ) . " vendors\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );

		// ── Unzip ─────────────────────────────────────────────────────────────
		// WP_Filesystem must be initialised before unzip_file() is called.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$unzip = unzip_file( $zip_path, $tmp_dir );
		if ( is_wp_error( $unzip ) ) {
			$result['errors'][] = 'Unzip failed: ' . $unzip->get_error_message();
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}

		// Resolve vendor-data.json (may be directly in tmp_dir or in a sub-folder).
		$json_path = self::find_in_dir( $tmp_dir, 'vendor-data.json' );
		if ( null === $json_path ) {
			$result['errors'][] = 'vendor-data.json not found inside demo pack zip.';
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}

		$media_dir = dirname( $json_path );

		// ── Import ────────────────────────────────────────────────────────────
		$raw     = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) || empty( $payload['vendors'] ) ) {
			$result['errors'][] = 'vendor-data.json inside demo pack is empty or malformed.';
			self::cleanup_tmp_dir( $tmp_dir );
			return $result;
		}

		foreach ( $payload['vendors'] as $vendor_data ) {
			$outcome = self::import_vendor( $vendor_data, $media_dir, $result );
			if ( 'imported' === $outcome ) {
				$result['imported']++;
			} elseif ( 'updated' === $outcome ) {
				$result['updated']++;
			}
		}

		self::cleanup_tmp_dir( $tmp_dir );
		return $result;
	}

	/**
	 * Fetch the remote manifest JSON.
	 *
	 * @param  string|null $manifest_url  Override URL (defaults to ECOMCINE_DEMO_MANIFEST_URL).
	 * @return array|null  Decoded manifest array, or null on failure.
	 */
	public static function fetch_manifest( ?string $manifest_url = null ): ?array {
		$url = $manifest_url ?? ECOMCINE_DEMO_MANIFEST_URL;
		$url = (string) apply_filters( 'ecomcine_demo_manifest_url', $url );

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * CLI entry point: imports the first available remote pack (or a specific zip URL).
	 * Usage via WP-CLI: wp eval 'EcomCine_Demo_Importer::run_remote_cli();'
	 *
	 * @param string $zip_url Optional. If empty, fetches the manifest and uses the first pack.
	 */
	public static function run_remote_cli( string $zip_url = '' ): void {
		if ( empty( $zip_url ) ) {
			$manifest = self::fetch_manifest();
			if ( ! $manifest || empty( $manifest['packs'][0]['zip_url'] ) ) {
				echo "[demo-import] ERROR: Could not fetch manifest or no packs found.\n";
				return;
			}
			$zip_url = $manifest['packs'][0]['zip_url'];
		}
		$result = self::run_remote( $zip_url );
		echo "[demo-import] DONE: {$result['imported']} vendors created, {$result['updated']} updated.\n";
		foreach ( $result['errors'] as $err ) {
			echo "[demo-import] ERROR: {$err}\n";
		}
		foreach ( $result['log'] as $msg ) {
			echo "[demo-import] {$msg}\n";
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private static function empty_result(): array {
		return array(
			'imported' => 0,
			'updated'  => 0,
			'errors'   => array(),
			'log'      => array(),
		);
	}

	/**
	 * Import or update a single vendor (upsert).
	 *
	 * @param array  $v         Vendor data from JSON.
	 * @param string $media_dir Absolute path to the media directory for this pack.
	 * @param array  &$result   Result accumulator for logging.
	 * @return string 'imported' | 'updated' | 'error'
	 */
	private static function import_vendor( array $v, string $media_dir, array &$result ): string {
		$login = sanitize_user( $v['user_login'] ?? '', true );
		$email = sanitize_email( $v['user_email'] ?? '' );
		$slug  = sanitize_title( $v['slug'] ?? $login );

		if ( empty( $login ) || empty( $email ) ) {
			$result['errors'][] = "Vendor entry missing login or email (slug: {$slug}).";
			return 'error';
		}

		$existing_by_login = username_exists( $login );
		$existing_by_email = email_exists( $email );
		$existing_id       = $existing_by_login ?: $existing_by_email;
		$is_update         = (bool) $existing_id;

		if ( $is_update ) {
			$user_id = (int) $existing_id;
			$result['log'][] = "Updating existing vendor: {$login} (ID {$user_id})";
		} else {
			$password = wp_generate_password( 24, true, true );
			$user_id  = wp_create_user( $login, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				$result['errors'][] = "Failed to create user {$login}: " . $user_id->get_error_message();
				return 'error';
			}
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => sanitize_text_field( $v['display_name'] ?? $login ),
			'first_name'   => sanitize_text_field( $v['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $v['last_name'] ?? '' ),
		) );

		// Assign roles.
		$user = new WP_User( $user_id );
		if ( get_role( 'ecomcine_person' ) ) {
			if ( ! in_array( 'ecomcine_person', (array) $user->roles, true ) ) {
				$user->add_role( 'ecomcine_person' );
			}
		}
		if ( isset( $GLOBALS['wp_roles'] ) && array_key_exists( 'seller', $GLOBALS['wp_roles']->roles ) ) {
			if ( ! in_array( 'seller', (array) $user->roles, true ) ) {
				$user->add_role( 'seller' );
			}
		} elseif ( ! get_role( 'ecomcine_person' ) && ! $is_update ) {
			$user->set_role( 'subscriber' );
		}

		// Write plain user meta.
		$meta = (array) ( $v['meta'] ?? array() );
		foreach ( $meta as $key => $value ) {
			if ( 'dokan_profile_settings' === $key ) {
				continue;
			}
			update_user_meta( $user_id, $key, $value );
		}

		$profile_seed = isset( $meta['dokan_profile_settings'] ) ? (array) $meta['dokan_profile_settings'] : array();
		$banner_id    = 0;
		$avatar_id    = 0;

		// Sideload banner + gravatar.
		$media_refs = (array) ( $v['media'] ?? array() );
		foreach ( array( 'banner' => 'banner', 'gravatar' => 'gravatar' ) as $field => $label ) {
			if ( empty( $media_refs[ $label ] ) ) {
				continue;
			}
			$src = $media_dir . '/' . ltrim( (string) $media_refs[ $label ], '/' );
			if ( ! file_exists( $src ) ) {
				$result['log'][] = "Media not found for {$login}/{$label}: {$src}";
				continue;
			}
			$aid = self::sideload_media( $src, "{$slug}-{$label}", $user_id );
			if ( $aid > 0 ) {
				if ( 'banner' === $field ) {
					$banner_id = $aid;
				} elseif ( 'gravatar' === $field ) {
					$avatar_id = $aid;
				}
			} else {
				$result['log'][] = "Failed to sideload media for {$login}/{$label}.";
			}
		}

		// Sideload videos.
		$video_paths = self::resolve_vendor_video_paths( $media_refs, $media_dir, $slug );
		$existing_bio = (string) ( $profile_seed['vendor_biography'] ?? get_user_meta( $user_id, 'ecomcine_bio', true ) );
		$shortcode_pattern = get_shortcode_regex( array( 'playlist' ) );
		if ( $shortcode_pattern && '' !== $existing_bio ) {
			$existing_bio = preg_replace_callback(
				'/' . $shortcode_pattern . '/s',
				static function( $match ) {
					$tag      = isset( $match[2] ) ? (string) $match[2] : '';
					$atts_raw = isset( $match[3] ) ? (string) $match[3] : '';
					if ( 'playlist' !== $tag ) {
						return $match[0];
					}
					$atts = shortcode_parse_atts( $atts_raw );
					$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : 'audio';

					return 'video' === $type ? '' : $match[0];
				},
				$existing_bio
			);
		}
		$existing_bio = trim( $existing_bio );
		if ( ! empty( $video_paths ) ) {
			$video_ids = array();
			foreach ( $video_paths as $i => $vid_rel ) {
				$src = $media_dir . '/' . ltrim( (string) $vid_rel, '/' );
				if ( ! file_exists( $src ) ) {
					$result['log'][] = "Video not found for {$login} (video" . ( $i + 1 ) . "): {$src}";
					continue;
				}
				$aid = self::sideload_media( $src, "{$slug}-video" . ( $i + 1 ), $user_id );
				if ( $aid > 0 ) {
					$video_ids[] = $aid;
				}
			}
			if ( ! empty( $video_ids ) ) {
				$video_shortcode = '[playlist type="video" ids="' . implode( ',', $video_ids ) . '"]';
				$existing_bio = '' === $existing_bio ? $video_shortcode : $existing_bio . "\n\n" . $video_shortcode;
			}
		}
		$existing_bio = trim( $existing_bio );

		// Write EcomCine canonical meta.
		$ecomcine_info = array(
			'ecomcine_store_name' => sanitize_text_field( (string) ( $profile_seed['store_name'] ?? '' ) ),
			'ecomcine_bio'        => wp_kses_post( $existing_bio ),
			'ecomcine_phone'      => sanitize_text_field( (string) ( $profile_seed['phone'] ?? '' ) ),
			'ecomcine_banner_id'  => $banner_id,
			'ecomcine_avatar_id'  => $avatar_id,
			'ecomcine_address'    => is_array( $profile_seed['address'] ?? null ) ? $profile_seed['address'] : array(),
			'ecomcine_social'     => is_array( $profile_seed['social'] ?? null ) ? $profile_seed['social'] : array(),
			'ecomcine_enabled'    => '1',
		);
		foreach ( $ecomcine_info as $meta_key => $meta_value ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}

		// Write canonical geo keys so the vendors-map shortcode meta_query finds them.
		// Resolve lat/lng: prefer explicit geolocation object, fall back to parsing dps['location'] as "lat,lng".
		$geo_data = isset( $profile_seed['geolocation'] ) && is_array( $profile_seed['geolocation'] ) ? $profile_seed['geolocation'] : array();
		if ( ( empty( $geo_data['latitude'] ) || empty( $geo_data['longitude'] ) ) ) {
			$loc_raw = (string) ( $profile_seed['location'] ?? '' );
			if ( preg_match( '/^\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/', $loc_raw, $coord_m ) ) {
				$geo_data['latitude']  = $coord_m[1];
				$geo_data['longitude'] = $coord_m[2];
			}
		}
		if ( ! empty( $geo_data['latitude'] ) && ! empty( $geo_data['longitude'] ) ) {
			update_user_meta( $user_id, 'ecomcine_geo_lat', (string) $geo_data['latitude'] );
			update_user_meta( $user_id, 'ecomcine_geo_lng', (string) $geo_data['longitude'] );
			// Human-readable address: prefer find_address, then non-coordinate location string.
			$geo_address = sanitize_text_field( (string) ( $profile_seed['find_address'] ?? '' ) );
			if ( '' === $geo_address ) {
				$loc_candidate = sanitize_text_field( (string) ( $profile_seed['location'] ?? '' ) );
				if ( ! preg_match( '/^\s*-?\d+\.?\d*\s*,\s*-?\d+\.?\d*\s*$/', $loc_candidate ) ) {
					$geo_address = $loc_candidate;
				}
			}
			if ( '' !== $geo_address ) {
				update_user_meta( $user_id, 'ecomcine_geo_address', $geo_address );
			}
		}

		update_user_meta( $user_id, 'dokan_enable_selling', 'yes' );
		update_user_meta( $user_id, 'tm_l1_complete', '1' );

		// Assign EcomCine person categories.
		$category_slugs = array();
		if ( ! empty( $v['store_categories'] ) && is_array( $v['store_categories'] ) ) {
			foreach ( $v['store_categories'] as $slug_raw ) {
				$s = sanitize_title( (string) $slug_raw );
				if ( '' !== $s && 'uncategorized' !== $s ) {
					$category_slugs[] = $s;
				}
			}
		}
		if ( empty( $category_slugs ) ) {
			$cat_objects = isset( $profile_seed['categories'] ) ? (array) $profile_seed['categories'] : array();
			foreach ( $cat_objects as $cat ) {
				$s = isset( $cat['slug'] ) ? sanitize_title( $cat['slug'] ) : '';
				if ( '' !== $s && 'uncategorized' !== $s ) {
					$category_slugs[] = $s;
				}
			}
		}
		if ( ! empty( $category_slugs ) && class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			EcomCine_Person_Category_Registry::set_person_categories_by_slug( $user_id, $category_slugs );
			$result['log'][] = "Set EcomCine categories for {$login}: " . implode( ', ', $category_slugs );
		}
		if ( ! empty( $category_slugs ) && taxonomy_exists( 'store_category' ) ) {
			$term_ids = array();
			foreach ( $category_slugs as $s ) {
				$term = get_term_by( 'slug', $s, 'store_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term->term_id;
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $user_id, $term_ids, 'store_category' );
			}
		}

		// Ensure a published tm_vendor CPT post exists so ecomcine_has_public_person_profile()
		// returns true and the vendor appears on the Talents listing page.
		if ( class_exists( 'TMP_WP_Vendor_CPT', false ) ) {
			$cpt_data = array(
				'biography'    => wp_kses_post( $existing_bio ),
				'banner_image' => $banner_id,
			);
			$cpt_result = TMP_WP_Vendor_CPT::upsert_vendor( $user_id, $cpt_data );
			if ( is_wp_error( $cpt_result ) ) {
				$result['log'][] = "Warning: tm_vendor CPT upsert failed for {$login}: " . $cpt_result->get_error_message();
			} else {
				$result['log'][] = "tm_vendor CPT post {$cpt_result} for {$login}";
			}
		}

		if ( class_exists( 'EcomCine_Dokan_Data_Migration', false ) && EcomCine_Dokan_Data_Migration::cleanup_legacy_profile_surface( $user_id, false ) ) {
			$result['log'][] = "Removed Dokan legacy profile surface for {$login}";
		}

		$result['log'][] = $is_update ? "Updated vendor: {$login} (ID {$user_id})" : "Created vendor: {$login} (ID {$user_id})";
		return $is_update ? 'updated' : 'imported';
	}

	/**
	 * Resolve vendor video paths from explicit metadata or legacy demo-pack layout.
	 *
	 * Older demo packs include files like media/<slug>/video1.mp4 without a
	 * corresponding media.videos manifest entry. Keep supporting that layout so
	 * imports do not silently strip playable media.
	 *
	 * @param array  $media_refs Media block from vendor-data.json.
	 * @param string $media_dir  Absolute path to the pack root.
	 * @param string $slug      Sanitized vendor slug.
	 * @return array<int,string>
	 */
	private static function resolve_vendor_video_paths( array $media_refs, string $media_dir, string $slug ): array {
		$video_paths = array();

		if ( ! empty( $media_refs['videos'] ) && is_array( $media_refs['videos'] ) ) {
			foreach ( $media_refs['videos'] as $video_path ) {
				$video_path = trim( (string) $video_path );
				if ( '' !== $video_path ) {
					$video_paths[] = $video_path;
				}
			}
		}

		if ( ! empty( $video_paths ) ) {
			return array_values( array_unique( $video_paths ) );
		}

		$vendor_media_dir = trailingslashit( $media_dir ) . 'media/' . $slug;
		if ( ! is_dir( $vendor_media_dir ) ) {
			return array();
		}

		$video_files = glob( $vendor_media_dir . '/video*.*' );
		if ( ! is_array( $video_files ) || empty( $video_files ) ) {
			return array();
		}

		natsort( $video_files );
		foreach ( $video_files as $video_file ) {
			if ( is_file( $video_file ) ) {
				$video_paths[] = 'media/' . $slug . '/' . basename( $video_file );
			}
		}

		return array_values( array_unique( $video_paths ) );
	}

	/**
	 * Sideload a local file into the WP media library.
	 */
	private static function sideload_media( string $source_path, string $title, int $user_id = 0 ): int {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload   = wp_upload_dir();
		$dest_dir = $upload['basedir'] . '/ecomcine-demo';

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return 0;
		}

		$filename = sanitize_file_name( basename( $source_path ) );
		$dest     = $dest_dir . '/' . $filename;
		$info     = pathinfo( $filename );
		$i        = 1;
		while ( file_exists( $dest ) ) {
			$dest     = $dest_dir . '/' . $info['filename'] . '-' . $i . '.' . ( $info['extension'] ?? '' );
			$filename = $info['filename'] . '-' . $i . '.' . ( $info['extension'] ?? '' );
			$i++;
		}

		if ( ! copy( $source_path, $dest ) ) {
			return 0;
		}

		$filetype   = wp_check_filetype( $dest );
		$attachment = array(
			'guid'           => $upload['baseurl'] . '/ecomcine-demo/' . $filename,
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => max( 0, $user_id ),
		);

		$attachment_id = wp_insert_attachment( $attachment, $dest );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}

	/**
	 * Download a remote URL to a local path using WP HTTP API.
	 *
	 * @return true|\WP_Error
	 */
	private static function download_file( string $url, string $dest_path ) {
		// wp_remote_get streams the body into memory — for large files use
		// download_url() which streams to a temp file instead.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp = download_url( $url, 300 ); // 5-minute timeout.
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// rename() may fail across mount points; use copy + unlink.
		if ( ! @copy( $tmp, $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new \WP_Error( 'copy_failed', "Could not move downloaded file to {$dest_path}." );
		}
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		return true;
	}

	/**
	 * Create a unique temporary directory inside WP uploads.
	 *
	 * @param array &$result Result accumulator to record errors.
	 * @return string|null   Absolute path, or null on failure.
	 */
	private static function make_tmp_dir( array &$result ): ?string {
		$upload  = wp_upload_dir();
		$tmp_dir = $upload['basedir'] . '/ecomcine-demo-tmp-' . uniqid( '', true );
		if ( ! wp_mkdir_p( $tmp_dir ) ) {
			$result['errors'][] = 'Could not create temporary directory for demo download.';
			return null;
		}
		return $tmp_dir;
	}

	/**
	 * Recursively delete a temporary directory.
	 */
	private static function cleanup_tmp_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $dir, true );
		}
	}

	/**
	 * Search a directory recursively for a file by name.
	 *
	 * @return string|null Absolute path to the first match, or null.
	 */
	private static function find_in_dir( string $dir, string $filename ): ?string {
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) );
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getFilename() === $filename ) {
				return $file->getPathname();
			}
		}
		return null;
	}
}
