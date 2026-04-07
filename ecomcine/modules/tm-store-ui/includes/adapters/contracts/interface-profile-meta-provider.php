<?php
/**
 * Contract: vendor profile meta read/write and completeness (tho-003).
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface THO_Profile_Meta_Provider {
	/**
	 * Return structured vendor profile meta.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array{biography: string, headline: string, skills: string[], social: array, completeness: int}
	 */
	public function get_vendor_profile_meta( int $vendor_id ): array;

	/**
	 * Persist one or more profile meta fields for a vendor.
	 *
	 * @param int   $vendor_id WP user ID.
	 * @param array $data      Partial VendorProfileMeta.
	 * @return bool
	 */
	public function save_vendor_profile_meta( int $vendor_id, array $data ): bool;

	/**
	 * Compute the profile completeness score.
	 *
	 * @param int $vendor_id WP user ID.
	 * @return array{score: int, missing_fields: string[]}
	 */
	public function compute_completeness_score( int $vendor_id ): array;
}
