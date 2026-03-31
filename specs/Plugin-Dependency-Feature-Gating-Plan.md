---
title: EcomCine — Plugin Dependency & Feature Gating Plan
type: architecture-plan
status: draft
authority: primary
created: 2026-03-28
related-files:
  - specs/WP-Default-Adapter-Refactoring-Plan.md
  - ecomcine/includes/admin/class-admin-settings.php
  - ecomcine/includes/core/runtime/class-runtime-adapters.php
  - ecomcine/modules/tm-vendor-booking-modal/tm-vendor-booking-modal.php
---

# EcomCine — Plugin Dependency & Feature Gating Plan

## 1. Problem Statement

EcomCine's features span a spectrum from "works on pure WordPress" to "requires an
external commercial plugin". Currently the system has two problems:

1. **No runtime plugin-capability registry.** The individual modules make ad-hoc
   `class_exists()` / `function_exists()` checks scattered across the codebase with
   no single authoritative source of truth.

2. **Render/checkout paths are not mode-aware.** The default-wp adapter layer correctly
   abstracts data *storage* (CPTs instead of WC tables) but the *rendering* and
   *checkout* code paths in the main plugin handlers still unconditionally call WooCommerce
   and WC Bookings hooks — making "Baseline WordPress Mode" functionally incomplete for
   the booking flow.

### The Booking CTA Bug as a Diagnosis

The "No booking product found" error (March 2026) is the clearest symptom:

```
enqueue_assets()    → guards on class_exists('WC_Booking_Form') → if absent: returns early
                      (no modal CSS/JS ever loads, vendorId=0 sent to JS)
get_booking_product() → calls wc_get_product(tm_offer_cpt_id) → null (CPT is not a WC product)
ajax_booking_form() → always calls do_action('woocommerce_booking_add_to_cart')
                      (no delegation to TVBM_WP_Booking_Form_Renderer ever happens)
```

Root cause: `TVBM_WP_Offer_Discovery` returns the tm_offer CPT post ID but the caller
needs a WC product ID for `wc_get_product()`. AND the form render path always uses the
WC Bookings hook regardless of which adapter is active.

---

## 2. Feature Dependency Taxonomy

Each feature belongs to one of four dependency tiers:

| Tier | Label | Meaning |
|------|-------|---------|
| **W0** | WP Core Native | Works with WordPress core + EcomCine CPTs only. No third-party plugin. |
| **W1** | WooCommerce Enhanced | Works natively via CPTs; WooCommerce adds richer data/flow if active. |
| **W2** | Plugin Required (Hard Gate) | Cannot function at all without the named plugin. Must be hidden when absent. |
| **W3** | Plugin Required (Graceful Degrade) | Plugin not present → feature falls back to a reduced-capability WP-native path. |

---

## 3. Feature-by-Feature Dependency Matrix

### tm-account-panel (TAP)

| Feature | ID | Tier | Required Plugin | Notes |
|---------|----|------|----------------|-------|
| Panel shell & modal UX | tap-001 | **W0** | — | `is_author()` replaces Dokan check in default-wp mode |
| AJAX login & session bootstrap | tap-002 | **W0** | — | Pure `wp_signon` / `wp_set_auth_cookie` |
| Talent onboarding / invitation | tap-003 | **W0** | — | tm_invitation CPT; no WC/Dokan needed |
| Orders panel section | tap-004 orders | **W1** | WooCommerce | default-wp: tm_order CPT; compat: `wc_get_orders()` |
| Bookings panel section | tap-004 bookings | **W1** | WooCommerce Bookings | default-wp: tm_booking CPT; compat: `WC_Booking_Data_Store` |
| IP assets panel section | tap-004 ip | **W0** | — | tm_invitation CPT |

### tm-vendor-booking-modal (TVBM)

| Feature | ID | Tier | Required Plugin | Notes |
|---------|----|------|----------------|-------|
| Offer discovery | tvbm-001 | **W1** | WooCommerce Bookings | default-wp: tm_offer CPT; compat: WC product tax_query |
| Booking form rendering | tvbm-002 | **W2→W3** | WooCommerce Bookings | WC Bookings: `woocommerce_booking_add_to_cart`; WP-native: `TVBM_WP_Booking_Form_Renderer` |
| Add to cart | tvbm-003 | **W2** | WooCommerce | No WP-native checkout path exists yet; FluentCart = future W3 |
| Checkout flow | tvbm-004 | **W2** | WooCommerce (+ FluentCart future) | No WP-native checkout path exists yet |

