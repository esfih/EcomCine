<?php
/**
 * Default-WP adapter: account data provider using tm_order / tm_booking CPTs.
 *
 * Eliminates WooCommerce and WooCommerce Bookings dependencies.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_WP_Account_Data_Provider implements TAP_Account_Data_Provider {

	const PAGE_SIZE = 10;

	// -----------------------------------------------------------------------
	// Orders
	// -----------------------------------------------------------------------

	public function get_orders( int $user_id, int $page = 1 ): array {
		$offset = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;

		$posts = get_posts( [
			'post_type'      => TAP_WP_Order_CPT::POST_TYPE,
			'author'         => $user_id,
			'post_status'    => 'any',
			'numberposts'    => self::PAGE_SIZE,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$orders = [];
		foreach ( $posts as $post ) {
			$vendor_id   = (int) get_post_meta( $post->ID, '_tm_vendor_id', true );
			$vendor_name = '';
			if ( $vendor_id ) {
				$u = get_userdata( $vendor_id );
				$vendor_name = $u ? $u->display_name : '';
			}

			$orders[] = [
				'order_id'    => $post->ID,
				'date'        => $post->post_date_gmt,
				'status'      => (string) get_post_meta( $post->ID, '_tm_order_status', true ),
				'total'       => (string) get_post_meta( $post->ID, '_tm_order_total', true ),
				'vendor_name' => $vendor_name,
			];
		}

		return [ 'orders' => $orders ];
	}

	// -----------------------------------------------------------------------
	// Bookings
	// -----------------------------------------------------------------------

	public function get_bookings( int $user_id, int $page = 1 ): array {
		$offset = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;

		$posts = get_posts( [
			'post_type'   => TAP_WP_Booking_CPT::POST_TYPE,
			'author'      => $user_id,
			'post_status' => 'any',
			'numberposts' => self::PAGE_SIZE,
			'offset'      => $offset,
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );

		$bookings = [];
		foreach ( $posts as $post ) {
			$vendor_id   = (int) get_post_meta( $post->ID, '_tm_vendor_id', true );
			$vendor_name = '';
			if ( $vendor_id ) {
				$u = get_userdata( $vendor_id );
				$vendor_name = $u ? $u->display_name : '';
			}

			$offer_id   = (int) get_post_meta( $post->ID, '_tm_offer_id', true );
			$offer_name = $offer_id ? get_the_title( $offer_id ) : '';

			$bookings[] = [
				'booking_id'   => $post->ID,
				'product_name' => $offer_name,
				'date'         => (string) get_post_meta( $post->ID, '_tm_booking_date', true ),
				'status'       => (string) get_post_meta( $post->ID, '_tm_booking_status', true ),
				'vendor_name'  => $vendor_name,
			];
		}

		return [ 'bookings' => $bookings ];
	}

	// -----------------------------------------------------------------------
	// IP assets
	// -----------------------------------------------------------------------

	public function get_ip_assets( int $user_id ): array {
		// Invitations: tm_invitation CPT authored by this user's talent pool.
		$inv_posts = get_posts( [
			'post_type'  => TAP_WP_Invitation_CPT::POST_TYPE,
			'author'     => $user_id,
			'post_status'=> [ 'publish', 'private' ],
			'numberposts'=> 100,
			'meta_query' => [ [ 'key' => '_tm_inv_type', 'value' => 'invite' ] ],
		] );

		$invitations = [];
		foreach ( $inv_posts as $post ) {
			$claimed  = (int) get_post_meta( $post->ID, '_tm_inv_claimed', true );
			$expiry   = (int) get_post_meta( $post->ID, '_tm_inv_expiry', true );

			if ( $claimed ) {
				$stage = 'claimed';
			} elseif ( $expiry > 0 && time() > $expiry ) {
				$stage = 'expired';
			} else {
				$stage = 'invited';
			}

			$u = get_userdata( (int) $post->post_author );
			$invitations[] = [
				'token'           => (string) get_post_meta( $post->ID, '_tm_inv_token', true ),
				'talent_name'     => $u ? $u->display_name : '',
				'lifecycle_stage' => $stage,
				'invited_at'      => $post->post_date_gmt,
				'claimed_at'      => $claimed ? gmdate( 'Y-m-d H:i:s', $claimed ) : null,
			];
		}

		// Share tokens: tm_invitation CPT with type=share.
		$share_posts = get_posts( [
			'post_type'  => TAP_WP_Invitation_CPT::POST_TYPE,
			'author'     => $user_id,
			'post_status'=> 'publish',
			'numberposts'=> 100,
			'meta_query' => [ [ 'key' => '_tm_inv_type', 'value' => 'share' ] ],
		] );

		$shares = [];
		foreach ( $share_posts as $post ) {
			$token  = (string) get_post_meta( $post->ID, '_tm_inv_token', true );
			$expiry = (int) get_post_meta( $post->ID, '_tm_inv_expiry', true );
			$u      = get_userdata( (int) $post->post_author );
			$share_base_url = function_exists( 'ecomcine_get_person_route_url' )
				? ecomcine_get_person_route_url( (int) $post->post_author )
				: '';
			if ( '' === $share_base_url ) {
				$share_base_url = get_author_posts_url( (int) $post->post_author );
			}

			$shares[] = [
				'share_url'   => add_query_arg( [ 'tm_share' => $token ], $share_base_url ),
				'talent_name' => $u ? $u->display_name : '',
				'expiry'      => $expiry ? gmdate( 'Y-m-d H:i:s', $expiry ) : '',
			];
		}

		return [
			'invitations' => $invitations,
			'shares'      => $shares,
		];
	}
}
