<?php
/**
 * Store Lists / [dokan-stores] Shortcode — Custom Hooks & Filters
 *
 * Single source of truth for all PHP logic that powers the [dokan-stores]
 * shortcode page. Loaded via require_once from functions.php.
 *
 * CONTENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. FILTER PARTIALS     – Injects custom filter <div> groups into Dokan's
 *                          store-listing filter form via the hook
 *                          dokan_before_store_lists_filter_apply_button.
 *
 * 2. QUERY FILTERING     – Applies all custom GET params to Dokan's
 *                          WP_User_Query via dokan_seller_listing_args.
 *
 * 3. ENHANCED SEARCH     – Extends the search to match store name OR
 *                          biography text via a custom pre_user_query WHERE.
 *
 * 4. VERIFIED BADGE      – Shows a "Verified" badge in the vendor card loop
 *                          via dokan_seller_listing_after_featured.
 *
 * 5. STORE-LISTING JS    – Category ↔ filter-group visibility, apply-filter
 *                          redirect, and URL state restore via wp_footer.
 *
 * ADDING A NEW FILTER GROUP
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Create:  dokan/store-lists/your-filter.php
 *    Markup:  <div class="custom-filter-group" data-category="your-slug">…</div>
 *
 * 2. Include it in section 1 (dokan_before_store_lists_filter_apply_button).
 *
 * 3. Add matching meta_query logic in filter_dokan_seller_listing_args()
 *    (section 2).
 *
 * 4. Add the field name to the JS apply-filter handler (section 5) so the
 *    value is included in the redirect URL.
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// 0. SUPPRESS DOKAN PRO GEOLOCATION FILTER
//    Dokan Pro's geolocation module hooks `load_store_lists_filter` onto
//    `dokan_store_lists_filter_form`, which renders a location/radius input
//    field we don't use.  We remove it here so it is never rendered at all
//    (rather than rendered and then hidden with CSS).
// =============================================================================

add_action( 'init', function() {
	// Filter form (location/radius input)
	remove_action( 'dokan_store_lists_filter_form',   [ 'Dokan_Geolocation_Vendor_View', 'load_store_lists_filter' ] );
	// Map container rendered before the store loop
	remove_action( 'dokan_before_seller_listing_loop', [ 'Dokan_Geolocation_Vendor_View', 'before_seller_listing_loop' ] );
	// Closing wrappers + map rendered after the store loop
	remove_action( 'dokan_after_seller_listing_loop',  [ 'Dokan_Geolocation_Vendor_View', 'after_seller_listing_loop' ] );
	// Map injected into the footer of each vendor card
	remove_action( 'dokan_seller_listing_footer_content', [ 'Dokan_Geolocation_Vendor_View', 'seller_listing_footer_content' ], 11 );
}, 20 );

// =============================================================================
// 0c. PER-PAGE: 8 VENDORS (4 × 2 GRID)
//     Forces [dokan-stores] to show exactly 8 vendors per page so each
//     Dokan page fills one 4-column / 2-row grid; the arrow pager maps to
//     Dokan pages and only appears when there are more than 8 vendors.
// =============================================================================
add_filter( 'dokan_store_listing_per_page', function( $defaults ) {
	$defaults['per_page'] = 8;
	return $defaults;
} );

// =============================================================================
// 0d. SORT HELPER
//     Maps the ?tm_order URL param to WP_User_Query orderby/order args so
//     both the visible grid AND the showcase-data query use the same ordering.
// =============================================================================
/**
 * Return WP_User_Query sort args for a given tm_order slug.
 *
 * @param  string $tm_order  One of newest|oldest|name_az|name_za.
 * @return array             Partial WP_User_Query args (orderby, order, meta_key).
 */
function tm_store_list_sort_args( $tm_order = 'newest' ) {
	switch ( $tm_order ) {
		case 'oldest':
			return [ 'orderby' => 'registered', 'order' => 'ASC' ];
		case 'name_az':
			return [ 'orderby' => 'meta_value', 'meta_key' => 'dokan_store_name', 'order' => 'ASC' ];
		case 'name_za':
			return [ 'orderby' => 'meta_value', 'meta_key' => 'dokan_store_name', 'order' => 'DESC' ];
		case 'newest':
		default:
			return [ 'orderby' => 'registered', 'order' => 'DESC' ];
	}
}