### theme-orchestration (THO)

| Feature | ID | Tier | Required Plugin | Notes |
|---------|----|------|----------------|-------|
| Vendor profile display | tho-001 | **W3** | Dokan | default-wp: WP author archive; Dokan: `dokan_is_store_page()` |
| Vendor listing / archive | tho-002 | **W3** | Dokan | default-wp: WP post_type archive; Dokan: store listing template |
| Asset policy | tho-003 | **W0** | — | Adapter-resolved, no plugin dependency |
| Profile completeness | tho-004 | **W0** | — | WP user meta only |
| Social metrics | tho-005 | **W0** | — | WP user meta only |

### tm-media-player (TMP)

| Feature | ID | Tier | Required Plugin | Notes |
|---------|----|------|----------------|-------|
| Media player + showcase | tmp-001 | **W0** | — | WP attachments + CPTs |
| Vendor media playlist | tmp-002 | **W0** | — | WP attachments |
| Fullscreen showcase UX | tmp-003 | **W0** | — | Pure JS/CSS |
| Showcase page template | tmp-004 | **W0** | — | WP page template |

### dokan-category-attributes (DCA)

| Feature | ID | Tier | Required Plugin | Notes |
|---------|----|------|----------------|-------|
| Vendor attribute fields | dca-001 | **W2** | Dokan | Renders on Dokan vendor dashboard |
| Category-based display | dca-002 | **W2** | Dokan | Uses `dokan_get_store_url`, vendor profile hooks |
| Store filter / search | dca-003 | **W2** | Dokan | `dokan_is_store_listing()` required |
| Admin attribute manager | dca-004 | **W0** | — | WP admin menu only |

---

## 4. Plugin Capability Registry

**New class:** `EcomCine_Plugin_Capability`  
**Location:** `ecomcine/includes/core/class-plugin-capability.php`

Responsibilities:
- Centralizes all plugin detection (`class_exists`, `function_exists`, `is_plugin_active`)
- Results are cached per-request (computed once on `plugins_loaded`)
- Single source of truth referenced by all modules and the settings page

```php
class EcomCine_Plugin_Capability {
    public static function has_woocommerce(): bool
    public static function has_wc_bookings(): bool
    public static function has_dokan(): bool
    public static function has_dokan_pro(): bool
    public static function has_fluentcart(): bool   // future
    public static function snapshot(): array        // for admin display
}
```

Detection logic:
- `has_woocommerce()` → `class_exists('WooCommerce')`
- `has_wc_bookings()` → `class_exists('WC_Booking') || class_exists('WC_Bookings')`
- `has_dokan()` → `function_exists('dokan_get_store_url')`
- `has_dokan_pro()` → `class_exists('WeDevs_Dokan_Pro')`
- `has_fluentcart()` → placeholder returning `false` (stub for future)

---

## 5. Feature Availability Gate

A feature is **available** when both conditions are true:

```
available = admin_toggle_ON && ( required_plugin_present || native_fallback_exists )
```

Two helper functions used at call sites:

```php
// Is the feature toggled on AND its dependency met?
ecomcine_feature_available( string $feature_key ): bool

// What's the resolved render path for a feature?
// Returns: 'wc_bookings' | 'wp_native' | 'unavailable'
ecomcine_feature_render_path( string $feature_key ): string
```

### Booking Modal availability examples

| Admin toggle | WC Bookings present | Runtime mode | `feature_available()` | `feature_render_path()` |
|---|---|---|---|---|
| ON | YES | preferred_stack | YES | `wc_bookings` |
| ON | NO | preferred_stack | YES | `wp_native` (date form only) |
| ON | YES | baseline_wp | YES | `wp_native` |
| OFF | — | — | NO | `unavailable` |

---

## 6. Implementation Phases

### Phase FG-0: Plugin Capability Registry (prerequisite for everything else)

**Deliverable:** `EcomCine_Plugin_Capability` class + unit tests + snapshot wired into
`ecomcine_get_settings_snapshot()`.

