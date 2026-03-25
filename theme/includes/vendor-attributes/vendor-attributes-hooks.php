<?php
/**
 * Vendor Attributes — Display & Save Hooks
 *
 * All WordPress hooks that render and save category-specific vendor attributes.
 * Option lists come from vendor-attribute-sets.php (loaded before this file).
 *
 * CONTENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. PROFILE DISPLAY   dokan_store_profile_bottom_drawer
 *    1a. Demographic & Availability      (priority 3 – all vendors)
 *    1b. Physical Attributes + Cameraman (priority 5 – model / artist / cameraman)
 *
 * 2. DASHBOARD SETTINGS FORM FIELDS  dokan_settings_after_store_phone
 *    2a. Demographic & Availability form (priority 5  – all vendors)
 *    2b. Cameraman Equipment form        (priority 10 – cameraman only)
 *
 * 3. PROFILE SAVE   dokan_store_profile_saved
 *    Persists physical, cameraman, and demographic fields on form submit.
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// 1. PROFILE DISPLAY
// =============================================================================

// --- 1a. Demographic & Availability (priority 3, all vendors) ---

/**
 * Display Demographic & Availability on Vendor Store Page
 * This appears for ALL vendors regardless of category
 * Priority 3 = appears before category-specific attributes (priority 5)
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

	$social_profiles = tm_get_vendor_social_profiles( $vendor_id );
	$linkedin_url = '';
	if ( ! empty( $social_profiles['linkedin'] ) ) {
		$linkedin_url = $social_profiles['linkedin'];
	} elseif ( ! empty( $social_profiles['linked_in'] ) ) {
		$linkedin_url = $social_profiles['linked_in'];
	}
	$linkedin_metrics     = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin', true );
	$linkedin_followers   = is_array( $linkedin_metrics ) && isset( $linkedin_metrics['followers'] ) ? (int) $linkedin_metrics['followers'] : null;
	$linkedin_connections = is_array( $linkedin_metrics ) && isset( $linkedin_metrics['connections'] ) ? (int) $linkedin_metrics['connections'] : null;
	$linkedin_display_url = $linkedin_url;
	if ( ! $linkedin_display_url && is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['url'] ) ) {
		$linkedin_display_url = $linkedin_metrics['url'];
	}
	$linkedin_updated_at = is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ? $linkedin_metrics['updated_at'] : '';
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

	// Collect field data
	$demo_fields = [
		'age' => [
			'label' => 'Age',
			'icon'  => '📅',
			'value' => (function() use ($vendor_id) {
				$birth_date = get_user_meta( $vendor_id, 'demo_birth_date', true );
				if ( empty( $birth_date ) ) return '';
				$birth = new DateTime( $birth_date );
				$today = new DateTime();
				$age   = $today->diff( $birth )->y;
				return $age . ' years';
			})()
		],
		'ethnicity' => [
			'label'   => 'Ethnicity',
			'icon'    => '🌍',
			'value'   => get_user_meta( $vendor_id, 'demo_ethnicity', true ),
			'multi'   => true,
			'options' => [
				'caucasian'        => 'Caucasian',
				'african'          => 'African',
				'asian'            => 'Asian',
				'hispanic'         => 'Hispanic/Latino',
				'middle_eastern'   => 'Middle Eastern',
				'native_american'  => 'Native American',
				'pacific_islander' => 'Pacific Islander',
			]
		],
		'languages' => [
			'label'   => 'Languages',
			'icon'    => '💬',
			'value'   => get_user_meta( $vendor_id, 'demo_languages', true ),
			'multi'   => true,
			'options' => [
				'english'    => 'English',
				'spanish'    => 'Spanish',
				'french'     => 'French',
				'german'     => 'German',
				'italian'    => 'Italian',
				'portuguese' => 'Portuguese',
				'arabic'     => 'Arabic',
				'chinese'    => 'Chinese (Mandarin)',
				'japanese'   => 'Japanese',
				'korean'     => 'Korean',
				'hindi'      => 'Hindi',
				'russian'    => 'Russian',
			]
		],
		'availability' => [
			'label'   => 'Availability',
			'icon'    => '🕒',
			'value'   => get_user_meta( $vendor_id, 'demo_availability', true ),
			'options' => [
				'part-time' => 'Part-time',
				'full-time' => 'Full-time',
				'on-demand' => 'On-demand',
			]
		],
		'notice_time' => [
			'label'   => 'Notice Time',
			'icon'    => '🔔',
			'value'   => get_user_meta( $vendor_id, 'demo_notice_time', true ),
			'options' => [
				'in_days'   => 'in Days',
				'in_weeks'  => 'in Weeks',
				'in_months' => 'in Months',
			]
		],
		'can_travel' => [
			'label'   => 'Can Travel',
			'icon'    => '✈️',
			'value'   => get_user_meta( $vendor_id, 'demo_can_travel', true ),
			'options' => [
				'yes' => 'Yes',
				'no'  => 'No',
			]
		],
		'daily_rate' => [
			'label'   => 'Daily Rate',
			'icon'    => '💰',
			'value'   => get_user_meta( $vendor_id, 'demo_daily_rate', true ),
			'options' => [
				'under_1k' => '<$1K',
				'1k_to_2k' => '$1K to $2K',
				'3k_to_5k' => '$3K to $5K',
				'over_5k'  => '>$5K',
			]
		],
		'education' => [
			'label'   => 'Education',
			'icon'    => '🎓',
			'value'   => get_user_meta( $vendor_id, 'demo_education', true ),
			'options' => [
				'doctorate'  => 'Doctorate',
				'masters'    => 'Master\'s Degree',
				'bachelors'  => 'Bachelor\'s Degree',
				'associates' => 'Associate\'s Degree',
				'diploma'    => 'Diploma',
				'high_school' => 'High School',
			]
		],
	];

	// Check if any fields have values
	$has_values = false;
	foreach ( $demo_fields as $field ) {
		if ( ! empty( $field['value'] ) ) {
			$has_values = true;
			break;
		}
	}

	?>
	<div id="demographic-section" class="talent-physical-attributes vendor-demographic-section attribute-slide-section">
		<h3 class="section-title">
			<i class="fas fa-user-circle section-title-icon"></i> Demographic & Availability
			<?php if ( $is_owner ) : ?>
				<span class="owner-edit-hint">(Click pencil to edit)</span>
			<?php endif; ?>
		</h3>
		<div class="attributes-grid">
			<?php if ( ! $has_values && ! $is_owner ) : ?>
				<div class="stat-item">
					<strong class="stat-value--gold">No demographic data available yet.</strong>
				</div>
			<?php else : ?>
				<?php
				$birth_date = get_user_meta( $vendor_id, 'demo_birth_date', true );
				render_editable_attribute([
					'name'       => 'demo_birth_date',
					'label'      => 'Age',
					'edit_label' => 'Birth Date',
					'icon'       => '📅',
					'user_id'    => $vendor_id,
					'is_owner'   => $is_owner,
					'value'      => $demo_fields['age']['value'],
					'raw_value'  => $birth_date,
					'type'       => 'date',
					'help_text'  => 'FORMAT: MM/DD/YYYY (AGE IS AUTO CALCULATED)',
				]);

				render_editable_attribute([
					'name'      => 'demo_ethnicity',
					'label'     => 'Ethnicity',
					'icon'      => '🌍',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['ethnicity']['options'],
					'multi'     => true,
					'help_text' => 'Use CTRL + CLICK to select multiple options',
				]);

				render_editable_attribute([
					'name'      => 'demo_languages',
					'label'     => 'Languages',
					'icon'      => '💬',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['languages']['options'],
					'multi'     => true,
					'help_text' => 'Use CTRL + CLICK to select multiple languages',
				]);

				render_editable_attribute([
					'name'      => 'demo_availability',
					'label'     => 'Availability',
					'icon'      => '🕒',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['availability']['options'],
					'help_text' => 'Select your general availability for bookings',
				]);

				render_editable_attribute([
					'name'      => 'demo_notice_time',
					'label'     => 'Notice Time',
					'icon'      => '🔔',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['notice_time']['options'],
					'help_text' => 'Minimum time needed before accepting a booking',
				]);

				render_editable_attribute([
					'name'      => 'demo_can_travel',
					'label'     => 'Can Travel',
					'icon'      => '✈️',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['can_travel']['options'],
					'help_text' => 'Willing to travel for work opportunities',
				]);

				render_editable_attribute([
					'name'      => 'demo_daily_rate',
					'label'     => 'Daily Rate',
					'icon'      => '💰',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['daily_rate']['options'],
					'help_text' => 'Your standard daily rate for bookings',
				]);

				render_editable_attribute([
					'name'      => 'demo_education',
					'label'     => 'Education',
					'icon'      => '🎓',
					'user_id'   => $vendor_id,
					'is_owner'  => $is_owner,
					'options'   => $demo_fields['education']['options'],
					'help_text' => 'Highest level of education completed',
				]);
				?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}, 3, 2 ); // Priority 3 – appears before category-specific attributes (priority 5)


// --- 1b. Physical Attributes + Cameraman Equipment (priority 5) ---

/**
 * Display Physical Attributes & Cameraman Equipment on Vendor Store Page
 * Combined in one parent wrapper for consistent positioning
 */
