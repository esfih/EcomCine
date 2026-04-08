<?php
/**
 * Standalone Talents filter form.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

$search_value = function_exists( 'tm_store_ui_get_listing_request_value' )
	? tm_store_ui_get_listing_request_value( array( 'ecomcine_person_search' ) )
	: '';
$selected_slug = function_exists( 'tm_store_ui_get_listing_request_value' )
	? tm_store_ui_get_listing_request_value( array( 'ecomcine_person_category' ) )
	: '';
$selected_level   = isset( $_GET['profile_level'] ) ? sanitize_text_field( wp_unslash( $_GET['profile_level'] ) ) : '';
$selected_country = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
$is_featured      = isset( $_GET['featured'] ) && 'yes' === sanitize_text_field( wp_unslash( $_GET['featured'] ) );
$is_verified      = isset( $_GET['verified'] ) && 'yes' === sanitize_text_field( wp_unslash( $_GET['verified'] ) );

$raw_categories = class_exists( 'EcomCine_Person_Category_Registry', false )
	? EcomCine_Person_Category_Registry::get_all()
	: array();

$categories          = array();
$seen_category_slugs = array();
$selected_slug_key   = sanitize_title( $selected_slug );

foreach ( (array) $raw_categories as $category ) {
	if ( ! is_array( $category ) ) {
		continue;
	}

	$slug = isset( $category['slug'] ) ? sanitize_title( (string) $category['slug'] ) : '';
	$name = isset( $category['name'] ) ? trim( (string) $category['name'] ) : '';

	if ( '' !== $name && ctype_digit( $name ) && function_exists( 'tm_store_ui_resolve_legacy_category_token' ) ) {
		$resolved = tm_store_ui_resolve_legacy_category_token( $name );
		if ( '' !== $resolved ) {
			$name = $resolved;
		}
	} elseif ( '' === $name && '' !== $slug && ctype_digit( $slug ) && function_exists( 'tm_store_ui_resolve_legacy_category_token' ) ) {
		$name = tm_store_ui_resolve_legacy_category_token( $slug );
	}

	$name = trim( $name );
	if ( '' === $name && '' !== $slug && ! ctype_digit( $slug ) ) {
		$name = ucwords( str_replace( '-', ' ', $slug ) );
	}

	if ( '' === $slug || '' === $name || ctype_digit( $name ) || isset( $seen_category_slugs[ $slug ] ) ) {
		continue;
	}

	$seen_category_slugs[ $slug ] = true;
	$category['slug']             = $slug;
	$category['name']             = $name;
	$categories[]                 = $category;
}

$selected_label = '';
foreach ( (array) $categories as $category ) {
	if ( ! isset( $category['slug'] ) || $category['slug'] !== $selected_slug_key ) {
		continue;
	}
	$selected_label = isset( $category['name'] ) ? (string) $category['name'] : '';
	break;
}

$level_labels = array(
	'basic'     => __( 'Basic', 'tm-store-ui' ),
	'mediatic'  => __( 'Mediatic', 'tm-store-ui' ),
	'cinematic' => __( 'Cinematic', 'tm-store-ui' ),
);

// Build distinct country list from canonical geo address meta.
global $wpdb;
$_raw_addrs        = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'ecomcine_geo_address' AND meta_value != ''" );
$available_countries = array();
foreach ( (array) $_raw_addrs as $_addr ) {
	$_parts = array_values( array_filter( array_map( 'trim', explode( ',', $_addr ) ), 'strlen' ) );
	if ( empty( $_parts ) ) {
		continue;
	}
	$_cname = end( $_parts );
	// Skip raw lat/lng tokens.
	if ( preg_match( '/^-?\d+(\.\d+)?$/', $_cname ) ) {
		continue;
	}
	$available_countries[ $_cname ] = true;
}
$available_countries = array_keys( $available_countries );
sort( $available_countries );

$attributes_options = function_exists( 'get_talent_physical_attributes_options' ) ? get_talent_physical_attributes_options() : array();
$cameraman_options  = function_exists( 'get_cameraman_filter_options' ) ? get_cameraman_filter_options() : array();

$clear_filter_keys = array(
	'ecomcine_person_search', 'ecomcine_person_category', 'verified', 'featured', 'profile_level', 'country',
	'talent_height', 'talent_weight', 'talent_waist', 'talent_hip', 'talent_chest', 'talent_shoe_size',
	'talent_eye_color', 'talent_hair_color', 'camera_type', 'experience_level', 'editing_software',
	'specialization', 'years_experience', 'equipment_ownership', 'lighting_equipment', 'audio_equipment',
	'drone_capability', 'demo_age', 'demo_ethnicity', 'demo_languages', 'demo_availability', 'demo_notice_time',
	'demo_can_travel', 'demo_daily_rate', 'demo_education',
);
$remaining_args = array_diff_key( $_GET, array_flip( $clear_filter_keys ) );
$clear_url      = esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) . ( $remaining_args ? '?' . http_build_query( $remaining_args ) : '' ) );
?>

<form id="ecomcine-person-listing-filter-form" class="ecomcine-person-listing__filters" method="get" action="<?php echo esc_url( get_permalink( get_queried_object_id() ) ?: ( function_exists( 'ecomcine_get_person_listing_url' ) ? ecomcine_get_person_listing_url() : home_url( '/talents/' ) ) ); ?>">
	<div class="store-lists-other-filter-wrap ecomcine-person-filter-bar">
		<div class="store-search-field item ecomcine-person-filter-field">
			<label for="ecomcine-person-search">&#128269; <?php esc_html_e( 'Search:', 'tm-store-ui' ); ?></label>
			<input type="search" id="ecomcine-person-search" name="ecomcine_person_search" class="ecomcine-form-control" placeholder="<?php echo esc_attr__( 'Search by name or keyword', 'tm-store-ui' ); ?>" value="<?php echo esc_attr( $search_value ); ?>">
		</div>

		<?php if ( ! empty( $categories ) ) : ?>
			<div class="store-lists-category item ecomcine-person-filter-field">
				<div class="category-input ecomcine-person-filter-toggle">
					<span class="category-label"><?php esc_html_e( 'Category:', 'tm-store-ui' ); ?></span>
					<span class="category-items"><?php echo esc_html( $selected_label ?: __( 'All Categories', 'tm-store-ui' ) ); ?></span>
					<span class="ecomcine-icon dashicons dashicons-arrow-down-alt2"></span>
				</div>
				<div class="category-box ecomcine-person-category" style="display:none;">
					<ul>
						<?php foreach ( $categories as $category ) : ?>
							<li data-slug="<?php echo esc_attr( (string) $category['slug'] ); ?>"<?php echo ( isset( $category['slug'] ) && $category['slug'] === $selected_slug_key ) ? ' class="selected ecomcine-btn-primary"' : ''; ?>><?php echo esc_html( (string) $category['name'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<div class="featured item ecomcine-person-filter-field">
			<label for="ecomcine-featured"><?php esc_html_e( 'Featured', 'tm-store-ui' ); ?></label>
			<input type="checkbox" class="ecomcine-toggle-checkbox" id="ecomcine-featured" name="featured" value="yes" <?php checked( $is_featured, true ); ?>>
		</div>

		<div class="open-now item ecomcine-person-filter-field">
			<label for="ecomcine-verified"><?php esc_html_e( 'Verified:', 'tm-store-ui' ); ?></label>
			<input type="checkbox" class="ecomcine-toggle-checkbox" id="ecomcine-verified" name="verified" value="yes" <?php checked( $is_verified, true ); ?>>
		</div>

		<div class="item store-lists-category tm-level-filter-wrap ecomcine-person-filter-field">
			<input type="hidden" id="ecomcine-profile-level" name="profile_level" value="<?php echo esc_attr( $selected_level ); ?>">
			<div class="category-input ecomcine-person-filter-toggle" id="ecomcine-level-filter-toggle">
				<span class="category-label" style="color:#D4AF37;font-weight:600;"><?php esc_html_e( 'Level:', 'tm-store-ui' ); ?></span>
				<span class="tm-level-items"><?php echo esc_html( $selected_level && isset( $level_labels[ $selected_level ] ) ? $level_labels[ $selected_level ] : '' ); ?></span>
				<span class="category-arrow" aria-hidden="true">&#9662;</span>
			</div>
			<div class="category-box tm-level-box" style="display:none;">
				<ul>
					<li data-value="basic"><?php esc_html_e( 'Basic', 'tm-store-ui' ); ?></li>
					<li data-value="mediatic"><?php esc_html_e( 'Mediatic', 'tm-store-ui' ); ?></li>
					<li data-value="cinematic"><?php esc_html_e( 'Cinematic', 'tm-store-ui' ); ?></li>
				</ul>
			</div>
		</div>

		<?php if ( ! empty( $available_countries ) ) : ?>
		<div class="item store-lists-category tm-country-filter-wrap ecomcine-person-filter-field">
			<input type="hidden" id="ecomcine-country-filter" name="country" value="<?php echo esc_attr( $selected_country ); ?>">
			<div class="category-input ecomcine-person-filter-toggle" id="ecomcine-country-filter-toggle">
				<span class="category-label" style="color:#D4AF37;font-weight:600;"><?php esc_html_e( 'Country:', 'tm-store-ui' ); ?></span>
				<span class="tm-country-items"><?php echo esc_html( $selected_country ); ?></span>
				<span class="category-arrow" aria-hidden="true">&#9662;</span>
			</div>
			<div class="category-box tm-country-box" style="display:none;">
				<ul>
				<?php foreach ( $available_countries as $_country_name ) : ?>
					<li data-value="<?php echo esc_attr( $_country_name ); ?>"<?php echo ( $selected_country === $_country_name ) ? ' class="selected ecomcine-btn-primary"' : ''; ?>><?php echo esc_html( $_country_name ); ?></li>
				<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endif; ?>

		<div id="ecomcine-filter-actions-cell">
			<span id="ecomcine-filter-links-wrap">
				<a href="#" id="ecomcine-view-all-filters-btn" class="ecomcine-filter-link"><?php esc_html_e( 'View all filters', 'tm-store-ui' ); ?></a>
				<a href="<?php echo $clear_url; ?>" id="ecomcine-clear-all-filters-btn" class="ecomcine-filter-link"><?php esc_html_e( 'Clear all filters', 'tm-store-ui' ); ?></a>
			</span>
			<button id="ecomcine-apply-filters-btn" class="ecomcine-filter-apply" type="button"><?php esc_html_e( 'Go', 'tm-store-ui' ); ?></button>
		</div>
	</div>

	<?php
	$current_age          = isset( $_GET['demo_age'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_age'] ) ) : '';
	$current_ethnicity    = isset( $_GET['demo_ethnicity'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_ethnicity'] ) ) : '';
	$current_languages    = isset( $_GET['demo_languages'] ) ? (array) wp_unslash( $_GET['demo_languages'] ) : array();
	$current_availability = isset( $_GET['demo_availability'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_availability'] ) ) : '';
	$current_notice       = isset( $_GET['demo_notice_time'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_notice_time'] ) ) : '';
	$current_travel       = isset( $_GET['demo_can_travel'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_can_travel'] ) ) : '';
	$current_rate         = isset( $_GET['demo_daily_rate'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_daily_rate'] ) ) : '';
	$current_education    = isset( $_GET['demo_education'] ) ? sanitize_text_field( wp_unslash( $_GET['demo_education'] ) ) : '';
	?>
	<div class="custom-filter-group demographic-filters always-visible" data-category="model,artist,cameraman,actor,tv-host" style="display:none; border-top:none !important; margin-top:10px !important; padding-top:0 !important;">
		<div class="filter-group-items" style="grid-template-columns: repeat(8, 1fr) !important; gap: 10px !important;">
			<div class="filter-item attribute-filter">
				<label for="filter_demo_age">📅 <?php esc_html_e( 'Age:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_age" name="demo_age" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<optgroup label="<?php echo esc_attr__( 'Age Ranges', 'tm-store-ui' ); ?>">
						<option value="18-25" <?php selected( $current_age, '18-25' ); ?>>18-25</option>
						<option value="26-35" <?php selected( $current_age, '26-35' ); ?>>26-35</option>
						<option value="36-45" <?php selected( $current_age, '36-45' ); ?>>36-45</option>
						<option value="46-55" <?php selected( $current_age, '46-55' ); ?>>46-55</option>
						<option value="56-65" <?php selected( $current_age, '56-65' ); ?>>56-65</option>
						<option value="66+" <?php selected( $current_age, '66+' ); ?>>66+</option>
					</optgroup>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_ethnicity">🌍 <?php esc_html_e( 'Ethnicity:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_ethnicity" name="demo_ethnicity" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="caucasian" <?php selected( $current_ethnicity, 'caucasian' ); ?>><?php esc_html_e( 'Caucasian', 'tm-store-ui' ); ?></option>
					<option value="african" <?php selected( $current_ethnicity, 'african' ); ?>><?php esc_html_e( 'African', 'tm-store-ui' ); ?></option>
					<option value="asian" <?php selected( $current_ethnicity, 'asian' ); ?>><?php esc_html_e( 'Asian', 'tm-store-ui' ); ?></option>
					<option value="hispanic" <?php selected( $current_ethnicity, 'hispanic' ); ?>><?php esc_html_e( 'Hispanic/Latino', 'tm-store-ui' ); ?></option>
					<option value="middle_eastern" <?php selected( $current_ethnicity, 'middle_eastern' ); ?>><?php esc_html_e( 'Middle Eastern', 'tm-store-ui' ); ?></option>
					<option value="native_american" <?php selected( $current_ethnicity, 'native_american' ); ?>><?php esc_html_e( 'Native American', 'tm-store-ui' ); ?></option>
					<option value="pacific_islander" <?php selected( $current_ethnicity, 'pacific_islander' ); ?>><?php esc_html_e( 'Pacific Islander', 'tm-store-ui' ); ?></option>
					<option value="mixed" <?php selected( $current_ethnicity, 'mixed' ); ?>><?php esc_html_e( 'Mixed/Other', 'tm-store-ui' ); ?></option>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_languages">💬 <?php esc_html_e( 'Languages:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_languages" name="demo_languages" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<?php foreach ( array( 'english','spanish','french','german','italian','portuguese','arabic','chinese','japanese','korean','hindi','russian' ) as $language ) : ?>
						<option value="<?php echo esc_attr( $language ); ?>" <?php selected( in_array( $language, $current_languages, true ), true ); ?>><?php echo esc_html( ucfirst( $language ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_availability">🕒 <?php esc_html_e( 'Availability:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_availability" name="demo_availability" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="part-time" <?php selected( $current_availability, 'part-time' ); ?>><?php esc_html_e( 'Part-time', 'tm-store-ui' ); ?></option>
					<option value="full-time" <?php selected( $current_availability, 'full-time' ); ?>><?php esc_html_e( 'Full-time', 'tm-store-ui' ); ?></option>
					<option value="on-demand" <?php selected( $current_availability, 'on-demand' ); ?>><?php esc_html_e( 'On-demand', 'tm-store-ui' ); ?></option>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_notice_time">🔔 <?php esc_html_e( 'Notice Time:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_notice_time" name="demo_notice_time" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="in_days" <?php selected( $current_notice, 'in_days' ); ?>><?php esc_html_e( 'in Days', 'tm-store-ui' ); ?></option>
					<option value="in_weeks" <?php selected( $current_notice, 'in_weeks' ); ?>><?php esc_html_e( 'in Weeks', 'tm-store-ui' ); ?></option>
					<option value="in_months" <?php selected( $current_notice, 'in_months' ); ?>><?php esc_html_e( 'in Months', 'tm-store-ui' ); ?></option>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_can_travel">✈️ <?php esc_html_e( 'Can Travel:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_can_travel" name="demo_can_travel" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="yes" <?php selected( $current_travel, 'yes' ); ?>><?php esc_html_e( 'Yes', 'tm-store-ui' ); ?></option>
					<option value="no" <?php selected( $current_travel, 'no' ); ?>><?php esc_html_e( 'No', 'tm-store-ui' ); ?></option>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_daily_rate">💰 <?php esc_html_e( 'Daily Rate:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_daily_rate" name="demo_daily_rate" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="under_1k" <?php selected( $current_rate, 'under_1k' ); ?>>&lt;$1K</option>
					<option value="1k_to_2k" <?php selected( $current_rate, '1k_to_2k' ); ?>>$1K to $2K</option>
					<option value="3k_to_5k" <?php selected( $current_rate, '3k_to_5k' ); ?>>$3K to $5K</option>
					<option value="over_5k" <?php selected( $current_rate, 'over_5k' ); ?>>&gt;$5K</option>
				</select>
			</div>
			<div class="filter-item attribute-filter">
				<label for="filter_demo_education">🎓 <?php esc_html_e( 'Education:', 'tm-store-ui' ); ?></label>
				<select id="filter_demo_education" name="demo_education" class="ecomcine-form-control">
					<option value=""><?php esc_html_e( 'Select', 'tm-store-ui' ); ?></option>
					<option value="doctorate" <?php selected( $current_education, 'doctorate' ); ?>><?php esc_html_e( 'Doctorate', 'tm-store-ui' ); ?></option>
					<option value="masters" <?php selected( $current_education, 'masters' ); ?>><?php esc_html_e( 'Master\'s Degree', 'tm-store-ui' ); ?></option>
					<option value="bachelors" <?php selected( $current_education, 'bachelors' ); ?>><?php esc_html_e( 'Bachelor\'s Degree', 'tm-store-ui' ); ?></option>
					<option value="associates" <?php selected( $current_education, 'associates' ); ?>><?php esc_html_e( 'Associate\'s Degree', 'tm-store-ui' ); ?></option>
					<option value="diploma" <?php selected( $current_education, 'diploma' ); ?>><?php esc_html_e( 'Diploma', 'tm-store-ui' ); ?></option>
					<option value="high_school" <?php selected( $current_education, 'high_school' ); ?>><?php esc_html_e( 'High School', 'tm-store-ui' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<?php $physical_values = array(
		'height'     => isset( $_GET['talent_height'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_height'] ) ) : '',
		'weight'     => isset( $_GET['talent_weight'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_weight'] ) ) : '',
		'waist'      => isset( $_GET['talent_waist'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_waist'] ) ) : '',
		'hip'        => isset( $_GET['talent_hip'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_hip'] ) ) : '',
		'chest'      => isset( $_GET['talent_chest'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_chest'] ) ) : '',
		'shoe_size'  => isset( $_GET['talent_shoe_size'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_shoe_size'] ) ) : '',
		'eye_color'  => isset( $_GET['talent_eye_color'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_eye_color'] ) ) : '',
		'hair_color' => isset( $_GET['talent_hair_color'] ) ? sanitize_text_field( wp_unslash( $_GET['talent_hair_color'] ) ) : '',
	); ?>
	<div class="custom-filter-group physical-attributes-filters" data-category="model,artist">
		<div class="filter-group-title"><span class="filter-group-heading"><?php esc_html_e( 'Physical Attributes', 'tm-store-ui' ); ?></span></div>
		<div class="filter-group-items">
			<?php foreach ( array(
				'talent_height' => array( '📏', __( 'Height', 'tm-store-ui' ), $attributes_options['height'] ?? array() ),
				'talent_weight' => array( '⚖️', __( 'Weight', 'tm-store-ui' ), $attributes_options['weight'] ?? array() ),
				'talent_waist' => array( '📐', __( 'Waist', 'tm-store-ui' ), $attributes_options['waist'] ?? array() ),
				'talent_hip' => array( '📐', __( 'Hip', 'tm-store-ui' ), $attributes_options['hip'] ?? array() ),
				'talent_chest' => array( '📐', __( 'Chest', 'tm-store-ui' ), $attributes_options['chest'] ?? array() ),
				'talent_shoe_size' => array( '👟', __( 'Shoe Size', 'tm-store-ui' ), $attributes_options['shoe_size'] ?? array() ),
				'talent_eye_color' => array( '👁️', __( 'Eye Color', 'tm-store-ui' ), $attributes_options['eye_color'] ?? array() ),
				'talent_hair_color' => array( '💇', __( 'Hair Color', 'tm-store-ui' ), $attributes_options['hair_color'] ?? array() ),
			) as $field_key => $field_data ) : ?>
				<?php list( $emoji, $label, $options ) = $field_data; $value_key = str_replace( 'talent_', '', $field_key ); ?>
				<div class="filter-item attribute-filter">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $emoji . ' ' . $label . ':' ); ?></label>
					<select name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control">
						<?php foreach ( (array) $options as $value => $label_text ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $physical_values[ $value_key ] ?? '', $value ); ?>><?php echo esc_html( $label_text ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php $cameraman_values = array(
		'camera_type' => isset( $_GET['camera_type'] ) ? sanitize_text_field( wp_unslash( $_GET['camera_type'] ) ) : '',
		'experience_level' => isset( $_GET['experience_level'] ) ? sanitize_text_field( wp_unslash( $_GET['experience_level'] ) ) : '',
		'editing_software' => isset( $_GET['editing_software'] ) ? sanitize_text_field( wp_unslash( $_GET['editing_software'] ) ) : '',
		'specialization' => isset( $_GET['specialization'] ) ? sanitize_text_field( wp_unslash( $_GET['specialization'] ) ) : '',
		'years_experience' => isset( $_GET['years_experience'] ) ? sanitize_text_field( wp_unslash( $_GET['years_experience'] ) ) : '',
		'equipment_ownership' => isset( $_GET['equipment_ownership'] ) ? sanitize_text_field( wp_unslash( $_GET['equipment_ownership'] ) ) : '',
		'lighting_equipment' => isset( $_GET['lighting_equipment'] ) ? sanitize_text_field( wp_unslash( $_GET['lighting_equipment'] ) ) : '',
		'audio_equipment' => isset( $_GET['audio_equipment'] ) ? sanitize_text_field( wp_unslash( $_GET['audio_equipment'] ) ) : '',
		'drone_capability' => isset( $_GET['drone_capability'] ) ? sanitize_text_field( wp_unslash( $_GET['drone_capability'] ) ) : '',
	); ?>
	<div class="custom-filter-group cameraman-filters" data-category="cameraman">
		<div class="filter-group-title"><span class="filter-group-heading"><?php esc_html_e( 'Equipment & Skills', 'tm-store-ui' ); ?></span></div>
		<div class="filter-group-items">
			<?php foreach ( array(
				'camera_type' => array( '📷', __( 'Camera Type', 'tm-store-ui' ), $cameraman_options['camera_type'] ?? array() ),
				'experience_level' => array( '⭐', __( 'Experience Level', 'tm-store-ui' ), $cameraman_options['experience_level'] ?? array() ),
				'editing_software' => array( '🎬', __( 'Editing Software', 'tm-store-ui' ), $cameraman_options['editing_software'] ?? array() ),
				'specialization' => array( '🎯', __( 'Specialization', 'tm-store-ui' ), $cameraman_options['specialization'] ?? array() ),
				'years_experience' => array( '📅', __( 'Years of Experience', 'tm-store-ui' ), $cameraman_options['years_experience'] ?? array() ),
				'equipment_ownership' => array( '🎥', __( 'Equipment Ownership', 'tm-store-ui' ), $cameraman_options['equipment_ownership'] ?? array() ),
				'lighting_equipment' => array( '💡', __( 'Lighting Equipment', 'tm-store-ui' ), $cameraman_options['lighting_equipment'] ?? array() ),
				'audio_equipment' => array( '🎤', __( 'Audio Equipment', 'tm-store-ui' ), $cameraman_options['audio_equipment'] ?? array() ),
				'drone_capability' => array( '🚁', __( 'Drone/Aerial', 'tm-store-ui' ), $cameraman_options['drone_capability'] ?? array() ),
			) as $field_key => $field_data ) : ?>
				<?php list( $emoji, $label, $options ) = $field_data; ?>
				<div class="filter-item cameraman-filter">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $emoji . ' ' . $label . ':' ); ?></label>
					<select name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control">
						<?php foreach ( (array) $options as $value => $label_text ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $cameraman_values[ $field_key ], $value ); ?>><?php echo esc_html( $label_text ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="custom-filter-group social-metrics-filters" data-category="influencer">
		<h3 class="filter-section-title" style="color:#D4AF37;font-size:14px;font-weight:600;margin-bottom:15px;text-transform:uppercase;letter-spacing:0.5px;">📊 <?php esc_html_e( 'Social Influence Metrics', 'tm-store-ui' ); ?></h3>
		<div class="filter-group-items" style="grid-template-columns: repeat(5, 1fr) !important; gap: 15px !important;">
			<?php foreach ( array(
				'filter_youtube_followers' => array( 'youtube_followers', 'YouTube', '#FF0000', 'youtube', array( '1000' => '1K+', '10000' => '10K+', '50000' => '50K+', '100000' => '100K+', '500000' => '500K+', '1000000' => '1M+' ), __( 'Min Followers', 'tm-store-ui' ) ),
				'filter_instagram_followers' => array( 'instagram_followers', 'Instagram', '#E1306C', 'instagram', array( '1000' => '1K+', '10000' => '10K+', '50000' => '50K+', '100000' => '100K+', '500000' => '500K+', '1000000' => '1M+' ), __( 'Min Followers', 'tm-store-ui' ) ),
				'filter_facebook_followers' => array( 'facebook_followers', 'Facebook', '#1877F2', 'facebook-square', array( '1000' => '1K+', '10000' => '10K+', '50000' => '50K+', '100000' => '100K+', '500000' => '500K+', '1000000' => '1M+' ), __( 'Min Followers', 'tm-store-ui' ) ),
				'filter_linkedin_followers' => array( 'linkedin_followers', 'LinkedIn', '#0A66C2', 'linkedin', array( '500' => '500+', '1000' => '1K+', '5000' => '5K+', '10000' => '10K+', '25000' => '25K+', '50000' => '50K+' ), __( 'Min Connections', 'tm-store-ui' ) ),
				'filter_growth_rate' => array( 'growth_rate', 'Growth Rate', '', '', array( '5' => '5%+', '10' => '10%+', '15' => '15%+', '20' => '20%+', '30' => '30%+', '50' => '50%+' ), __( 'Min Monthly Growth', 'tm-store-ui' ) ),
			) as $field_id => $field_data ) : ?>
				<?php list( $field_name, $label, $color, $icon, $options, $placeholder ) = $field_data; ?>
				<div class="filter-item attribute-filter">
					<label for="<?php echo esc_attr( $field_id ); ?>">
						<?php if ( '' !== $icon ) : ?><span style="color: <?php echo esc_attr( $color ); ?>; margin-right: 5px;"><?php echo TM_Icons::svg( $icon ); ?></span><?php else : ?>📈 <?php endif; ?><?php echo esc_html( $label ); ?>
					</label>
					<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" class="ecomcine-form-control">
						<option value=""><?php echo esc_html( $placeholder ); ?></option>
						<?php foreach ( $options as $value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $_GET[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $field_name ] ) ) : '', $value ); ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</form>