<?php
/**
 * Preferred commerce adapter for WooCommerce + Dokan stack.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Commerce_Adapter_WooDokan implements EcomCine_Commerce_Adapter {
	public function id() {
		return 'woo-dokan';
	}

	public function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'dokan_get_store_url' );
	}

	public function get_vendor_store_url( $vendor_id ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id ) {
			return '';
		}

		if ( function_exists( 'tm_get_vendor_public_profile_url' ) ) {
			$url = tm_get_vendor_public_profile_url( $vendor_id );
			if ( '' !== $url ) {
				return $url;
			}
		}

		if ( function_exists( 'ecomcine_get_person_route_url' ) ) {
			$url = ecomcine_get_person_route_url( $vendor_id );
			if ( '' !== $url ) {
				return $url;
			}
		}

		if ( ! function_exists( 'dokan_get_store_url' ) ) {
			return '';
		}

		return (string) dokan_get_store_url( $vendor_id );
	}

	// -----------------------------------------------------------------------
	// Financial KPIs.
	// -----------------------------------------------------------------------

	public function get_vendor_balance_html( $vendor_id ) {
		if ( ! function_exists( 'dokan_get_seller_earnings' ) ) {
			return '';
		}

		return (string) dokan_get_seller_earnings( (int) $vendor_id );
	}

	public function get_vendor_mrr( $vendor_id ) {
		global $wpdb;

		$vendor_id  = (int) $vendor_id;
		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$month_end   = gmdate( 'Y-m-t 23:59:59' );

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(debit),0) FROM {$wpdb->prefix}dokan_vendor_balance
			 WHERE vendor_id = %d AND trn_date BETWEEN %s AND %s AND status = 'wc-completed'",
			$vendor_id, $month_start, $month_end
		) );
	}

	public function get_vendor_arr( $vendor_id ) {
		global $wpdb;

		$vendor_id = (int) $vendor_id;
		$year_start = gmdate( 'Y-01-01 00:00:00' );
		$year_end   = gmdate( 'Y-12-31 23:59:59' );

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(debit),0) FROM {$wpdb->prefix}dokan_vendor_balance
			 WHERE vendor_id = %d AND trn_date BETWEEN %s AND %s AND status = 'wc-completed'",
			$vendor_id, $year_start, $year_end
		) );
	}

	public function format_price( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return (string) wc_price( (float) $amount );
		}

		return '$' . number_format( (float) $amount, 0 );
	}

	// -----------------------------------------------------------------------
	// Orders HTML.
	// -----------------------------------------------------------------------

	public function get_orders_table_html( $vendor_id ) {
		if ( ! function_exists( 'dokan' ) || ! function_exists( 'dokan_get_current_user_id' ) ) {
			return '<p class="tm-account-muted">Orders are unavailable.</p>';
		}

		$seller_id = (int) $vendor_id;
		$limit     = 10;
		$page      = 1;

		$query_args = array(
			'seller_id' => $seller_id,
			'paged'     => $page,
			'limit'     => $limit,
			'return'    => 'objects',
		);
		$user_orders = dokan()->order->all( $query_args );

		$query_args['return'] = 'count';
		$total_count = (int) dokan()->order->all( $query_args );
		$num_of_pages = $limit > 0 ? (int) ceil( $total_count / $limit ) : 1;

		$allow_shipment       = dokan_get_option( 'enabled', 'dokan_shipping_status_setting', 'off' );
		$wc_shipping_enabled  = get_option( 'woocommerce_calc_shipping' ) === 'yes';
		$bulk_order_statuses  = apply_filters(
			'dokan_bulk_order_statuses',
			array(
				'-1'            => __( 'Bulk Actions', 'dokan-lite' ),
				'wc-on-hold'    => __( 'Change status to on-hold', 'dokan-lite' ),
				'wc-processing' => __( 'Change status to processing', 'dokan-lite' ),
				'wc-completed'  => __( 'Change status to completed', 'dokan-lite' ),
			)
		);

		add_filter( 'post_date_column_time', array( $this, '_format_order_date_filter' ), 10, 2 );
		ob_start();
		dokan_get_template_part(
			'orders/listing',
			'',
			array(
				'user_orders'         => $user_orders,
				'bulk_order_statuses' => $bulk_order_statuses,
				'allow_shipment'      => $allow_shipment,
				'wc_shipping_enabled' => $wc_shipping_enabled,
				'num_of_pages'        => $num_of_pages,
				'page_links'          => array(),
			)
		);
		$markup = ob_get_clean();
		remove_filter( 'post_date_column_time', array( $this, '_format_order_date_filter' ), 10 );

		// Strip columns and controls not needed in the panel.
		$markup = preg_replace( '/<th[^>]*class="[^"]*column-cb[^"]*"[^>]*>.*?<\/th>/s', '', $markup );
		$markup = preg_replace( '/<th[^>]*class="[^"]*dokan-order-select[^"]*"[^>]*>.*?<\/th>/s', '', $markup );
		$markup = preg_replace( '/<th[^>]*>\s*Action\s*<\/th>/i', '', $markup );
		$markup = preg_replace( '/<td[^>]*class="[^"]*dokan-order-action[^"]*"[^>]*>.*?<\/td>/s', '', $markup );
		$markup = preg_replace( '/<div[^>]*class="[^"]*pagination-wrap[^"]*"[^>]*>.*?<\/div>/s', '', $markup );
		$markup = preg_replace(
			'/(<th[^>]*>)(\s*Order Total\s*)(<\/th>)/i',
			'$1Amount$3',
			$markup,
			1
		);
		$markup = preg_replace_callback(
			'/(<td[^>]*class="[^"]*dokan-order-id[^"]*"[^>]*>)(.*?)(<a[^>]*href="([^"]*order_id=\d+[^"]*)"[^>]*>)/s',
			static function ( $matches ) {
				$view_button = '<a class="dokan-btn dokan-btn-default dokan-btn-sm tips tm-account-order-view"'
					. ' href="' . esc_url( $matches[4] ) . '"'
					. ' data-toggle="tooltip" data-placement="top" title="View" aria-label="View">'
					. '<i class="far fa-eye"></i></a> ';
				return $matches[1] . $matches[2] . $view_button . $matches[3];
			},
			$markup
		);

		return (string) $markup;
	}

	public function _format_order_date_filter( $date, $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return $date;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_date_created() ) {
			return $date;
		}

		return date_i18n( 'd/m/Y', $order->get_date_created()->getTimestamp() );
	}

	public function get_order_detail_html( $order_id ) {
		if ( ! function_exists( 'dokan_get_template_part' ) ) {
			return '';
		}

		ob_start();
		dokan_get_template_part( 'orders/details', '', array( 'order_id' => (int) $order_id ) );
		return (string) ob_get_clean();
	}

	public function can_view_order( $order_id, $user_id ) {
		$order_id = (int) $order_id;
		$user_id  = (int) $user_id;

		if ( function_exists( 'dokan_get_seller_id_by_order' ) ) {
			$seller_id = (int) dokan_get_seller_id_by_order( $order_id );
			if ( $seller_id && $seller_id !== $user_id ) {
				return false;
			}
		}

		return user_can( $user_id, 'dokan_view_order' );
	}
}
