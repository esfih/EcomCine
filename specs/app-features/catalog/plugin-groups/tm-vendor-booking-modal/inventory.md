# Inventory - tm-vendor-booking-modal

## Group Summary

Current module controls modal booking and checkout interactions tied to WooCommerce Bookings products on vendor store pages.

## Feature Inventory

1. Vendor store booking product discovery (half-day category)
- Current dependencies: Woo product query, product_type=booking taxonomy, vendor author filtering
- Core logic candidate: offer discovery and featured booking selection rules
- Default WP adapter target: WP CPT offer registry and pricing/slot metadata

2. Modal booking form rendering and booking asset loading
- Current dependencies: Woo Bookings form classes/scripts
- Core logic candidate: booking input contract and form state machine
- Default WP adapter target: custom booking form component + WP REST availability endpoint

3. Add-to-cart and checkout modal actions
- Current dependencies: Woo cart/checkout AJAX endpoints and checkout params
- Core logic candidate: transactional action flow and validation contract
- Default WP adapter target: order-intent and checkout API workflow built on WP-native entities

4. Checkout field/privacy/terms customization filters
- Current dependencies: Woo checkout hooks and filters
- Core logic candidate: checkout policy and required fields logic
- Default WP adapter target: policy/consent blocks and server-side validation middleware

## Migration Risk Notes

- Highest risk transactional area due to payment/booking coupling.
- Current flow depends on Woo JS runtime assumptions.

## Parity Oracle (Initial)

- Same booking eligibility and validation outcomes.
- Same checkout-required field/consent behavior.
- Same successful booking transaction state transitions.
