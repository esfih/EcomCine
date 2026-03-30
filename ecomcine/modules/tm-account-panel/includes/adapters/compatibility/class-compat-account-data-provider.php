<?php
/**
 * Compatibility adapter: account data provider via WooCommerce + Dokan.
 *
 * Implements tap-004 contracts using wc_get_orders(),
 * WC_Booking_Data_Store and WP user meta.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAP_Compat_Account_Data_Provider implements TAP_Account_Data_Provider {

	const PAGE_SIZE = 10;

	// -----------------------------------------------------------------------
	// Orders
	// -----------------------------------------------------------------------

	public function get_orders( int $user_id, int $page = 1 ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [ 'orders' => [] ];
		}

		$offset = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;
		$raw    = wc_get_orders( [
			'customer' => $user_id,
			'limit'    => self::PAGE_SIZE,
			'offset'   => $offset,
			'orderby'  => 'date',
			'order'    => 'DESC',
		] );

		$orders = [];
		foreach ( $raw as $order ) {
			/** @var WC_Order $order */
			$vendor_name = '';
			if ( function_exists( 'dokan_get_seller_id_by_order' ) ) {
				$seller_id = (int) dokan_get_seller_id_by_order( $order->get_id() );
				if ( $seller_id ) {
					$u = get_userdata( $seller_id );
					$vendor_name = $u ? $u->display_name : '';
				}
			}

			$orders[] = [
				'order_id'    => $order->get_id(),
				'date'        => $order->get_date_created()
					? $order->get_date_created()->date( 'Y-m-d H:i:s' )
					: '',
				'status'      => $order->get_status(),
				'total'       => $order->get_formatted_order_total(),
				'vendor_name' => $vendor_name,
			];
		}

		return [ 'orders' => $orders ];
	}

	// -----------------------------------------------------------------------
	// Bookings
	// -----------------------------------------------------------------------

	public function get_bookings( int $user_id, int $page = 1 ): array {
		if ( ! class_exists( 'WC_Booking_Data_Store' ) ) {
			return [ 'bookings' => [] ];
		}

		$raw  = WC_Booking_Data_Store::get_bookings_for_user( $user_id );
		$raw  = is_array( $raw ) ? $raw : [];
		$offset = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;
		$slice  = array_slice( $raw, $offset, self::PAGE_SIZE );

		$bookings = [];
		foreach ( $slice as $booking ) {
			/** @var WC_Booking $booking */
			$bookings[] = [
				'booking_id'   => $booking->get_id(),
				'product_name' => $booking->get_product() ? $booking->get_product()->get_name() : '',
				'date'         => $booking->get_start_date( 'Y-m-d H:i:s' ),
				'status'       => $booking->get_status(),
				'vendor_name'  => '', // Not readily available from WC Bookings.
			];
		}

		return [ 'bookings' => $bookings ];
	}

	// -----------------------------------------------------------------------
	// IP assets (invitations + shares — see tap-003)
	// -----------------------------------------------------------------------

	public function get_ip_assets( int $user_id ): array {
		// Invitations where this admin user created them (by admin_id meta).
		$admin_id = $user_id; // Current user is the admin querying their talent pool.

		$talent_users = get_users( [
			'meta_key'   => 'tm_preonboard_admin_id',
			'meta_value' => $admin_id,
			'number'     => 100,
		] );

		$invitations = [];
		foreach ( $talent_users as $talent ) {
			$status = $this->get_onboarding_status_for( $talent->ID );
			$invitations[] = [
				'token'           => (string) get_user_meta( $talent->ID, 'tm_invitation_token', true ),
				'talent_name'     => $talent->display_name,
				'lifecycle_stage' => $status['lifecycle_stage'],
				'invited_at'      => $status['invited_at'] ?? '',
				'claimed_at'      => $status['claimed_at'] ?? null,
			];
		}

		// Share tokens created by this user for their own profile.
		$share_tokens = (array) get_user_meta( $user_id, 'tm_share_tokens', true );
		$shares = [];
		foreach ( $share_tokens as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$shares[] = [
				'share_url'   => add_query_arg( [ 'tm_share' => $entry['token'] ?? '' ], get_author_posts_url( $user_id ) ),
				'talent_name' => get_userdata( $user_id ) ? get_userdata( $user_id )->display_name : '',
				'expiry'      => isset( $entry['expiry'] ) ? gmdate( 'Y-m-d H:i:s', (int) $entry['expiry'] ) : '',
			];
		}

		return [
			'invitations' => $invitations,
			'shares'      => $shares,
		];
	}

	// -----------------------------------------------------------------------
	// Private helper
	// -----------------------------------------------------------------------

	private function get_onboarding_status_for( int $talent_user_id ): array {
		$invited_at = (int) get_user_meta( $talent_user_id, 'tm_invited_at', true );
		$claimed_at = (int) get_user_meta( $talent_user_id, 'tm_claimed_at', true );
		$expiry     = (int) get_user_meta( $talent_user_id, 'tm_invitation_expiry', true );
		$claimed    = (int) get_user_meta( $talent_user_id, 'tm_invitation_claimed', true );

		if ( $claimed_at ) {
			$stage = 'claimed';
		} elseif ( $invited_at && $expiry > 0 && time() > $expiry ) {
			$stage = 'expired';
		} elseif ( $invited_at ) {
			$stage = 'invited';
		} else {
			$stage = 'anonymous';
		}

		$result = [ 'lifecycle_stage' => $stage ];
		if ( $invited_at ) {
			$result['invited_at']   = gmdate( 'Y-m-d H:i:s', $invited_at );
			$result['token_expiry'] = gmdate( 'Y-m-d H:i:s', $expiry );
		}
		if ( $claimed_at ) {
			$result['claimed_at'] = gmdate( 'Y-m-d H:i:s', $claimed_at );
		}

		return $result;
	}
}
