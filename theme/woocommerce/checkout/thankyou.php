<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.1.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$is_failed = $order && $order->has_status( 'failed' );
$title = $is_failed ? __( 'Payment failed', 'woocommerce' ) : __( 'Booking confirmed', 'woocommerce' );
$subtitle = $is_failed
	? __( 'Please review the details below and try again.', 'woocommerce' )
	: __( 'Thanks for booking your session. A confirmation has been sent to your email.', 'woocommerce' );

?>

<div class="tm-booking-confirmation">
	<div class="tm-booking-confirmation__dialog">
		<div class="tm-booking-confirmation__header">
			<h3><?php echo esc_html( $title ); ?></h3>
			<p class="tm-booking-confirmation__subtitle"><?php echo esc_html( $subtitle ); ?></p>
		</div>
		<div class="tm-booking-confirmation__body">
			<div class="tm-booking-confirmation__panel">
				<div class="woocommerce-order">
					<?php
					if ( $order ) :
						$booking_id = 0;
						foreach ( $order->get_items() as $item ) {
							$booking_meta = $item->get_meta( '_booking_id', true );
							if ( ! $booking_meta ) {
								$booking_meta = $item->get_meta( 'booking_id', true );
							}
							if ( is_array( $booking_meta ) ) {
								$booking_meta = reset( $booking_meta );
							}
							if ( $booking_meta ) {
								$booking_id = (int) $booking_meta;
								break;
							}
						}

						do_action( 'woocommerce_before_thankyou', $order->get_id() );
						?>

						<?php if ( $order->has_status( 'failed' ) ) : ?>

							<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>

							<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
								<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
								<?php if ( is_user_logged_in() ) : ?>
									<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
								<?php endif; ?>
							</p>

						<?php else : ?>

							<?php wc_get_template( 'checkout/order-received.php', array( 'order' => $order ) ); ?>

							<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

								<li class="woocommerce-order-overview__order order">
									<?php esc_html_e( 'Order ID:', 'woocommerce' ); ?>
									<strong><?php echo esc_html( 'OD-' . $order->get_order_number() ); ?></strong>
								</li>

								<?php if ( $booking_id ) : ?>
									<li class="woocommerce-order-overview__booking booking">
										<?php esc_html_e( 'Booking ID:', 'woocommerce' ); ?>
										<strong><?php echo esc_html( 'BI-' . $booking_id ); ?></strong>
									</li>
								<?php endif; ?>

								<li class="woocommerce-order-overview__date date">
									<?php esc_html_e( 'Date:', 'woocommerce' ); ?>
									<strong><?php echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
								</li>

								<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
									<li class="woocommerce-order-overview__email email">
										<?php esc_html_e( 'Email:', 'woocommerce' ); ?>
										<strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
									</li>
								<?php endif; ?>

								<li class="woocommerce-order-overview__total total">
									<?php esc_html_e( 'Total:', 'woocommerce' ); ?>
									<strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
								</li>

								<?php if ( $order->get_payment_method_title() ) : ?>
									<li class="woocommerce-order-overview__payment-method method">
										<?php esc_html_e( 'Payment method:', 'woocommerce' ); ?>
										<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
									</li>
								<?php endif; ?>

							</ul>

						<?php endif; ?>

						<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
						<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

					<?php else : ?>

						<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
