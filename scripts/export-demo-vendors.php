<?php
/**
 * Export 32 demo vendor profiles + media files from local WordPress.
 *
 * Catalog ID : data.vendors.export.demo
 * Runner     : ./scripts/wp.sh php scripts/export-demo-vendors.php
 *
 * Output:
 *   ecomcine/demo/vendor-data.json
 *   ecomcine/demo/media/<slug>/banner.<ext>
 *   ecomcine/demo/media/<slug>/gravatar.<ext>
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ec_demo_extract_video_attachment_ids' ) ) {
	/**
	 * Extract ordered video attachment IDs from a vendor biography playlist shortcode.
	 *
	 * @param string $biography Raw vendor biography.
	 * @return array<int,int>
	 */
	function ec_demo_extract_video_attachment_ids( string $biography ): array {
		$ids = [];
		if ( '' === $biography ) {
			return $ids;
		}

		$pattern = get_shortcode_regex( [ 'playlist' ] );
		if ( ! $pattern || ! preg_match_all( '/' . $pattern . '/s', $biography, $matches, PREG_SET_ORDER ) ) {
			return $ids;
		}

		foreach ( $matches as $match ) {
			$tag = isset( $match[2] ) ? (string) $match[2] : '';
			if ( 'playlist' !== $tag ) {
				continue;
			}

			$atts = shortcode_parse_atts( isset( $match[3] ) ? (string) $match[3] : '' );
			$type = isset( $atts['type'] ) ? strtolower( (string) $atts['type'] ) : 'audio';
			if ( 'video' !== $type || empty( $atts['ids'] ) ) {
				continue;
			}

			foreach ( array_map( 'trim', explode( ',', (string) $atts['ids'] ) ) as $id_raw ) {
				$id = absint( $id_raw );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}
}

// ── Demo vendor user IDs ──────────────────────────────────────────────────────
$DEMO_IDS = [ 7, 27, 15, 32, 16, 17, 8, 18, 41, 42, 40, 13, 28, 22, 19, 23, 14, 10, 24, 30, 25, 29, 31, 4, 5, 33, 9, 20, 26, 12, 21, 11 ];

// ── Usermeta keys to export ───────────────────────────────────────────────────
$META_KEYS = [
	'dokan_profile_settings',
	'dokan_enable_selling',
	'dokan_feature_seller',
	'dokan_publishing',
	'dokan_store_name',
	'dokan_admin_percentage',
	'dokan_admin_percentage_type',
	'dokan_admin_additional_fee',
	'_dokan_enable_manual_order',
	'tm_l1_complete',
	'tm_l2_complete',
	'tm_contact_email',
	'tm_contact_email_main',
	'tm_contact_emails',
	'tm_contact_phone_main',
	'tm_contact_phones',
	'talent_height',
	'talent_weight',
	'talent_waist',
	'talent_hip',
	'talent_chest',
	'talent_shoe_size',
	'talent_eye_color',
	'talent_hair_color',
	'talent_hair_style',
	'camera_type',
	'tm_social_youtube_url',
	'tm_social_facebook_url',
	'tm_social_instagram_url',
	'tm_social_linkedin_url',
];

// ── Output paths (inside container, auto-mapped to ./ecomcine/ on host) ───────
$plugin_dir = WP_PLUGIN_DIR . '/ecomcine';
$demo_dir   = $plugin_dir . '/demo';
$media_dir  = $demo_dir . '/media';

wp_mkdir_p( $demo_dir );
wp_mkdir_p( $media_dir );

$vendors     = [];
$skipped     = [];
$media_count = 0;

foreach ( $DEMO_IDS as $uid ) {
	$user = get_userdata( $uid );
	if ( ! $user ) {
		$skipped[] = $uid;
		continue;
	}

	$slug             = sanitize_title( $user->user_login );
	$vendor_media_dir = $media_dir . '/' . $slug;
	wp_mkdir_p( $vendor_media_dir );

	$vendor = [
		'user_login'   => $user->user_login,
		'user_email'   => $user->user_email,
		'display_name' => $user->display_name,
		'first_name'   => get_user_meta( $uid, 'first_name', true ),
		'last_name'    => get_user_meta( $uid, 'last_name', true ),
		'slug'         => $slug,
		'meta'         => [],
		'media'        => [],
	];

	// Collect usermeta.
	foreach ( $META_KEYS as $key ) {
		$val = get_user_meta( $uid, $key, true );
		if ( '' !== $val && false !== $val && null !== $val ) {
			$vendor['meta'][ $key ] = $val;
		}
	}

	// Resolve banner and gravatar attachments to physical files.
	$dps = isset( $vendor['meta']['dokan_profile_settings'] )
		? (array) $vendor['meta']['dokan_profile_settings']
		: [];

	foreach ( [ 'banner' => 'banner', 'gravatar' => 'gravatar' ] as $field => $label ) {
		$attachment_id = intval( $dps[ $field ] ?? 0 );
		if ( $attachment_id <= 0 ) {
			continue;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			echo "[demo-export] WARN: attachment {$attachment_id} ({$label}) for {$slug} not found on disk.\n";
			continue;
		}

		$ext  = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$dest = $vendor_media_dir . '/' . $label . '.' . $ext;

		if ( copy( $file_path, $dest ) ) {
			$vendor['media'][ $label ] = 'media/' . $slug . '/' . $label . '.' . $ext;
			// Zero out the attachment ID so the importer re-creates it.
			$dps[ $field ] = 0;
			$media_count++;
		} else {
			echo "[demo-export] WARN: failed to copy {$file_path} for {$slug}/{$label}.\n";
		}
	}

	// Write back the cleaned dokan_profile_settings.
	if ( isset( $vendor['meta']['dokan_profile_settings'] ) ) {
		$vendor['meta']['dokan_profile_settings'] = $dps;
	}

	$biography = (string) ( $dps['vendor_biography'] ?? '' );
	$video_ids = ec_demo_extract_video_attachment_ids( $biography );
	if ( ! empty( $video_ids ) ) {
		$vendor['media']['videos'] = [];
		foreach ( $video_ids as $index => $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				echo "[demo-export] WARN: video attachment {$attachment_id} for {$slug} not found on disk.\n";
				continue;
			}

			$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			$basename = 'video' . ( $index + 1 ) . '.' . $ext;
			$dest     = $vendor_media_dir . '/' . $basename;

			if ( copy( $file_path, $dest ) ) {
				$vendor['media']['videos'][] = 'media/' . $slug . '/' . $basename;
				$media_count++;
			} else {
				echo "[demo-export] WARN: failed to copy {$file_path} for {$slug}/{$basename}.\n";
			}
		}

		if ( empty( $vendor['media']['videos'] ) ) {
			unset( $vendor['media']['videos'] );
		}
	}

	$vendors[] = $vendor;
}

// ── Write JSON file ───────────────────────────────────────────────────────────
$payload = [
	'version'      => '1.0',
	'exported_at'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
	'vendor_count' => count( $vendors ),
	'vendors'      => $vendors,
];

$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$json_path = $demo_dir . '/vendor-data.json';

if ( false === file_put_contents( $json_path, $json ) ) {
	echo "[demo-export] ERROR: failed to write {$json_path}\n";
	exit( 1 );
}

echo "[demo-export] DONE: " . count( $vendors ) . " vendors, {$media_count} media files exported to ecomcine/demo/\n";

if ( ! empty( $skipped ) ) {
	echo "[demo-export] SKIPPED (user not found): " . implode( ', ', $skipped ) . "\n";
}
