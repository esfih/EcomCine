<?php
/**
 * Social Influence Metrics Filters for Store Listing Page
 * This appears for vendors in the "Influencer" category
 * 
 * Visual mockup only - data will be populated dynamically later
 */
?>

<div class="custom-filter-group social-metrics-filters" data-category="influencer">
	<h3 class="filter-section-title" style="color: #D4AF37; font-size: 14px; font-weight: 600; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px;">
		📊 Social Influence Metrics
	</h3>
	
	<div class="filter-group-items" style="grid-template-columns: repeat(5, 1fr) !important; gap: 15px !important;">
		
		<!-- YouTube Stats Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_youtube_followers">
				<span style="color: #FF0000; margin-right: 5px;"><?php echo TM_Icons::svg( 'youtube' ); ?></span> YouTube
			</label>
			<select id="filter_youtube_followers" name="youtube_followers" class="dokan-form-control">
				<option value="">Min Followers</option>
				<option value="1000">1K+</option>
				<option value="10000">10K+</option>
				<option value="50000">50K+</option>
				<option value="100000">100K+</option>
				<option value="500000">500K+</option>
				<option value="1000000">1M+</option>
			</select>
		</div>
		
		<!-- Instagram Stats Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_instagram_followers">
				<span style="color: #E1306C; margin-right: 5px;"><?php echo TM_Icons::svg( 'instagram' ); ?></span> Instagram
			</label>
			<select id="filter_instagram_followers" name="instagram_followers" class="dokan-form-control">
				<option value="">Min Followers</option>
				<option value="1000">1K+</option>
				<option value="10000">10K+</option>
				<option value="50000">50K+</option>
				<option value="100000">100K+</option>
				<option value="500000">500K+</option>
				<option value="1000000">1M+</option>
			</select>
		</div>
		
		<!-- Facebook Stats Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_facebook_followers">
				<span style="color: #1877F2; margin-right: 5px;"><?php echo TM_Icons::svg( 'facebook-square' ); ?></span> Facebook
			</label>
			<select id="filter_facebook_followers" name="facebook_followers" class="dokan-form-control">
				<option value="">Min Followers</option>
				<option value="1000">1K+</option>
				<option value="10000">10K+</option>
				<option value="50000">50K+</option>
				<option value="100000">100K+</option>
				<option value="500000">500K+</option>
				<option value="1000000">1M+</option>
			</select>
		</div>
		
		<!-- LinkedIn Stats Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_linkedin_followers">
				<span style="color: #0A66C2; margin-right: 5px;"><?php echo TM_Icons::svg( 'linkedin' ); ?></span> LinkedIn
			</label>
			<select id="filter_linkedin_followers" name="linkedin_followers" class="dokan-form-control">
				<option value="">Min Connections</option>
				<option value="500">500+</option>
				<option value="1000">1K+</option>
				<option value="5000">5K+</option>
				<option value="10000">10K+</option>
				<option value="25000">25K+</option>
				<option value="50000">50K+</option>
			</select>
		</div>
		
		<!-- Growth Metrics Filter -->
		<div class="filter-item attribute-filter">
			<label for="filter_growth_rate">
				📈 Growth Rate
			</label>
			<select id="filter_growth_rate" name="growth_rate" class="dokan-form-control">
				<option value="">Min Monthly Growth</option>
				<option value="5">5%+</option>
				<option value="10">10%+</option>
				<option value="15">15%+</option>
				<option value="20">20%+</option>
				<option value="30">30%+</option>
				<option value="50">50%+</option>
			</select>
		</div>
		
	</div>
</div>