// =============================================================================
// 1. FILTER PARTIALS
//    Injects custom filter groups into Dokan's store-listing <form>.
//
//    Template files (dokan/store-lists/):
//      demographic-filters.php    – age, ethnicity, availability… (all categories)
//      physical-attributes.php    – height, weight, eye colour… (model / artist)
//      cameraman-filters.php      – camera type, experience…     (cameraman)
//      social-metrics-filters.php – follower thresholds           (influencer)
//      open-now.php               – "Verified" checkbox           (all)
// =============================================================================

// =============================================================================
// 0. SEARCH FIELD – rendered server-side inside .store-lists-other-filter-wrap
//    Hooks before the category dropdown so the field is in the DOM immediately
//    (not delayed by JS/document.ready). CSS order:1 positions it first.
// =============================================================================
add_action( 'dokan_before_store_lists_filter_category', function() {
	$search_val = isset( $_GET['dokan_seller_search'] ) ? esc_attr( sanitize_text_field( $_GET['dokan_seller_search'] ) ) : '';
	?>
	<div class="store-search-field item">
		<label for="dokan_seller_search">&#128269; Search:</label>
		<input type="search" id="dokan_seller_search" name="dokan_seller_search"
		       placeholder="Search by name or keyword" value="<?php echo $search_val; ?>">
	</div>
	<?php
}, 5 );


// =============================================================================
// 0b. FILTER ACTIONS CELL (6th grid column)
//     GO button + View/Clear all filters links, rendered server-side inside
//     .store-lists-other-filter-wrap so they appear in the same single row as
//     the other filter fields with no JS-on-load delay.
//     Priority 30 = after featured (10) and open-now/level (also 10 via featured_store()).
// =============================================================================
add_action( 'dokan_after_store_lists_filter_category', function() {
	// Build the "Clear all filters" URL: strip known filter params, keep everything else.
	$_filter_keys = [
		'dokan_seller_search', 'dokan_seller_category',
		'verified', 'featured', 'profile_level',
		'talent_height',     'talent_weight',     'talent_waist',
		'talent_hip',        'talent_chest',      'talent_shoe_size',
		'talent_eye_color',  'talent_hair_color',
		'camera_type',       'experience_level',  'editing_software',
		'specialization',    'years_experience',  'equipment_ownership',
		'lighting_equipment','audio_equipment',   'drone_capability',
		'demo_age',          'demo_ethnicity',    'demo_languages',
		'demo_availability', 'demo_notice_time',  'demo_can_travel',
		'demo_daily_rate',   'demo_education',
	];
	$_remaining = array_diff_key( $_GET, array_flip( $_filter_keys ) );
	$_clear_url = esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) . ( $_remaining ? '?' . http_build_query( $_remaining ) : '' ) );
	?>
	<div id="filter-actions-cell">
		<span id="filter-links-wrap">
			<a href="#" id="view-all-filters-btn" class="dokan-clear-filters-link">View all filters</a>
			<a href="<?php echo $_clear_url; ?>" id="clear-all-filters-btn" class="dokan-clear-filters-link">Clear all filters</a>
		</span>
		<button id="apply-filter-btn" class="dokan-btn" type="button">GO</button>
	</div>
	<?php
}, 30 );

add_action( 'dokan_before_store_lists_filter_apply_button', function() {
	$dir = get_stylesheet_directory() . '/dokan/store-lists/';

	// Demographic & Availability – always first, shown for ALL categories.
	$tmpl = $dir . 'demographic-filters.php';
	if ( file_exists( $tmpl ) ) {
		include $tmpl;
	}

	// Physical Attributes – model / artist.
	$tmpl = $dir . 'physical-attributes.php';
	if ( file_exists( $tmpl ) ) {
		include $tmpl;
	}

	// Cameraman Equipment & Skills – cameraman category.
	$tmpl = $dir . 'cameraman-filters.php';
	if ( file_exists( $tmpl ) ) {
		include $tmpl;
	}

	// Social Influence Metrics – influencer category.
	$tmpl = $dir . 'social-metrics-filters.php';
	if ( file_exists( $tmpl ) ) {
		include $tmpl;
	}

}, 15 );


// =============================================================================
// 2. QUERY FILTERING
//    Applies all [dokan-stores] GET parameters to Dokan's WP_User_Query args.
// =============================================================================

/**
 * Apply all custom store-listing filter parameters to Dokan's seller query.
 *
 * Handles: category, enhanced search, verified badge, physical attributes,
 *          cameraman skills, and demographic/availability filters.
 *
 * @param  array $args  WP_User_Query args array passed by Dokan.
 * @return array        Modified args.
 */
