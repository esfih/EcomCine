<?php
/**
 * Vendor Attribute Sets — Data Registry
 *
 * Single source of truth for all allowed-value lists used by
 * category-specific vendor attribute fields.
 *
 * Every consumer calls these functions so option lists stay in sync:
 *   • dokan/store-lists/physical-attributes.php  (store listing filter form)
 *   • dokan/store-lists/cameraman-filters.php     (store listing filter form)
 *   • includes/vendor-attributes/vendor-attributes-hooks.php  (profile display + dashboard)
 *   • dokan/settings/store-form.php               (vendor settings template)
 *   • includes/.../store-lists-hooks.php          (query-arg filter function)
 *
 * HOW TO ADD A NEW CATEGORY'S ATTRIBUTE SET
 * ──────────────────────────────────────────
 * 1. Create:  get_<category>_attribute_options() → [ field_key => [ value => label ] ]
 * 2. Register it in get_vendor_attribute_set() below.
 * 3. Add display/save logic in vendor-attributes-hooks.php.
 * 4. Add store-listing filter template in dokan/store-lists/ and wire into
 *    store-lists-hooks.php (filter partials hook + filter_dokan_seller_listing_args).
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;


// =============================================================================
// PHYSICAL ATTRIBUTES  (categories: model, artist)
// Field meta-key prefix: talent_
// =============================================================================

/**
 * Physical Attributes - Dropdown Options (American Units)
 */
function get_talent_physical_attributes_options() {
	return [
		'height' => [
			'' => 'Select Height',
			'4-10' => '4\'10" - 5\'0"',
			'5-1' => '5\'1" - 5\'3"',
			'5-4' => '5\'4" - 5\'6"',
			'5-7' => '5\'7" - 5\'9"',
			'5-10' => '5\'10" - 6\'0"',
			'6-1' => '6\'1" - 6\'3"',
			'6-4' => '6\'4" - 6\'6"',
			'6-7' => '6\'7" and above',
		],
		'weight' => [
			'' => 'Select Weight',
			'under-100' => 'Under 100 lbs',
			'100-120' => '100-120 lbs',
			'121-140' => '121-140 lbs',
			'141-160' => '141-160 lbs',
			'161-180' => '161-180 lbs',
			'181-200' => '181-200 lbs',
			'201-220' => '201-220 lbs',
			'over-220' => 'Over 220 lbs',
		],
		'waist' => [
			'' => 'Select Waist',
			'22-24' => '22-24"',
			'25-27' => '25-27"',
			'28-30' => '28-30"',
			'31-33' => '31-33"',
			'34-36' => '34-36"',
			'37-40' => '37-40"',
			'41-44' => '41-44"',
			'45-plus' => '45" and above',
		],
		'hip' => [
			'' => 'Select Hip',
			'30-32' => '30-32"',
			'33-35' => '33-35"',
			'36-38' => '36-38"',
			'39-41' => '39-41"',
			'42-44' => '42-44"',
			'45-48' => '45-48"',
			'49-plus' => '49" and above',
		],
		'chest' => [
			'' => 'Select Chest',
			'30-32' => '30-32"',
			'33-35' => '33-35"',
			'36-38' => '36-38"',
			'39-41' => '39-41"',
			'42-44' => '42-44"',
			'45-48' => '45-48"',
			'49-plus' => '49" and above',
		],
		'shoe_size' => [
			'' => 'Select Shoe Size',
			'5' => 'US 5',
			'5.5' => 'US 5.5',
			'6' => 'US 6',
			'6.5' => 'US 6.5',
			'7' => 'US 7',
			'7.5' => 'US 7.5',
			'8' => 'US 8',
			'8.5' => 'US 8.5',
			'9' => 'US 9',
			'9.5' => 'US 9.5',
			'10' => 'US 10',
			'10.5' => 'US 10.5',
			'11' => 'US 11',
			'11.5' => 'US 11.5',
			'12' => 'US 12',
			'12.5' => 'US 12.5',
			'13' => 'US 13',
			'13.5' => 'US 13.5',
			'14' => 'US 14',
			'14.5' => 'US 14.5',
			'15' => 'US 15',
		],
		'eye_color' => [
			'' => 'Select Eye Color',
			'blue' => 'Blue',
			'brown' => 'Brown',
			'green' => 'Green',
			'hazel' => 'Hazel',
			'gray' => 'Gray',
			'amber' => 'Amber',
			'black' => 'Black',
			'other' => 'Other',
		],
		'hair_color' => [
			'' => 'Select Hair Color',
			'blonde' => 'Blonde',
			'brunette' => 'Brunette',
			'black' => 'Black',
			'red' => 'Red',
			'auburn' => 'Auburn',
			'gray' => 'Gray',
			'white' => 'White',
			'salt-pepper' => 'Salt & Pepper',
			'other' => 'Other',
		],
	];
}


