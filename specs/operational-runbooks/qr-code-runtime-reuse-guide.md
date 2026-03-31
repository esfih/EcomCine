# QR Runtime Reuse Guide

## Purpose

Keep vendor CTA QR rendering deterministic across plugin-only, theme-only, and mixed runtime layouts.

## Source Fix Applied

Active QR rendering in `tm-store-ui` now resolves:

- target URL using the current vendor storefront URL when the request path belongs to the same vendor
- QR library autoload from multiple known locations used by historical/live environments

This removes the previous dependency on one specific plugin/theme load path.

## Canonical Helper Contract

Canonical helper implementation in unified runtime:

- `ecomcine/includes/compat/vendor-utilities.php`

Template callers (including `tm-store-ui`) use global function names, so the first-loaded definition wins. In the current runtime, that canonical definition is loaded by the `ecomcine` plugin and should be treated as source of truth.

Primary helper contract:

- `tm_get_vendor_qr_svg_markup( int $vendor_id, array $args = [] ): string`
- returns `""` when no QR can be generated
- returns markup containing `.qr-code-live` when successful

URL resolver contract:

- `tm_get_vendor_public_profile_url( int $vendor_id ): string`
- prefers current page URL for the same vendor storefront path
- falls back to `dokan_get_store_url()`

## Supported QR Library Locations

Library resolution checks these autoload paths (in order):

1. active stylesheet theme `vendor/autoload.php`
2. active stylesheet theme `lib/php-qrcode/vendor/autoload.php`
3. active template theme `vendor/autoload.php`
4. active template theme `lib/php-qrcode/vendor/autoload.php`
5. `wp-content/themes/astra-child/vendor/autoload.php`
6. `wp-content/themes/astra-child/lib/php-qrcode/vendor/autoload.php`
7. `ECOMCINE_DIR/vendor/autoload.php` when available

## Regression Guard

Playwright scenario `tools/playwright/tests/fixtures/interactions.vendor-store-cta-tabs.json` now asserts:

- `.vendor-cta-qr .qr-code-live` is visible
- `.vendor-cta-qr .qr-code-fallback` is hidden/absent

Run via catalog command:

- `./scripts/run-catalog-command.sh qa.playwright.test.interactions tools/playwright/tests/fixtures/interactions.vendor-store-cta-tabs.json`

## Troubleshooting

If QR still falls back:

1. Confirm one supported autoload path exists in runtime container.
2. Confirm vendor store URL resolves from `dokan_get_store_url( $vendor_id )`.
3. Clear transients/object cache if stale QR markup is suspected.
4. Run `qa.playwright.test.interactions` and inspect `tools/playwright/playwright-report`.
5. Collect diagnostics with `debug.snapshot.collect`.