function filter_dokan_seller_listing_args( $args ) {

	// ── BASE: require L1-complete vendors ──────────────────────────────────────
	// NOTE: We only add tm_l1_complete here. The dokan_enable_selling = 'yes'
	// condition is intentionally NOT added here because Dokan's own
	// Manager::get_vendors() (called later in the chain) ALWAYS appends its own
	// dokan_enable_selling condition based on $status = ['approved'].  Adding it
	// a second time creates a duplicate INNER JOIN on wp_usermeta for the same
	// meta_key, which in some WP/MySQL versions causes certain users to be
	// silently excluded from the result set.
	if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
		$args['meta_query'] = [];
	}
	if ( ! isset( $args['meta_query']['relation'] ) ) {
		$args['meta_query']['relation'] = 'AND';
	}
	$args['meta_query'][] = [
		'key'     => 'tm_l1_complete',
		'value'   => '1',
		'compare' => '=',
	];

	// ── Category ─────────────────────────────────────────────────────────────
	if ( isset( $_GET['dokan_seller_category'] ) && ! empty( $_GET['dokan_seller_category'] ) ) {
		$category_slug = sanitize_text_field( $_GET['dokan_seller_category'] );

		if ( ! isset( $args['store_category_query'] ) ) {
			$args['store_category_query'] = array();
		}

		$args['store_category_query'][] = array(
			'taxonomy' => 'store_category',
			'field'    => 'slug',
			'terms'    => $category_slug,
		);
	}

	// ── Enhanced Search: name + biography ────────────────────────────────────
	if ( isset( $_GET['dokan_seller_search'] ) && ! empty( $_GET['dokan_seller_search'] ) ) {
		$search_term = sanitize_text_field( $_GET['dokan_seller_search'] );

		// Remove Dokan's default name-only meta_query condition.
		if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			foreach ( $args['meta_query'] as $key => $query ) {
				if ( isset( $query['key'] ) && $query['key'] === 'dokan_store_name' ) {
					unset( $args['meta_query'][ $key ] );
					$args['meta_query'] = array_values( $args['meta_query'] );
				}
			}
		}

		add_filter( 'dokan_seller_listing_search_term', function() use ( $search_term ) {
			return $search_term;
		} );

		add_action( 'pre_user_query', 'dokan_enhanced_search_query', 1 );
	}

	// ── Verified vendors ──────────────────────────────────────────────────────
	if ( isset( $_GET['verified'] ) && $_GET['verified'] === 'yes' ) {
		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}
		$args['meta_query']['relation'] = 'AND';
		$args['meta_query'][] = array(
			'key'     => 'dokan_verification_status',
			'value'   => 'approved',
			'compare' => '=',
		);
	}

	// ── Helper: add one meta_query condition ──────────────────────────────────
	$add_meta_filter = function( &$args, $meta_key, $value, $compare = '=' ) {
		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}
		if ( ! isset( $args['meta_query']['relation'] ) ) {
			$args['meta_query']['relation'] = 'AND';
		}
		$args['meta_query'][] = array(
			'key'     => $meta_key,
			'value'   => $value,
			'compare' => $compare,
		);
	};

	// ── Physical Attributes (9 fields) ───────────────────────────────────────
	$physical_keys = [
		'talent_height',    'talent_weight',    'talent_waist',
		'talent_hip',       'talent_chest',     'talent_shoe_size',
		'talent_eye_color', 'talent_hair_color',
	];
	foreach ( $physical_keys as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) {
			$add_meta_filter( $args, $key, sanitize_text_field( $_GET[ $key ] ) );
		}
	}

	// ── Cameraman Skills (9 fields) ───────────────────────────────────────────
	$cameraman_keys = [
		'camera_type',       'experience_level',   'editing_software',
		'specialization',    'years_experience',   'equipment_ownership',
		'lighting_equipment','audio_equipment',    'drone_capability',
	];
	foreach ( $cameraman_keys as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) {
			$add_meta_filter( $args, $key, sanitize_text_field( $_GET[ $key ] ) );
		}
	}

	// ── Age filter (post-query, calculated from birth date) ───────────────────
	if ( isset( $_GET['demo_age'] ) && ! empty( $_GET['demo_age'] ) ) {
		$age_range = sanitize_text_field( $_GET['demo_age'] );

		add_filter( 'dokan_get_sellers', function( $sellers ) use ( $age_range ) {
			if ( empty( $sellers ) ) {
				return $sellers;
			}
			$filtered = array_filter( $sellers['users'], function( $seller ) use ( $age_range ) {
				$birth_date = get_user_meta( $seller->ID, 'demo_birth_date', true );
				return age_matches_range( $birth_date, $age_range );
			} );
			$sellers['users'] = array_values( $filtered );
			$sellers['count'] = count( $sellers['users'] );
			return $sellers;
		}, 99 );
	}

	// ── Demographic / Availability (7 fields) ────────────────────────────────
	$demographic_keys = [
		'demo_ethnicity', 'demo_languages', 'demo_availability',
		'demo_notice_time','demo_can_travel','demo_daily_rate', 'demo_education',
	];
	foreach ( $demographic_keys as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) {
			$value   = sanitize_text_field( $_GET[ $key ] );
			$compare = ( $key === 'demo_languages' ) ? 'LIKE' : '=';
			$val     = ( $key === 'demo_languages' ) ? serialize( $value ) : $value;
			$add_meta_filter( $args, $key, $val, $compare );
		}
	}

	// ── Profile Level ──────────────────────────────────────────────────────────
	if ( isset( $_GET['profile_level'] ) && ! empty( $_GET['profile_level'] ) ) {
		$_pl = sanitize_text_field( $_GET['profile_level'] );
		if ( $_pl === 'mediatic' ) {
			// L2 complete
			$args['meta_query'][] = [
				'key'     => 'tm_l2_complete',
				'value'   => '1',
				'compare' => '=',
			];
		} elseif ( $_pl === 'basic' ) {
			// L1 complete, L2 NOT yet complete
			$args['meta_query'][] = [
				'relation' => 'OR',
				[ 'key' => 'tm_l2_complete', 'value' => '0', 'compare' => '=' ],
				[ 'key' => 'tm_l2_complete', 'compare' => 'NOT EXISTS' ],
			];
		}
	}

	// ── Sort order ────────────────────────────────────────────────────────────
	$_tm_order = isset( $_GET['tm_order'] ) ? sanitize_key( $_GET['tm_order'] ) : 'newest';
	$_sort     = tm_store_list_sort_args( $_tm_order );
	$args['orderby'] = $_sort['orderby'];
	$args['order']   = $_sort['order'];
	if ( isset( $_sort['meta_key'] ) ) {
		$args['meta_key'] = $_sort['meta_key'];
	}

	return $args;
}
add_filter( 'dokan_seller_listing_args', 'filter_dokan_seller_listing_args', 20, 1 );


