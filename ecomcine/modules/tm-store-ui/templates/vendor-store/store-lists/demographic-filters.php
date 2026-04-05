<?php
/**
 * Demographic & Availability Filters for Store Listing Page
 * These filters appear for ALL store categories
 */

// Get current filter values
$current_age = isset( $_GET['demo_age'] ) ? sanitize_text_field( $_GET['demo_age'] ) : '';
$current_ethnicity = isset( $_GET['demo_ethnicity'] ) ? sanitize_text_field( $_GET['demo_ethnicity'] ) : '';
$current_languages = isset( $_GET['demo_languages'] ) ? (array) $_GET['demo_languages'] : [];
$current_availability = isset( $_GET['demo_availability'] ) ? sanitize_text_field( $_GET['demo_availability'] ) : '';
$current_notice = isset( $_GET['demo_notice_time'] ) ? sanitize_text_field( $_GET['demo_notice_time'] ) : '';
$current_travel = isset( $_GET['demo_can_travel'] ) ? sanitize_text_field( $_GET['demo_can_travel'] ) : '';
$current_rate = isset( $_GET['demo_daily_rate'] ) ? sanitize_text_field( $_GET['demo_daily_rate'] ) : '';
$current_education = isset( $_GET['demo_education'] ) ? sanitize_text_field( $_GET['demo_education'] ) : '';
?>

