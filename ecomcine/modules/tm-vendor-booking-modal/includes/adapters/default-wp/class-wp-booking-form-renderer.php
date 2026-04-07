<?php
/**
 * Default-WP adapter: booking form renderer without WooCommerce Bookings.
 *
 * Reads tm_offer post meta and emits a lightweight custom form schema.
 * No WC_Booking_Form or WC Bookings scripts are used.
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_WP_Booking_Form_Renderer implements TVBM_Booking_Form_Renderer {

	public function render_booking_form( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [ 'error' => 'invalid_product' ];
		}

		$post = get_post( $product_id );
		if ( ! $post || TVBM_WP_Offer_CPT::POST_TYPE !== $post->post_type ) {
			return [ 'error' => 'invalid_product' ];
		}

		$offer_type = (string) get_post_meta( $product_id, '_tm_offer_type', true );
		$duration   = (int) get_post_meta( $product_id, '_tm_offer_duration', true );
		$price      = (string) get_post_meta( $product_id, '_tm_offer_price', true );

		ob_start();
		?>
		<form class="tm-booking-form" data-offer-id="<?php echo esc_attr( $product_id ); ?>">
			<div class="tm-booking-field">
				<label for="tm-booking-date">Select Date</label>
				<input type="date" id="tm-booking-date" name="booking_date" required
					min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
			</div>
			<?php if ( $duration > 0 ) : ?>
			<div class="tm-booking-field tm-booking-duration">
				<span class="tm-booking-duration-label">Duration: <?php echo esc_html( $duration ); ?> hours</span>
				<input type="hidden" name="booking_duration" value="<?php echo esc_attr( $duration ); ?>">
			</div>
			<?php endif; ?>
			<?php if ( $price ) : ?>
			<div class="tm-booking-field tm-booking-price">
				<span class="tm-booking-price-label">Price: <?php echo esc_html( $price ); ?></span>
			</div>
			<?php endif; ?>
			<div class="tm-booking-actions">
				<button type="submit" class="tm-booking-submit">Continue</button>
			</div>
		</form>
		<?php
		$html = ob_get_clean();

		return [ 'html' => $html ];
	}

	public function load_booking_assets( int $vendor_id, int $product_id ): void {
		// No WC Bookings scripts needed in default-WP mode.
		// The front-end JS bundled with the modal handles date picking.
	}

	public function render_modal_trigger( int $vendor_id, int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}
		return sprintf(
			'<div class="tm-booking-trigger-wrap"><button class="tm-booking-trigger" data-vendor="%d" data-product="%d" type="button">Book Now</button></div>',
			esc_attr( $vendor_id ),
			esc_attr( $product_id )
		);
	}
}