// =============================================================================
// 3. ENHANCED SEARCH
//    Extends WP_User_Query WHERE to match store name OR biography text.
// =============================================================================

/**
 * Append a custom WHERE clause so the search matches both dokan_store_name
 * and the serialised dokan_profile_settings (which contains the biography).
 *
 * @param WP_User_Query $query
 */
function dokan_enhanced_search_query( $query ) {
	global $wpdb;

	if ( ! isset( $query->query_vars['role__in'] ) ||
	     ! in_array( 'seller', $query->query_vars['role__in'] ) ) {
		return;
	}

	$search_term = apply_filters( 'dokan_seller_listing_search_term', '' );
	if ( empty( $search_term ) ) {
		return;
	}

	// Prevent recursion.
	remove_action( 'pre_user_query', 'dokan_enhanced_search_query', 1 );

	$like = '%' . $wpdb->esc_like( $search_term ) . '%';

	$custom_where = " AND EXISTS (
		SELECT 1 FROM {$wpdb->usermeta} um
		WHERE um.user_id = {$wpdb->users}.ID
		AND (
			(um.meta_key = 'dokan_store_name'         AND um.meta_value LIKE %s)
			OR (um.meta_key = 'dokan_profile_settings' AND um.meta_value LIKE %s)
		)
	)";

	$query->query_where .= $wpdb->prepare( $custom_where, $like, $like );
}


// =============================================================================
// 4. STORE LISTING LOOP — Verified badge
// =============================================================================

/**
 * Show a "Verified" badge inside the vendor card when the vendor has been
 * approved through Dokan's vendor verification module.
 *
 * @param WP_User $seller
 * @param array   $store_info
 */
function add_verified_badge_to_seller_listing( $seller, $store_info ) {
	if ( class_exists( '\WeDevs\DokanPro\Modules\VendorVerification\Helper' ) ) {
		$is_verified = \WeDevs\DokanPro\Modules\VendorVerification\Helper::is_seller_verified( $seller->ID );
		if ( $is_verified ) {
			echo '<div class="verified-label">' . esc_html__( 'Verified', 'dokan-lite' ) . '</div>';
		}
	}
}
add_action( 'dokan_seller_listing_after_featured', 'add_verified_badge_to_seller_listing', 10, 2 );


