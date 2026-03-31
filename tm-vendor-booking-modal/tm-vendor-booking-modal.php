<?php
/**
 * Plugin Name: TM Vendor Booking Modal
 * Description: Loads a featured booking product in a modal on vendor store pages.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class TM_Vendor_Booking_Modal {
	const VERSION = '1.0.0';
	const NONCE_ACTION = 'tm_vendor_booking_modal';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_thankyou_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_action( 'wp_ajax_tm_vendor_booking_form', array( $this, 'ajax_booking_form' ) );
		add_action( 'wp_ajax_nopriv_tm_vendor_booking_form', array( $this, 'ajax_booking_form' ) );
		add_action( 'wp_ajax_tm_vendor_booking_add_to_cart', array( $this, 'ajax_booking_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_tm_vendor_booking_add_to_cart', array( $this, 'ajax_booking_add_to_cart' ) );
		add_action( 'wp_ajax_tm_vendor_booking_checkout', array( $this, 'ajax_booking_checkout' ) );
		add_action( 'wp_ajax_nopriv_tm_vendor_booking_checkout', array( $this, 'ajax_booking_checkout' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ), 20 );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_modal_checkout_flag' ) );
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'disable_order_notes' ) );
		add_filter( 'woocommerce_checkout_coupon_message', array( $this, 'filter_coupon_message' ) );
		add_filter( 'woocommerce_get_privacy_policy_text', array( $this, 'filter_privacy_text' ), 20 );
		add_filter( 'woocommerce_checkout_privacy_policy_text', array( $this, 'filter_privacy_text' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'set_order_defaults' ), 10, 2 );
	}

	private function is_store_page() {
		if ( function_exists( 'dokan_is_store_page' ) ) {
			return dokan_is_store_page();
		}

		return is_author();
	}

	private function get_vendor_id() {
		$vendor_id = absint( get_query_var( 'author' ) );
		return $vendor_id;
	}

	private function get_half_day_booking_product_id( $vendor_id ) {
		if ( ! $vendor_id ) {
			return 0;
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'author'         => $vendor_id,
			'tax_query'      => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array( 'booking' ),
				),
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( 'half-day' ),
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return (int) $query->posts[0]->ID;
		}

		return 0;
	}

	private function get_booking_product( $vendor_id ) {
		$product_id = $this->get_half_day_booking_product_id( $vendor_id );
		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'booking' ) ) {
			return null;
		}

		return $product;
	}

	public function enqueue_assets() {
		if ( ! $this->is_store_page() ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Booking_Form' ) ) {
			return;
		}

		$vendor_id = $this->get_vendor_id();
		if ( ! $vendor_id ) {
			return;
		}

		$product = $this->get_booking_product( $vendor_id );
		$product_id = $product ? $product->get_id() : 0;

		wp_enqueue_style(
			'tm-vendor-booking-modal',
			plugin_dir_url( __FILE__ ) . 'assets/css/tm-vendor-booking-modal.css',
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'tm-vendor-booking-modal',
			plugin_dir_url( __FILE__ ) . 'assets/js/tm-vendor-booking-modal.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		if ( $product ) {
			$booking_form = new WC_Booking_Form( $product );
			$booking_form->scripts();
		}

		wp_enqueue_script( 'wc-checkout' );
		wp_localize_script(
			'wc-checkout',
			'wc_checkout_params',
			array(
				'ajax_url'                  => WC()->ajax_url(),
				'wc_ajax_url'               => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'update_order_review_nonce' => wp_create_nonce( 'update-order-review' ),
				'apply_coupon_nonce'        => wp_create_nonce( 'apply-coupon' ),
				'remove_coupon_nonce'       => wp_create_nonce( 'remove-coupon' ),
				'option_guest_checkout'     => get_option( 'woocommerce_enable_guest_checkout' ),
				'checkout_url'              => WC_AJAX::get_endpoint( 'checkout' ),
				'is_checkout'               => 1,
				'debug_mode'                => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'i18n_checkout_error'       => sprintf(
					__( 'There was an error processing your order. Please check for any charges in your payment method and review your <a href="%s">order history</a> before placing the order again.', 'woocommerce' ),
					esc_url( wc_get_account_endpoint_url( 'orders' ) )
				),
			)
		);

		// Enqueue WooCommerce Stripe Gateway scripts and params
		if ( class_exists( 'WC_Stripe_Assets' ) ) {
			// Enqueue all Stripe assets
			WC_Stripe_Assets::enqueue_scripts();
			
			// Also ensure inline params are localized
			if ( class_exists( 'WC_Gateway_Stripe' ) ) {
				$stripe_gateway = new WC_Gateway_Stripe();
				if ( method_exists( $stripe_gateway, 'get_localized_params' ) ) {
					$params = $stripe_gateway->get_localized_params();
					if ( ! empty( $params ) ) {
						wp_localize_script( 'wc-stripe-upe', 'wc_stripe_upe_params', $params );
					}
				}
			}
		} elseif ( function_exists( 'wc_stripe_enqueue_scripts' ) ) {
			wc_stripe_enqueue_scripts();
		}

		$scripts = wp_scripts();
		$styles = wp_styles();
		$booking_script_src = '';
		$checkout_script_src = '';
		$stripe_js_src = '';
		$stripe_upe_src = '';
		$asset_scripts = array();
		$asset_styles = array();

		if ( $scripts && isset( $scripts->registered['wc-bookings-booking-form'] ) ) {
			$booking_script_src = $scripts->registered['wc-bookings-booking-form']->src;
		}

		if ( $scripts && isset( $scripts->registered['wc-checkout'] ) ) {
			$checkout_script_src = $scripts->registered['wc-checkout']->src;
		}

		if ( $scripts && isset( $scripts->registered['stripe-js'] ) ) {
			$stripe_js_src = $scripts->registered['stripe-js']->src;
		}

		if ( ! $stripe_js_src ) {
			$stripe_js_src = 'https://js.stripe.com/v3/';
		}

		if ( $scripts && isset( $scripts->registered['wc-stripe-upe'] ) ) {
			$stripe_upe_src = $scripts->registered['wc-stripe-upe']->src;
		}

		$resolve_asset_src = function( $src, $base_url ) {
			if ( empty( $src ) ) {
				return '';
			}
			if ( 0 === strpos( $src, '//' ) ) {
				return ( is_ssl() ? 'https:' : 'http:' ) . $src;
			}
			if ( false === strpos( $src, '://' ) ) {
				if ( 0 === strpos( $src, '/' ) ) {
					return home_url( $src );
				}
				return trailingslashit( $base_url ) . $src;
			}
			return $src;
		};

		$script_handles = array(
			'js-cookie',
			'wc-jquery-blockui',
			'jquery-ui-core',
			'jquery-ui-widget',
			'jquery-ui-datepicker',
			'wc-bookings-date',
			'wc-country-select',
			'wc-address-i18n',
			'wc-checkout',
			'wc-add-to-cart',
			'wc-cart-fragments',
			'woocommerce',
		);
		foreach ( $script_handles as $handle ) {
			if ( $scripts && isset( $scripts->registered[ $handle ] ) ) {
				$src = $resolve_asset_src( $scripts->registered[ $handle ]->src, $scripts->base_url );
				$inline = '';
				if ( isset( $scripts->registered[ $handle ]->extra['data'] ) ) {
					$inline = $scripts->registered[ $handle ]->extra['data'];
				}
				$asset_scripts[] = array(
					'handle' => $handle,
					'src'    => $src,
					'inline' => $inline,
				);
			}
		}

		$style_handles = array(
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce-general',
			'jquery-ui-style',
			'wc-bookings-styles',
		);
		foreach ( $style_handles as $handle ) {
			if ( $styles && isset( $styles->registered[ $handle ] ) ) {
				$src = $resolve_asset_src( $styles->registered[ $handle ]->src, $styles->base_url );
				$asset_styles[] = array(
					'handle' => $handle,
					'href'   => $src,
				);
			}
		}

		$add_to_cart_url = '';
		if ( class_exists( 'WC_Ajax_Compat' ) ) {
			$add_to_cart_url = WC_Ajax_Compat::get_endpoint( 'add_to_cart' );
		} elseif ( class_exists( 'WC_AJAX' ) ) {
			$add_to_cart_url = WC_AJAX::get_endpoint( 'add_to_cart' );
		}

		$privacy_url = get_privacy_policy_url();
		$privacy_text = 'Your data will be used to process your order.';
		if ( $privacy_url ) {
			$privacy_text .= ' <a href="' . esc_url( $privacy_url ) . '">Privacy policy</a>.';
		}

		$base_url = home_url();
		$privacy_link = trailingslashit( $base_url ) . 'privacy';
		$terms_link = trailingslashit( $base_url ) . 'terms';
		$terms_html = '<div class="tm-modal-terms-grid">'
			. '<div class="tm-modal-terms-item">'
			. '<input type="checkbox" id="tm-modal-accept-privacy" name="tm_accept_privacy" class="input-checkbox" />'
			. '<label for="tm-modal-accept-privacy">Accept <a href="' . esc_url( $privacy_link ) . '" target="_blank" rel="noopener noreferrer">privacy policy</a></label>'
			. '</div>'
			. '<div class="tm-modal-terms-item">'
			. '<input type="checkbox" id="terms" name="terms" class="input-checkbox" required="required" />'
			. '<label for="terms">Accept <a href="' . esc_url( $terms_link ) . '" target="_blank" rel="noopener noreferrer">terms &amp; conditions</a></label>'
			. '</div>'
			. '</div>';

		wp_localize_script(
			'tm-vendor-booking-modal',
			'tmVendorBookingModal',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
				'vendorId'           => $vendor_id,
				'productId'          => $product_id,
				'formAction'         => 'tm_vendor_booking_form',
				'addToCartAction'    => 'tm_vendor_booking_add_to_cart',
				'checkoutAction'     => 'tm_vendor_booking_checkout',
				'addToCartUrl'       => $add_to_cart_url,
				'bookingScriptSrc'   => $booking_script_src,
				'checkoutScriptSrc'  => $checkout_script_src,
				'stripeJsSrc'        => $stripe_js_src,
				'stripeUpeSrc'       => $stripe_upe_src,
				'assets'             => array(
					'scripts' => $asset_scripts,
					'styles'  => $asset_styles,
				),
				'strings'            => array(
					'loadingForm'     => 'Loading booking form...',
					'loadingCheckout' => 'Loading checkout...',
					'missingProduct'  => 'No booking product found in the Half Day category.',
					'addToCartError'  => 'Unable to add booking to cart. Please try again.',
					'checkoutError'   => 'Unable to load checkout. Please try again.',
					'privacyText'    => $privacy_text,
					'termsHtml'      => $terms_html,
				),
			)
		);
	}

	public function enqueue_thankyou_assets() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( ! is_checkout() || ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		wp_enqueue_style(
			'tm-vendor-booking-thankyou',
			plugin_dir_url( __FILE__ ) . 'assets/css/tm-vendor-booking-thankyou.css',
			array(),
			self::VERSION
		);
	}


	public function render_modal() {
		if ( ! $this->is_store_page() ) {
			return;
		}

		?>
		<div class="tm-booking-modal" aria-hidden="true" role="dialog" aria-modal="true">
			<div class="tm-booking-modal__backdrop" aria-hidden="true"></div>
			<div class="tm-booking-modal__dialog" role="document" aria-labelledby="tm-booking-modal-title">
				<button class="tm-booking-modal__close" type="button" aria-label="Close booking">
					<span aria-hidden="true">&times;</span>
				</button>
				<div class="tm-booking-modal__header">
					<h3 id="tm-booking-modal-title">Book a session</h3>
					<p class="tm-booking-modal__subtitle">Choose a time, then complete checkout.</p>
				</div>
				<div class="tm-booking-modal__body">
					<div class="tm-booking-modal__panel tm-booking-modal__panel--booking">
						<div class="tm-booking-modal__loading">Loading booking form...</div>
					</div>
					<div class="tm-booking-modal__panel tm-booking-modal__panel--checkout"></div>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_booking_form() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		$vendor_id = isset( $_POST['vendorId'] ) ? absint( $_POST['vendorId'] ) : 0;
		if ( ! $vendor_id ) {
			wp_send_json_error( array( 'message' => 'Missing vendor.' ), 400 );
		}

		$product = $this->get_booking_product( $vendor_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Missing booking product.' ), 404 );
		}

		$product_id = $product->get_id();
		$post = get_post( $product_id );

		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Missing product.' ), 404 );
		}

		$GLOBALS['product'] = $product;
		setup_postdata( $post );
		wc_setup_product_data( $post );

		ob_start();
		?>
		<div class="tm-booking-modal__section tm-booking-modal__section--booking">
			<div class="tm-booking-modal__section-title">Select booking details</div>
			<?php do_action( 'woocommerce_booking_add_to_cart' ); ?>
		</div>
		<?php
		$html = ob_get_clean();
		wp_reset_postdata();

		// Get vendor display name
		$vendor = get_userdata( $vendor_id );
		$vendor_name = $vendor ? $vendor->display_name : '';

		wp_send_json_success(
			array(
				'html'       => $html,
				'productId'  => $product_id,
				'productUrl' => get_permalink( $product_id ),
				'vendorName' => $vendor_name,
			)
		);
	}

	public function ajax_booking_add_to_cart() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Cart unavailable.' ), 400 );
		}

		$form_data = isset( $_POST['formData'] ) ? (string) wp_unslash( $_POST['formData'] ) : '';
		if ( '' === $form_data ) {
			wp_send_json_error( array( 'message' => 'Missing booking data.' ), 400 );
		}

		parse_str( $form_data, $data );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => 'Invalid booking data.' ), 400 );
		}

		$product_id = 0;
		if ( isset( $data['add-to-cart'] ) ) {
			$product_id = absint( $data['add-to-cart'] );
		} elseif ( isset( $data['product_id'] ) ) {
			$product_id = absint( $data['product_id'] );
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Missing product.' ), 400 );
		}

		$_POST = $data;
		$_POST['add-to-cart'] = $product_id;

		wc_clear_notices();
		$added = WC()->cart->add_to_cart( $product_id, 1 );
		$errors = wc_get_notices( 'error' );
		wc_clear_notices();

		if ( ! $added || ! empty( $errors ) ) {
			$message = 'Unable to add booking to cart.';
			if ( ! empty( $errors[0]['notice'] ) ) {
				$message = wp_strip_all_tags( $errors[0]['notice'] );
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		wp_send_json_success( array( 'message' => 'Added to cart.' ) );
	}

	public function ajax_booking_checkout() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Cart unavailable.' ), 400 );
		}

		if ( ! is_user_logged_in() && 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
			$account_url = wc_get_page_permalink( 'myaccount' );
			$login_html = '<div class="tm-booking-modal__message">Please sign in to continue checkout.</div>';
			$login_html .= '<a class="tm-booking-modal__login" href="' . esc_url( $account_url ) . '">Log in or create an account</a>';
			wp_send_json_success( array( 'html' => $login_html ) );
		}

		if ( ! defined( 'TM_BOOKING_MODAL_CHECKOUT' ) ) {
			define( 'TM_BOOKING_MODAL_CHECKOUT', true );
		}

		// CRITICAL: Set up proper WooCommerce checkout page context
		global $wp, $wp_query;
		
		// Make WooCommerce think we're on the checkout page
		$checkout_page_id = wc_get_page_id( 'checkout' );
		if ( $checkout_page_id > 0 ) {
			$wp_query->queried_object_id = $checkout_page_id;
			$wp_query->queried_object = get_post( $checkout_page_id );
			$wp->query_vars['page_id'] = $checkout_page_id;
		}
		
		// Force is_checkout() to return true
		$wp_query->set( 'page', '' );
		$wp_query->is_page = true;
		$wp_query->is_singular = true;
		$wp_query->is_checkout = true;
		
		// Enqueue payment gateway scripts in AJAX context
		do_action( 'wp_enqueue_scripts' );
		
		// Specifically trigger payment gateway script enqueuing
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		foreach ( $available_gateways as $gateway ) {
			if ( method_exists( $gateway, 'payment_scripts' ) ) {
				$gateway->payment_scripts();
			}
		}

		// Capture all enqueued scripts data to send with response
		$scripts_data = array();
		if ( class_exists( 'WC_Gateway_Stripe' ) ) {
			$stripe_gateway = WC()->payment_gateways->payment_gateways()['stripe'] ?? null;
			if ( $stripe_gateway && method_exists( $stripe_gateway, 'get_localized_params' ) ) {
				$scripts_data['stripe_params'] = $stripe_gateway->get_localized_params();
			}
		}

		ob_start();
		if ( WC()->cart->is_empty() ) {
			?>
			<div class="tm-booking-modal__empty">Your cart is empty.</div>
			<?php
		} else {
			// Output the checkout form
			echo do_shortcode( '[woocommerce_checkout]' );
			
			// Also output inline scripts that payment gateways may have added
			wp_print_scripts();
			wp_print_styles();
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 
			'html' => $html,
			'scripts_data' => $scripts_data 
		) );
	}

	private function is_modal_checkout() {
		return defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT;
	}

	private function is_modal_checkout_request() {
		if ( $this->is_modal_checkout() ) {
			return true;
		}

		return isset( $_REQUEST['tm_modal_checkout'] ) && '1' === (string) wp_unslash( $_REQUEST['tm_modal_checkout'] );
	}

	public function render_modal_checkout_flag() {
		if ( ! $this->is_modal_checkout() ) {
			return;
		}

		echo '<input type="hidden" name="tm_modal_checkout" value="1" />';
	}

	public function filter_checkout_fields( $fields ) {
		if ( ! $this->is_modal_checkout_request() ) {
			return $fields;
		}

		// Keep only essential billing fields
		$keep = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
		);

		// Remove all fields except the ones we want to keep
		foreach ( $fields['billing'] as $key => $field ) {
			if ( ! in_array( $key, $keep, true ) ) {
				unset( $fields['billing'][ $key ] );
			}
		}

		// Configure first name field
		$fields['billing']['billing_first_name']['label'] = 'Full Name';
		$fields['billing']['billing_first_name']['placeholder'] = 'Full Name';
		$fields['billing']['billing_first_name']['required'] = true;
		$fields['billing']['billing_first_name']['class'] = array( 'form-row-third', 'tm-modal-full-name' );
		$fields['billing']['billing_first_name']['priority'] = 10;

		// Configure last name field (hidden - auto-populated by JavaScript)
		$fields['billing']['billing_last_name']['label'] = 'Last Name';
		$fields['billing']['billing_last_name']['placeholder'] = '';
		$fields['billing']['billing_last_name']['required'] = false;
		$fields['billing']['billing_last_name']['class'] = array( 'form-row-hidden', 'tm-modal-last-name-hidden' );
		$fields['billing']['billing_last_name']['priority'] = 15;

		// Configure email field
		$fields['billing']['billing_email']['label'] = 'Email Address';
		$fields['billing']['billing_email']['placeholder'] = 'Email Address';
		$fields['billing']['billing_email']['required'] = true;
		$fields['billing']['billing_email']['class'] = array( 'form-row-third' );
		$fields['billing']['billing_email']['priority'] = 20;

		// Configure phone field
		$fields['billing']['billing_phone']['label'] = 'Phone (optional)';
		$fields['billing']['billing_phone']['placeholder'] = 'Phone Number';
		$fields['billing']['billing_phone']['required'] = false;
		$fields['billing']['billing_phone']['class'] = array( 'form-row-third' );
		$fields['billing']['billing_phone']['priority'] = 30;

		// Remove shipping fields completely
		if ( isset( $fields['shipping'] ) ) {
			$fields['shipping'] = array();
		}

		// Remove account fields
		if ( isset( $fields['account'] ) ) {
			$fields['account'] = array();
		}

		return $fields;
	}

	public function disable_order_notes( $enabled ) {
		if ( $this->is_modal_checkout() ) {
			return false;
		}

		return $enabled;
	}

	public function filter_coupon_message( $message ) {
		if ( $this->is_modal_checkout() ) {
			return '';
		}

		return $message;
	}

	public function filter_privacy_text( $text ) {
		if ( ! $this->is_modal_checkout() ) {
			return $text;
		}

		// Shorter privacy text for modal checkout
		$privacy_url = get_privacy_policy_url();
		$privacy_text = 'Your data will be used to process your order.';
		if ( $privacy_url ) {
			$privacy_text .= ' <a href="' . esc_url( $privacy_url ) . '">Privacy policy</a>.';
		}

		return $privacy_text;
	}

	public function set_order_defaults( $order, $data ) {
		if ( ! $this->is_modal_checkout() ) {
			return;
		}

		// Set default billing country and state if not provided
		$base_country = WC()->countries->get_base_country();
		$base_state = WC()->countries->get_base_state( $base_country );

		if ( empty( $data['billing_country'] ) ) {
			$order->set_billing_country( $base_country );
		}

		if ( empty( $data['billing_state'] ) ) {
			$order->set_billing_state( $base_state );
		}

		// Also set shipping to same as billing
		$order->set_shipping_country( $order->get_billing_country() );
		$order->set_shipping_state( $order->get_billing_state() );
		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
	}
}

new TM_Vendor_Booking_Modal();
