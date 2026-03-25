<?php
/**
 * Vendor Profile Completeness Engine
 *
 * Calculates multi-level profile completion for vendors.
 * Completely independent of Dokan Pro's native profile_completion system
 * (which only tracks Dokan-native fields and is payment-centric).
 *
 * Levels:
 *   Level 1 – Basic Profile    : identity + contact + demographics + category attributes
 *   Level 2 – Mediatic Profile : ≥ 6 files in vendor media library + ≥ 1 item assigned to playlist
 *   Level 3 – Cinematic        : placeholder (coming soon)
 *
 * Usage:
 *   $c = tm_vendor_completeness( $vendor_id );
 *   // $c['level1']['pct']      → integer 0-100
 *   // $c['level1']['complete'] → bool
 *   // $c['level1']['missing']  → string[] of human-readable field labels
 *   // $c['published']          → bool (dokan_enable_selling === 'yes')
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calculate vendor profile completeness across all levels.
 *
 * @param  int        $vendor_id  WP user ID of the vendor.
 * @return array|null {
 *     @type bool  $published  Whether dokan_enable_selling === 'yes'.
 *     @type array $level1 {
 *         @type bool     $complete
 *         @type int      $pct      Percentage 0-100.
 *         @type string[] $missing  Human-readable labels of incomplete fields.
 *         @type int      $done     Count of completed fields.
 *         @type int      $total    Total required fields.
 *     }
 *     @type array $level2  Same shape as $level1.
 * }
 */
