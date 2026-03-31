---
feature_id: tvbm-002
title: Modal booking form rendering and booking asset loading
group: tm-vendor-booking-modal
migration_risk: high
migration_phase: 3
adapter_status:
  compatibility: active
  default_wp: complete
last_updated: 2026-03-27
---

# tvbm-002 — Modal Booking Form Rendering and Booking Asset Loading

## Business Objective

Load and render the WooCommerce Bookings product form inside a modal overlay on the vendor store page, providing the date/slot selection UX before checkout proceeds.

## Actors

- **Buyer/Visitor** — interacts with booking form calendar and options inside the modal
- **System** — enqueues WC Bookings scripts; renders WC booking form HTML on AJAX request

## UX Journey / User Actions

1. Vendor store page loads; plugin calls `get_half_day_booking_product_id($vendor_id)` (tvbm-001)
2. If product found (`product_id > 0`): enqueue modal CSS/JS and WooCommerce Bookings scripts
3. Modal trigger button rendered in vendor page footer via `wp_footer` hook
4. User clicks booking trigger → modal opens
5. JS fires `wp_ajax_tm_vendor_booking_form` with `product_id`
6. Server creates `WC_Product_Booking` object, creates `WC_Booking_Form`, buffers form output via `ob_start()`
7. Rendered form HTML returned as `{ success: true, html: '<form>...' }`
8. JS injects `html` into modal body; WC Bookings scripts initialize date picker + availability

## Inputs

| Field | Type | Source |
|---|---|---|
| vendor_id | int | JS (from page context) |
| product_id | int | JS (from DOM data attr) |
| nonce | string | `tm_vendor_booking_modal` |

## Outputs

| Output | Description |
|---|---|
| Modal trigger HTML | Rendered in page footer via `wp_footer` |
| Booking form HTML | `WC_Booking_Form` output buffered and returned as JSON `html` string |
| WC Bookings scripts | `wc_booking_form_scripts`, calendar JS, availability AJAX scripts enqueued |

## State Transitions

```
modal_state:   hidden → open          (user clicks trigger)
               open   → form_loaded   (AJAX returns form HTML and JS injects it)
form_state:    empty  → selecting     (user opening date picker)
               selecting → validated  (all required booking fields filled)
```

## Conditional Logic

- Modal not rendered if `get_half_day_booking_product_id` returns `0`
- WC Bookings scripts only enqueued on vendor store pages (context check via `is_store_page()`)
- Form output requires `WC_Product_Booking` class and `WC_Booking_Form` (WooCommerce Bookings active)
- Nonce `tm_vendor_booking_modal` verified before serving form HTML
- If product is not a valid booking product → AJAX returns `{ success: false, error: 'invalid_product' }`

## Side Effects

- WooCommerce Bookings JavaScript runtime enqueued (`booking-form.js`, `wc-bookings-booking-form`)
- WC Bookings availability AJAX endpoints enabled (`wp_ajax_wc_bookings_*`)

## Current Dependencies

| Dependency | Usage |
|---|---|
| WooCommerce Bookings `WC_Product_Booking` | Product wrapper for booking product |
| WooCommerce Bookings `WC_Booking_Form` | Form renderer (date picker, options, submit button) |
| WC AJAX nonce system | `wc-add-to-cart-nonce`, `woocommerce-process-checkout` |
| WC asset handles | WC Bookings scripts/styles enqueue handles |
| `wp_ajax_tm_vendor_booking_form` | AJAX action (both authed + nopriv) |
| `ob_start` / `ob_get_clean` | Buffer form HTML from `WC_Booking_Form->output()` |
| `wp_footer` hook | Modal trigger HTML injection |

## Core Contract Definition

```
renderBookingForm(product_id: int) → BookingFormResult
  { html: string }           // WC Booking form HTML suitable for modal injection
  | { error: string }        // 'invalid_product' or 'unavailable'

loadBookingAssets(vendor_id: int, product_id: int) → void
  // Enqueues all scripts/styles required for booking form to be interactive
  // Must be called before wp_footer fires

renderModalTrigger(vendor_id: int, product_id: int) → HTML
  // Button/trigger HTML rendered in footer; drives JS modal open
```

## Compatibility Adapter Behavior

- `WC_Booking_Form::output()` (or equivalent method) buffered via `ob_start()` / `ob_get_clean()`
- WC Bookings standard script enqueue calls (handle names from WC Bookings plugin)
- `wp_ajax_tm_vendor_booking_form` action for server-side form rendering
- AJAX availability checks delegated to WC Bookings own AJAX handlers

## Default WP Adapter Behavior

- Custom booking form component: JSON schema derived from `tm_offer` CPT post meta → rendered as custom block/template
- No `WC_Booking_Form` dependency; no WC Bookings scripts
- Availability API: `GET /wp-json/tm/v1/availability/{offer_id}?date=YYYY-MM-DD` → available slots
- Custom date/slot selection UI (custom JS component replacing WC Bookings calendar)
- `POST /wp-json/tm/v1/booking-form/{offer_id}` → returns custom form schema/HTML

## Parity Oracle

| Assertion | Pass Condition |
|---|---|
| Form renders | Booking form HTML with date picker injects successfully into modal |
| Date selection | User can select a date and receive availability feedback |
| Required field validation | Form blocks proceed if required booking fields are empty |
| Unavailable dates | Unavailable dates shown as disabled in calendar |
| Product guard | `product_id = 0` or non-booking product → no form rendered, no scripts loaded |