**Files:**
- Add `ecomcine/includes/core/class-plugin-capability.php`
- Update `ecomcine/ecomcine.php` to `require_once` it before module loading
- Wire `snapshot()` into `ecomcine_get_settings_snapshot()` output

---

### Phase FG-1: TVBM Dual-Path Booking Flow (fixes the current booking CTA bug + the correct source-fix)

This is a `source-fix` (not mitigation). Two co-dependent changes:

#### FG-1a: Fix offer discovery return value
`TVBM_WP_Offer_Discovery::discover_booking_offer()` must return `_tm_src_wc_product_id`
(the backing WC product ID) not `$posts[0]->ID` (the tm_offer CPT ID).

This allows `wc_get_product()` to work in compat mode and gives the render path the
WC product when WC Bookings is available.

**File:** `ecomcine/modules/tm-vendor-booking-modal/includes/adapters/default-wp/class-wp-offer-discovery.php`

```php
// Change:
$product_id = ! empty( $posts ) ? (int) $posts[0]->ID : 0;
// To:
$product_id = ! empty( $posts ) ? (int) get_post_meta( $posts[0]->ID, '_tm_src_wc_product_id', true ) : 0;
```

#### FG-1b: Make `enqueue_assets()` mode-aware

Remove the hard `WC_Booking_Form` guard. Replace with a render-path branch:

```
if render_path == 'wc_bookings':
    load WC Bookings assets + WC_Booking_Form scripts
    set productId = WC product ID
if render_path == 'wp_native':
    load custom date-picker assets only
    set productId = tm_offer CPT ID
if render_path == 'unavailable':
    do not enqueue, return
```

**File:** `ecomcine/modules/tm-vendor-booking-modal/tm-vendor-booking-modal.php`

#### FG-1c: Make `ajax_booking_form()` mode-aware

```
if render_path == 'wc_bookings':
    validate WC product, call do_action('woocommerce_booking_add_to_cart')
if render_path == 'wp_native':
    use TVBM_Adapter_Registry::get_form_renderer()->render_booking_form( $offer_id )
    ( offer_id = tm_offer CPT ID directly — NOT wc_get_product() )
```

**File:** `ecomcine/modules/tm-vendor-booking-modal/tm-vendor-booking-modal.php`

#### FG-1d: `get_booking_product()` refactor

This method conflates two concerns: getting the offer ID (adapter-resolved) and
validating it as a WC product (WC-specific). Split:

```
get_offer_id( vendor_id ) → int   // always adapter-resolved; works in both modes
get_wc_product( offer_id ) → WC_Product|null   // only called in wc_bookings path
```

#### FG-1e: Add to cart / checkout — gated or graceful degrade

`ajax_booking_add_to_cart()` and `ajax_booking_checkout()` are WooCommerce-only.

- If `has_woocommerce()` is false: return `wp_send_json_error` with message
  "Checkout requires WooCommerce. Please contact us to arrange booking."
- (FluentCart path deferred to Phase FG-4)

---

### Phase FG-2: Account Panel Section Visibility Gates

When `tap-004` sections load, their data provider adapters already handle the
storage-layer difference (WC vs CPT). What's missing is the **UI visibility gate**:
a section should be hidden / shown correctly based on whether data is available.

Changes:
- Orders section: visible if `has_woocommerce()` (compat) OR `tm_order` CPTs exist (default-wp)
- Bookings section: visible if `has_wc_bookings()` (compat) OR `tm_booking` CPTs exist (default-wp)
- Both sections produce an appropriate empty-state message, never an error, when provider
  returns zero records

**No structural adapter changes needed** — the adapters already return empty arrays when
no records exist. Gate is at the JS rendering layer: only show section tab if `admin_enabled`
AND the data provider's feature path resolves to something other than `unavailable`.

---

### Phase FG-3: Settings Page — Plugin Dependency UI

The EcomCine Settings page at `/wp-admin/admin.php?page=ecomcine-settings` gains a
**"Plugin Requirements"** section rendered from `EcomCine_Plugin_Capability::snapshot()`.

#### 3a: Detected plugins status panel

Displayed as a read-only table at the top of the settings page:

| Plugin | Required by | Status |
|--------|-------------|--------|
| WooCommerce | Checkout flow, Orders section (enhanced) | ✅ Active |
| WooCommerce Bookings | Booking CTA (WC path) | ✅ Active |
| Dokan Lite | Vendor store pages, DCA | ✅ Active |
| Dokan Pro | DCA Pro features | ❌ Not detected |
| FluentCart | Checkout (future) | ❌ Not detected |

