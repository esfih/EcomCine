<?php
/**
 * Sample Data Installer
 * 
 * Creates sample attribute sets for testing
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Sample_Data {
	
	/**
	 * Install sample data
	 */
	public static function install() {
		$manager = new DCA_Attribute_Manager();
		
		// Check if sample data already exists
		$existing = $manager->get_attribute_sets();
		if ( ! empty( $existing ) ) {
			return; // Don't reinstall if data exists
		}
		
		// Physical Attributes Set
		self::install_physical_attributes( $manager );
		
		// Equipment & Skills Set
		self::install_cameraman_attributes( $manager );
	}
	
	/**
	 * Install Physical Attributes set
	 * 
	 * @param DCA_Attribute_Manager $manager
	 */
	private static function install_physical_attributes( $manager ) {
		$set_data = array(
			'name' => 'Physical Attributes',
			'slug' => 'physical_attributes',
			'icon' => 'admin-users',
			'categories' => array( 'model', 'artist', 'actor' ),
			'priority' => 10,
			'status' => 'active',
		);
		
		$fields = array(
			array(
				'field_name' => 'talent_gender',
				'field_label' => 'Gender',
				'field_icon' => 'groups',
				'field_type' => 'select',
				'field_options' => array(
					'male' => 'Male',
					'female' => 'Female',
				),
			),
			array(
				'field_name' => 'talent_age_range',
				'field_label' => 'Age Range',
				'field_icon' => 'calendar-alt',
				'field_type' => 'select',
				'field_options' => array(
					'18-25' => '18-25',
					'26-35' => '26-35',
					'36-45' => '36-45',
					'46-55' => '46-55',
					'56+' => '56+',
				),
			),
			array(
				'field_name' => 'talent_eye_color',
				'field_label' => 'Eye Color',
				'field_icon' => 'visibility',
				'field_type' => 'select',
				'field_options' => array(
					'brown' => 'Brown',
					'blue' => 'Blue',
					'green' => 'Green',
					'hazel' => 'Hazel',
					'gray' => 'Gray',
				),
			),
			array(
				'field_name' => 'talent_hair_color',
				'field_label' => 'Hair Color',
				'field_icon' => 'art',
				'field_type' => 'select',
				'field_options' => array(
					'black' => 'Black',
					'brown' => 'Brown',
					'blonde' => 'Blonde',
					'red' => 'Red',
					'gray' => 'Gray',
					'white' => 'White',
				),
			),
			array(
				'field_name' => 'talent_height',
				'field_label' => 'Height Range (cm)',
				'field_icon' => 'editor-expand',
				'field_type' => 'select',
				'field_options' => array(
					'150-160' => '150-160 cm',
					'160-170' => '160-170 cm',
					'170-180' => '170-180 cm',
					'180-190' => '180-190 cm',
					'190+' => '190+ cm',
				),
			),
			array(
				'field_name' => 'talent_weight',
				'field_label' => 'Weight Range (kg)',
				'field_icon' => 'archive',
				'field_type' => 'select',
				'field_options' => array(
					'40-50' => '40-50 kg',
					'50-60' => '50-60 kg',
					'60-70' => '60-70 kg',
					'70-80' => '70-80 kg',
					'80-90' => '80-90 kg',
					'90+' => '90+ kg',
				),
			),
			array(
				'field_name' => 'talent_body_type',
				'field_label' => 'Body Type',
				'field_icon' => 'admin-appearance',
				'field_type' => 'select',
				'field_options' => array(
					'slim' => 'Slim',
					'athletic' => 'Athletic',
					'average' => 'Average',
					'curvy' => 'Curvy',
					'muscular' => 'Muscular',
				),
			),
			array(
				'field_name' => 'talent_ethnicity',
				'field_label' => 'Ethnicity',
				'field_icon' => 'admin-site-alt3',
				'field_type' => 'select',
				'field_options' => array(
					'caucasian' => 'Caucasian',
					'african' => 'African',
					'asian' => 'Asian',
					'hispanic' => 'Hispanic',
					'middle_eastern' => 'Middle Eastern',
					'mixed' => 'Mixed',
				),
			),
			array(
				'field_name' => 'talent_skin_tone',
				'field_label' => 'Skin Tone',
				'field_icon' => 'admin-customizer',
				'field_type' => 'select',
				'field_options' => array(
					'fair' => 'Fair',
					'light' => 'Light',
					'medium' => 'Medium',
					'olive' => 'Olive',
					'tan' => 'Tan',
					'dark' => 'Dark',
				),
			),
		);
		
		$manager->create_attribute_set( $set_data, $fields );
	}
	
	/**
	 * Install Cameraman attributes set
	 * 
	 * @param DCA_Attribute_Manager $manager
	 */
	private static function install_cameraman_attributes( $manager ) {
		$set_data = array(
			'name' => 'Equipment & Skills',
			'slug' => 'cameraman_attributes',
			'icon' => 'camera',
			'categories' => array( 'cameraman' ),
			'priority' => 10,
			'status' => 'active',
		);
		
		$fields = array(
			array(
				'field_name' => 'camera_type',
				'field_label' => 'Camera Type',
				'field_icon' => 'camera-alt',
				'field_type' => 'select',
				'field_options' => array(
					'dslr' => 'DSLR',
					'mirrorless' => 'Mirrorless',
					'cinema' => 'Cinema Camera',
					'broadcast' => 'Broadcast Camera',
				),
			),
			array(
				'field_name' => 'video_resolution',
				'field_label' => 'Video Resolution',
				'field_icon' => 'format-video',
				'field_type' => 'select',
				'field_options' => array(
					'1080p' => '1080p (Full HD)',
					'4k' => '4K (UHD)',
					'6k' => '6K',
					'8k' => '8K',
				),
			),
			array(
				'field_name' => 'specialty',
				'field_label' => 'Specialty',
				'field_icon' => 'star-filled',
				'field_type' => 'select',
				'field_options' => array(
					'weddings' => 'Weddings',
					'corporate' => 'Corporate Events',
					'documentary' => 'Documentary',
					'music_videos' => 'Music Videos',
					'commercials' => 'Commercials',
					'sports' => 'Sports',
				),
			),
			array(
				'field_name' => 'drone_operator',
				'field_label' => 'Drone Operator',
				'field_icon' => 'airplane',
				'field_type' => 'select',
				'field_options' => array(
					'yes' => 'Yes',
					'no' => 'No',
				),
			),
			array(
				'field_name' => 'lighting_equipment',
				'field_label' => 'Lighting Equipment',
				'field_icon' => 'lightbulb',
				'field_type' => 'select',
				'field_options' => array(
					'basic' => 'Basic',
					'advanced' => 'Advanced',
					'professional' => 'Professional',
				),
			),
			array(
				'field_name' => 'audio_recording',
				'field_label' => 'Audio Recording',
				'field_icon' => 'microphone',
				'field_type' => 'select',
				'field_options' => array(
					'basic' => 'Basic',
					'professional' => 'Professional',
					'none' => 'None',
				),
			),
			array(
				'field_name' => 'editing_software',
				'field_label' => 'Editing Software',
				'field_icon' => 'edit',
				'field_type' => 'select',
				'field_options' => array(
					'premiere' => 'Adobe Premiere Pro',
					'final_cut' => 'Final Cut Pro',
					'davinci' => 'DaVinci Resolve',
					'avid' => 'Avid Media Composer',
				),
			),
			array(
				'field_name' => 'years_experience',
				'field_label' => 'Years of Experience',
				'field_icon' => 'awards',
				'field_type' => 'select',
				'field_options' => array(
					'1-3' => '1-3 years',
					'3-5' => '3-5 years',
					'5-10' => '5-10 years',
					'10+' => '10+ years',
				),
			),
			array(
				'field_name' => 'live_streaming',
				'field_label' => 'Live Streaming',
				'field_icon' => 'video-alt3',
				'field_type' => 'select',
				'field_options' => array(
					'yes' => 'Yes',
					'no' => 'No',
				),
			),
		);
		
		$manager->create_attribute_set( $set_data, $fields );
	}
}