// =============================================================================
// 5a. SHOWCASE DATA
//     Runs a second Dokan query with the same active filters but no pagination
//     limit to collect ALL matching vendor IDs. The IDs are emitted as
//     window.tmShowcaseData for the JS pager "Showcase" button.
// =============================================================================
add_action( 'wp_footer', function() {
	$on_store_page = is_page_template( 'dokan/store-listing.php' ) ||
	                 has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'dokan-stores' );
	if ( ! $on_store_page ) {
		return;
	}

	// Apply the same filter (reads $_GET) with no pagination limit.
	// Use the same sort order as the visible grid so showcase IDs match the
	// displayed order exactly.
	$_sc_order    = isset( $_GET['tm_order'] ) ? sanitize_key( $_GET['tm_order'] ) : 'newest';
	$_sc_sort     = tm_store_list_sort_args( $_sc_order );
	$_sc_base     = array_merge(
		[ 'role__in' => [ 'seller' ], 'number' => 9999, 'offset' => 0, 'meta_query' => [] ],
		$_sc_sort
	);
	$all_args = apply_filters( 'dokan_seller_listing_args', $_sc_base, null );

	// dokan_get_sellers so the dokan_get_sellers post-filters (e.g. age) also apply.
	$all_sellers = dokan_get_sellers( $all_args );
	$all_ids     = array_values( array_map( 'intval', wp_list_pluck( $all_sellers['users'], 'ID' ) ) );
	$total       = count( $all_ids );

	// Resolve showcase page URL.
	$showcase_pages = get_pages( [
		'meta_key'   => '_wp_page_template',
		'meta_value' => 'template-talent-showcase-full.php',
		'number'     => 1,
	] );
	$showcase_url = $showcase_pages
		? esc_url( get_permalink( $showcase_pages[0]->ID ) )
		: esc_url( home_url( '/showcase/' ) );

	echo '<script>window.tmShowcaseData=' . wp_json_encode( [
		'url'   => $showcase_url,
		'total' => $total,
		'ids'   => $all_ids,
	] ) . ';</script>';
}, 990 );

// =============================================================================
// 5. STORE-LISTING PAGE JS
//    • Category ↔ filter-group visibility toggle
//    • Apply-filter button: collects all custom params → redirects
//    • Restores filter state (category, verified, attributes…) from URL
// =============================================================================

