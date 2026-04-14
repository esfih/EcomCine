<?php
/**
 * Default-WP adapter: vendor identity via tm_vendor CPT, no Dokan.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_WP_Vendor_Identity_Projector implements THO_Vendor_Identity_Projector {

	public function project_vendor_identity( int $vendor_id ): array {
		if ( $vendor_id <= 0 ) {
			return $this->empty_identity();
		}

		$post_id = $this->get_cpt_post_id( $vendor_id );

		$name = '';
		if ( $post_id ) {
			$name = get_the_title( $post_id );
		}
		if ( empty( $name ) ) {
			$user = get_user_by( 'id', $vendor_id );
			$name = $user ? $user->display_name : '';
		}

		// Avatar: CPT featured image → wp_avatar → ''
		$avatar_url = '';
		if ( $post_id ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$src        = wp_get_attachment_image_src( $thumbnail_id, 'thumbnail' );
				$avatar_url = $src ? (string) $src[0] : '';
			}
		}
		if ( empty( $avatar_url ) ) {
			$avatar_url = get_avatar_url( $vendor_id, [ 'size' => 96 ] );
		}

		// Banner: CPT featured image (large) → ''
		$banner_url = '';
		if ( $post_id ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$src        = wp_get_attachment_image_src( $thumbnail_id, 'large' );
				$banner_url = $src ? (string) $src[0] : '';
			}
		}

		$store_url = function_exists( 'tm_get_vendor_public_profile_url' )
			? tm_get_vendor_public_profile_url( $vendor_id )
			: '';
		if ( '' === $store_url && function_exists( 'ecomcine_get_person_route_url' ) ) {
			$store_url = ecomcine_get_person_route_url( $vendor_id );
		}
		if ( '' === $store_url ) {
			$store_url = get_author_posts_url( $vendor_id );
		}

		return [
			'vendor_id'  => $vendor_id,
			'name'       => $name,
			'avatar_url' => $avatar_url,
			'banner_url' => $banner_url,
			'store_url'  => $store_url,
		];
	}

	public function render_vendor_identity_block( int $vendor_id, string $context = 'product_loop' ): string {
		$identity = $this->project_vendor_identity( $vendor_id );

		if ( empty( $identity['name'] ) ) {
			return '';
		}

		$name_esc      = esc_html( $identity['name'] );
		$avatar_esc    = esc_url( $identity['avatar_url'] );
		$store_url_esc = esc_url( $identity['store_url'] );

		$avatar_html = $avatar_esc
			? '<img class="tm-vendor-avatar" src="' . $avatar_esc . '" alt="' . $name_esc . '" width="48" height="48" />'
			: '';

		return sprintf(
			'<div class="tm-vendor-identity tm-vendor-identity--%1$s"><a href="%2$s">%3$s<span class="tm-vendor-name">%4$s</span></a></div>',
			esc_attr( $context ),
			$store_url_esc,
			$avatar_html,
			$name_esc
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_cpt_post_id( int $vendor_id ): int {
		// Listing service is the canonical authority for listing-ID-to-user mapping.
		if ( class_exists( 'EcomCine_Listing_Service', false ) ) {
			return EcomCine_Listing_Service::get_listing_id_for_user( $vendor_id );
		}
		if ( class_exists( 'TMP_WP_Vendor_CPT' ) ) {
			return (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $vendor_id );
		}

		$posts = get_posts( [
			'post_type'      => 'tm_vendor',
			'meta_key'       => '_tm_vendor_user_id',
			'meta_value'     => $vendor_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	private function empty_identity(): array {
		return [
			'vendor_id'  => 0,
			'name'       => '',
			'avatar_url' => '',
			'banner_url' => '',
			'store_url'  => '',
		];
	}
}