function tm_vendor_completeness( int $vendor_id ) : ?array {

	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id ) {
		return null;
	}

	// ── Published state ────────────────────────────────────────────────────
	$published = get_user_meta( $vendor_id, 'dokan_enable_selling', true ) === 'yes';

	// ── Dokan profile settings (single meta read, reused everywhere) ───────
	$ps = get_user_meta( $vendor_id, 'dokan_profile_settings', true );
	if ( ! is_array( $ps ) ) {
		$ps = [];
	}

	// ── Resolve store category IDs → slugs ─────────────────────────────────
	$cat_ids = [];
	if ( ! empty( $ps['dokan_category'] ) ) {
		$cat_ids = (array) $ps['dokan_category'];
	} elseif ( ! empty( $ps['categories'] ) ) {
		$cat_ids = (array) $ps['categories'];
	}
	if ( empty( $cat_ids ) ) {
		$meta_cats = get_user_meta( $vendor_id, 'dokan_store_categories', true );
		if ( is_array( $meta_cats ) ) {
			$cat_ids = $meta_cats;
		}
	}
	if ( empty( $cat_ids ) ) {
		$term_ids = wp_get_object_terms( $vendor_id, 'store_category', [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $term_ids ) ) {
			$cat_ids = $term_ids;
		}
	}
	// Normalize: any source may contain WP_Term objects, integer IDs, or string IDs.
	// absint() cannot handle WP_Term objects, so extract term_id first.
	$cat_ids = array_map( function( $item ) {
		return ( $item instanceof WP_Term ) ? $item->term_id : $item;
	}, (array) $cat_ids );
	$cat_ids = array_values( array_filter( array_map( 'absint', $cat_ids ) ) );

	$cat_slugs = [];
	foreach ( $cat_ids as $tid ) {
		$term = get_term( $tid, 'store_category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$cat_slugs[] = $term->slug;
		}
	}

	// ── LEVEL 1: Basic Profile ─────────────────────────────────────────────
	// $l1 = [ 'Human Label' => bool (satisfied?) ]
	$l1 = [];

	// --- Identity ---

	// Talent Name
	$shop_name = '';
	if ( function_exists( 'dokan' ) && dokan()->vendor ) {
		$v = dokan()->vendor->get( $vendor_id );
		if ( $v ) {
			$shop_name = (string) $v->get_shop_name();
		}
	}
	$l1['Talent Name'] = $shop_name !== '';

	// Profile Photo (stored as attachment ID in dokan_profile_settings['gravatar'])
	$l1['Profile Photo'] = ! empty( $ps['gravatar'] );

	// Banner Image (stored as attachment ID in dokan_profile_settings['banner'])
	$l1['Banner Image'] = ! empty( $ps['banner'] );

	// Location (city text OR geolocation coordinates)
	$has_loc = ! empty( $ps['location'] );
	if ( ! $has_loc && ! empty( $ps['geolocation'] ) && is_array( $ps['geolocation'] ) ) {
		$geo     = $ps['geolocation'];
		$has_loc = ! empty( $geo['city'] )
		        || ( ! empty( $geo['latitude'] ) && ! empty( $geo['longitude'] ) );
	}
	$l1['Location'] = $has_loc;

	// Category (at least one store_category term assigned)
	$l1['Category'] = ! empty( $cat_ids );

	// --- Contact ---

	// Phone  (profile_settings['phone'], or our tm_contact_phones / tm_contact_phone_main meta)
	$has_phone = ! empty( $ps['phone'] );
	if ( ! $has_phone ) {
		$phones    = get_user_meta( $vendor_id, 'tm_contact_phones', true );
		$has_phone = is_array( $phones ) && ! empty( array_filter( $phones ) );
	}
	if ( ! $has_phone ) {
		$has_phone = (bool) get_user_meta( $vendor_id, 'tm_contact_phone_main', true );
	}
	$l1['Phone'] = $has_phone;

	// Email — check custom contact meta (same logic as store-header.php display).
	// WP's user_email always exists, but vendors must set their profile contact email.
	$has_email = (bool) get_user_meta( $vendor_id, 'tm_contact_email_main', true );
	if ( ! $has_email ) {
		$has_email = (bool) get_user_meta( $vendor_id, 'tm_contact_email', true ); // legacy
	}
	if ( ! $has_email ) {
		$_emails   = get_user_meta( $vendor_id, 'tm_contact_emails', true );
		$has_email = is_array( $_emails ) && ! empty( array_filter( $_emails ) );
	}
	$l1['Email'] = $has_email;

	// --- Demographic & Availability (8 fields) ---
	$demo_map = [
		'Birth Date'   => 'demo_birth_date',
		'Ethnicity'    => 'demo_ethnicity',
		'Languages'    => 'demo_languages',
		'Availability' => 'demo_availability',
		'Notice Time'  => 'demo_notice_time',
		'Can Travel'   => 'demo_can_travel',
		'Daily Rate'   => 'demo_daily_rate',
		'Education'    => 'demo_education',
	];
	foreach ( $demo_map as $label => $meta_key ) {
		$val        = get_user_meta( $vendor_id, $meta_key, true );
		$l1[$label] = is_array( $val )
			? ! empty( array_filter( $val ) )
			: ( $val !== '' && $val !== false && $val !== null );
	}

	// --- Category-conditional: Physical Attributes (model / artist) ---
	if ( in_array( 'model', $cat_slugs, true ) || in_array( 'artist', $cat_slugs, true ) ) {
		$phys = [
			'Height'     => 'talent_height',
			'Weight'     => 'talent_weight',
			'Waist'      => 'talent_waist',
			'Hip'        => 'talent_hip',
			'Chest'      => 'talent_chest',
			'Shoe Size'  => 'talent_shoe_size',
			'Eye Color'  => 'talent_eye_color',
			'Hair Color' => 'talent_hair_color',
		];
		foreach ( $phys as $label => $meta_key ) {
			$l1[$label] = (bool) get_user_meta( $vendor_id, $meta_key, true );
		}
	}

	// --- Category-conditional: Cameraman Equipment & Skills ---
	if ( in_array( 'cameraman', $cat_slugs, true ) ) {
		$cam = [
			'Camera Type'         => 'camera_type',
			'Experience Level'    => 'experience_level',
			'Editing Software'    => 'editing_software',
			'Specialization'      => 'specialization',
			'Years Experience'    => 'years_experience',
			'Equipment Ownership' => 'equipment_ownership',
			'Lighting Equipment'  => 'lighting_equipment',
			'Audio Equipment'     => 'audio_equipment',
			'Drone Capability'    => 'drone_capability',
		];
		foreach ( $cam as $label => $meta_key ) {
			$l1[$label] = (bool) get_user_meta( $vendor_id, $meta_key, true );
		}
	}

	// Compute L1 score from all required fields
	$l1_scoreable = $l1;

	$l1_total   = count( $l1_scoreable );
	$l1_done    = count( array_filter( $l1_scoreable ) );
	$l1_missing = array_keys( array_filter( $l1_scoreable, static fn( $v ) => ! $v ) );
	$l1_pct     = $l1_total > 0 ? (int) round( $l1_done / $l1_total * 100 ) : 0;
	$l1_complete = count( $l1_missing ) === 0;

	// ── LEVEL 2: Mediatic Profile ──────────────────────────────────────────
	// Requires ALL of:
	//   a) ≥ 6 files uploaded to the vendor's WP media library (any type).
	//   b) ≥ 1 media item assigned to the vendor's profile playlist (bio shortcodes).
	//   c) All 4 social media URLs set (YouTube, Instagram, Facebook, LinkedIn).

	// (a) Count ALL attachments authored by this vendor (uploaded via WP Media Library).
	global $wpdb;
	$library_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type   = 'attachment'
			   AND post_author = %d
			   AND post_status != 'trash'",
			$vendor_id
		)
	);

	// (b) Count items referenced in the profile playlist shortcodes (bio field).
	$playlist_count = 0;
	if ( function_exists( 'tm_get_vendor_media_playlist' ) ) {
		$_playlist = tm_get_vendor_media_playlist( $vendor_id );
		$_items    = isset( $_playlist['items'] ) && is_array( $_playlist['items'] )
			? $_playlist['items']
			: [];
		$playlist_count = count( $_items );
	}

	// (c) All 4 social URLs set (stored in dokan_profile_settings['social']).
	$_social_data  = isset( $ps['social'] ) && is_array( $ps['social'] ) ? $ps['social'] : [];
	$_social_keys  = [ 'youtube', 'instagram', 'facebook', 'linkedin' ];
	$social_count  = 0;
	foreach ( $_social_keys as $_sk ) {
		if ( ! empty( $_social_data[ $_sk ] ) ) {
			$social_count++;
		}
	}

	$l2 = [
		'6+ Files in Media Library'    => $library_count >= 6,
		'1+ Media Assigned to Playlist' => $playlist_count >= 1,
		'All 4 Social Media URLs'       => $social_count >= 4,
	];

	$l2_total    = count( $l2 );
	$l2_done     = count( array_filter( $l2 ) );
	$l2_missing  = array_keys( array_filter( $l2, static fn( $v ) => ! $v ) );
	$l2_pct      = $l2_total > 0 ? (int) round( $l2_done / $l2_total * 100 ) : 0;
	$l2_complete = count( $l2_missing ) === 0;

	return [
		'published' => $published,
		'level1'    => [
			'complete' => $l1_complete,
			'pct'      => $l1_pct,
			'missing'  => $l1_missing,
			'done'     => $l1_done,
			'total'    => $l1_total,
		],
		'level2'    => [
			'complete'  => $l2_complete,
			'pct'       => $l2_pct,
			'missing'   => $l2_missing,
			'done'      => $l2_done,
			'total'     => $l2_total,
			'library'   => $library_count,   // raw counts for messaging
			'playlist'  => $playlist_count,
			'social'    => $social_count,
		],
	];
}