add_action( 'wp_footer', function() {
	$on_store_page = is_page_template( 'dokan/store-listing.php' ) ||
	                 has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'dokan-stores' );
	if ( ! $on_store_page ) {
		return;
	}
	?>
	<script type="text/javascript">
	(function($) {
		'use strict';

		function initStoreFilters() {

			// ── Prevent Dokan from collapsing the always-open filter form ────────────
			// Dokan Lite's init() calls slideToggle() on the form when URL filter params
			// exist (designed to OPEN a hidden form). Since we keep the form open via
			// CSS (display:block !important), slideToggle() finds it open and CLOSES it
			// instead — height-animating to 0 + overflow:hidden.
			// jQuery animations are queued asynchronously; calling stop() here, in the
			// same synchronous document.ready tick (but registered after Dokan's), clears
			// the queue before any animation frame has run.
			var $filterForm = $('#dokan-store-listing-filter-form-wrap');
			$filterForm.stop(true, false); // cancel Dokan's pending slideToggle
			$filterForm.css({height: '', overflow: ''}); // wipe any stale inline overrides

			// Rebind Cancel button: Dokan binds it to toggleForm() → slideToggle().
			// Since the form is always visible we make Cancel a no-op.
			$('#cancel-filter-btn').off('click').on('click', function(e) {
				e.preventDefault();
				e.stopImmediatePropagation();
			});

			// Declare urlParams once at the top so all restore-from-URL code can use it.
			var urlParams = new URLSearchParams( window.location.search );

			// ── Category ↔ filter-group visibility ───────────────────────────
			function updateCategorySpecificFilters( categorySlug ) {
				// Hide all category-specific groups (demographic filters controlled separately by #view-all-filters-btn).
				$('.custom-filter-group').not('.always-visible').css('display', 'none');

				if ( categorySlug ) {
					$('.custom-filter-group').not('.always-visible').each(function() {
						var categories = $(this).data('category');
						if ( categories ) {
							var arr = categories.toString().split(',').map(function(c) {
								return c.trim();
							});
							if ( arr.indexOf( categorySlug ) !== -1 ) {
								$(this).css('display', 'block');
							}
						}
					});
				}
			}

			// ── Dropdown open/close toggle ───────────────────────────────────────
			// Override Dokan Pro's handler which toggles ALL .category-box elements
			// when any .category-input is clicked. Our version scopes each trigger
			// to only its own sibling .category-box.
			$('.category-input').off('click').on('click', function(e) {
				e.stopPropagation();
				var $clicked = $(this);
				var $box     = $clicked.siblings('.category-box');

				// Close every OTHER open box and reset its arrow
				$('.category-box').not($box).slideUp();
				$('.category-input').not($clicked).find('.dokan-icon')
					.addClass('dashicons-arrow-down-alt2')
					.removeClass('dashicons-arrow-up-alt2');

				// Toggle this box and its arrow
				$box.slideToggle();
				$clicked.find('.dokan-icon')
					.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
			});

			// Close all dropdowns when clicking outside
			$(document).off('click.tm-dropdown').on('click.tm-dropdown', function(e) {
				if ( ! $(e.target).closest('.category-input, .category-box').length ) {
					$('.category-box').slideUp();
					$('.category-input .dokan-icon')
						.addClass('dashicons-arrow-down-alt2')
						.removeClass('dashicons-arrow-up-alt2');
				}
			});

			// -- Single-select category list (excludes level box) ─────────────────
			$('.store-lists-category .category-box:not(.tm-level-box) ul li')
				.off('click')
				.on('click', function(e) {
					e.preventDefault();
					var $this       = $(this);
					var wasSelected = $this.hasClass('selected');

					$('.store-lists-category .category-box:not(.tm-level-box) ul li').removeClass('selected dokan-btn-theme');

					if ( wasSelected ) {
						$('.store-lists-category .category-items:not(.tm-level-items)').text('Select a category');
						updateCategorySpecificFilters(null);
					} else {
						$this.addClass('selected');
						$('.store-lists-category .category-items:not(.tm-level-items)').text( $this.text().trim() );
						updateCategorySpecificFilters( $this.data('slug') );
					}
					// Close the dropdown after a selection
					$this.closest('.category-box').slideUp();
					$this.closest('.store-lists-category').find('.category-input .dokan-icon')
						.addClass('dashicons-arrow-down-alt2')
						.removeClass('dashicons-arrow-up-alt2');
				});

			// -- Profile Level item selection ─────────────────────────────────────
			$('.tm-level-box ul li').off('click').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $li         = $(this);
				var wasSelected = $li.hasClass('selected');

				$('.tm-level-box ul li').removeClass('selected');

				if ( wasSelected ) {
					$('#profile_level').val('');
					$('.tm-level-items').text('');
				} else {
					$li.addClass('selected');
					$('#profile_level').val( $li.data('value') );
					$('.tm-level-items').text( $li.text().trim() );
				}
				$('.tm-level-box').slideUp();
			});

			// Restore level state from URL
			var selectedLevel = urlParams.get('profile_level');
			if ( selectedLevel ) {
				var $lvlItem = $('.tm-level-box ul li[data-value="' + selectedLevel + '"]');
				if ( $lvlItem.length ) {
					$lvlItem.addClass('selected');
					$('.tm-level-items').text( $lvlItem.text().trim() );
					$('#profile_level').val( selectedLevel );
				}
			}

			// ── Restore state from URL on page load ──────────────────────────
			// PHP (category-area.php + profile-level-filter.php) already renders
			// the correct display text. JS only needs to:
			//  1. mark the active <li> selected (for click-deselect to work)
			//  2. call updateCategorySpecificFilters to show matching filter rows.
			var selectedCategorySlug = urlParams.get('dokan_seller_category');

			if ( selectedCategorySlug ) {
				var $item = $('.store-lists-category .category-box ul li[data-slug="' + selectedCategorySlug + '"]');
				if ( $item.length ) {
					$item.addClass('selected');
					// text already correct from PHP — no .text() call needed
					updateCategorySpecificFilters( selectedCategorySlug );
				}
			} else {
				updateCategorySpecificFilters(null);
			}

			// ── "View all filters" toggle ────────────────────────────────────────
			// Document delegation so this fires whether the button is in the page
			// DOM (Talents page) or injected into the overlay (showcase page).
			$(document).off('click.vaf', '#view-all-filters-btn').on('click.vaf', '#view-all-filters-btn', function(e) {
				e.preventDefault();
				var $demoFilters = $('.custom-filter-group.demographic-filters');
				if ( $demoFilters.is(':visible') ) {
					$demoFilters.hide();
					$(this).text('View all filters');
				} else {
					$demoFilters.show();
					$(this).text('Hide filters');
				}
			});

			// ── Apply-filter button ──────────────────────────────────────────
			// Document delegation guarantees the handler fires even if
			// initStoreFilters() ran before the button was injected into the DOM
			// (overlay context).  .off() before .on() prevents stacking.
			$(document).off('click.sf', '#apply-filter-btn').on('click.sf', '#apply-filter-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();

				// In overlay context use the overlay's current URL as base;
				// otherwise fall back to the page URL (standard Talents-page navigation).
				var currentUrl = new URL( window.location.href, window.location.origin );
				var params     = new URLSearchParams( currentUrl.search );

				// Always include the nonce so Dokan's shortcode populates $requested_data
				// (without it, featured/verified filters are silently ignored).
				var nonce = $('input[name="_store_filter_nonce"]').first().val();
				if ( nonce ) {
					params.set('_store_filter_nonce', nonce);
				}

				// Verified
				if ( $('#verified').is(':checked') ) {
					params.set('verified', 'yes');
				} else {
					params.delete('verified');
				}

				// Profile Level
				var profileLevelVal = $('#profile_level').val();
				if ( profileLevelVal ) {
					params.set('profile_level', profileLevelVal);
				} else {
					params.delete('profile_level');
				}

				// Search
				var searchValue = $('#dokan_seller_search').val();
				if ( searchValue && searchValue.trim() ) {
					params.set('dokan_seller_search', searchValue.trim());
				} else {
					params.delete('dokan_seller_search');
				}

				// Category
				var selectedCategory = $('.store-lists-category .category-box ul li.selected').data('slug');
				if ( selectedCategory ) {
					params.set('dokan_seller_category', selectedCategory);
				} else {
					params.delete('dokan_seller_category');
				}

				// Featured
				if ( $('#featured').is(':checked') ) {
					params.set('featured', 'yes');
				} else {
					params.delete('featured');
				}

				// Physical attributes (9 fields)
				[
					'talent_height',    'talent_weight',    'talent_waist',
					'talent_hip',       'talent_chest',     'talent_shoe_size',
					'talent_eye_color', 'talent_hair_color'
				].forEach(function(f) {
					var v = $('#' + f).val();
					if ( v ) { params.set(f, v); } else { params.delete(f); }
				});

				// Cameraman skills (9 fields)
				[
					'camera_type',        'experience_level',   'editing_software',
					'specialization',     'years_experience',   'equipment_ownership',
					'lighting_equipment', 'audio_equipment',    'drone_capability'
				].forEach(function(f) {
					var v = $('#' + f).val();
					if ( v ) { params.set(f, v); } else { params.delete(f); }
				});

				// Demographic / availability (8 fields)
				[
					'demo_age',       'demo_ethnicity',  'demo_languages',   'demo_availability',
					'demo_notice_time','demo_can_travel','demo_daily_rate',  'demo_education'
				].forEach(function(f) {
					var v = $('[name="' + f + '"]').val();
					if ( v ) { params.set(f, v); } else { params.delete(f); }
				});

				var targetUrl = currentUrl.pathname + '?' + params.toString();
				window.location.href = targetUrl;
				return false;
			});

		// ── Arrow pager + Sort/Showcase bar ──────────────────────────────────
		// Prev/next arrows replace Dokan's numbered pagination.
		// A fixed bottom bar holds the Sort dropdown (left) and Showcase button (right).

		(function() {
			var $wrap = $( '#dokan-seller-listing-wrap' ).first();
			if ( ! $wrap.length ) { return; }

			var $pag     = $wrap.find( '.pagination-container' ).first();
			var prevHref = null, nextHref = null;
			if ( $pag.length ) {
				var $pa = $pag.find( 'a.prev' ); if ( $pa.length ) { prevHref = $pa.attr( 'href' ); }
				var $na = $pag.find( 'a.next' ); if ( $na.length ) { nextHref = $na.attr( 'href' ); }
				$pag.hide();
			}

			// Remove stale elements from a previous load
			$( '#tm-card-pager' ).remove();
			$( '#tm-pager-bar' ).remove();

			// Preserve tm_order (and all active filter params) when navigating pages.
			function pagerHref( rawHref ) {
				if ( ! rawHref ) { return null; }
				try {
					var target = new URL( rawHref, window.location.origin );
					var cur    = new URLSearchParams( window.location.search );
					var tmOrd  = cur.get( 'tm_order' );
					if ( tmOrd ) { target.searchParams.set( 'tm_order', tmOrd ); }
					return target.toString();
				} catch( e ) { return rawHref; }
			}

			function makeArrow( id, label, pts, href ) {
				var $btn = $( '<button/>', { id: id, 'class': 'tm-card-arrow', 'aria-label': label } );
				$btn.html( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"' +
					' stroke="currentColor" stroke-width="2.5" stroke-linecap="round"' +
					' stroke-linejoin="round"><polyline points="' + pts + '"/></svg>' );
				if ( ! href ) {
					$btn.prop( 'disabled', true );
				} else {
					var resolved = pagerHref( href );
					$btn.on( 'click', function() { window.location.href = resolved; } );
				}
				return $btn;
			}

			var $pager = $( '<div/>', { id: 'tm-card-pager' } );
			$wrap.before( $pager );

			// ── Assemble the full pager bar (arrows, sort, showcase) ───────────
			var $bar = $( '<div/>', { id: 'tm-pager-bar' } );
			var hasContent = false;

			// Prepend "Previous" Arrow
			if ( prevHref ) {
				$bar.append( makeArrow( 'tm-card-prev', 'Previous page', '15 18 9 12 15 6', prevHref ) );
				hasContent = true;
			}

			// Add Sort and Showcase buttons (if applicable)
			if ( window.tmShowcaseData && window.tmShowcaseData.ids && window.tmShowcaseData.ids.length ) {
				var activeSortKey = new URLSearchParams( window.location.search ).get( 'tm_order' ) || 'newest';
				var sortOptions   = [
					{ value: 'newest',  label: 'Newest first'    },
					{ value: 'oldest',  label: 'Oldest first'    },
					{ value: 'name_az', label: 'Name A \u2192 Z' },
					{ value: 'name_za', label: 'Name Z \u2192 A' },
				];

				// Sort button + dropdown
				var $sortWrap = $( '<div/>', { id: 'tm-sort-wrap' } );
				var $sortBtn  = $( '<button/>', { id: 'tm-sort-btn', 'aria-label': 'Sort order', type: 'button' } );
				$sortBtn.html(
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"' +
					' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
					'<line x1="4" y1="6" x2="20" y2="6"/>' +
					'<line x1="4" y1="12" x2="14" y2="12"/>' +
					'<line x1="4" y1="18" x2="9"  y2="18"/>' +
					'</svg>'
				);

				var $sortDrop = $( '<ul/>', { id: 'tm-sort-dropdown' } );
				sortOptions.forEach( function( opt ) {
					var $li = $( '<li/>', { text: opt.label, 'data-value': opt.value } );
					if ( opt.value === activeSortKey ) { $li.addClass( 'selected' ); }
					$li.on( 'click', function( e ) {
						e.stopPropagation();
						var p = new URLSearchParams( window.location.search );
						p.set( 'tm_order', opt.value );
						// Reset to page 1 when sort changes
						p.delete( 'paged' );
						p.delete( 'page' );
						window.location.href = window.location.pathname + '?' + p.toString();
					} );
					$sortDrop.append( $li );
				} );

				$sortWrap.append( $sortBtn, $sortDrop );

				$sortBtn.on( 'click', function( e ) {
					e.stopPropagation();
					$sortDrop.toggleClass( 'is-open' );
					$sortBtn.toggleClass( 'is-open' );
				} );
				$( document ).off( 'click.tm-sort' ).on( 'click.tm-sort', function() {
					$sortDrop.removeClass( 'is-open' );
					$sortBtn.removeClass( 'is-open' );
				} );

				// Showcase button
				var d   = window.tmShowcaseData;
				var $sc = $( '<a/>', {
					id:   'tm-showcase-btn',
					href: d.url + '?tm_ids=' + d.ids.join( ',' ),
					text: '\u25b6\u2009Showcase these ' + d.total + ' talents',
				} );

				$bar.append( $sortWrap, $sc );
				hasContent = true;
			}

			// Append "Next" Arrow
			if ( nextHref ) {
				$bar.append( makeArrow( 'tm-card-next', 'Next page',     '9 6 15 12 9 18',  nextHref ) );
				hasContent = true;
			}

			// Add the vendor grid and the assembled pager bar to the main container
			$pager.append( $wrap );
			if ( hasContent ) { // Only append the bar if it has any content (arrows or buttons)
				$pager.append( $bar );
			}
		})();

		} // initStoreFilters

		$(document).ready( initStoreFilters );

	})(jQuery);
	</script>
	<?php
}, 1000 );
