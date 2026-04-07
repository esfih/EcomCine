# Inventory - dokan-category-attributes

## Group Summary

Current module manages category-specific vendor attributes, dashboard field rendering, and storefront filtering/display.

## Feature Inventory

1. Attribute schema and storage tables
- Current dependencies: plugin-managed custom DB tables + Dokan category model
- Core logic candidate: attribute-set model, field types, conditional visibility rules
- Default WP adapter target: WP taxonomy + post meta/user meta schema or custom CPT schema

2. Vendor dashboard dynamic field rendering
- Current dependencies: Dokan seller dashboard hooks and templates
- Core logic candidate: field renderer contract by category + validation rules
- Default WP adapter target: Gutenberg sidebar/panel blocks + profile edit screen integration

3. Storefront/vendor profile display of category attributes
- Current dependencies: Dokan storefront templates and user meta conventions
- Core logic candidate: attribute projection and formatting rules for public profile
- Default WP adapter target: block render callbacks + template parts

4. Store listing filters by attributes
- Current dependencies: Dokan store list query/filter hooks
- Core logic candidate: filter schema, query translation, and result ordering behavior
- Default WP adapter target: WP_Query/meta_query bridge endpoints and block filter UI

## Migration Risk Notes

- Moderate risk and good pilot candidate for Default WP Adapter.
- Strongly data-model-centric with lower payment-system coupling.

## Parity Oracle (Initial)

- Same field visibility and conditional logic behavior by category.
- Same filter results for equivalent attribute combinations.
- Same profile attribute output for a given vendor data state.
