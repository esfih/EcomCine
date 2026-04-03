---
title: EcomCine — Absolute Portability Plan
type: architecture
status: pending-approval
authority: primary
intent-scope: all
created: 2026-03-31
---

# EcomCine — Absolute Portability Plan

**Goal:** Every EcomCine plugin and the bundled theme must work on a bare WordPress install with zero dependency on Dokan, WooCommerce, WooCommerce Bookings, or the Astra theme. All external-plugin coupling must be replaced with EcomCine-owned infrastructure.

Items are grouped by dependency **domain**. Each entry states the current coupling, the files affected, and the proposed portable replacement. No code changes have been made yet — this document is for review and approval.

---

## Dependency Domain Index

1. [Vendor Role — `seller`](#1-vendor-role--seller)
2. [Vendor Profile Meta — `dokan_profile_settings`](#2-vendor-profile-meta--dokan_profile_settings)
3. [Store Categories — `store_category` taxonomy](#3-store-categories--store_category-taxonomy)
4. [Store URL — `dokan_get_store_url()`](#4-store-url--dokan_get_store_url)
5. [Store Info — `dokan_get_store_info()`](#5-store-info--dokan_get_store_info)
6. [Page Context Detectors — `dokan_is_store_page()` / `dokan_is_store_listing()`](#6-page-context-detectors)
7. [Vendor Geolocation Meta — `dokan_geo_*`](#7-vendor-geolocation-meta--dokan_geo_)
8. [Country Name Lookup — `WC()->countries->get_countries()`](#8-country-name-lookup--wccountries-get_countries)
9. [Order System — Dokan order API + WooCommerce orders](#9-order-system)
10. [Earnings — `dokan_get_seller_earnings()`](#10-earnings--dokan_get_seller_earnings)
11. [Mapbox Token — `dokan_get_option('mapbox_access_token', ...)`](#11-mapbox-token)
12. [Dokan Asset Handles — dequeue targets](#12-dokan-asset-handles--dequeue-targets)
13. [Dokan CSS Classes in Output HTML](#13-dokan-css-classes-in-output-html)
14. [Dokan Hooks used as Extension Points](#14-dokan-hooks-used-as-extension-points)
15. [Vendor Registration Form](#15-vendor-registration-form)
16. [Booking / Checkout — WooCommerce + WC Bookings](#16-booking--checkout--woocommerce--wc-bookings)
17. [WooCommerce Product Taxonomy — `product_cat`](#17-woocommerce-product-taxonomy--product_cat)
18. [Astra Theme Filters](#18-astra-theme-filters)
19. [Bundled Theme — Canonical Minimal Theme](#19-bundled-theme--canonical-minimal-theme)
20. [Dokan Template Parts — `dokan_get_template_part()`](#20-dokan-template-parts--dokan_get_template_part)
21. [WooCommerce Thank-You Page Template Override](#21-woocommerce-thank-you-page-template-override)
22. [Demo Importer — Dokan-specific setup](#22-demo-importer--dokan-specific-setup)
23. [Vendor Completeness Admin — Dokan meta reads](#23-vendor-completeness-admin--dokan-meta-reads)
24. [Plugin Capability Probes](#24-plugin-capability-probes)

---

## 1. Vendor Role — `seller`

**Current coupling:** The `seller` WP user role is registered by Dokan. EcomCine and all sub-plugins use it throughout to identify talent/vendor users.

**Affected files:**
- `ecomcine/includes/class-demo-importer.php` — `set_role('seller')`, `role => 'seller'` check
- `ecomcine/includes/admin/class-admin-settings.php` — `set_role('seller')`
- `tm-store-ui/includes/admin/vendor-completeness-admin.php` — `get_users(['role' => 'seller'])`
- `tm-account-panel/tm-account-panel.php` — role checks `'seller'`, `'dokandar'` capability
- `tm-media-player/tm-media-player.php` — `'role__in' => ['seller', 'vendor']`
- `theme/includes/vendor-profile/vendor-profile-ajax.php` — `dokan_is_user_seller()`, `user_can(..., 'dokandar')`

**Proposed portable alternative:**
- Register an EcomCine-owned role: `ecomcine_talent` (capability group: `manage_talent_profile`), created via `add_role()` on plugin activation.
- All role checks replaced with `ecomcine_is_talent_user($user_id)` — a single function in `ecomcine/includes/functions.php` that checks for `ecomcine_talent` role first, and if Dokan is present also accepts `seller` for backward compatibility.
- `get_users(['role' => 'ecomcine_talent'])` replaces all `seller` role queries.
- Migration: on activation, scan existing `seller` users and add `ecomcine_talent` role so existing Dokan sites are immediately compatible.

---

## 2. Vendor Profile Meta — `dokan_profile_settings`

**Current coupling:** Virtually all vendor data (store name, bio, address, banner, avatar, social links, phone, geo data, store categories) is stored in a single serialized array under the `dokan_profile_settings` user meta key. Every plugin reads and writes this key.

**Affected files (read/write):**
- `ecomcine/includes/compat/vendor-utilities.php`
- `ecomcine/includes/class-demo-importer.php`
- `ecomcine/includes/admin/class-admin-settings.php`
- `tm-store-ui/includes/adapters/compatibility/class-compat-profile-meta-provider.php`
- `tm-store-ui/includes/adapters/compatibility/class-compat-metrics-provider.php`
- `tm-account-panel/tm-account-panel.php`
- `tm-media-player/includes/adapters/compatibility/class-compat-media-source-provider.php`
- `theme/includes/vendor-profile/vendor-profile-ajax.php` (dozens of read/write locations)

**Proposed portable alternative:**
- Define canonical EcomCine-owned meta keys: flat, individual `usermeta` rows under the `ecomcine_` prefix:
  - `ecomcine_store_name`, `ecomcine_bio`, `ecomcine_phone`, `ecomcine_banner_id`, `ecomcine_avatar_id`
  - `ecomcine_address` (serialized sub-array: `street_1`, `city`, `state`, `zip`, `country`)
  - `ecomcine_social` (serialized sub-array: `fb`, `youtube`, `instagram`, `tiktok`, etc.)
  - `ecomcine_geo_address`, `ecomcine_geo_lat`, `ecomcine_geo_lng`
- A compatibility shim `ecomcine_get_profile_field($user_id, $key)` reads from `ecomcine_*` meta first, falls back to `dokan_profile_settings[$key]` when Dokan is present, so existing Dokan-seeded data continues to work.
- On first profile save via EcomCine, migrate `dokan_profile_settings` values into the new flat keys (one-time, non-destructive).

---

## 3. Store Categories — `store_category` taxonomy

**Current coupling:** Categories are stored as terms in Dokan Pro's `store_category` taxonomy attached to the `dokan_seller` object type. Dokan Pro registers this taxonomy. No native WP admin UI exists for it. The category assignment, filtering, and display all reference this taxonomy slug.

**Affected files:**
- `ecomcine/includes/class-demo-importer.php` — `get_term_by('slug', ..., 'store_category')`, `wp_set_object_terms(..., 'store_category')`
- `tm-store-ui/includes/vendor-attributes/vendor-attributes-hooks.php` — `wp_get_object_terms(..., 'store_category')`
- `tm-store-ui/includes/admin/vendor-completeness-admin.php` — `wp_get_object_terms(..., 'store_category')`
- `tm-store-ui/templates/dokan/store-lists/store-lists-hooks.php` — `store_category_query` in user query
- `tm-store-ui/templates/dokan/store-lists/category-area.php` — `dokan_seller_category` GET param, dropdown population
- `theme/includes/vendor-profile/vendor-profile-ajax.php` — `wp_get_object_terms`, `wp_set_object_terms`, `dokan_store_categories` meta key
- `theme/includes/admin/vendor-completeness-admin.php` — same

**Proposed portable alternative (already designed, confirmed pending implementation):**
- Two new DB tables: `wp_ecomcine_categories` (id, name, slug, description, sort_order) and `wp_ecomcine_user_categories` (user_id, category_id) — pure `$wpdb`/`dbDelta`.
- New class: `EcomCine_Talent_Category_Registry` with full CRUD + user assignment API.
- New **Talent Categories** tab in EcomCine Settings for admin CRUD.
- `vendor-data.json` gets a top-level `"store_categories": ["model"]` per vendor; importer calls `EcomCine_Talent_Category_Registry::set_user_categories()`.
- All `store_category_query` Dokan hooks replaced with `include => EcomCine_Talent_Category_Registry::get_user_ids_for_slug($slug)` in `WP_User_Query`.
- Backward compat: when Dokan is present, `EcomCine_Talent_Category_Registry::get_all()` merges EcomCine table terms with any existing `store_category` terms so Dokan-managed sites aren't broken.

---

## 4. Store URL — `dokan_get_store_url()`

**Current coupling:** Vendor public profile URLs are built exclusively via Dokan's routing. No fallback exists for bare WP.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php` — `dokan_get_store_url($vendor_id)` (hard-require with `function_exists` guard that returns empty on failure)
- `tm-store-ui/includes/compat/vendor-utilities-fallback.php` — same
- `tm-store-ui/includes/admin/vendor-edit-logs.php` — `dokan_get_store_url()`
- `tm-account-panel/tm-account-panel.php` — multiple locations
- `theme/includes/vendor-profile/vendor-profile-ajax.php` — multiple locations

**Proposed portable alternative:**
- EcomCine registers a rewrite rule on activation: `/talent/{store-slug}/` → `?ecomcine_talent=1&vendor_slug={slug}`.
- `ecomcine_get_vendor_url($user_id)`: returns `home_url('/talent/' . get_user_meta($user_id, 'ecomcine_store_slug', true) . '/')`. Falls back to `dokan_get_store_url()` when Dokan is present.
- Store slug stored in `ecomcine_store_slug` user meta, derived from `sanitize_title(display_name)` on first creation.

---

## 5. Store Info — `dokan_get_store_info()`

**Current coupling:** Returns a merged array of a vendor's full profile. Used as a general-purpose vendor data getter throughout media, template, and account panel code.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php`
- `tm-store-ui/includes/template-helpers.php`
- `tm-store-ui/includes/admin/vendor-completeness-admin.php`
- `tm-media-player/includes/adapters/compatibility/class-compat-media-source-provider.php`
- `theme/includes/vendor-profile/vendor-profile-ajax.php`

**Proposed portable alternative:**
- `ecomcine_get_store_info($user_id)`: returns an array with canonical keys (`store_name`, `bio`, `phone`, `banner`, `gravatar`, `address`, `social`, `geo_address`, etc.) sourced from `ecomcine_*` meta. When Dokan is active, merges with `dokan_get_store_info()` output for full compatibility.
- Single function, single source of truth, lives in `ecomcine/includes/functions.php`.

---

## 6. Page Context Detectors

**Current coupling:** `dokan_is_store_page()` and `dokan_is_store_listing()` are used to conditionally load assets, suppress headers, and gate logic. These only work when Dokan's routing is active.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php` — asset dequeue logic
- `tm-store-ui/includes/adapters/class-adapter-registry.php`
- `tm-store-ui/tm-store-ui.php`
- `tm-account-panel/tm-account-panel.php`
- `tm-media-player/tm-media-player.php`
- `theme/functions.php`
- `theme/page-platform.php`

**Proposed portable alternative:**
- `ecomcine_is_talent_page()`: returns true when `get_query_var('ecomcine_talent')` is set (our own rewrite) OR when `dokan_is_store_page()` exists and returns true.
- `ecomcine_is_talent_listing()`: returns true when the current page contains the `[tm_talent_player]` shortcode (detect via `is_page()` + stored option for the Talents page ID) OR when `dokan_is_store_listing()` exists and returns true.
- Live in `ecomcine/includes/functions.php`.

---

## 7. Vendor Geolocation Meta — `dokan_geo_*`

**Current coupling:** Geo coordinates stored as `dokan_geo_address`, `dokan_geo_latitude`, `dokan_geo_longitude` user meta keys. Map shortcode and vendor-profile AJAX both read/write these directly.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php`
- `theme/includes/vendor-profile/vendor-profile-ajax.php`
- `theme/includes/vendors-map/vendors-map-shortcode.php`

**Proposed portable alternative:**
- Own meta keys: `ecomcine_geo_address`, `ecomcine_geo_lat`, `ecomcine_geo_lng`.
- `ecomcine_get_vendor_geo($user_id)`: reads `ecomcine_geo_*` first; falls back to `dokan_geo_*` when Dokan is present (read-only migration path).
- On first write via EcomCine, copies `dokan_geo_*` values into `ecomcine_geo_*` and continues writing only to EcomCine keys.

---

## 8. Country Name Lookup — `WC()->countries->get_countries()`

**Current coupling:** The vendor location display (`tm_get_vendor_geo_location_display()`) uses WooCommerce's country list to resolve ISO codes to full names and build flag images.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php`
- `tm-store-ui/includes/template-helpers.php`

**Proposed portable alternative:**
- Bundle a static ISO 3166-1 alpha-2 country array directly in `ecomcine/includes/data/iso-countries.php` (a plain PHP `return [...]` file, ~250 entries).
- `ecomcine_get_countries()`: returns from that static file first; if WooCommerce is active, delegates to `WC()->countries->get_countries()` so WC locale/translation support is inherited when available.

---

## 9. Order System

**Current coupling:** Order listing and detail viewing in `tm-account-panel` and `ecomcine` compat adapter rely entirely on Dokan's order API (`dokan()->order->all()`, `dokan_get_template_part('orders/...')`) and WooCommerce order objects (`WC_Order`, `wc_get_order()`, WC order status slugs `wc-*`).

**Affected files:**
- `ecomcine/includes/core/adapters/class-commerce-adapter-woodokan.php` — full file
- `tm-account-panel/tm-account-panel.php` — order list rendering, order detail, bulk status logic

**Proposed portable alternative:**
- This is the deepest coupling. The `wp_cpt` and `wp_woo_dokan` modes already have an adapter pattern (`EcomCine_Commerce_Adapter` interface). The WooDokan adapter is expected to use Dokan/WooCommerce — that is by design.
- For **bare WP mode** (`wp_cpt`): a `EcomCine_Commerce_Adapter_WP` implementation that uses a custom `ecomcine_order` CPT (already in the roadmap) with plain WP post meta for order data. Booking/checkout flows use a simple native WP form-to-post-meta pattern.
- **No change is needed to the WooDokan adapter** — it is explicitly the Dokan/WooCommerce mode. The portability goal is that `wp_cpt` mode works completely without it.
- Action: verify that all order-related code paths are gated behind adapter interfaces and never called unconditionally. Any unconditional order call outside of an adapter is a bug to fix.

---

## 10. Earnings — `dokan_get_seller_earnings()`

**Current coupling:** Account panel shows vendor earnings via `dokan_get_seller_earnings()` and `{$wpdb->prefix}dokan_vendor_balance` DB table.

**Affected files:**
- `tm-account-panel/tm-account-panel.php`
- `ecomcine/includes/core/adapters/class-commerce-adapter-woodokan.php`

**Proposed portable alternative:**
- `ecomcine_get_vendor_earnings($user_id)`: returns from `dokan_get_seller_earnings()` when Dokan is present; returns `null` (with UI showing "—") when not. In `wp_cpt` mode earnings will derive from `ecomcine_order` CPT totals via a dedicated query function.
- Earnings display in account panel wrapped in `if ( ecomcine_vendor_has_earnings_support() )` to gracefully hide the widget in bare WP mode.

---

## 11. Mapbox Token

**Current coupling:** Mapbox access token retrieved from Dokan's appearance settings via `dokan_get_option('mapbox_access_token', 'dokan_appearance', '')`. Used in map shortcode and vendor profile geolocation save.

**Affected files:**
- `tm-media-player/tm-media-player.php`
- `theme/includes/vendor-profile/vendor-profile-ajax.php`
- `theme/includes/vendors-map/vendors-map-shortcode.php`

**Proposed portable alternative:**
- Add `mapbox_token` field to EcomCine Settings → Settings tab, stored in `ecomcine_settings['mapbox_token']`.
- `ecomcine_get_mapbox_token()`: reads from `ecomcine_settings` first; falls back to `dokan_get_option('mapbox_access_token', 'dokan_appearance', '')` when Dokan is active.
- All three files use `ecomcine_get_mapbox_token()`.

---

## 12. Dokan Asset Handles — Dequeue Targets

**Current coupling:** Asset cleanup code dequeues Dokan and WooCommerce stylesheet/script handles by name. These handle names are only registered when Dokan/WooCommerce are active; the dequeue calls are harmless on bare WP but they create a conceptual tie.

**Affected files:**
- `ecomcine/includes/compat/vendor-utilities.php` — `wp_dequeue_style('dokan-mapbox-gl')`, etc.
- `tm-store-ui/includes/adapters/compatibility/class-compat-asset-policy-provider.php`
- `tm-vendor-booking-modal/tm-vendor-booking-modal.php`

**Proposed portable alternative:**
- Wrap all Dokan-handle dequeue calls in `if (function_exists('dokan'))` guards (many already have these; standardize the rest).
- Wrap all WooCommerce-handle dequeue calls in `if (class_exists('WooCommerce'))` guards.
- This is a low-effort cleanup — the calls are already no-ops on bare WP, but the guards make intent explicit and suppress potential notices.

---

## 13. Dokan CSS Classes in Output HTML

**Current coupling:** Several templates and PHP files emit `dokan-*` CSS class names directly into rendered HTML (`.dokan-store-wrap`, `#dokan-primary`, `.dokan-table`, `.dokan-btn`, `.dokan-form-group`, etc.). This means our CSS and JS depend on Dokan's classnames being present.

**Affected files:**
- `ecomcine/bundled-theme/template-talent-showcase-full.php`
- `ecomcine/includes/core/adapters/class-commerce-adapter-fluentcart.php`
- `tm-store-ui/includes/vendor-attributes/vendor-attributes-hooks.php` (the entire vendor-settings form)
- `tm-media-player/tm-media-player.php`
- `tm-account-panel/tm-account-panel.php`
- `tm-store-ui/templates/dokan/store-lists/category-area.php`

**Proposed portable alternative:**
- Replace all `dokan-*` class names in EcomCine-generated HTML with `ecomcine-*` equivalents (e.g. `ecomcine-btn`, `ecomcine-table`, `ecomcine-store-wrap`).
- EcomCine's own CSS defines these classes with identical visual styles.
- For sections where Dokan templates are rendered (order details, etc.) and we cannot control the classnames — wrap with an `ecomcine-compat-layer` div and use CSS descendant selectors rather than patching Dokan classnames.
- The vendor attributes form in `vendor-attributes-hooks.php` is entirely our HTML; it gets fully re-classed.

---

## 14. Dokan Hooks used as Extension Points

**Current coupling:** EcomCine hooks into Dokan action hooks to inject UI into vendor-facing settings pages and store profile pages.

**Affected files:**
- `tm-store-ui/includes/vendor-attributes/vendor-attributes-hooks.php` — `add_action('dokan_store_profile_bottom_drawer', ...)`, `add_action('dokan_settings_after_store_phone', ...)`
- `theme/includes/vendor-attributes/vendor-attributes-hooks.php` — `add_action('dokan_store_profile_saved', ...)`
- `theme/includes/social-metrics/social-metrics.php` — `add_action('dokan_store_profile_saved', ...)`

**Proposed portable alternative:**
- EcomCine defines its own hooks: `ecomcine_vendor_profile_fields`, `ecomcine_vendor_profile_saved`.
- The bundled theme's store-header template and vendor dashboard templates fire these native hooks.
- When Dokan is active, add bridge hooks: `add_action('dokan_store_profile_saved', function($vendor_id) { do_action('ecomcine_vendor_profile_saved', $vendor_id); })` — so Dokan-mode sites continue working.
- This way all EcomCine logic hooks into `ecomcine_*` only.

---

## 15. Vendor Registration Form

**Current coupling:** The vendor registration template uses Dokan's registration system entirely — Dokan nonces, WooCommerce option reads, `[dokan-vendor-registration]` shortcode, `dokan-lite` textdomain, `seller` hidden role field, Dokan action hooks.

**Affected files:**
- `tm-store-ui/templates/dokan/account/vendor-registration.php`
- `theme/dokan/account/vendor-registration.php`
- `tm-account-panel/tm-account-panel.php` — `do_shortcode('[dokan-vendor-registration]')`

**Proposed portable alternative:**
- EcomCine ships its own registration template: `ecomcine/templates/registration.php`. Plain HTML form posting to `admin-post.php` with action `ecomcine_vendor_register`. Handler in `ecomcine/includes/class-registration-handler.php`.
- Fields: username, email, password, store name. On submit: `wp_create_user()` → `$user->set_role('ecomcine_talent')` → create `ecomcine_*` meta.
- Shortcode `[ecomcine_register]` replaces `[dokan-vendor-registration]`.
- When Dokan is active, `[ecomcine_register]` can optionally delegate to `[dokan-vendor-registration]` via a toggle in EcomCine Settings.

---

## 16. Booking / Checkout — WooCommerce + WC Bookings

**Current coupling:** `tm-vendor-booking-modal` is deeply wired to WooCommerce cart/checkout and WC Bookings' `WC_Booking_Form`. This is by design for the `wp_woo_dokan_booking` mode, but it makes the plugin completely inoperable on bare WP.

**Affected files:**
- `tm-vendor-booking-modal/tm-vendor-booking-modal.php` — entire checkout pipeline
- `tm-vendor-booking-modal/includes/adapters/compatibility/class-compat-booking-form-renderer.php`
- `tm-vendor-booking-modal/includes/adapters/compatibility/class-compat-checkout-handler.php`
- `tm-vendor-booking-modal/includes/adapters/compatibility/class-compat-offer-discovery.php`
- `tm-vendor-booking-modal/includes/adapters/compatibility/class-compat-checkout-policy.php`

**Proposed portable alternative:**
- The adapter interfaces already exist (`TM_Booking_Form_Renderer_Interface`, `TM_Checkout_Handler_Interface`). The concrete WooCommerce implementations live inside `compat/` — they are the right place. No structural change needed.
- Add a `default-wp` adapter set (already partially started in `tm-vendor-booking-modal/includes/adapters/default-wp/`): uses a simple custom `ecomcine_booking` CPT + email-based inquiry form, no WooCommerce cart involved.
- The booking modal plugin must not load ANY WooCommerce class or hook unconditionally at the top level — all WC usage must be inside adapter concretions that are only instantiated when WC is detected. **Audit the top-level plugin file** and move all unconditional WC hooks behind `class_exists('WooCommerce')` guards.

---

## 17. WooCommerce Product Taxonomy — `product_cat`

**Current coupling:** The `ecomcine_categories` shortcode (used in the auto-bootstrapped Categories page) reads from `product_cat` taxonomy, falling back to core `category`. The offer discovery adapter uses `product_cat` in a `tax_query`.

**Affected files:**
- `ecomcine/includes/admin/class-admin-settings.php` — `taxonomy_exists('product_cat')` in `shortcode_categories()`
- `tm-vendor-booking-modal/includes/adapters/compatibility/class-compat-offer-discovery.php` — `'taxonomy' => 'product_cat'` in WP_Query

**Proposed portable alternative:**
- `[ecomcine_categories]` shortcode: reads from `ecomcine_talent_categories` (our own table via `EcomCine_Talent_Category_Registry::get_all()`) and renders a list. Removes the `product_cat` fallback.
- `class-compat-offer-discovery.php` is the WooCommerce compat adapter — product_cat query stays there. The default-wp adapter uses `ecomcine_offer` CPT without taxonomy.

---

## 18. Astra Theme Filters

**Current coupling:** Several templates suppress the Astra theme header/footer by returning false from `astra_header_display` and `astra_footer_display` filters. On non-Astra themes these filters simply don't fire, so they're harmless — but they create a named dependency.

**Affected files:**
- `tm-media-player/tm-media-player.php` — `add_filter('astra_header_display', ...)`, `add_filter('astra_footer_display', ...)`
- `theme/functions.php` — `add_filter('astra_footer_display', '__return_false', 20)`
- `theme/page-platform.php` — `add_filter('astra_header_display', '__return_false')`
- `theme/template-talent-showcase.php` — `add_filter('astra_header_display', '__return_false')`
- `theme/template-talent-showcase-full.php` — `add_filter('astra_header_display', '__return_false')`

**Proposed portable alternative:**
- Replace with a **theme-agnostic header/footer suppression mechanism**: EcomCine sets `$GLOBALS['ecomcine_suppress_header'] = true` / `$GLOBALS['ecomcine_suppress_footer'] = true` before `get_header()`/`get_footer()`.
- The bundled theme (`ecomcine-base`) checks these globals natively.
- For third-party themes (Astra, GeneratePress, etc.), EcomCine adds a thin theme-compat layer in `ecomcine/includes/theme-compat/` with one file per popular theme that translates the global into the theme's suppression filter. Astra: `add_filter('astra_header_display', ...)` gated behind `function_exists('astra_header')`. This layer is additive — not a hard dependency.

---

## 19. Bundled Theme — Canonical Minimal Theme

**Current canonical model:** `ecomcine-base` (`ecomcine/bundled-theme/`) is the only required theme for standalone operation. It is a minimal shell theme with no parent dependency and exists specifically so EcomCine can own the runtime without fighting third-party theme behavior.

**Affected files:**
- `ecomcine/bundled-theme/functions.php` — base style handle and essential theme supports
- `ecomcine/bundled-theme/style.css` — minimal base styling only
- `ecomcine/bundled-theme/header.php` / `footer.php` — document shell and hook points

**Portable rule:**
- `ecomcine-base` remains the canonical theme for standalone installs.
- Third-party themes may still be supported through compatibility layers, but are not required by product design.
- **Action:** Audit `tm-media-player` and `ecomcine` core to ensure no code path does `locate_template('dokan/store-header.php')` without a fallback to the bundled-theme path. (One instance confirmed in `tm-media-player.php` line 213.)

---

## 20. Dokan Template Parts — `dokan_get_template_part()`

**Current coupling:** Several places call `dokan_get_template_part()` to render Dokan vendor templates (store header, order details). These hard-fail or silently output nothing on bare WP.

**Affected files:**
- `ecomcine/bundled-theme/template-talent-showcase-full.php` — `dokan_get_template_part('store-header')`
- `tm-media-player/tm-media-player.php` — `dokan_get_template_part('store-header')`
- `tm-account-panel/tm-account-panel.php` — `dokan_get_template_part('orders/details', ...)`

**Proposed portable alternative:**
- `ecomcine_load_template($template_name, $args)`: looks for the template first in the active theme, then in `ecomcine/templates/`, then falls back to bundled defaults. No Dokan involvement.
- For `store-header`: EcomCine ships its own `ecomcine/templates/talent-header.php` (already largely built as the cinematic header). `ecomcine_load_template('talent-header', ['vendor_id' => $id])`.
- For `orders/details`: EcomCine's `wp_cpt` mode order detail template lives at `ecomcine/templates/order-detail.php`. WooDokan adapter continues to call `dokan_get_template_part()` as before.

---

## 21. WooCommerce Thank-You Page Template Override

**Current coupling:** `theme/woocommerce/checkout/thankyou.php` is a full WooCommerce template override with `WC_Order` type hint, `wc_*` functions, WC action hooks, and WooCommerce textdomain.

**Affected files:**
- `theme/woocommerce/checkout/thankyou.php`

**Proposed portable alternative:**
- This template belongs to the `castingagency.co` production child theme which runs WooCommerce — it is expected to exist there.
- For the plugin (EcomCine core + bundled theme): the bundled theme does not include a `woocommerce/` override folder. No change needed to the plugin.
- **Action:** verify the bundled theme directory does not accidentally contain WooCommerce template overrides. If it does, remove them.

---

## 22. Demo Importer — Dokan-specific Setup

**Current coupling:** Demo importer hardcodes `set_role('seller')`, writes `dokan_profile_settings` meta, writes `dokan_enable_selling`, reads `store_category` taxonomy terms, and calls `wp_set_object_terms`.

**Affected files:**
- `ecomcine/includes/class-demo-importer.php`
- `ecomcine/includes/admin/class-admin-settings.php` (legacy importer)
- `ecomcine/demo/vendor-data.json`

**Proposed portable alternative:**
- Use `ecomcine_talent` role and `ecomcine_*` meta keys.
- `vendor-data.json` gets top-level `"store_categories": ["model"]` slug array per vendor.
- `class-demo-importer.php` calls `EcomCine_Talent_Category_Registry::set_user_categories()`.
- When Dokan is active, also write `dokan_profile_settings` and `dokan_enable_selling` as a compatibility bonus  (vendors show up in Dokan dashboards too).

---

## 23. Vendor Completeness Admin — Dokan Meta Reads

**Current coupling:** The completeness admin widget reads `dokan_enable_selling` meta, calls `dokan_get_store_info()`, and reads `store_category` taxonomy terms directly.

**Affected files:**
- `tm-store-ui/includes/admin/vendor-completeness-admin.php`
- `theme/includes/admin/vendor-completeness-admin.php`

**Proposed portable alternative:**
- Replace `dokan_enable_selling` check with `ecomcine_is_talent_enabled($user_id)` which reads `ecomcine_enabled` meta (falling back to `dokan_enable_selling` when Dokan present).
- Replace `dokan_get_store_info()` with `ecomcine_get_store_info()`.
- Replace `wp_get_object_terms(..., 'store_category')` with `EcomCine_Talent_Category_Registry::get_for_user($user_id)`.

---

## 24. Plugin Capability Probes

**Current coupling:** `EcomCine_Plugin_Capability` detects Dokan, WooCommerce, WC Bookings by class/function existence. These probes are correct and intentional — they are not hard couplings, they are feature-detection. However the Settings UI mode dropdown exposes modes that require absent plugins.

**Affected files:**
- `ecomcine/includes/core/class-plugin-capability.php`
- `ecomcine/includes/admin/class-admin-settings.php`

**Proposed portable alternative:**
- Probes themselves are fine — keep as-is.
- The Settings UI: when a mode's prerequisites are not met, render the option as disabled (`disabled` attribute) with a tooltip explaining what plugin is needed. Currently this only shows a warning below the select; it does not prevent selection. Tighten this so an unmet-prerequisite mode cannot be saved.

---

## Summary: Implementation Phases

| Phase | Items | Risk | Effort |
|---|---|---|---|
| **P1 — Data layer** | #3 (categories table), #7 (geo meta), #8 (country lookup), #2 (partial: new meta keys + shim) | Low | Medium |
| **P2 — Identity + routing** | #1 (ecomcine_talent role), #4 (store URL / rewrites), #6 (page detectors) | Medium | Medium |
| **P3 — API functions** | #5 (store info), #10 (earnings), #11 (mapbox token), #14 (hooks) | Low | Low |
| **P4 — UI / templates** | #13 (CSS classes), #15 (registration form), #18 (Astra filters), #20 (template loader), #22 (demo importer) | Medium | Medium |
| **P5 — Asset cleanup** | #12 (dequeue guards), #17 (product_cat), #23 (completeness admin), #24 (settings UI) | Low | Low |
| **P6 — Booking modal** | #16 (WC Bookings adapter gating) | High | High |
| **Out of scope** | #9 (WooDokan order adapter — correct by design), #21 (WC thankyou template — theme territory) | — | — |

---

*This document is the source of truth for EcomCine portability work. No code changes are to be made without each item being reviewed and approved here first.*
