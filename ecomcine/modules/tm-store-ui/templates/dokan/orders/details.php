<?php
/**
 * Dokan Order Details (Theme Override)
 */

global $woocommerce, $wpdb;

if ( ! dokan_is_seller_has_order( dokan_get_current_user_id(), $order_id ) ) {
	echo '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'This is not yours, I swear!', 'dokan-lite' ) . '</div>';
	return;
}

$statuses = wc_get_order_statuses();
$order = wc_get_order( $order_id ); // phpcs:ignore
$hide_customer_info = dokan_get_option( 'hide_customer_info', 'dokan_selling', 'off' );

$status_label = dokan_get_order_status_translated( $order->get_status() );
$status_class = dokan_get_order_status_class( $order->get_status() );
$order_date = $order->get_date_created();
$formatted_date = $order_date ? date_i18n( 'd/m/Y', $order_date->getTimestamp() ) : '';
$amount_total = wc_price( $order->get_total(), [
	'currency' => $order->get_currency(),
	'decimals' => wc_get_price_decimals(),
] );
$earning_total = wc_price( dokan()->commission->get_earning_by_order( $order ), [
	'currency' => $order->get_currency(),
	'decimals' => wc_get_price_decimals(),
] );
$booking_product_name = '';
$booking_date = '';
$booking_location = $order->get_formatted_shipping_address();
$buyer_name = $order->get_formatted_billing_full_name();

if ( ! $buyer_name ) {
	$buyer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
}

if ( ! $buyer_name ) {
	$buyer_name = __( 'Guest', 'dokan-lite' );
}

if ( class_exists( 'WC_Booking_Data_Store' ) && function_exists( 'get_wc_booking' ) ) {
	$booking_ids = WC_Booking_Data_Store::get_booking_ids_from_order_id( $order->get_id() );
	if ( ! empty( $booking_ids ) ) {
		$booking = get_wc_booking( $booking_ids[0] );
		if ( $booking ) {
			$product = $booking->get_product();
			if ( $product ) {
				$booking_product_name = $product->get_name();
			}
			$start_timestamp = $booking->get_start();
			if ( $start_timestamp ) {
				$booking_date = date_i18n( 'd/m/Y', $start_timestamp );
			}
		}
	}
}

if ( ! $booking_product_name ) {
	$line_items = $order->get_items( 'line_item' );
	$first_item = $line_items ? reset( $line_items ) : null;
	if ( $first_item ) {
		$booking_product_name = $first_item->get_name();
	}
}

$can_manage_order = current_user_can( 'dokan_manage_order' ) && dokan_get_option( 'order_status_change', 'dokan_selling', 'on' ) === 'on';
$order_status = $order->get_status();
$can_approve = $can_manage_order && in_array( $order_status, [ 'pending', 'on-hold' ], true );
$can_cancel = $can_manage_order && ! in_array( $order_status, [ 'cancelled', 'refunded' ], true );
$can_refund = $can_manage_order && $order_status !== 'refunded' && (float) $order->get_total_refunded() < (float) $order->get_total();
$change_status_nonce = wp_create_nonce( 'dokan_change_status' );
?>
<div class="dokan-clearfix dokan-order-details-wrap tm-account-order-enhanced">
	<div class="tm-account-order-summary">
		<div class="tm-account-order-summary-left">
			<div class="tm-account-order-id">
				<?php echo esc_html( sprintf( __( 'Order #%d', 'dokan-lite' ), $order->get_id() ) ); ?>
			</div>
			<div class="tm-account-order-meta">
				<span class="tm-account-order-status"><?php echo esc_html( $status_label ); ?></span>
				<?php if ( $formatted_date ) : ?>
					<span class="tm-account-order-date"><?php echo esc_html( $formatted_date ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<div class="tm-account-order-summary-financial">
			<div class="tm-account-order-summary-middle">
				<span class="tm-account-order-amount-label">Amount</span>
				<span class="tm-account-order-amount-value"><?php echo wp_kses_post( $amount_total ); ?></span>
			</div>
			<div class="tm-account-order-summary-right">
				<span class="tm-account-order-earning-label">Earnings</span>
				<span class="tm-account-order-earning-amount"><?php echo wp_kses_post( $earning_total ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( $booking_product_name || $booking_date || $booking_location ) : ?>
		<div class="tm-account-order-summary tm-account-booking-summary">
			<div class="tm-account-order-summary-left">
				<span class="tm-account-summary-label">Booking</span>
				<span class="tm-account-summary-value">
					<?php echo esc_html( $booking_product_name ? $booking_product_name : __( 'Booking', 'dokan-lite' ) ); ?>
				</span>
			</div>
			<div class="tm-account-order-summary-middle">
				<span class="tm-account-summary-label">Booking Date</span>
				<span class="tm-account-summary-value">
					<?php echo esc_html( $booking_date ? $booking_date : __( 'TBD', 'dokan-lite' ) ); ?>
				</span>
			</div>
			<div class="tm-account-order-summary-right">
				<span class="tm-account-summary-label">Booking Location</span>
				<span class="tm-account-summary-value">
					<?php
					if ( $booking_location ) {
						echo wp_kses_post( $booking_location );
					} else {
						echo esc_html__( 'TBD', 'dokan-lite' );
					}
					?>
				</span>
			</div>
			<div class="tm-account-order-summary-right tm-account-order-summary-client">
				<span class="tm-account-summary-label">Client</span>
				<span class="tm-account-summary-value">
					<?php echo esc_html( $buyer_name ); ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $can_manage_order && ( $can_approve || $can_cancel || $can_refund ) ) : ?>
		<div class="tm-account-order-actions" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( $change_status_nonce ); ?>">
			<?php if ( $can_approve ) : ?>
				<button class="tm-account-action-btn" type="button" data-status="processing">Approve</button>
			<?php endif; ?>
			<?php if ( $can_cancel ) : ?>
				<button class="tm-account-action-btn is-ghost" type="button" data-status="cancelled">Cancel</button>
			<?php endif; ?>
			<?php if ( $can_refund ) : ?>
				<button class="tm-account-action-btn is-ghost" type="button" data-status="refunded">Refund</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
