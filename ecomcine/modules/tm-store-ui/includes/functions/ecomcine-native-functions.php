<?php
/**
 * EcomCine Native Functions
 * 
 * Pure EcomCine implementation - no Dokan dependencies
 * 
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get store tabs for a vendor
 * 
 * @param int $vendor_id
 * @return array
 */
function ecomcine_get_store_tabs( $vendor_id ) {
	// Return EcomCine-native store tabs structure
	$tabs = array(
		array(
			'name'    => __( 'Products', 'ecomcine' ),
			'slug'    => 'products',
			'priority' => 10,
		),
		array(
			'name'    => __( 'About', 'ecomcine' ),
			'slug'    => 'about',
			'priority' => 20,
		),
		array(
			'name'    => __( 'Contact', 'ecomcine' ),
			'slug'    => 'contact',
			'priority' => 30,
		),
	);

	// Sort by priority
	usort( $tabs, function( $a, $b ) {
		return $a['priority'] - $b['priority'];
	} );

	return $tabs;
}

/**
 * Get social profile fields configuration
 * 
 * @return array
 */
function ecomcine_get_social_profile_fields() {
	return array(
		'facebook'  => array(
			'label' => __( 'Facebook', 'ecomcine' ),
			'icon'  => 'facebook',
		),
		'twitter'   => array(
			'label' => __( 'Twitter', 'ecomcine' ),
			'icon'  => 'twitter',
		),
		'instagram' => array(
			'label' => __( 'Instagram', 'ecomcine' ),
			'icon'  => 'instagram',
		),
		'linkedin'  => array(
			'label' => __( 'LinkedIn', 'ecomcine' ),
			'icon'  => 'linkedin',
		),
		'website'   => array(
			'label' => __( 'Website', 'ecomcine' ),
			'icon'  => 'globe',
		),
	);
}

/**
 * Get current datetime in EcomCine format
 * 
 * @return WP_DateTime
 */
function ecomcine_current_datetime() {
	return new DateTime( 'now', wp_timezone() );
}

/**
 * Get seller short address
 * 
 * @param int   $vendor_id
 * @param bool  $full
 * @return string
 */
function ecomcine_get_seller_short_address( $vendor_id, $full = false ) {
	// Get vendor meta
	$address = get_user_meta( $vendor_id, 'store_address', true );
	$city    = get_user_meta( $vendor_id, 'store_city', true );
	$country = get_user_meta( $vendor_id, 'store_country', true );

	if ( empty( $address ) && empty( $city ) ) {
		return __( 'Location not specified', 'ecomcine' );
	}

	$parts = array();
	if ( ! empty( $address ) ) {
		$parts[] = $address;
	}
	if ( ! empty( $city ) ) {
		$parts[] = $city;
	}
	if ( ! empty( $country ) ) {
		$parts[] = $country;
	}

	if ( $full ) {
		return implode( ', ', $parts );
	}

	// Return short format (city + country)
	if ( ! empty( $city ) && ! empty( $country ) ) {
		return $city . ', ' . $country;
	}

	return implode( ', ', $parts );
}

/**
 * Get EcomCine option value
 * 
 * @param string $option_name
 * @param string $group
 * @param mixed  $default
 * @return mixed
 */
function ecomcine_get_option( $option_name, $group = '', $default = '' ) {
	if ( ! empty( $group ) ) {
		$option_name = $group . '_' . $option_name;
	}

	$value = get_option( $option_name, $default );
	return $value;
}

/**
 * Get vendor store banner width
 * 
 * @return int
 */
function ecomcine_get_vendor_store_banner_width() {
	$width = get_option( 'ecomcine_banner_width', 1200 );
	return absint( $width );
}

/**
 * Get store user object (EcomCine native)
 * 
 * @param int $vendor_id
 * @return object|null
 */
function ecomcine_get_store_user( $vendor_id ) {
	$vendor_id = absint( $vendor_id );

	if ( $vendor_id <= 0 || ! user_can( $vendor_id, 'edit_posts' ) ) {
		return null;
	}

	if ( class_exists( 'TM_Standalone_Store_User', false ) ) {
		return new TM_Standalone_Store_User( $vendor_id );
	}

	$user = get_userdata( $vendor_id );
	if ( ! $user ) {
		return null;
	}

	return new class( $user ) {
		private WP_User $user;
		private array $shop_info;

		public function __construct( WP_User $user ) {
			$this->user = $user;
			$this->shop_info = array(
				'name'               => (string) get_user_meta( $user->ID, 'store_name', true ),
				'description'        => (string) get_user_meta( $user->ID, 'store_description', true ),
				'banner'             => get_user_meta( $user->ID, 'store_banner', true ),
				'avatar'             => get_user_meta( $user->ID, 'store_avatar', true ),
				'social_profiles'    => get_user_meta( $user->ID, 'store_social_profiles', true ),
				'store_time'         => get_user_meta( $user->ID, 'store_time', true ),
				'store_time_enabled' => get_user_meta( $user->ID, 'store_time_enabled', true ),
			);
		}

		public function get_id(): int {
			return (int) $this->user->ID;
		}

		public function get_shop_info(): array {
			return $this->shop_info;
		}

		public function get_social_profiles(): array {
			$profiles = $this->shop_info['social_profiles'] ?? array();
			return is_array( $profiles ) ? $profiles : array();
		}
	};
}
