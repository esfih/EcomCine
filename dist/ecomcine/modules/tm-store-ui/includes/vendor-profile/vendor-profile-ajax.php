<?php
/**
 * Vendor Profile  AJAX Handlers
 *
 * All wp_ajax_* handlers for vendor profile inline editing and media uploads.
 * Requires: tm_can_edit_vendor_profile() loaded from vendor-edit-logs.php
 *           (both files are require_once'd from functions.php before this).
 *
 * HANDLERS
 * 
 * vendor_save_attribute          inline-edit any vendor meta field
 * tm_social_metrics_status       social metrics panel status (admin)
 * tm_social_debug_dump           social metrics raw debug dump (admin)
 * vendor_update_avatar           replace vendor avatar image
 * vendor_update_banner           replace vendor banner image
 * vendor_update_media_playlist   manage vendor media gallery
 * vendor_update_store_name       rename vendor store
 * vendor_update_contact_info     save phone/email
 * vendor_update_location         save city/country
 * ajax_query_attachments_args    scope media library to current vendor
 *
 * NOTE: tm_social_metrics_status + tm_social_debug_dump will move to
 *       includes/social-metrics/ when the social engine is extracted.
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// Fix 2: Clean output-buffer helpers
// WordPress plugins (e.g. Dokan's vendor-verification module) hook into
// update_user_meta / updated_user_meta and may echo HTML during handler
// execution. That HTML accumulates in PHP's output buffer and is flushed by
// wp_ob_end_flush_all() at shutdown — AFTER the JSON body — corrupting the
// response. These wrappers discard all buffered output before sending JSON.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'tm_ajax_send_json_success' ) ) {
	function tm_ajax_send_json_success( $data = null, $status_code = null ) {
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		wp_send_json_success( $data, $status_code );
	}
	function tm_ajax_send_json_error( $data = null, $status_code = null ) {
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		wp_send_json_error( $data, $status_code );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Fix 3: Suppress Dokan Pro's address-change echo during AJAX
// Dokan Pro's vendor-verification Dashboard class hooks `update_user_meta`
// (fires BEFORE the DB write) and unconditionally echoes an HTML warning div
// whenever dokan_profile_settings is written with a changed address. On AJAX
// this HTML goes into the output buffer and corrupts the JSON response.
// We bracket that window with ob_start / ob_end_clean so the echo is silently
// discarded, leaving the buffer clean for our JSON response.
// ─────────────────────────────────────────────────────────────────────────────
if ( wp_doing_ajax() ) {
	add_action( 'update_user_meta', function( $meta_id, $user_id, $meta_key ) {
		if ( $meta_key === 'dokan_profile_settings' ) {
			ob_start(); // capture anything echoed by hooks before the DB write
		}
	}, 1, 3 );
	add_action( 'update_user_meta', function( $meta_id, $user_id, $meta_key ) {
		if ( $meta_key === 'dokan_profile_settings' && ob_get_level() > 0 ) {
			ob_end_clean(); // discard – e.g. Dokan's address-verification warning
		}
	}, 999, 3 );
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
		tm_ajax_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( ['message' => 'Not a vendor'], 403 );
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
		'talent_hair_color',
		'camera_type', 'experience_level', 'editing_software',
		'specialization', 'years_experience', 'equipment_ownership',
		'lighting_equipment', 'audio_equipment', 'drone_capability',
		'demo_ethnicity', 'demo_availability', 'demo_notice_time', 'demo_languages',
		'demo_daily_rate', 'demo_education', 'demo_birth_date', 'demo_can_travel',
		'social_youtube', 'social_instagram', 'social_facebook', 'social_linkedin',
		'store_categories'
	];
	
	if ( ! in_array( $field, $allowed_fields ) ) {
		tm_ajax_send_json_error( ['message' => 'Invalid field'], 400 );
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
					tm_ajax_send_json_error( [ 'message' => 'Please enter a valid Facebook profile or page URL.' ], 400 );
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
			tm_ajax_send_json_error( [ 'message' => 'Please enter a valid Instagram profile URL.' ], 400 );
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
		
		tm_ajax_send_json_success( [
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
			tm_ajax_send_json_error( [ 'message' => 'Store categories taxonomy missing' ], 500 );
		}

		$result = wp_set_object_terms( $user_id, $term_ids, 'store_category', false );
		if ( is_wp_error( $result ) ) {
			tm_ajax_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
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

		tm_ajax_send_json_success( [
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
	
	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( [ 'message' => 'Missing vendor or platform.' ], 400 );
	}
	if ( ! tm_can_edit_vendor_profile( $vendor_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
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
			tm_ajax_send_json_error( [ 'message' => 'Unsupported platform.' ], 400 );
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

	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( [ 'message' => 'Missing vendor or platform.' ], 400 );
	}
	if ( ! tm_can_edit_vendor_profile( $vendor_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
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

	tm_ajax_send_json_success( $response );
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
		tm_ajax_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	// Verify attachment exists and is an image
	if ( ! $avatar_id || ! wp_attachment_is_image( $avatar_id ) ) {
		tm_ajax_send_json_error( ['message' => 'Invalid image'], 400 );
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
	
	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
	}

	if ( ! $banner_id || ! wp_attachment_is_image( $banner_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Invalid image' ], 400 );
	}

	$profile_settings = get_user_meta( $user_id, 'dokan_profile_settings', true );
	if ( ! is_array( $profile_settings ) ) {
		$profile_settings = [];
	}

	$profile_settings['banner'] = $banner_id;
	update_user_meta( $user_id, 'dokan_profile_settings', $profile_settings );

	$banner_url = wp_get_attachment_image_url( $banner_id, 'full' );

	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
	}

	if ( ! in_array( $playlist_type, [ 'image', 'video', 'audio' ], true ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Invalid playlist type' ], 400 );
	}

	$ids_list = [];
	if ( is_array( $ids_raw ) ) {
		$ids_list = $ids_raw;
	} elseif ( is_string( $ids_raw ) ) {
		$ids_list = explode( ',', $ids_raw );
	}

	$ids = array_values( array_filter( array_map( 'absint', $ids_list ) ) );
	if ( empty( $ids ) && ! $clear ) {
		tm_ajax_send_json_error( [ 'message' => 'No media selected' ], 400 );
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

	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	if ( empty( $store_name ) ) {
		tm_ajax_send_json_error( ['message' => 'Name cannot be empty'], 400 );
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
	
	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	// Capture old values for logging before making changes
	$old_contact_emails = get_user_meta( $user_id, 'tm_contact_emails', true );
	$old_contact_phones = get_user_meta( $user_id, 'tm_contact_phones', true );
	$old_email_main = get_user_meta( $user_id, 'tm_contact_email_main', true );
	$old_phone_main = get_user_meta( $user_id, 'tm_contact_phone_main', true );

	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Not a vendor' ], 403 );
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

	tm_ajax_send_json_success( [
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
		tm_ajax_send_json_error( ['message' => 'Unauthorized'], 403 );
	}
	
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
		tm_ajax_send_json_error( ['message' => 'Not a vendor'], 403 );
	}
	
	if ( empty( $geo_address ) ) {
		tm_ajax_send_json_error( ['message' => 'Location cannot be empty'], 400 );
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

	// ── Write the standalone geo meta keys the vendors map reads ─────────────
	// dokan_geo_latitude / dokan_geo_longitude are separate user meta entries
	// (set by Dokan Geolocation module). dokan_profile_settings['geolocation']
	// is a different store used by Dokan Pro's address UI — we need both.
	if ( $location_obj && isset( $location_obj['center'] ) && count( $location_obj['center'] ) === 2 ) {
		// Coordinates came straight from the Mapbox geocoder result — use them.
		$lat_new = floatval( $location_obj['center'][1] );
		$lng_new = floatval( $location_obj['center'][0] );
		update_user_meta( $user_id, 'dokan_geo_latitude',  $lat_new );
		update_user_meta( $user_id, 'dokan_geo_longitude', $lng_new );
	} else {
		// No coordinates provided (user typed a free-text address without
		// selecting from the autocomplete dropdown). Re-geocode server-side
		// via the Mapbox Geocoding API so the map dot stays accurate.
		$token = dokan_get_option( 'mapbox_access_token', 'dokan_appearance', '' );
		if ( ! empty( $token ) ) {
			$geocode_url = add_query_arg(
				array(
					'access_token' => $token,
					'limit'        => 1,
					'types'        => 'address,place,locality,region,country',
				),
				'https://api.mapbox.com/geocoding/v5/mapbox.places/' . rawurlencode( $geo_address ) . '.json'
			);
			$geocode_response = wp_remote_get( $geocode_url, array( 'timeout' => 8 ) );
			if ( ! is_wp_error( $geocode_response ) ) {
				$geocode_body = json_decode( wp_remote_retrieve_body( $geocode_response ), true );
				if ( ! empty( $geocode_body['features'][0]['center'] ) ) {
					$lat_new = floatval( $geocode_body['features'][0]['center'][1] );
					$lng_new = floatval( $geocode_body['features'][0]['center'][0] );
					update_user_meta( $user_id, 'dokan_geo_latitude',  $lat_new );
					update_user_meta( $user_id, 'dokan_geo_longitude', $lng_new );
				}
			}
		}
	}

	// Log admin changes
	tm_log_admin_vendor_edit( $user_id, 'geo_location', 'updated', $old_location, $geo_address );
	
	// Get formatted display
	$geo_display = '';
	if ( function_exists( 'tm_get_vendor_geo_location_display' ) ) {
		$geo_display = tm_get_vendor_geo_location_display( $user_id, $profile_settings, $profile_settings['address'] ?? [] );
	}
	
	tm_ajax_send_json_success( [
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

// =============================================================================
// PUBLISH VENDOR STORE
// =============================================================================

/**
 * AJAX Handler: Publish vendor store (set dokan_enable_selling = yes).
 *
 * Requirements (publish):
 *  - Vendor must be authenticated via tm_can_edit_vendor_profile().
 *  - Level 1 completeness must be 100% (all basic + demographic + category fields).
 *  - Uses Dokan's Vendor::make_active() so Dokan's own hooks fire as expected.
 * Unpublish has no completeness requirement — vendor owner or admin can always
 * remove a live profile from the marketplace.
 *
 * Nonce action: 'tm_vendor_publish'
 * POST params:  vendor_id (int), nonce (string), action_type ('publish'|'unpublish')
 */