<div class="custom-filter-group demographic-filters always-visible" data-category="model,artist,cameraman,actor,tv-host" style="display: none; border-top: none !important; margin-top: 10px !important; padding-top: 0 !important;">
	<div class="filter-group-items" style="grid-template-columns: repeat(8, 1fr) !important; gap: 10px !important;">
		
		<!-- Age Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_age">📅 Age:</label>
			<select id="filter_demo_age" name="demo_age" class="dokan-form-control">
				<option value="">Select</option>
				<optgroup label="Age Ranges">
					<option value="18-25" <?php selected( $current_age, '18-25' ); ?>>18-25</option>
					<option value="26-35" <?php selected( $current_age, '26-35' ); ?>>26-35</option>
					<option value="36-45" <?php selected( $current_age, '36-45' ); ?>>36-45</option>
					<option value="46-55" <?php selected( $current_age, '46-55' ); ?>>46-55</option>
					<option value="56-65" <?php selected( $current_age, '56-65' ); ?>>56-65</option>
					<option value="66+" <?php selected( $current_age, '66+' ); ?>>66+</option>
				</optgroup>
			</select>
		</div>
		
		<!-- Ethnicity Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_ethnicity">🌍 Ethnicity:</label>
			<select id="filter_demo_ethnicity" name="demo_ethnicity" class="dokan-form-control">
				<option value="">Select</option>
				<option value="caucasian" <?php selected( $current_ethnicity, 'caucasian' ); ?>>Caucasian</option>
				<option value="african" <?php selected( $current_ethnicity, 'african' ); ?>>African</option>
				<option value="asian" <?php selected( $current_ethnicity, 'asian' ); ?>>Asian</option>
				<option value="hispanic" <?php selected( $current_ethnicity, 'hispanic' ); ?>>Hispanic/Latino</option>
				<option value="middle_eastern" <?php selected( $current_ethnicity, 'middle_eastern' ); ?>>Middle Eastern</option>
				<option value="native_american" <?php selected( $current_ethnicity, 'native_american' ); ?>>Native American</option>
				<option value="pacific_islander" <?php selected( $current_ethnicity, 'pacific_islander' ); ?>>Pacific Islander</option>
				<option value="mixed" <?php selected( $current_ethnicity, 'mixed' ); ?>>Mixed/Other</option>
			</select>
		</div>
		
		<!-- Languages Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_languages">💬 Languages:</label>
			<select id="filter_demo_languages" name="demo_languages" class="dokan-form-control">
				<option value="">Select</option>
				<option value="english" <?php selected( in_array( 'english', $current_languages ), true ); ?>>English</option>
				<option value="spanish" <?php selected( in_array( 'spanish', $current_languages ), true ); ?>>Spanish</option>
				<option value="french" <?php selected( in_array( 'french', $current_languages ), true ); ?>>French</option>
				<option value="german" <?php selected( in_array( 'german', $current_languages ), true ); ?>>German</option>
				<option value="italian" <?php selected( in_array( 'italian', $current_languages ), true ); ?>>Italian</option>
				<option value="portuguese" <?php selected( in_array( 'portuguese', $current_languages ), true ); ?>>Portuguese</option>
				<option value="arabic" <?php selected( in_array( 'arabic', $current_languages ), true ); ?>>Arabic</option>
				<option value="chinese" <?php selected( in_array( 'chinese', $current_languages ), true ); ?>>Chinese</option>
				<option value="japanese" <?php selected( in_array( 'japanese', $current_languages ), true ); ?>>Japanese</option>
				<option value="korean" <?php selected( in_array( 'korean', $current_languages ), true ); ?>>Korean</option>
				<option value="hindi" <?php selected( in_array( 'hindi', $current_languages ), true ); ?>>Hindi</option>
				<option value="russian" <?php selected( in_array( 'russian', $current_languages ), true ); ?>>Russian</option>
			</select>
		</div>
		
		<!-- Availability Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_availability">🕒 Availability:</label>
			<select id="filter_demo_availability" name="demo_availability" class="dokan-form-control">
				<option value="">Select</option>
				<option value="part-time" <?php selected( $current_availability, 'part-time' ); ?>>Part-time</option>
				<option value="full-time" <?php selected( $current_availability, 'full-time' ); ?>>Full-time</option>
				<option value="on-demand" <?php selected( $current_availability, 'on-demand' ); ?>>On-demand</option>
			</select>
		</div>
		
		<!-- Notice Time Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_notice_time">🔔 Notice Time:</label>
			<select id="filter_demo_notice_time" name="demo_notice_time" class="dokan-form-control">
				<option value="">Select</option>
				<option value="in_days" <?php selected( $current_notice, 'in_days' ); ?>>in Days</option>
				<option value="in_weeks" <?php selected( $current_notice, 'in_weeks' ); ?>>in Weeks</option>
				<option value="in_months" <?php selected( $current_notice, 'in_months' ); ?>>in Months</option>
			</select>
		</div>
		
		<!-- Can Travel Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_can_travel">✈️ Can Travel:</label>
			<select id="filter_demo_can_travel" name="demo_can_travel" class="dokan-form-control">
				<option value="">Select</option>
				<option value="yes" <?php selected( $current_travel, 'yes' ); ?>>Yes</option>
				<option value="no" <?php selected( $current_travel, 'no' ); ?>>No</option>
			</select>
		</div>
		
		<!-- Daily Rate Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_daily_rate">💰 Daily Rate:</label>
			<select id="filter_demo_daily_rate" name="demo_daily_rate" class="dokan-form-control">
				<option value="">Select</option>
				<option value="under_1k" <?php selected( $current_rate, 'under_1k' ); ?>>&lt;$1K</option>
				<option value="1k_to_2k" <?php selected( $current_rate, '1k_to_2k' ); ?>>$1K to $2K</option>
				<option value="3k_to_5k" <?php selected( $current_rate, '3k_to_5k' ); ?>>$3K to $5K</option>
				<option value="over_5k" <?php selected( $current_rate, 'over_5k' ); ?>>&gt;$5K</option>
			</select>
		</div>
		
		<!-- Education Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_demo_education">🎓 Education:</label>
			<select id="filter_demo_education" name="demo_education" class="dokan-form-control">
				<option value="">Select</option>
				<option value="doctorate" <?php selected( $current_education, 'doctorate' ); ?>>Doctorate</option>
				<option value="masters" <?php selected( $current_education, 'masters' ); ?>>Master's Degree</option>
				<option value="bachelors" <?php selected( $current_education, 'bachelors' ); ?>>Bachelor's Degree</option>
				<option value="associates" <?php selected( $current_education, 'associates' ); ?>>Associate's Degree</option>
				<option value="diploma" <?php selected( $current_education, 'diploma' ); ?>>Diploma</option>
				<option value="high_school" <?php selected( $current_education, 'high_school' ); ?>>High School</option>
			</select>
		</div>
		
	</div>
</div>
