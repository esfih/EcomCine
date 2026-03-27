---
feature_id: tvbm-003
title: Add-to-cart and checkout modal actions
group: tm-vendor-booking-modal
migration_risk: high
migration_phase: 3
adapter_status:
  compatibility: active
  default_wp: not-started
last_updated: 2026-03-27
---

# tvbm-003 — Add-to-Cart and Checkout Modal Actions

## Business Objective

Complete a frictionless booking checkout entirely within the vendor store modal — from adding the booking product to the WooCommerce cart through to order placement — without navigating away from the store page.

## Actors

- **Buyer** — fills booking form and billing fields; submits from within the modal
- **System** — processes add-to-cart, validates checkout data, creates WC order

## UX Journey / User Actions

1. Buyer fills WC booking form in modal (date, duration, options) → tvbm-002
2. Buyer clicks **Continue** / **Book Now** → JS fires `wp_ajax_tm_vendor_booking_add_to_cart`
3. Server: validates nonce, calls `WC()->cart->add_to_cart($product_id, 1, 0, [], $checkout_data)` with booking meta
4. On success: returns `{ success: true, cart_key }` → modal transitions to checkout step
5. Buyer fills billing details (first name, last name, email, phone — tvbm-004 field policy)
6. JS fires `wp_ajax_tm_vendor_booking_checkout`
7. Server: validates nonce, processes WC checkout (`wc_create_order` or `WC_Checkout::process_checkout`)
8. Order created; WC Bookings booking resource reserved
9. Server returns `{ success: true, order_id, redirect_url }` or `{ success: false, errors[] }`
10. Modal transitions to **confirmation** step with order summary

## Inputs

**Add-to-cart:**

| Field | Type | Source |
|---|---|---|
| product_id | int | POST body |
| booking_data | object (date, duration, etc.) | POST body (from WC Bookings form) |
| nonce | string | `tm_vendor_booking_modal` |

**Checkout:**

| Field | Type | Source |
|---|---|---|
| billing fields | object | POST body (first_name, last_name, email, phone) |
| nonce | string | `woocommerce-process-checkout` |
| cart contents | WC session | Server-side WC cart |

## Outputs

```
AddToCartResult {
  success: true,  cart_key: string
  | success: false, error: string
}

CheckoutResult {
  success: true,  order_id: int, redirect_url: string
  | success: false, errors: string[]
}
```

## State Transitions

```
booking_state:  form_validated → in_cart           (add-to-cart succeeds)
                in_cart        → checkout_pending   (modal transitions to billing step)
                checkout_pending → order_created    (checkout succeeds)
                any_step       → error_shown        (AJAX returns failure)

modal_state:    form → checkout → confirmation
```

## Conditional Logic

- Add-to-cart requires valid booking data (WC Bookings validates date/duration via `WC_Booking_Form`)
- Checkout requires `woocommerce-process-checkout` nonce
- `set_order_defaults` injects `tm_modal_checkout = 1` meta on created order (via `woocommerce_checkout_create_order` hook)
- If checkout validation fails: errors array returned, checkout step stays open
- Stripe / payment gateway handles payment capture after order creation (WC standard flow)
- Cart cleared after successful order (WC behavior)

## Side Effects

- WC cart session item created on add-to-cart
- WC order created with order status `pending` or `processing` (gateway-dependent)
- WooCommerce Bookings booking `post` created; resource reserved
- WC order confirmation email sent by WooCommerce
- Payment gateway charge triggered (Stripe or other active gateway)
- Order meta `tm_modal_checkout = 1` set for modal-origin tracking

## Current Dependencies

| Dependency | Usage |
|---|---|
| `WC()->cart->add_to_cart` | Add booking product to cart |
| WooCommerce Bookings booking meta | Date/duration/resource metadata for cart item |
| WC checkout: `WC_Checkout` or `wc_create_order` | Order creation |
| WooCommerce Bookings: booking resource reservation on order | Post-order booking state |
| WC nonce: `woocommerce-process-checkout` | Checkout security |
| `wp_ajax_tm_vendor_booking_add_to_cart` | Add-to-cart AJAX action (authed + nopriv) |
| `wp_ajax_tm_vendor_booking_checkout` | Checkout AJAX action (authed + nopriv) |
| Stripe gateway (or active WC payment gateway) | Payment capture |

## Core Contract Definition

```
addToCart(product_id: int, booking_data: BookingData) → AddToCartResult
  // Adds booking product to WC cart with booking-specific meta
  // Validates booking data via WC Bookings form validation
  // Returns cart_key on success

processCheckout(billing_data: BillingFields, nonce: string) → CheckoutResult
  // Validates billing fields per tvbm-004 field policy
  // Creates WC order from current cart
  // Triggers payment gateway
  // Returns order_id + redirect_url on success; field errors on failure

BookingData { date: string, duration: int, resource_id?: int, ... }
BillingFields { first_name, last_name, email, phone }
```

## Compatibility Adapter Behavior

- `WC()->cart->add_to_cart($product_id, 1, 0, [], $booking_meta)` with WC Bookings booking meta structure
- WC checkout AJAX flow via `woocommerce_checkout_process` + `wc_create_order`
- `woocommerce_checkout_create_order` action hook to inject `tm_modal_checkout` order meta
- Full WooCommerce + WooCommerce Bookings dependency at runtime

## Default WP Adapter Behavior

- **No WC cart**: direct booking intent creation
  - `POST /wp-json/tm/v1/bookings` → creates `tm_booking` CPT with status `pending`
- **No WC checkout AJAX**: single transaction endpoint
  - `POST /wp-json/tm/v1/orders` with `{ booking_id, billing_data, payment_method_id }` → creates `tm_order` + triggers payment
- Payment: Stripe Elements integration calling Stripe API directly (no WC Stripe plugin)
- `tm_modal_checkout = 1` set as CPT meta on `tm_order` post

## Parity Oracle

| Assertion | Pass Condition |
|---|---|
| Booking confirmed | Order ID issued; booking resource reserved |
| Billing validation | Required fields (first_name, last_name, email, phone) enforced; invalid submissions rejected |
| Invalid booking data | Add-to-cart rejected; user sees booking form error |
| Payment captured | Payment charged through active gateway on order creation |
| Modal-origin tracking | `tm_modal_checkout = 1` present on created order |
| Confirmation step | Modal transitions to order confirmation with order ID |
