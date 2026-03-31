---
feature_id: tvbm-004
title: Checkout field, privacy and terms customization filters
group: tm-vendor-booking-modal
migration_risk: high
migration_phase: 3
adapter_status:
  compatibility: active
  default_wp: complete
last_updated: 2026-03-27
---

# tvbm-004 — Checkout Field, Privacy and Terms Customization Filters

## Business Objective

Tailor the WooCommerce checkout experience within the booking modal by stripping unnecessary fields, suppressing default WC UI elements, customizing privacy/terms text with domain-specific policy links, and flagging modal-originated orders.

## Actors

- **Buyer** — sees a simplified, focused checkout form within the modal
- **System** — applies WC filters at checkout time and injects order metadata

## UX Journey / User Actions

1. Buyer reaches checkout step in booking modal (tvbm-003)
2. `woocommerce_checkout_fields` filter fires → plugin removes all fields except: `billing_first_name`, `billing_last_name`, `billing_email`, `billing_phone`
3. `woocommerce_after_checkout_billing_form` fires → plugin injects hidden `<input name="tm_modal_checkout" value="1">`
4. Privacy text replaced via `woocommerce_get_privacy_policy_text` and `woocommerce_checkout_privacy_policy_text` filters → custom text with Talent Terms and Hirer Terms links
5. Order notes disabled via `woocommerce_enable_order_notes_field` → returns `false`
6. Coupon message replaced/suppressed via `woocommerce_checkout_coupon_message` filter
7. `woocommerce_checkout_create_order` action fires → `set_order_defaults` sets `_tm_modal_checkout = 1` order meta

## Inputs

| Field | Type | Source |
|---|---|---|
| `$fields` (checkout_fields) | array | WooCommerce filter chain |
| `$privacy_text` | string | WooCommerce filter chain |
| Current page context | bool | `is_store_page()` / modal flag |

## Outputs

| Output | Description |
|---|---|
| Modified checkout fields | Only first_name, last_name, email, phone retained; all shipping + address fields removed |
| Custom privacy text | Domain privacy text with Talent Terms and Hirer Terms URLs |
| Suppressed UI | No coupon field; no order notes field |
| Order meta | `_tm_modal_checkout = 1` on every order created through modal |

## State Transitions

None — filter-only; no new state created outside of order meta.

## Conditional Logic

- **Field removal**: all `billing_*` fields except retained four removed; all `shipping_*` fields removed; `order_comments` removed
- `ship_to_different_address` field removed
- **Modal detection**: hidden field `tm_modal_checkout` injected via `woocommerce_after_checkout_billing_form`; order meta set via `woocommerce_checkout_create_order`
- Privacy text filter fires regardless of page context (set unconditionally in constructor hooks)
- Future consideration: scope modifications to modal-checkout context only via `tm_modal_checkout` POST param check

## Side Effects

- Orders created through modal contain `_tm_modal_checkout = 1` meta (queryable for analytics)
- Removed fields cause any WC billing address fields to be absent from order records (acceptable; only email/phone/name needed for booking)

## Current Dependencies

| Dependency | Usage |
|---|---|
| WC filter `woocommerce_checkout_fields` | Prune checkout field list |
| WC filter `woocommerce_enable_order_notes_field` | Disable order notes |
| WC filter `woocommerce_checkout_coupon_message` | Suppress coupon UI |
| WC filter `woocommerce_get_privacy_policy_text` | Replace privacy text |
| WC filter `woocommerce_checkout_privacy_policy_text` | Replace privacy policy text in checkout |
| WC action `woocommerce_after_checkout_billing_form` | Inject modal hidden field |
| WC action `woocommerce_checkout_create_order` | Set order defaults (modal meta) |

## Core Contract Definition

```
checkoutFieldPolicy() → FieldPolicy
FieldPolicy {
  required_fields: ['billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone']
  removed_fields: ['billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
                   'billing_postcode', 'billing_country', 'billing_company',
                   'shipping_*', 'order_comments', 'ship_to_different_address']
  show_coupon: false
  show_order_notes: false
}

privacyTermsPolicy() → PrivacyPolicy
PrivacyPolicy {
  privacy_text: string   // custom HTML with Talent Terms (/talent-terms/) and Hirer Terms (/hirer-terms/) links
  terms_text: string
}

setOrderModalFlag(order: WC_Order) → void
  // Sets order meta '_tm_modal_checkout' = '1' on every modal-originated order
```

## Compatibility Adapter Behavior

- All policies applied via WC filter/action hooks registered in constructor
- Privacy URLs: `home_url('/talent-terms/')` and `home_url('/hirer-terms/')`
- `set_order_defaults` receives `WC_Order` instance via `woocommerce_checkout_create_order` action

## Default WP Adapter Behavior

- Custom checkout form component built with explicit field list from `checkoutFieldPolicy()` — no WC filter needed
- Privacy/consent block rendered directly with Talent/Hirer terms links from `privacyTermsPolicy()`
- `tm_order` CPT creation handler sets `tm_modal_checkout` CPT meta directly on creation
- WC hooks no longer needed; policy enforced at form construction time

## Parity Oracle

| Assertion | Pass Condition |
|---|---|
| Field policy | Only first_name, last_name, email, phone presented to buyer in checkout form |
| Removed fields absent | No address fields, company, shipping, or order notes in modal checkout |
| Privacy text | Domain-specific privacy/terms text rendered with correct URLs |
| Order meta | `_tm_modal_checkout = 1` (or equivalent) present on every modal-created order |
| No coupon field | Coupon UI absent from modal checkout |
