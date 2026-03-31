<?php
/**
 * Parity check: tm-vendor-booking-modal adapter layer.
 *
 * Run via WP-CLI:
 *   TVBM_ADAPTER=default-wp wp eval-file includes/parity/class-parity-check.php
 *
 * @package TM_Vendor_Booking_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVBM_Parity_Check {

	/** @var array{pass: int, fail: int, results: array} */
	private array $report = [ 'pass' => 0, 'fail' => 0, 'results' => [] ];

	public function run(): array {
		$this->check_offer_discovery_invalid_vendor();
		$this->check_offer_discovery_returns_structure();
		$this->check_offer_discovery_default_wp_returns_offer_id();
		$this->check_offer_discovery_default_wp_links_to_wc_product();
		$this->check_form_renderer_invalid_product();
		$this->check_form_renderer_returns_structure();
		$this->check_checkout_handler_invalid_product();
		$this->check_checkout_policy_returns_required_fields();
		$this->check_checkout_policy_privacy_text_contains_talent_terms();

		return $this->report;
	}

	private function check_offer_discovery_invalid_vendor(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$discovery = $this->make_offer_discovery( $mode );
			$result    = $discovery->discover_booking_offer( 0 );
			$this->assert(
				"offer_discovery[{$mode}] vendor_id=0 → product_id=0",
				isset( $result['product_id'] ) && 0 === $result['product_id']
			);
		}
	}

	private function check_offer_discovery_returns_structure(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$discovery = $this->make_offer_discovery( $mode );
			$result    = $discovery->discover_booking_offer( 9999999 );
			$this->assert(
				"offer_discovery[{$mode}] returns array with 'product_id' int key",
				is_array( $result ) && array_key_exists( 'product_id', $result ) && is_int( $result['product_id'] )
			);
		}
	}

	private function check_offer_discovery_default_wp_returns_offer_id(): void {
		$discovery = new TVBM_WP_Offer_Discovery();
		$result    = $discovery->discover_booking_offer( 9999999 );
		$this->assert(
			"offer_discovery[default-wp] returns array with 'offer_id' int key",
			is_array( $result ) && array_key_exists( 'offer_id', $result ) && is_int( $result['offer_id'] )
		);
		$result0 = $discovery->discover_booking_offer( 0 );
		$this->assert(
			"offer_discovery[default-wp] vendor_id=0 → offer_id=0",
			isset( $result0['offer_id'] ) && 0 === $result0['offer_id']
		);
	}

	private function check_offer_discovery_default_wp_links_to_wc_product(): void {
		// Find a tm_offer CPT that has _tm_src_wc_product_id set.
		$posts = get_posts( array(
			'post_type'   => 'tm_offer',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_query'  => array(
				array(
					'key'     => '_tm_src_wc_product_id',
					'value'   => '',
					'compare' => '!=',
				),
			),
		) );

		if ( empty( $posts ) ) {
			// No offer CPTs on this install — skip live-data assertions.
			return;
		}

		$vendor_id = (int) $posts[0]->post_author;
		$discovery = new TVBM_WP_Offer_Discovery();
		$result    = $discovery->discover_booking_offer( $vendor_id );
		$offer_id  = $result['offer_id'] ?? 0;

		$this->assert(
			"offer_discovery[default-wp] offer_id is non-zero when tm_offer CPT exists",
			$offer_id > 0
		);

		// WC product resolution — only when WooCommerce is active.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product_id = $result['product_id'] ?? 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$this->assert(
			"offer_discovery[default-wp] product_id resolves to a valid WC_Product",
			$product instanceof WC_Product
		);
	}

	private function check_form_renderer_invalid_product(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$renderer = $this->make_form_renderer( $mode );
			$result   = $renderer->render_booking_form( 0 );
			$this->assert(
				"form_renderer[{$mode}] product_id=0 → error key present",
				is_array( $result ) && array_key_exists( 'error', $result )
			);
		}
	}

	private function check_form_renderer_returns_structure(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$renderer = $this->make_form_renderer( $mode );
			// Invalid product → must return array (not throw).
			$result   = $renderer->render_booking_form( 9999999 );
			$this->assert(
				"form_renderer[{$mode}] nonexistent product → returns array",
				is_array( $result )
			);
		}
	}

	private function check_checkout_handler_invalid_product(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$handler = $this->make_checkout_handler( $mode );
			$result  = $handler->add_to_cart( 0, [] );
			$this->assert(
				"checkout_handler[{$mode}] add_to_cart with product_id=0 → success=false",
				isset( $result['success'] ) && false === $result['success']
			);
		}
	}

	private function check_checkout_policy_returns_required_fields(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$policy   = $this->make_checkout_policy( $mode );
			$fp       = $policy->checkout_field_policy();
			$required = $fp['required_fields'] ?? [];
			$expected = [ 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone' ];
			$this->assert(
				"checkout_policy[{$mode}] required_fields contains 4 standard billing fields",
				is_array( $required ) && count( array_intersect( $required, $expected ) ) === 4
			);
		}
	}

	private function check_checkout_policy_privacy_text_contains_talent_terms(): void {
		foreach ( [ 'compat', 'default-wp' ] as $mode ) {
			$policy = $this->make_checkout_policy( $mode );
			$pp     = $policy->privacy_terms_policy();
			$text   = $pp['privacy_text'] ?? '';
			$this->assert(
				"checkout_policy[{$mode}] privacy_text contains talent-terms URL",
				is_string( $text ) && str_contains( $text, 'talent-terms' )
			);
		}
	}

	// -----------------------------------------------------------------------
	// Factory helpers
	// -----------------------------------------------------------------------

	private function make_offer_discovery( string $mode ): TVBM_Offer_Discovery {
		return 'default-wp' === $mode
			? new TVBM_WP_Offer_Discovery()
			: new TVBM_Compat_Offer_Discovery();
	}

	private function make_form_renderer( string $mode ): TVBM_Booking_Form_Renderer {
		return 'default-wp' === $mode
			? new TVBM_WP_Booking_Form_Renderer()
			: new TVBM_Compat_Booking_Form_Renderer();
	}

	private function make_checkout_handler( string $mode ): TVBM_Checkout_Handler {
		return 'default-wp' === $mode
			? new TVBM_WP_Checkout_Handler()
			: new TVBM_Compat_Checkout_Handler();
	}

	private function make_checkout_policy( string $mode ): TVBM_Checkout_Policy {
		return 'default-wp' === $mode
			? new TVBM_WP_Checkout_Policy()
			: new TVBM_Compat_Checkout_Policy();
	}

	// -----------------------------------------------------------------------
	// Assertion helper
	// -----------------------------------------------------------------------

	private function assert( string $label, bool $pass ): void {
		$this->report['results'][] = [ 'label' => $label, 'pass' => $pass ];
		if ( $pass ) {
			$this->report['pass']++;
		} else {
			$this->report['fail']++;
		}
	}
}
