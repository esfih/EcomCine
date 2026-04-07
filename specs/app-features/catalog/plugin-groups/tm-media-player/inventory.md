# Inventory - tm-media-player

## Group Summary

Current module provides vendor media showcase, playlist extraction, and store-page media API delivery.

## Feature Inventory

1. Vendor playlist extraction from biography shortcodes and media attributes
- Current dependencies: Dokan store info (`dokan_get_store_info`), WP media APIs, shortcode parsing
- Core logic candidate: normalize vendor media sources into unified playlist payload
- Default WP adapter target: vendor profile CPT/meta + WP block content parser

2. Fallback media strategy (banner video/banner image)
- Current dependencies: Dokan vendor banner methods, user meta (`dokan_banner_video`)
- Core logic candidate: media fallback resolution order and feature flags
- Default WP adapter target: profile CPT featured media and custom fields

3. REST endpoint for vendor store content payload
- Current dependencies: store template inclusion from Dokan theme paths
- Core logic candidate: vendor storefront content projection API
- Default WP adapter target: WP REST controller backed by core contracts and block-rendered sections

4. AJAX navigation list/store content loaders
- Current dependencies: Dokan store context + theme template fragments
- Core logic candidate: fetch and serialize vendor storefront sections
- Default WP adapter target: REST-first storefront section endpoints

## Migration Risk Notes

- High coupling to Dokan store template rendering.
- Vendor context resolution currently assumes Dokan store page semantics.

## Parity Oracle (Initial)

- Same vendor media list ordering and media type fidelity.
- Same fallback media behavior for missing playlist items.
- Same storefront media rendering outcomes on vendor pages.