add_action( 'dokan_store_profile_bottom_drawer', function( $store_user, $store_info ) {
	$vendor_id = get_vendor_id_from_store_user( $store_user );
	if ( ! $vendor_id ) {
		return;
	}

	$store_categories = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $store_categories ) ) {
		$store_categories = array();
	}
	$has_physical_category  = in_array( 'model', $store_categories, true ) || in_array( 'artist', $store_categories, true );
	$has_cameraman_category = in_array( 'cameraman', $store_categories, true );

	$current_user_id = get_current_user_id();
	$is_owner = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id )
		: ( $current_user_id && $current_user_id == $vendor_id );

	echo '<div class="vendor-custom-attributes-wrapper" data-is-owner="' . ( $is_owner ? '1' : '0' ) . '">';

	// ========== PHYSICAL ATTRIBUTES SECTION ==========
	$physical_attributes = [
		'height'     => [ 'label' => 'Height',     'icon' => '📏', 'value' => get_user_meta( $vendor_id, 'talent_height', true ) ],
		'weight'     => [ 'label' => 'Weight',     'icon' => '⚖️', 'value' => get_user_meta( $vendor_id, 'talent_weight', true ) ],
		'waist'      => [ 'label' => 'Waist',      'icon' => '📐', 'value' => get_user_meta( $vendor_id, 'talent_waist', true ) ],
		'hip'        => [ 'label' => 'Hip',        'icon' => '📐', 'value' => get_user_meta( $vendor_id, 'talent_hip', true ) ],
		'chest'      => [ 'label' => 'Chest',      'icon' => '📐', 'value' => get_user_meta( $vendor_id, 'talent_chest', true ) ],
		'shoe_size'  => [ 'label' => 'Shoe Size',  'icon' => '👟', 'value' => get_user_meta( $vendor_id, 'talent_shoe_size', true ) ],
		'eye_color'  => [ 'label' => 'Eye Color',  'icon' => '👁️', 'value' => get_user_meta( $vendor_id, 'talent_eye_color', true ) ],
		'hair_color' => [ 'label' => 'Hair Color', 'icon' => '💇', 'value' => get_user_meta( $vendor_id, 'talent_hair_color', true ) ],
	];

	$has_physical = false;
	foreach ( $physical_attributes as $attr ) {
		if ( ! empty( $attr['value'] ) ) { $has_physical = true; break; }
	}

	if ( $has_physical_category && ( $has_physical || $is_owner ) ) {
		$options = get_talent_physical_attributes_options();
		$attribute_fields = [
			'talent_height'     => [ 'label' => 'Height',     'icon' => '📏', 'options' => $options['height']     ?? [] ],
			'talent_weight'     => [ 'label' => 'Weight',     'icon' => '⚖️', 'options' => $options['weight']     ?? [] ],
			'talent_waist'      => [ 'label' => 'Waist',      'icon' => '📐', 'options' => $options['waist']      ?? [] ],
			'talent_hip'        => [ 'label' => 'Hip',        'icon' => '📐', 'options' => $options['hip']        ?? [] ],
			'talent_chest'      => [ 'label' => 'Chest',      'icon' => '📐', 'options' => $options['chest']      ?? [] ],
			'talent_shoe_size'  => [ 'label' => 'Shoe Size',  'icon' => '👟', 'options' => $options['shoe_size']  ?? [] ],
			'talent_eye_color'  => [ 'label' => 'Eye Color',  'icon' => '👁️', 'options' => $options['eye_color']  ?? [] ],
			'talent_hair_color' => [ 'label' => 'Hair Color', 'icon' => '💇', 'options' => $options['hair_color'] ?? [] ],
		];
		?>
		<div id="physical-section" class="talent-physical-attributes attribute-slide-section">
			<h3 class="section-title">
				<i class="fas fa-ruler section-title-icon"></i> Physical Attributes
				<?php if ( $is_owner ) : ?>
					<span class="owner-edit-hint">(Click pencil to edit)</span>
				<?php endif; ?>
			</h3>
			<div class="attributes-grid">
				<?php
				foreach ( $attribute_fields as $field_name => $field_config ) {
					render_editable_attribute([
						'name'    => $field_name,
						'label'   => $field_config['label'],
						'icon'    => $field_config['icon'],
						'user_id' => $vendor_id,
						'is_owner'=> $is_owner,
						'options' => $field_config['options'],
					]);
				}
				?>
			</div>
		</div>
		<?php
	}

	// ========== CAMERAMAN EQUIPMENT & SKILLS SECTION ==========
	$cameraman_options = get_cameraman_filter_options();

	$cameraman_fields = [
		'camera_type'        => [ 'label' => 'Camera Type',        'icon' => '📷', 'value' => get_user_meta( $vendor_id, 'camera_type', true ),        'options' => $cameraman_options['camera_type'] ],
		'experience_level'   => [ 'label' => 'Experience Level',   'icon' => '⭐', 'value' => get_user_meta( $vendor_id, 'experience_level', true ),   'options' => $cameraman_options['experience_level'] ],
		'editing_software'   => [ 'label' => 'Editing Software',   'icon' => '🎬', 'value' => get_user_meta( $vendor_id, 'editing_software', true ),   'options' => $cameraman_options['editing_software'] ],
		'specialization'     => [ 'label' => 'Specialization',     'icon' => '🎯', 'value' => get_user_meta( $vendor_id, 'specialization', true ),     'options' => $cameraman_options['specialization'] ],
		'years_experience'   => [ 'label' => 'Years of Experience','icon' => '📅', 'value' => get_user_meta( $vendor_id, 'years_experience', true ),   'options' => $cameraman_options['years_experience'] ],
		'equipment_ownership'=> [ 'label' => 'Equipment Ownership','icon' => '🎥', 'value' => get_user_meta( $vendor_id, 'equipment_ownership', true ),'options' => $cameraman_options['equipment_ownership'] ],
		'lighting_equipment' => [ 'label' => 'Lighting Equipment', 'icon' => '💡', 'value' => get_user_meta( $vendor_id, 'lighting_equipment', true ), 'options' => $cameraman_options['lighting_equipment'] ],
		'audio_equipment'    => [ 'label' => 'Audio Equipment',    'icon' => '🎤', 'value' => get_user_meta( $vendor_id, 'audio_equipment', true ),    'options' => $cameraman_options['audio_equipment'] ],
		'drone_capability'   => [ 'label' => 'Drone/Aerial',       'icon' => '🚁', 'value' => get_user_meta( $vendor_id, 'drone_capability', true ),   'options' => $cameraman_options['drone_capability'] ],
	];

	$has_cameraman_values = false;
	foreach ( $cameraman_fields as $attr ) {
		if ( ! empty( $attr['value'] ) ) { $has_cameraman_values = true; break; }
	}

	if ( $has_cameraman_category && ( $has_cameraman_values || $is_owner ) ) {
		?>
		<div id="cameraman-section" class="talent-physical-attributes cameraman-equipment attribute-slide-section">
			<h3 class="section-title">
				<i class="fas fa-video section-title-icon"></i> Equipment & Skills
				<?php if ( $is_owner ) : ?>
					<span class="owner-edit-hint">(Click pencil to edit)</span>
				<?php endif; ?>
			</h3>
			<div class="attributes-grid">
				<?php if ( ! $has_cameraman_values && ! $is_owner ) : ?>
					<div class="stat-item">
						<strong class="stat-value--gold">No equipment/skills data available yet.</strong>
					</div>
				<?php else : ?>
					<?php
					foreach ( $cameraman_fields as $field_name => $field_config ) {
						render_editable_attribute([
							'name'    => $field_name,
							'label'   => $field_config['label'],
							'icon'    => $field_config['icon'],
							'user_id' => $vendor_id,
							'is_owner'=> $is_owner,
							'options' => $field_config['options'],
						]);
					}
					?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	echo '</div>'; // .vendor-custom-attributes-wrapper
}, 5, 2 );


