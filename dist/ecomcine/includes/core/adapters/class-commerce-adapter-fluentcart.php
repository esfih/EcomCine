<?php
/**
 * Commerce adapter for the FluentCart stack (wp_fluentcart mode).
 *
 * FluentCart customers are linked to WordPress users via the `user_id` column
 * on the `fct_customers` table.  Orders belong to customers via `customer_id`.
 * The adapter resolves the FluentCart customer record from the WP user ID for
 * every KPI/orders call.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Commerce_Adapter_FluentCart implements EcomCine_Commerce_Adapter {

	// -----------------------------------------------------------------------
	// Core contract.
	// -----------------------------------------------------------------------

	public function id() {
		return 'fluentcart';
	}

	public function is_available() {
		return class_exists( 'FluentCart\App\Models\Order' )
			&& class_exists( 'FluentCart\App\Models\Customer' );
	}

	public function get_vendor_store_url( $vendor_id ) {
		// FluentCart is a single-store platform — no per-vendor store URL.
		return '';
	}

	// -----------------------------------------------------------------------
	// Financial KPIs.
	// -----------------------------------------------------------------------

	/**
	 * FluentCart does not have a vendor commission balance concept.
	 * Return empty so the UI omits the balance row.
	 */
	public function get_vendor_balance_html( $vendor_id ) {
		return '';
	}

	public function get_vendor_mrr( $vendor_id ) {
		$customer = $this->get_customer_by_user_id( (int) $vendor_id );
		if ( ! $customer ) {
			return 0.0;
		}

		$month_start = gmdate( 'Y-m-01' );
		$month_end   = gmdate( 'Y-m-t' );

		return $this->sum_paid_orders( $customer->id, $month_start, $month_end );
	}

	public function get_vendor_arr( $vendor_id ) {
		$customer = $this->get_customer_by_user_id( (int) $vendor_id );
		if ( ! $customer ) {
			return 0.0;
		}

		$year_start = gmdate( 'Y-01-01' );
		$year_end   = gmdate( 'Y-12-31' );

		return $this->sum_paid_orders( $customer->id, $year_start, $year_end );
	}

	public function format_price( $amount ) {
		$currency = get_option( 'fluent_cart_currency', 'USD' );
		$symbol   = $this->get_currency_symbol( $currency );

		return $symbol . number_format( (float) $amount, 2 );
	}

	// -----------------------------------------------------------------------
	// Orders HTML.
	// -----------------------------------------------------------------------

	public function get_orders_table_html( $vendor_id ) {
		if ( ! $this->is_available() ) {
			return '<p class="tm-account-muted">Orders are unavailable.</p>';
		}

		$customer = $this->get_customer_by_user_id( (int) $vendor_id );
		if ( ! $customer ) {
			return '<p class="tm-account-muted">No orders found.</p>';
		}

		/** @var \FluentCart\App\Models\Order[] $orders */
		$orders = \FluentCart\App\Models\Order::query()
			->where( 'customer_id', $customer->id )
			->whereNull( 'parent_id' )
			->orderBy( 'created_at', 'desc' )
			->limit( 10 )
			->get();

		if ( $orders->isEmpty() ) {
			return '<p class="tm-account-muted">No orders found.</p>';
		}

		$rows = '';
		foreach ( $orders as $order ) {
			$status_label = esc_html( ucfirst( str_replace( '_', ' ', $order->payment_status ?? $order->status ) ) );
			$date_label   = $order->created_at
				? date_i18n( 'd/m/Y', strtotime( $order->created_at ) )
				: '—';
			$amount_label = $this->format_price( (float) ( $order->total_paid ?? $order->subtotal ?? 0 ) );

			$rows .= '<tr>'
				. '<td class="dokan-order-id">'
				. '<span class="order-number">#' . esc_html( $order->id ) . '</span> '
				. '<a class="dokan-btn dokan-btn-default dokan-btn-sm tips tm-account-order-view"'
				. ' href="#" data-order-id="' . esc_attr( $order->id ) . '"'
				. ' data-toggle="tooltip" data-placement="top" title="View" aria-label="View">'
				. '<i class="far fa-eye"></i></a>'
				. '</td>'
				. '<td>' . $date_label . '</td>'
				. '<td>' . $status_label . '</td>'
				. '<td>' . esc_html( $amount_label ) . '</td>'
				. '</tr>';
		}

		return '<table class="dokan-table dokan-table-striped order-items tm-account-orders-table">'
			. '<thead><tr>'
			. '<th>Order</th>'
			. '<th>Date</th>'
			. '<th>Status</th>'
			. '<th>Amount</th>'
			. '</tr></thead>'
			. '<tbody>' . $rows . '</tbody>'
			. '</table>';
	}

	public function get_order_detail_html( $order_id ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		$order_id = (int) $order_id;

		/** @var \FluentCart\App\Models\Order|null $order */
		$order = \FluentCart\App\Models\Order::query()
			->with( array( 'order_items', 'billing_address', 'customer' ) )
			->find( $order_id );

		if ( ! $order ) {
			return '';
		}

		$date_label   = $order->created_at
			? date_i18n( 'd/m/Y', strtotime( $order->created_at ) )
			: '—';
		$status_label = esc_html( ucfirst( str_replace( '_', ' ', $order->payment_status ?? $order->status ) ) );
		$total        = $this->format_price( (float) ( $order->total_paid ?? $order->subtotal ?? 0 ) );

		$items_html = '';
		if ( $order->order_items ) {
			foreach ( $order->order_items as $item ) {
				$item_name  = esc_html( $item->name ?? $item->product_id ?? '—' );
				$item_qty   = (int) ( $item->quantity ?? 1 );
				$item_price = $this->format_price( (float) ( $item->subtotal ?? 0 ) );
				$items_html .= '<tr>'
					. '<td>' . $item_name . '</td>'
					. '<td>' . $item_qty . '</td>'
					. '<td>' . esc_html( $item_price ) . '</td>'
					. '</tr>';
			}
		}

		$billing = $order->billing_address;
		$billing_html = '';
		if ( $billing ) {
			$billing_html = '<p>'
				. esc_html( trim( ( $billing->first_name ?? '' ) . ' ' . ( $billing->last_name ?? '' ) ) ) . '<br>'
				. esc_html( $billing->address ?? '' ) . '<br>'
				. esc_html( trim( ( $billing->city ?? '' ) . ' ' . ( $billing->state ?? '' ) . ' ' . ( $billing->zip ?? '' ) ) ) . '<br>'
				. esc_html( $billing->country ?? '' )
				. '</p>';
		}

		return '<div class="tm-order-detail dokan-order-detail">'
			. '<p><strong>Order #' . esc_html( $order->id ) . '</strong> &mdash; ' . $status_label . ' &mdash; ' . $date_label . '</p>'
			. ( $billing_html ? '<h4>Billing Address</h4>' . $billing_html : '' )
			. '<h4>Items</h4>'
			. '<table class="dokan-table">'
			. '<thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead>'
			. '<tbody>' . $items_html . '</tbody>'
			. '<tfoot><tr><td colspan="2"><strong>Total</strong></td><td><strong>' . esc_html( $total ) . '</strong></td></tr></tfoot>'
			. '</table>'
			. '</div>';
	}

	public function can_view_order( $order_id, $user_id ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$order_id = (int) $order_id;
		$user_id  = (int) $user_id;

		// Admins may always view.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$order    = \FluentCart\App\Models\Order::query()
			->with( 'customer' )
			->find( $order_id );

		if ( ! $order || ! $order->customer ) {
			return false;
		}

		return (int) $order->customer->user_id === $user_id;
	}

	// -----------------------------------------------------------------------
	// Internal helpers.
	// -----------------------------------------------------------------------

	/**
	 * Resolve the FluentCart Customer record for a given WP user ID.
	 *
	 * @param int $wp_user_id
	 * @return \FluentCart\App\Models\Customer|null
	 */
	private function get_customer_by_user_id( $wp_user_id ) {
		if ( ! class_exists( 'FluentCart\App\Models\Customer' ) ) {
			return null;
		}

		return \FluentCart\App\Models\Customer::query()
			->where( 'user_id', (int) $wp_user_id )
			->first() ?: null;
	}

	/**
	 * Sum total_paid for paid orders within a date window.
	 *
	 * @param int    $fct_customer_id  FluentCart customer ID (not WP user ID).
	 * @param string $date_start       Y-m-d
	 * @param string $date_end         Y-m-d
	 * @return float
	 */
	private function sum_paid_orders( $fct_customer_id, $date_start, $date_end ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fct_orders';

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(total_paid),0) FROM {$table}
			 WHERE customer_id = %d
			   AND payment_status = 'paid'
			   AND DATE(created_at) BETWEEN %s AND %s",
			(int) $fct_customer_id,
			$date_start,
			$date_end
		) );
	}

	/**
	 * Return a basic currency symbol for the given ISO currency code.
	 *
	 * @param string $currency
	 * @return string
	 */
	private function get_currency_symbol( $currency ) {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'JPY' => '¥',
		);

		return $symbols[ strtoupper( (string) $currency ) ] ?? '$';
	}
}
