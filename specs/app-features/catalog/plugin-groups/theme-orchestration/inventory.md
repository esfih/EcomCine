# Inventory - theme-orchestration

## Group Summary

Current theme layer orchestrates asset loading, template overrides, Dokan/Woo hook manipulation, and vendor profile/listing behavior.

## Feature Inventory

1. Store/listing template override orchestration
- Current dependencies: Dokan template hierarchy and shortcode-based store listing paths
- Core logic candidate: storefront section composition and render ordering
- Default WP adapter target: block templates and template-part orchestration

2. Asset governance for vendor pages
- Current dependencies: dequeue/deregister of Dokan/Woo/Mapbox/Google assets
- Core logic candidate: page-context-driven asset policy
- Default WP adapter target: WP-native asset policy registry per route/view model

3. Vendor profile meta synchronization and custom fields
- Current dependencies: Dokan profile settings/user meta conventions
- Core logic candidate: vendor profile state model and synchronization rules
- Default WP adapter target: vendor profile CPT/meta schema with explicit sync services

4. Product/store vendor identity presentation hooks
- Current dependencies: Woo product hooks + Dokan store URL/info helpers
- Core logic candidate: vendor identity projection for product/store cards
- Default WP adapter target: render callbacks using WP-native vendor entities

5. Social metrics, vendor completeness, map modules in includes/
- Current dependencies: theme include modules and Dokan context hooks
- Core logic candidate: profile scoring and social-metric computation rules
- Default WP adapter target: service-layer modules independent from Dokan templates

6. No Scroll Grid auto-fit mode for person listing
- Current dependencies: persons-grid settings, listing shortcode wrapper CSS variables, viewport metrics
- Core logic candidate: ratio-safe grid fit solver constrained by viewport and reserved UI regions
- Default WP adapter target: native EcomCine listing fit mode with no-scroll UX contract

## Migration Risk Notes

- High coupling hotspot: many behaviors are implemented as hook-level interventions.
- Requires staged extraction into plugin-level core services plus adapter bindings.

## Parity Oracle (Initial)

- Same storefront section visibility and ordering.
- Same vendor profile state after save/update events.
- Same vendor identity blocks on product and listing surfaces.
- With No Scroll Grid enabled, configured rows/columns and pagination fit without page scroll on supported desktop viewports.