/**
 * Compute and persist L1/L2 completeness flags as user meta.
 *
 * Stored as `tm_l1_complete` / `tm_l2_complete` = '1' | '0'.
 * These flags let the store-listing query filter vendors at the DB level
 * without running the full completeness engine per-vendor on every page load.
 *
 * @param int $vendor_id  WP user ID of the vendor.
 */
function tm_update_completeness_flags( int $vendor_id ) : void {
	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id ) return;
	$c = tm_vendor_completeness( $vendor_id );
	if ( ! $c ) return;
	update_user_meta( $vendor_id, 'tm_l1_complete', $c['level1']['complete'] ? '1' : '0' );
	update_user_meta( $vendor_id, 'tm_l2_complete', $c['level2']['complete'] ? '1' : '0' );
	// Flush WP's user object cache for this vendor so WP_User_Query picks up
	// the new meta values immediately (important if a persistent object cache
	// like Redis or Memcached is active).
	clean_user_cache( $vendor_id );
}

// Auto-update flags whenever a relevant meta key is written.
foreach ( [ 'added_user_meta', 'updated_user_meta' ] as $_tm_completeness_hook ) {
	add_action( $_tm_completeness_hook, function( $meta_id, $user_id, $meta_key ) {
		static $queued = [];
		$_watched = [
			'dokan_profile_settings',
			'tm_contact_email_main', 'tm_contact_emails', 'tm_contact_email',
			'tm_contact_phone_main', 'tm_contact_phones',
			'demo_birth_date', 'demo_ethnicity', 'demo_languages', 'demo_availability',
			'demo_notice_time', 'demo_can_travel', 'demo_daily_rate', 'demo_education',
			'talent_height', 'talent_weight', 'talent_waist', 'talent_hip', 'talent_chest',
			'talent_shoe_size', 'talent_eye_color', 'talent_hair_color',
			'camera_type', 'experience_level', 'editing_software', 'specialization',
			'years_experience', 'equipment_ownership', 'lighting_equipment', 'audio_equipment',
			'drone_capability',
		];
		if ( ! in_array( $meta_key, $_watched, true ) ) return;
		if ( ! empty( $queued[ $user_id ] ) ) return; // already queued this request
		$queued[ $user_id ] = true;
		if ( wp_doing_ajax() ) {
			// On AJAX: run immediately so the work finishes before wp_send_json_*() is
			// called. Deferring to 'shutdown' on AJAX causes any output produced by hooks
			// during the shutdown phase to be flushed into the HTTP stream after the JSON
			// body, corrupting the response and triggering jQuery parse errors.
			tm_update_completeness_flags( (int) $user_id );
		} else {
			// On regular page requests: defer to shutdown so all meta writes in one
			// request are batched into a single completeness recalculation.
			add_action( 'shutdown', function() use ( $user_id ) {
				tm_update_completeness_flags( (int) $user_id );
			}, 5 );
		}
	}, 10, 3 );
}

// Set initial flags when a new vendor account is created.
add_action( 'dokan_new_seller_created', function( $vendor_id ) {
	tm_update_completeness_flags( absint( $vendor_id ) );
}, 20 );

// =============================================================================
// SELF-HEALING FLAG MAINTENANCE
// On every non-AJAX, non-REST page load, find any seller whose tm_l1_complete
// flag is missing (never computed or cleared) and compute it on the spot.
// This is cheap: the DB query fetches IDs only for vendors WITHOUT the meta key,
// so on a healthy site the inner loop body never executes.
//
// A forced full-recompute for ALL vendors can be triggered via the
// WP Admin page: Appearance → Vendor Flags.
// =============================================================================
add_action( 'init', function() {

	// Only run on a regular web request (not REST, not AJAX, not CLI import).
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	// Find sellers who are missing the tm_l1_complete flag entirely.
	$unflagged_ids = get_users( [
		'role'       => 'seller',
		'fields'     => 'ID',
		'number'     => -1,
		'meta_query' => [
			[
				'key'     => 'tm_l1_complete',
				'compare' => 'NOT EXISTS',
			],
		],
	] );

	foreach ( $unflagged_ids as $_vid ) {
		tm_update_completeness_flags( (int) $_vid );
	}
}, 99 );
