# Inventory - tm-account-panel

## Group Summary

Current module provides front-end account access, auth actions, onboarding-related interactions, and vendor-facing account panels.

## Feature Inventory

1. Store-page account panel shell and modal UX
- Current dependencies: Dokan store/listing context detection, page template checks
- Core logic candidate: account panel visibility and route eligibility rules
- Default WP adapter target: block-based account panel region + native route guards

2. AJAX login and session bootstrap
- Current dependencies: WP auth APIs + current page/modal context
- Core logic candidate: identifier resolution, nonce checks, auth result contract
- Default WP adapter target: REST auth endpoint wrapper + block UI

3. Admin-assisted talent onboarding and claim/share flows
- Current dependencies: custom AJAX/admin-post handlers in plugin
- Core logic candidate: invitation/claim lifecycle and role transitions
- Default WP adapter target: WP-native custom post type workflow + REST actions

4. Vendor account management panel (orders/bookings/IP sections)
- Current dependencies: Dokan earnings and vendor dashboard assumptions
- Core logic candidate: account dashboard section composition and entitlement checks
- Default WP adapter target: WP user dashboard pages + CPT-backed data widgets

## Migration Risk Notes

- Mixed concerns: auth, onboarding, and vendor operations in one UI layer.
- Current sections rely on Dokan/Woo data availability contracts.

## Parity Oracle (Initial)

- Same login success/failure behavior and error messaging classes.
- Same onboarding lifecycle outcomes for invited users.
- Same account-panel section visibility conditions per role/state.
