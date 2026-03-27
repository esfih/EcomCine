---
feature_id: tvbm-001
title: Vendor store booking product discovery (half-day category)
group: tm-vendor-booking-modal
migration_risk: high
migration_phase: 3
adapter_status:
  compatibility: active
  default_wp: not-started
last_updated: 2026-03-27
---

# tvbm-001 — Vendor Store Booking Product Discovery (Half-Day Category)

## Business Objective

Discover the featured WooCommerce Bookings product for a vendor — specifically targeting `product_cat=half-day` with `product_type=booking` — to present as the bookable offer in the vendor store modal.

## Actors

- **System** — resolves the featured booking product on vendor page load or AJAX request

## UX Journey / User Actions

1. User loads a vendor store page
2. Plugin calls `get_half_day_booking_product_id($vendor_id)`
3. `WP_Query` executed: `post_type=product`, `author=$vendor_id`, `post_status=publish`, `posts_per_page=1`
4. `tax_query` with `relation=AND`:
   - `product_type` taxonomy: term `booking`
   - `product_cat` taxonomy: term `half-day`
5. First matching post ID returned; or `0` if no match found
6. Returned ID used by tvbm-002 to load and render the booking form modal

## Inputs

| Field | Type | Source |
|---|---|---|
| vendor_id | int | `$this->get_vendor_id()` from `get_query_var('author')` |

## Outputs

| Output | Type | Description |
|---|---|---|
| product_id | int | Matching published booking product ID; `0` if not found |

## State Transitions

None — pure discovery query. No state written.

## Conditional Logic

- Must match **both** `product_type=booking` AND `product_cat=half-day`
- Only `post_status=publish` products considered (drafts/private excluded)
- Only products where `post_author = vendor_id` considered
- `posts_per_page=1` → returns only the first match (no multi-offer selection in V1)
- Returns `0` if vendor has no matching published booking product → modal not rendered

## Side Effects

None — read-only query.

## Current Dependencies

| Dependency | Usage |
|---|---|
| WooCommerce `product_type` taxonomy | `booking` term registered by WooCommerce Bookings |
| WooCommerce `product_cat` taxonomy | `half-day` category term |
| `WP_Query` | Product discovery query |
| `get_query_var('author')` | Vendor ID resolution from URL |
| `dokan_is_store_page()` / `tm_is_showcase_page()` | Context check before discovery |

## Core Contract Definition

```
discoverBookingOffer(vendor_id: int, offer_type: string = 'half-day') → DiscoveryResult

DiscoveryResult {
  product_id: int    // 0 if not found
}

// Rules:
// - offer_type 'half-day' maps to product_cat 'half-day' + product_type 'booking'
// - Only published products by vendor_id author
// - Returns first result only (deterministic with consistent sort order)
```

## Compatibility Adapter Behavior

- `WP_Query` with `tax_query`:
  ```
  ['relation' => 'AND',
   ['taxonomy' => 'product_type', 'field' => 'slug', 'terms' => ['booking']],
   ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => ['half-day']]]
  ```
- Depends on WooCommerce Bookings having registered the `booking` product type taxonomy term
- Product post `author` field carries the vendor/user_id (WooCommerce + Dokan convention)

## Default WP Adapter Behavior

- Custom CPT `tm_offer` replaces WooCommerce product as the offer entity
- `WP_Query`: `post_type=tm_offer`, `post_status=publish`, `author=$vendor_id`, `meta_query: offer_type=half-day`
- No `product_type` or `product_cat` taxonomy needed — offer type stored as CPT post meta
- Returns `tm_offer` post ID or `0`

## Parity Oracle

| Assertion | Pass Condition |
|---|---|
| Vendor with product | Returns non-zero product_id for vendors with a published half-day booking product |
| Vendor without product | Returns `0` for vendors with no matching product |
| Unpublished excluded | Draft/private booking products do not appear in results |
| Author scoping | Products from other vendors not returned |