#### 3b: Feature toggle rows gain dependency context

Each checkbox gains a descriptive note:

- **Booking modal** → "Booking form: WC Bookings (full) or WP-native date form (requires: `booking_modal` ON)"
- **Account panel** → "Orders section requires WooCommerce. Bookings section requires WC Bookings."

#### 3c: Warning banner

If a feature is toggled ON but its only available render path is `wp_native` and the
WP-native form path has known limitations (e.g. no actual payment), show:
> ⚠️ Booking Modal is active in WP-native mode. Checkout will show a contact form, not a
> payment flow. To enable full checkout, activate WooCommerce.

**File:** `ecomcine/includes/admin/class-admin-settings.php`

---

### Phase FG-4: DCA Dokan Dependency Gate

`dokan-category-attributes` hooks into Dokan vendor dashboard templates. If Dokan is not
active, all DCA frontend hooks should be silently skipped.

Changes:
- Wrap all `add_action` registrations for vendor-dashboard and store-listing hooks in
  `if ( EcomCine_Plugin_Capability::has_dokan() )` guard
- Admin menu (dca-004) remains available regardless — attribute definitions can be
  authored even before Dokan is active
- DCA frontend display (`dca-002`, `dca-003`) silently inactive when Dokan absent

**File:** `dokan-category-attributes/dokan-category-attributes.php` + `includes/class-dashboard-fields.php`

---

### Phase FG-5: FluentCart Checkout Path (Future / Stub)

When `has_fluentcart()` is true, `ajax_booking_add_to_cart()` and
`ajax_booking_checkout()` should route to a FluentCart adapter. This phase is deferred.

**Deliverable for now:** `TVBM_FluentCart_Checkout_Handler` stub class with
`add_to_cart(int $product_id, array $data): array` signature, returning
`['success' => false, 'message' => 'FluentCart checkout not yet implemented']`.

---

## 7. Parity Check Updates

The existing parity check for TVBM (`class-parity-check.php`) must gain a check that
validates the `feature_render_path` contract:

- `offer_discovery[default-wp]` vendor with tm_offer CPT → returned `product_id` resolves
  to a valid `WC_Product` when WC Bookings is present (i.e., `_tm_src_wc_product_id` value
  is a real WC product)
- This is the check that would have caught the original bug

---

## 8. Decision Gates Summary

| Phase | Classification | Removal Trigger |
|-------|---------------|-----------------|
| FG-0: Plugin Capability Registry | source-fix | Never (permanent infrastructure) |
| FG-1: TVBM dual-path flow | source-fix | Never (permanent) |
| FG-1e: WC-only checkout gate | mitigation | FG-5 FluentCart implementation |
| FG-2: Panel section visibility | source-fix | Never |
| FG-3: Settings dependency UI | source-fix | Never |
| FG-4: DCA Dokan gate | source-fix | Never |
| FG-5: FluentCart checkout | source-fix (future) | Replaces FG-1e |

---

## 9. Phase Priority / Sequencing

```
FG-0 (Plugin Capability Registry)
  └→ FG-1 (TVBM dual-path)          ← unblocks booking CTA
       └→ FG-1e (checkout gate msg)
  └→ FG-2 (account panel gates)
  └→ FG-3 (settings UI)
  └→ FG-4 (DCA Dokan gate)

FG-5 (FluentCart) — independent, deferred
```

FG-0 → FG-1 are the highest priority: they fix the active booking CTA regression.
FG-3 is purely additive (settings UI only), can be done at any time.

---

## 10. What Does NOT Need Plugin Gating

These features work on WP Core alone and should **not** be wrapped in plugin checks:

- Media player / showcase (TMP): pure WP attachments + CPTs
- Account panel shell, login, talent onboarding (TAP-001/002/003): pure WP auth
- IP assets section (TAP-004 ip): tm_invitation CPT only
- Vendor profile completeness (THO-004): WP user meta
- Social metrics (THO-005): WP user meta
- DCA admin attribute manager (DCA-004): WP admin only

Adding unnecessary plugin guards to these would reduce reliability with no benefit.
