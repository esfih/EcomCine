<?php
/**
 * Social Metrics Engine
 *
 * Bright Data integration for fetching + caching social platform metrics
 * (Instagram, YouTube, LinkedIn, Facebook) per vendor.  Also contains the
 * vendor-facing dashboard UI (fetch buttons + live polling JS) and the
 * store-page Social Influence Metrics display panel.
 *
 * SECTIONS
 * 
 * §1  API key helper             tm_get_brightdata_api_key()
 * §2  Monthly snapshot helpers   tm_get_monthly_snapshot_key()
 *                                tm_collect_social_totals()
 *                                tm_snapshot_monthly_social_totals()
 *                                tm_get_social_growth()
 * §3  Score calculator           tm_compute_social_influence_score()
 * §4  Profile normalizer         tm_get_vendor_social_profiles()
 * §5  Parsers / savers           tm_parse_linkedin_interaction_count()
 *                                tm_normalize_store_instagram_metrics()
 *                                tm_normalize_store_youtube_metrics()
 * §6  Platform fetchers/snapshots
 *     Instagram:  tm_fetch_instagram_metrics() / tm_fetch_instagram_snapshot()
 *     YouTube:    tm_fetch_youtube_metrics()   / tm_fetch_youtube_snapshot()
 *     LinkedIn:   tm_fetch_linkedin_metrics()  / tm_fetch_linkedin_snapshot()
 *     Facebook:   tm_fetch_facebook_metrics()  / tm_fetch_facebook_snapshot()
 * §7  Queue functions + cron hooks
 * §8  Profile update trigger     tm_handle_social_profile_update()
 * §9  AJAX: manual fetch         wp_ajax_tm_social_manual_fetch
 * §10 AJAX: LinkedIn raw debug   wp_ajax_tm_get_linkedin_raw
 * §11 Dashboard JS / UI          wp_footer social polling + fetch buttons
 * §12 Display hook (store page)  dokan_store_profile_bottom_drawer prio 4
 *
 * NOTE: wp_ajax_tm_social_metrics_status + wp_ajax_tm_social_debug_dump are
 *       in includes/vendor-profile/vendor-profile-ajax.php (admin-only panel).
 *       They will be consolidated here in a future pass.
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;


/**
 * Social Scraper API key (set in wp-config.php or environment)
 * Checks new constant first; falls back to legacy Bright Data constants for zero-downtime migration.
 */