add_action( 'wp_ajax_tm_vendor_publish', function() {

	$vendor_id   = isset( $_POST['vendor_id'] )   ? absint( $_POST['vendor_id'] )       : 0;
	$action_type = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : 'publish';

	// Nonce verification (covers both publish and unpublish directions)
	check_ajax_referer( 'tm_vendor_publish', 'nonce' );

	// Permission: vendor themselves OR an admin editing the profile
	if ( ! function_exists( 'tm_can_edit_vendor_profile' ) || ! tm_can_edit_vendor_profile( $vendor_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
	}

	// Must be a Dokan vendor
	if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $vendor_id ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Not a valid vendor account.' ], 400 );
	}

	// ── UNPUBLISH ──────────────────────────────────────────────────────────────
	if ( 'unpublish' === $action_type ) {
		if ( function_exists( 'dokan' ) && dokan()->vendor ) {
			$vendor_obj = dokan()->vendor->get( $vendor_id );
			if ( $vendor_obj && method_exists( $vendor_obj, 'make_inactive' ) ) {
				$vendor_obj->make_inactive();
			} else {
				update_user_meta( $vendor_id, 'dokan_enable_selling', 'no' );
			}
		} else {
			update_user_meta( $vendor_id, 'dokan_enable_selling', 'no' );
		}
		tm_ajax_send_json_success( [
			'message'    => 'Store taken offline.',
			'vendor_id'  => $vendor_id,
			'new_status' => 'unpublished',
		] );
	}

	// ── PUBLISH ────────────────────────────────────────────────────────────────
	// Guard: completeness function must be loaded
	if ( ! function_exists( 'tm_vendor_completeness' ) ) {
		tm_ajax_send_json_error( [ 'message' => 'Completeness engine not available.' ], 500 );
	}

	// Verify completeness
	$completeness = tm_vendor_completeness( $vendor_id );
	if ( ! $completeness ) {
		tm_ajax_send_json_error( [ 'message' => 'Could not calculate profile completeness.' ], 500 );
	}

	if ( $completeness['published'] ) {
		tm_ajax_send_json_error( [ 'message' => 'Store is already published.' ], 409 );
	}

	if ( ! $completeness['level1']['complete'] ) {
		tm_ajax_send_json_error( [
			'message' => 'Profile is not yet complete.',
			'missing' => $completeness['level1']['missing'],
			'pct'     => $completeness['level1']['pct'],
		], 422 );
	}

	// Publish using Dokan API so Dokan's own action hooks fire
	if ( function_exists( 'dokan' ) && dokan()->vendor ) {
		$vendor_obj = dokan()->vendor->get( $vendor_id );
		if ( $vendor_obj ) {
			$vendor_obj->make_active();
		} else {
			// Fallback: direct meta update
			update_user_meta( $vendor_id, 'dokan_enable_selling', 'yes' );
		}
	} else {
		update_user_meta( $vendor_id, 'dokan_enable_selling', 'yes' );
	}

	// Clear the pre-onboard flag so the vendor is treated as a normal live vendor
	delete_user_meta( $vendor_id, 'tm_preonboard' );

	tm_ajax_send_json_success( [
		'message'    => 'Store published successfully.',
		'vendor_id'  => $vendor_id,
		'new_status' => 'published',
	] );
} );
