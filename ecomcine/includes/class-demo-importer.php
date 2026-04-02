<?php
/**
 * EcomCine Demo Importer
 *
 * Imports the bundled demo vendor profiles (ecomcine/demo/vendor-data.json + media)
 * into the current WordPress installation.
 *
 * Upsert behaviour: if a vendor username already exists the profile meta and media
 * are updated in-place rather than skipped.  This correctly handles sites that
 * already have the old dummy vendors or a previous partial import.
 *
 * Entry points:
 *   EcomCine_Demo_Importer::run()      → returns result array (used by admin AJAX)
 *   EcomCine_Demo_Importer::run_cli()  → echoes summary line (used by WP-CLI eval)
 *
 * Catalog ID: data.vendors.import.demo
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Demo_Importer {

	/**
	 * Run the full import and return a result summary.
	 *
	 * @return array { imported: int, updated: int, errors: string[], log: string[] }
	 */
	public static function run() {
		$demo_dir  = ECOMCINE_DIR . 'demo';
		$json_path = $demo_dir . '/vendor-data.json';

		$result = [
			'imported' => 0,
			'updated'  => 0,
			'errors'   => [],
			'log'      => [],
		];

		if ( ! file_exists( $json_path ) ) {
			$result['errors'][] = 'Demo data file not found: ecomcine/demo/vendor-data.json';
			return $result;
		}

		$raw = file_get_contents( $json_path );
		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) || empty( $payload['vendors'] ) ) {
			$result['errors'][] = 'vendor-data.json is empty or malformed.';
			return $result;
		}

		foreach ( $payload['vendors'] as $vendor_data ) {
			$outcome = self::import_vendor( $vendor_data, $demo_dir, $result );
			if ( 'imported' === $outcome ) {
				$result['imported']++;
			} elseif ( 'updated' === $outcome ) {
				$result['updated']++;
			}
			// 'error' outcomes are already recorded in $result['errors']
		}

		return $result;
	}

	/**
	 * CLI entry point: echoes a summary line for WP-CLI eval.
	 */
	public static function run_cli() {
		$result = self::run();

		echo "[demo-import] DONE: {$result['imported']} vendors created, {$result['updated']} updated.\n";

		foreach ( $result['errors'] as $err ) {
			echo "[demo-import] ERROR: {$err}\n";
		}
		foreach ( $result['log'] as $msg ) {
			echo "[demo-import] {$msg}\n";
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Import or update a single vendor (upsert).
	 *
	 * @param array  $v        Vendor data from JSON.
	 * @param string $demo_dir Absolute path to the demo/ directory.
	 * @param array  &$result  Result accumulator for logging.
	 * @return string 'imported' | 'updated' | 'error'
	 */
	private static function import_vendor( array $v, $demo_dir, array &$result ) {
		$login = sanitize_user( $v['user_login'] ?? '', true );
		$email = sanitize_email( $v['user_email'] ?? '' );
		$slug  = sanitize_title( $v['slug'] ?? $login );

		if ( empty( $login ) || empty( $email ) ) {
			$result['errors'][] = "Vendor entry missing login or email (slug: {$slug}).";
			return 'error';
		}

		// Check if user already exists by login or email.
		$existing_by_login = username_exists( $login );
		$existing_by_email = email_exists( $email );
		$existing_id       = $existing_by_login ?: $existing_by_email;

		$is_update = (bool) $existing_id;

		if ( $is_update ) {
			$user_id = (int) $existing_id;
			$result['log'][] = "Updating existing vendor: {$login} (ID {$user_id})";
		} else {
			// Create the user.
			$password = wp_generate_password( 24, true, true );
			$user_id  = wp_create_user( $login, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				$result['errors'][] = "Failed to create user {$login}: " . $user_id->get_error_message();
				return 'error';
			}
		}

		// Set/update display name, first/last name.
		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => sanitize_text_field( $v['display_name'] ?? $login ),
			'first_name'   => sanitize_text_field( $v['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $v['last_name'] ?? '' ),
		] );

		// Set ecomcine_person role as primary; also assign 'seller' when Dokan is active
		// so existing Dokan-dependent code continues to work during migration.
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
			$result['log'][] = "No recognised person role found; {$login} set to subscriber.";
		}

		// Write all plain usermeta (excluding dokan_profile_settings which we handle separately).
		$meta = (array) ( $v['meta'] ?? [] );
		foreach ( $meta as $key => $value ) {
			if ( 'dokan_profile_settings' === $key ) {
				continue;
			}
			update_user_meta( $user_id, $key, $value );
		}

		// Handle dokan_profile_settings with media.
		$dps = isset( $meta['dokan_profile_settings'] )
			? (array) $meta['dokan_profile_settings']
			: [];

		// If updating, keep any existing field values not in our export schema.
		if ( $is_update ) {
			$existing_dps = get_user_meta( $user_id, 'dokan_profile_settings', true );
			if ( is_array( $existing_dps ) ) {
				$dps = array_merge( $existing_dps, $dps );
			}
		}

		$media_refs = (array) ( $v['media'] ?? [] );

		foreach ( [ 'banner' => 'banner', 'gravatar' => 'gravatar' ] as $field => $label ) {
			if ( empty( $media_refs[ $label ] ) ) {
				continue;
			}

			$src = $demo_dir . '/' . $media_refs[ $label ];
			if ( ! file_exists( $src ) ) {
				$result['log'][] = "Media not found for {$login}/{$label}: {$src}";
				continue;
			}

			$attachment_id = self::sideload_media( $src, "{$slug}-{$label}", $user_id );
			if ( $attachment_id > 0 ) {
				$dps[ $field ] = $attachment_id;
			} else {
				$result['log'][] = "Failed to sideload media for {$login}/{$label}.";
			}
		}

		// Sideload demo videos and write a playlist shortcode to vendor_biography.
		$video_paths = (array) ( $media_refs['videos'] ?? [] );
		if ( ! empty( $video_paths ) ) {
			$video_ids = [];
			foreach ( $video_paths as $i => $vid_rel ) {
				$src = $demo_dir . '/' . ltrim( $vid_rel, '/' );
				if ( ! file_exists( $src ) ) {
					$result['log'][] = "Video not found for {$login} (video" . ( $i + 1 ) . "): {$src}";
					continue;
				}
				$aid = self::sideload_media( $src, "{$slug}-video" . ( $i + 1 ), $user_id );
				if ( $aid > 0 ) {
					$video_ids[] = $aid;
				} else {
					$result['log'][] = "Failed to sideload video for {$login} (video" . ( $i + 1 ) . ").";
				}
			}
			if ( ! empty( $video_ids ) ) {
				$dps['vendor_biography'] = '[playlist type="video" ids="' . implode( ',', $video_ids ) . '"]';
				$result['log'][] = "Set video playlist for {$login}: ids=" . implode( ',', $video_ids );
			}
		}

		if ( ! empty( $dps ) ) {
			update_user_meta( $user_id, 'dokan_profile_settings', $dps );
		}

		// Write ecomcine_* canonical meta (authoritative; Dokan meta kept for compat).
		$ecomcine_info = array(
			'ecomcine_store_name' => sanitize_text_field( $dps['store_name'] ?? '' ),
			'ecomcine_bio'        => wp_kses_post( $dps['vendor_biography'] ?? '' ),
			'ecomcine_phone'      => sanitize_text_field( $dps['phone'] ?? '' ),
			'ecomcine_banner_id'  => (int) ( $dps['banner'] ?? 0 ),
			'ecomcine_avatar_id'  => (int) ( $dps['gravatar'] ?? 0 ),
			'ecomcine_address'    => is_array( $dps['address'] ?? null ) ? $dps['address'] : array(),
			'ecomcine_social'     => is_array( $dps['social'] ?? null ) ? $dps['social'] : array(),
			'ecomcine_enabled'    => '1',
		);
		foreach ( $ecomcine_info as $meta_key => $meta_value ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}

		// Always guarantee the two flags every demo vendor requires,
		// regardless of what the JSON export contained.
		update_user_meta( $user_id, 'dokan_enable_selling', 'yes' );
		update_user_meta( $user_id, 'tm_l1_complete', '1' );

		// Assign EcomCine person categories.
		// Prefer the top-level 'store_categories' key (array of slugs);
		// fall back to legacy dps['categories'] objects for backward compat.
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
			$category_objects = isset( $dps['categories'] ) ? (array) $dps['categories'] : [];
			foreach ( $category_objects as $cat ) {
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

		// Also keep store_category taxonomy assignments when Dokan is present.
		if ( ! empty( $category_slugs ) && taxonomy_exists( 'store_category' ) ) {
			$term_ids = [];
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

		if ( $is_update ) {
			return 'updated';
		}

		$result['log'][] = "Created vendor: {$login} (ID {$user_id})";
		return 'imported';
	}

	/**
	 * Copy a bundled demo media file into the uploads directory and register it as a WP attachment.
	 *
	 * @param string $source_path Absolute path to the source file in demo/media/.
	 * @param string $title       Attachment title.
	 * @param int    $user_id     Associated user ID (used as attachment post author).
	 * @return int Attachment ID, or 0 on failure.
	 */
	private static function sideload_media( $source_path, $title, $user_id = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_upload_dir();
		$dest_dir = $upload['basedir'] . '/ecomcine-demo';

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return 0;
		}

		$filename = sanitize_file_name( basename( $source_path ) );
		$dest     = $dest_dir . '/' . $filename;

		// Avoid name collisions by appending a suffix.
		$i = 1;
		$info = pathinfo( $filename );
		while ( file_exists( $dest ) ) {
			$dest     = $dest_dir . '/' . $info['filename'] . '-' . $i . '.' . $info['extension'];
			$filename = $info['filename'] . '-' . $i . '.' . $info['extension'];
			$i++;
		}

		if ( ! copy( $source_path, $dest ) ) {
			return 0;
		}

		$filetype = wp_check_filetype( $dest );
		$attachment = [
			'guid'           => $upload['baseurl'] . '/ecomcine-demo/' . $filename,
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => max( 0, (int) $user_id ),
		];

		$attachment_id = wp_insert_attachment( $attachment, $dest );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}
}