// =============================================================================
// CAMERAMAN EQUIPMENT & SKILLS  (category: cameraman)
// Field meta-key prefix: none (camera_type, experience_level, etc.)
// =============================================================================

/**
 * Cameraman/Cinematographer - Dropdown Options for Equipment & Skills
 */
function get_cameraman_filter_options() {
	return [
		'camera_type' => [
			'' => 'Select Camera Type',
			'dslr' => 'DSLR',
			'mirrorless' => 'Mirrorless',
			'cinema' => 'Cinema Camera',
			'broadcast' => 'Broadcast Camera',
			'action' => 'Action Camera',
			'all-types' => 'All Types',
		],
		'experience_level' => [
			'' => 'Select Experience',
			'beginner' => 'Beginner',
			'intermediate' => 'Intermediate',
			'professional' => 'Professional',
			'expert' => 'Expert/Master',
		],
		'editing_software' => [
			'' => 'Select Software',
			'premiere' => 'Adobe Premiere Pro',
			'finalcut' => 'Final Cut Pro',
			'davinci' => 'DaVinci Resolve',
			'avid' => 'Avid Media Composer',
			'after-effects' => 'After Effects',
			'multiple' => 'Multiple Software',
		],
		'specialization' => [
			'' => 'Select Specialization',
			'wedding' => 'Wedding/Events',
			'commercial' => 'Commercial/Advertising',
			'documentary' => 'Documentary',
			'music-video' => 'Music Videos',
			'corporate' => 'Corporate Videos',
			'film' => 'Film/Narrative',
			'sports' => 'Sports',
			'real-estate' => 'Real Estate',
			'generalist' => 'Generalist',
		],
		'years_experience' => [
			'' => 'Select Experience',
			'0-2' => '0-2 years',
			'3-5' => '3-5 years',
			'6-10' => '6-10 years',
			'11-15' => '11-15 years',
			'16-plus' => '16+ years',
		],
		'equipment_ownership' => [
			'' => 'Select Ownership',
			'own-gear' => 'Own Professional Gear',
			'rental-only' => 'Work with Rentals',
			'both' => 'Own + Rental Access',
		],
		'lighting_equipment' => [
			'' => 'Select Lighting',
			'none' => 'No Lighting Gear',
			'basic' => 'Basic Lighting',
			'professional' => 'Professional Lighting',
			'studio-grade' => 'Studio-Grade',
		],
		'audio_equipment' => [
			'' => 'Select Audio',
			'none' => 'No Audio Gear',
			'basic-mic' => 'Basic Microphone',
			'professional' => 'Professional Audio Setup',
			'wireless' => 'Wireless Systems',
			'full-setup' => 'Complete Audio Package',
		],
		'drone_capability' => [
			'' => 'Select Drone/Aerial',
			'none' => 'No Drone',
			'beginner' => 'Beginner Drone Pilot',
			'licensed' => 'Licensed/Certified',
			'professional' => 'Professional Aerial',
		],
	];
}


// =============================================================================
// GENERIC DISPATCHER
// Returns the full attribute option set for a given store category slug.
// Returns null for categories that have no custom attribute set.
//
// Usage:
//   $opts = get_vendor_attribute_set( 'cameraman' );
//   if ( $opts ) { foreach ( $opts as $field => $choices ) { ... } }
// =============================================================================

/**
 * Get the attribute option set for a vendor category.
 *
 * @param  string $category  Store category slug (e.g. 'model', 'cameraman').
 * @return array|null        Associative array of [ field_key => [ value => label ] ],
 *                           or null if the category has no registered attribute set.
 */
function get_vendor_attribute_set( string $category ) : ?array {
	$registry = [
		'model'     => 'get_talent_physical_attributes_options',
		'artist'    => 'get_talent_physical_attributes_options',
		'cameraman' => 'get_cameraman_filter_options',
	];

	if ( isset( $registry[ $category ] ) ) {
		return call_user_func( $registry[ $category ] );
	}

	return null;
}