function tm_get_brightdata_api_key() {
	if ( defined( 'TM_SOCIAL_SCRAPER_API_KEY' ) && TM_SOCIAL_SCRAPER_API_KEY ) {
		return TM_SOCIAL_SCRAPER_API_KEY;
	}
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
			'display'  => __( 'Every 30 days (Talent Marketplace)', 'tm-store-ui' ),
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
	if ( function_exists( 'ecomcine_get_person_info' ) ) {
		$person_info = ecomcine_get_person_info( $vendor_id );
		if ( ! empty( $person_info['social'] ) && is_array( $person_info['social'] ) ) {
			return $person_info['social'];
		}
	}
	return [];
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
			'error' => ! $api_key ? 'Missing Social Scraper API key.' : 'Missing Instagram URL.',
		] ) );
		return;
	}

	$endpoint = 'https://socialstats.axiombilling.com/scrape';

	$response = wp_remote_post( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'headers' => [
			'X-API-Key'    => $api_key,
			'Content-Type' => 'application/json',
		],
		'body' => wp_json_encode( [
			'platform' => 'instagram',
			'url'      => $instagram_url,
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
			'error' => ! $api_key ? 'Missing Social Scraper API key.' : 'Missing YouTube URL.',
		] ) );
		return;
	}

	$endpoint = 'https://socialstats.axiombilling.com/scrape';

	$response = wp_remote_post( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'headers' => [
			'X-API-Key'    => $api_key,
			'Content-Type' => 'application/json',
		],
		'body' => wp_json_encode( [
			'platform' => 'youtube',
			'url'      => $youtube_url,
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
			'error' => ! $api_key ? 'Missing Social Scraper API key.' : 'Missing LinkedIn URL.',
		] ) );
		return;
	}

	$endpoint = 'https://socialstats.axiombilling.com/scrape';

	$response = wp_remote_post( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'X-API-Key'    => $api_key,
			'Content-Type' => 'application/json',
		],
		'body' => wp_json_encode( [
			'platform' => 'linkedin',
			'url'      => $linkedin_url,
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
			'error' => ! $api_key ? 'Missing Social Scraper API key.' : 'Missing Facebook URL.',
		] ) );
		return;
	}

	$endpoint = 'https://socialstats.axiombilling.com/scrape';

	$response = wp_remote_post( $endpoint, [
		'timeout' => 120,
		'connect_timeout' => 20,
		'redirection' => 3,
		'headers' => [
			'X-API-Key'    => $api_key,
			'Content-Type' => 'application/json',
		],
		'body' => wp_json_encode( [
			'platform'     => 'facebook',
			'url'          => $facebook_url,
			'num_of_posts' => 10,
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
	if ( 'ecomcine_social' !== $meta_key ) {
		return;
	}
	if ( ! is_array( $meta_value ) ) {
		return;
	}
	$social = $meta_value;
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
 * Manual social metrics fetch (vendor dashboard)
 */
add_action( 'wp_ajax_tm_social_manual_fetch', function() {
	check_ajax_referer( 'tm_social_fetch', 'nonce' );
	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) {
		wp_send_json_error( [ 'message' => 'Not authenticated.' ], 403 );
	}

	// Support admin fetching on behalf of any vendor by passing vendor_id in the request.
	// Falls back to current user for vendor self-service.
	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	if ( ! $vendor_id ) {
		$vendor_id = $current_user_id;
	}

	// Allow: profile owner, WP admin, or user with edit_users capability.
	$can_fetch = ( $current_user_id === $vendor_id )
		|| ( function_exists( 'tm_can_edit_vendor_profile' ) && tm_can_edit_vendor_profile( $vendor_id, $current_user_id ) );
	if ( ! $can_fetch ) {
		wp_send_json_error( [ 'message' => 'Not authorized.' ], 403 );
	}

	// Reuse $user_id as the target vendor for all downstream calls.
	$user_id = $vendor_id;

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
	$drawer_social_label = function_exists( 'ecomcine_profile_get_drawer_section_label' )
		? ecomcine_profile_get_drawer_section_label( 'social_metrics', 'Social Influence Metrics' )
		: 'Social Influence Metrics';
	$social_metric_config = function_exists( 'ecomcine_profile_get_social_metric_config_map' )
		? ecomcine_profile_get_social_metric_config_map()
		: array();
	$get_social_metric_setting = static function( string $platform, string $key, string $fallback ) use ( $social_metric_config ): string {
		if ( isset( $social_metric_config[ $platform ][ $key ] ) && is_string( $social_metric_config[ $platform ][ $key ] ) ) {
			$value = trim( $social_metric_config[ $platform ][ $key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return $fallback;
	};
	$is_social_metric_enabled = static function( string $platform ) use ( $social_metric_config ): bool {
		if ( ! isset( $social_metric_config[ $platform ] ) || ! is_array( $social_metric_config[ $platform ] ) ) {
			return true;
		}

		return ! array_key_exists( 'enabled', $social_metric_config[ $platform ] ) || ! empty( $social_metric_config[ $platform ]['enabled'] );
	};
	$youtube_label = $get_social_metric_setting( 'youtube', 'label', 'YouTube' );
	$instagram_label = $get_social_metric_setting( 'instagram', 'label', 'Instagram' );
	$facebook_label = $get_social_metric_setting( 'facebook', 'label', 'Facebook' );
	$linkedin_label = $get_social_metric_setting( 'linkedin', 'label', 'LinkedIn' );
	
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
			<i class="fas fa-chart-line section-title-icon"></i> <?php echo esc_html( $drawer_social_label ); ?>
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

					$_can_fetch_social = $is_owner || current_user_can( 'manage_options' ) || current_user_can( 'edit_users' );
					function render_social_url_field( $platform, $icon_class, $current_url, $status_text = '', $show_fetch_btn = false, $fetch_vendor_id = 0 ) {
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
							<div class="social-url-input-row">
								<input
									type="url"
									class="social-url-input"
									data-field="<?php echo esc_attr( $field_name ); ?>"
									data-original="<?php echo esc_attr( (string) $current_url ); ?>"
									value="<?php echo esc_attr( (string) $current_url ); ?>"
									placeholder="https://<?php echo esc_attr( strtolower( $platform ) ); ?>.com/yourprofile"
								/>
								<?php if ( $show_fetch_btn ) : ?>
									<button
										type="button"
										class="tm-social-section-fetch-btn"
										data-platform="<?php echo esc_attr( strtolower( $platform ) ); ?>"
										data-vendor-id="<?php echo esc_attr( (string) $fetch_vendor_id ); ?>"
										title="Fetch latest metrics from Bright Data"
									>Fetch</button>
								<?php endif; ?>
							</div>
						</div>
						<?php
					}
					
					if ( $is_social_metric_enabled( 'youtube' ) ) {
						render_social_url_field( $youtube_label, 'fa-youtube', $youtube_url, $youtube_status, $_can_fetch_social, $vendor_id );
					}
					if ( $is_social_metric_enabled( 'instagram' ) ) {
						render_social_url_field( $instagram_label, 'fa-instagram', $instagram_url, $instagram_status, $_can_fetch_social, $vendor_id );
					}
					if ( $is_social_metric_enabled( 'facebook' ) ) {
						render_social_url_field( $facebook_label, 'fa-facebook-square', $facebook_url, $facebook_status, $_can_fetch_social, $vendor_id );
					}
					if ( $is_social_metric_enabled( 'linkedin' ) ) {
						render_social_url_field( $linkedin_label, 'fa-linkedin', $linkedin_url, $linkedin_status, $_can_fetch_social, $vendor_id );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
		
		<div class="attribute-grid">
			
			<!-- YouTube Metrics -->
			<?php if ( $is_social_metric_enabled( 'youtube' ) ) : ?>
			<div class="social-metric-column" data-platform="youtube">
				<div class="social-header">
					<i class="fab fa-youtube social-header-icon"></i>
					<div>
						<h4 class="social-title"><?php echo esc_html( $youtube_label ); ?></h4>
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
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'followers_label', 'Subscribers' ) ); ?>: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-chart-bar stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'views_label', 'Avg Views' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $youtube_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $youtube_subscribers !== null || $youtube_avg_views !== null || $youtube_avg_reactions !== null ) : ?>
						<?php if ( $youtube_subscribers !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'followers_label', 'Subscribers' ) ); ?>: <strong class="stat-value--gold"><?php echo $format_social_number( $youtube_subscribers ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $youtube_avg_views !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-chart-bar stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'views_label', 'Avg Views' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $youtube_avg_views ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $youtube_avg_reactions !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'youtube', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $youtube_avg_reactions ); ?></strong>
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
			<?php endif; ?>
			
			<!-- Instagram Metrics -->
			<?php if ( $is_social_metric_enabled( 'instagram' ) ) : ?>
			<div class="social-metric-column" data-platform="instagram">
				<div class="social-header">
					<i class="fab fa-instagram social-header-icon"></i>
					<div>
						<h4 class="social-title"><?php echo esc_html( $instagram_label ); ?></h4>
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
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-comment-dots stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'comments_label', 'Avg Comments' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $instagram_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $instagram_followers > 0 ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold"><?php echo $format_social_number( $instagram_followers ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $instagram_avg_reactions ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-comment-dots stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'instagram', 'comments_label', 'Avg Comments' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $instagram_avg_comments ); ?></strong>
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
			<?php endif; ?>
			
			<!-- Facebook Metrics -->
			<?php if ( $is_social_metric_enabled( 'facebook' ) ) : ?>
			<div class="social-metric-column" data-platform="facebook">
				<div class="social-header">
					<i class="fab fa-facebook-square social-header-icon"></i>
					<div>
						<h4 class="social-title"><?php echo esc_html( $facebook_label ); ?></h4>
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
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'views_label', 'Avg Views' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $facebook_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php elseif ( $facebook_stats_hidden ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold">N/A</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'views_label', 'Avg Views' ) ); ?>: <strong class="stat-value--rose">N/A</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">N/A</strong>
						</div>
					<?php elseif ( $facebook_followers > 0 ) : ?>
						<div class="stat-item">
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold"><?php echo $format_social_number( $facebook_followers ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-eye stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'views_label', 'Avg Views' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $facebook_avg_views ); ?></strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-thumbs-up stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'facebook', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose"><?php echo $format_social_number( $facebook_avg_reactions ); ?></strong>
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
			<?php endif; ?>
			
			<!-- LinkedIn Metrics -->
			<?php if ( $is_social_metric_enabled( 'linkedin' ) ) : ?>
			<div class="social-metric-column" data-platform="linkedin">
				<div class="social-header">
					<i class="fab fa-linkedin social-header-icon"></i>
					<div>
						<h4 class="social-title"><?php echo esc_html( $linkedin_label ); ?></h4>
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
							<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
						<div class="stat-item">
							<i class="fas fa-user-friends stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'connections_label', 'Connections' ) ); ?>: <strong class="stat-value--rose">...</strong>
						</div>
					<?php elseif ( $linkedin_error ) : ?>
						<div class="stat-item stat-item--muted">error</div>
					<?php else : ?>
						<?php if ( $linkedin_followers > 0 ) : ?>
							<div class="stat-item">
								<i class="fas fa-users stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'followers_label', 'Followers' ) ); ?>: <strong class="stat-value--gold">&nbsp;<?php echo $format_social_number( $linkedin_followers ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( isset( $linkedin_metrics['avg_reactions'] ) && $linkedin_metrics['avg_reactions'] !== null ) : ?>
							<div class="stat-item">
								<i class="fas fa-heart stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'reactions_label', 'Avg Reactions' ) ); ?>: <strong class="stat-value--rose">&nbsp;<?php echo $format_social_number( (int) $linkedin_metrics['avg_reactions'] ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( isset( $linkedin_metrics['connections'] ) && $linkedin_metrics['connections'] ) : ?>
							<div class="stat-item">
								<i class="fas fa-user-friends stat-icon"></i> <?php echo esc_html( $get_social_metric_setting( 'linkedin', 'connections_label', 'Connections' ) ); ?>: <strong class="stat-value--rose">&nbsp;<?php echo $format_social_number( (int) $linkedin_metrics['connections'] ); ?></strong>
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
			<?php endif; ?>
			
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