// =============================================================================
// 2. DASHBOARD SETTINGS FORM FIELDS
// =============================================================================

// --- 2a. Demographic & Availability form fields (priority 5, all vendors) ---

/**
 * Add Demographic & Availability fields to vendor dashboard
 * These fields appear for ALL vendors regardless of category
 * Priority 5 = appears before category-specific attributes (priority 10)
 */
add_action( 'dokan_settings_after_store_phone', function( $user_id, $profile_info ) {
	?>
	<div class="dokan-form-group demographic-availability-section">
		<div class="dokan-w12">
			<h3 style="margin-top: 30px; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f0ad4e; padding-bottom: 10px; color: #f0ad4e;">
				<span class="dashicons dashicons-id-alt" style="font-size: 20px; margin-right: 5px;"></span>
				<?php esc_html_e( 'Demographic & Availability', 'dokan' ); ?>
			</h3>
		</div>
	</div>

	<!-- Birth Date -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_birth_date">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php esc_html_e( 'Birth Date', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<?php $current_birth_date = get_user_meta( $user_id, 'demo_birth_date', true ); ?>
			<input type="date" id="demo_birth_date" name="demo_birth_date" class="dokan-form-control" value="<?php echo esc_attr( $current_birth_date ); ?>">
			<p class="description"><?php esc_html_e( 'Age will be calculated automatically', 'dokan' ); ?></p>
		</div>
	</div>

	<!-- Ethnicity -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_ethnicity">
			<span class="dashicons dashicons-admin-users"></span>
			<?php esc_html_e( 'Ethnicity', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_ethnicity" name="demo_ethnicity[]" class="dokan-form-control" multiple size="5">
				<?php
				$ethnicities = [
					'caucasian'        => 'Caucasian',
					'african'          => 'African',
					'asian'            => 'Asian',
					'hispanic'         => 'Hispanic/Latino',
					'middle_eastern'   => 'Middle Eastern',
					'native_american'  => 'Native American',
					'pacific_islander' => 'Pacific Islander',
				];
				$current_ethnicity = get_user_meta( $user_id, 'demo_ethnicity', true );
				if ( ! is_array( $current_ethnicity ) ) {
					$current_ethnicity = ! empty( $current_ethnicity ) ? [ $current_ethnicity ] : [];
				}
				foreach ( $ethnicities as $key => $label ) {
					$selected = in_array( $key, $current_ethnicity, true ) ? ' selected' : '';
					echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Hold CTRL (Windows) or CMD (Mac) to select multiple', 'dokan' ); ?></p>
		</div>
	</div>

	<!-- Languages -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_languages">
			<span class="dashicons dashicons-translation"></span>
			<?php esc_html_e( 'Languages', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_languages" name="demo_languages[]" class="dokan-form-control" multiple size="5">
				<?php
				$languages = [
					'english'    => 'English',
					'spanish'    => 'Spanish',
					'french'     => 'French',
					'german'     => 'German',
					'italian'    => 'Italian',
					'portuguese' => 'Portuguese',
					'arabic'     => 'Arabic',
					'chinese'    => 'Chinese (Mandarin)',
					'japanese'   => 'Japanese',
					'korean'     => 'Korean',
					'hindi'      => 'Hindi',
					'russian'    => 'Russian',
				];
				$current_languages = get_user_meta( $user_id, 'demo_languages', true );
				$current_languages = is_array( $current_languages ) ? $current_languages : [];
				foreach ( $languages as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( in_array( $key, $current_languages ), true, false ) . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Hold Ctrl (Cmd on Mac) to select multiple languages', 'dokan' ); ?></p>
		</div>
	</div>

	<!-- Availability -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_availability">
			<span class="dashicons dashicons-clock"></span>
			<?php esc_html_e( 'Availability', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_availability" name="demo_availability" class="dokan-form-control">
				<option value="">Select Availability</option>
				<?php
				$availability_options = [ 'part-time' => 'Part-time', 'full-time' => 'Full-time', 'on-demand' => 'On-demand' ];
				$current_availability = get_user_meta( $user_id, 'demo_availability', true );
				foreach ( $availability_options as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_availability, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
		</div>
	</div>

	<!-- Notice Time -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_notice_time">
			<span class="dashicons dashicons-bell"></span>
			<?php esc_html_e( 'Notice Time', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_notice_time" name="demo_notice_time" class="dokan-form-control">
				<option value="">Select Notice Time</option>
				<?php
				$notice_options = [ 'in_days' => 'in Days', 'in_weeks' => 'in Weeks', 'in_months' => 'in Months' ];
				$current_notice = get_user_meta( $user_id, 'demo_notice_time', true );
				foreach ( $notice_options as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_notice, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
		</div>
	</div>

	<!-- Can Travel -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_can_travel">
			<span class="dashicons dashicons-airplane"></span>
			<?php esc_html_e( 'Can Travel', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<?php $can_travel = get_user_meta( $user_id, 'demo_can_travel', true ); ?>
			<div class="checkbox">
				<label>
					<input type="hidden" name="demo_can_travel" value="no">
					<input type="checkbox" id="demo_can_travel" name="demo_can_travel" value="yes" <?php checked( $can_travel, 'yes' ); ?>>
					<?php esc_html_e( 'Yes, I can travel for work', 'dokan' ); ?>
				</label>
			</div>
		</div>
	</div>

	<!-- Daily Rate -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_daily_rate">
			<span class="dashicons dashicons-money-alt"></span>
			<?php esc_html_e( 'Daily Rate', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_daily_rate" name="demo_daily_rate" class="dokan-form-control">
				<option value="">Select Daily Rate</option>
				<?php
				$rate_options = [ 'under_1k' => '<$1K', '1k_to_2k' => '$1K to $2K', '3k_to_5k' => '$3K to $5K', 'over_5k' => '>$5K' ];
				$current_rate = get_user_meta( $user_id, 'demo_daily_rate', true );
				foreach ( $rate_options as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_rate, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
		</div>
	</div>

	<!-- Education -->
	<div class="dokan-form-group">
		<label class="dokan-w3 dokan-control-label" for="demo_education">
			<span class="dashicons dashicons-welcome-learn-more"></span>
			<?php esc_html_e( 'Education', 'dokan' ); ?>
		</label>
		<div class="dokan-w5 dokan-text-left">
			<select id="demo_education" name="demo_education" class="dokan-form-control">
				<option value="">Select Education</option>
				<?php
				$education_options = [
					'doctorate'  => 'Doctorate',
					'masters'    => 'Master\'s Degree',
					'bachelors'  => 'Bachelor\'s Degree',
					'associates' => 'Associate\'s Degree',
					'diploma'    => 'Diploma',
					'high_school'=> 'High School',
				];
				$current_education = get_user_meta( $user_id, 'demo_education', true );
				foreach ( $education_options as $key => $label ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_education, $key, false ) . '>' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
		</div>
	</div>
	<?php
}, 5, 2 ); // Priority 5 – appears before category-specific attributes (priority 10)


// --- 2b. Cameraman Equipment & Skills form fields (priority 10, cameraman only) ---

/**
 * Add Cameraman Fields to Vendor Dashboard Settings
 * Uses correct Dokan hook: dokan_settings_after_store_phone
 * Note: Physical Attributes are in the template file (store-form.php)
 */
add_action( 'dokan_settings_after_store_phone', function( $user_id, $profile_info ) {
	$cameraman_options = get_cameraman_filter_options();

	// Section header
	?>
	<div class="dokan-form-group cameraman-section-header" data-category="cameraman" style="display:none;">
		<div class="dokan-w12">
			<h3 style="margin-top: 30px; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f05025; padding-bottom: 10px;">
				🎬 <?php esc_html_e( 'Equipment & Skills', 'dokan' ); ?>
			</h3>
		</div>
	</div>
	<?php

	// Build each cameraman field generically
	$cameraman_field_defs = [
		'camera_type'         => [ 'icon' => '📷', 'label' => 'Camera Type' ],
		'experience_level'    => [ 'icon' => '⭐', 'label' => 'Experience Level' ],
		'editing_software'    => [ 'icon' => '💻', 'label' => 'Editing Software' ],
		'specialization'      => [ 'icon' => '🎬', 'label' => 'Specialization' ],
		'years_experience'    => [ 'icon' => '📅', 'label' => 'Years of Experience' ],
		'equipment_ownership' => [ 'icon' => '🎥', 'label' => 'Equipment Ownership' ],
		'lighting_equipment'  => [ 'icon' => '💡', 'label' => 'Lighting Equipment' ],
		'audio_equipment'     => [ 'icon' => '🎤', 'label' => 'Audio Equipment' ],
		'drone_capability'    => [ 'icon' => '🚁', 'label' => 'Drone Capability' ],
	];

	foreach ( $cameraman_field_defs as $field_key => $field_def ) {
		$current_value = get_user_meta( $user_id, $field_key, true );
		?>
		<div class="dokan-form-group <?php echo esc_attr( $field_key ); ?>" data-category="cameraman" style="display:none;">
			<label class="dokan-w3 dokan-control-label" for="<?php echo esc_attr( $field_key ); ?>">
				<?php echo esc_html( $field_def['icon'] . ' ' ); ?><?php esc_html_e( $field_def['label'], 'dokan' ); ?>
			</label>
			<div class="dokan-w5">
				<select class="dokan-form-control" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>">
					<?php
					foreach ( $cameraman_options[ $field_key ] as $value => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $value ),
							selected( $current_value, $value, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
			</div>
		</div>
		<?php
	}
}, 10, 2 );


// =============================================================================
// 3. PROFILE SAVE
// =============================================================================

/**
 * Save Physical Attributes & Cameraman Fields for Vendor Profile
 */
add_action( 'dokan_store_profile_saved', function ( $store_id, $dokan_settings ) {
	$store_id = (int) $store_id;
	if ( ! $store_id ) return;

	// Physical attributes
	$attributes = [
		'talent_height', 'talent_weight', 'talent_waist', 'talent_hip', 'talent_chest',
		'talent_shoe_size', 'talent_eye_color', 'talent_hair_color',
	];

	// Cameraman fields
	$cameraman_fields = [
		'camera_type', 'experience_level', 'editing_software', 'specialization',
		'years_experience', 'equipment_ownership', 'lighting_equipment', 'audio_equipment', 'drone_capability',
	];

	// Demographic & Availability fields
	$demographic_fields = [
		'demo_birth_date', 'demo_ethnicity', 'demo_availability',
		'demo_notice_time', 'demo_can_travel', 'demo_daily_rate', 'demo_education',
	];

	foreach ( array_merge( $attributes, $cameraman_fields, $demographic_fields ) as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			update_user_meta( $store_id, $field, $value );
		}
	}

	// Languages (multi-select)
	if ( isset( $_POST['demo_languages'] ) && is_array( $_POST['demo_languages'] ) ) {
		$languages = array_map( 'sanitize_text_field', $_POST['demo_languages'] );
		update_user_meta( $store_id, 'demo_languages', $languages );
	} else {
		delete_user_meta( $store_id, 'demo_languages' );
	}
}, 10, 2 );
