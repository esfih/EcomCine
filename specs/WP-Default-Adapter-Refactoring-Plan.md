# WP Default Adapter Refactoring Plan (V1)

Last updated: 2026-03-27
Owner: Productization / Architecture
Scope: Detangle business features from Dokan + WooCommerce + WooCommerce Bookings and rebuild feature parity on WordPress default stack (Gutenberg + default theme + WP-native CPT/meta/taxonomy).

## Phase Status

| Phase | Description | Status |
|---|---|---|
| Phase 0 | Inventory and Contracts | ✅ Complete |
| Phase 1 | Core Contract Scaffolding | ✅ Complete |
| Phase 2 | Default WP Pilot Adapter (TMP, DCA) | ✅ Complete |
| Phase 3 | Transactional Flow Migration (TAP 16/16, TVBM 14/14, THO 20/20) | ✅ Complete |
| Phase 4 | Controlled Cutover | ✅ Complete |

## Objective

Build a two-layer architecture:

1. Core Feature Layer
- Encodes business logic, UX flow, conditional rules, and expected outcomes.
- Must not depend directly on Dokan/Woo/Bookings APIs.

2. Adapter Layer
- Compatibility Adapter: current Dokan/Woo/Bookings implementation.
- Default WP Adapter: native WordPress implementation (CPT, meta, taxonomies, blocks, templates, REST, roles/caps).

Target state:
- Each feature spec has one core contract and at least one adapter implementation.
- Current stack remains operational through compatibility adapter during migration.

## Non-Goals (Phase V1)

- Immediate removal of Dokan/Woo/Bookings.
- Full UX redesign while architecture is still being separated.
- Data model replacement without migration contracts.

## Principles

1. Core-first, adapter-second.
2. Source-of-truth behavior captured in spec contracts before code moves.
3. No feature rewrite without parity oracle.
4. Migration in thin vertical slices, not big-bang replacement.
5. Compatibility adapter remains production-safe until default adapter passes parity gates.

## Workstreams

### Workstream A - Feature Contract Capture

Deliverables:
- Feature catalog grouped by current plugin/theme ownership.
- Per feature: business goal, actors, entry points, state model, conditional logic, success oracle.
- Dependency mapping to Dokan/Woo/Bookings APIs and data models.

Output location:
- specs/app-features/catalog/

### Workstream B - Core Contract Abstraction

Deliverables:
- Core contracts/interfaces for each feature group.
- Event and data boundaries independent of vendor stack.
- Canonical naming for entities and capability checks.

### Workstream C - Compatibility Adapter Stabilization

Deliverables:
- Existing Dokan/Woo/Bookings behavior mapped to core contracts.
- Explicit adapter boundary wrappers around third-party hooks/APIs.
- Reduced business logic directly inside theme/template overrides.

### Workstream D - Default WP Adapter Build

Deliverables:
- WordPress-native data model (CPT/meta/taxonomy/options) for each feature group.
- Gutenberg/block-based editor and front-end rendering path.
- WP-native roles/caps and REST endpoints replacing Dokan/Woo/Bookings touchpoints where required.

### Workstream E - Migration and Validation

Deliverables:
- Parity test matrix by feature and adapter.
- Data migration scripts and rollback strategy.
- Feature toggles for adapter switch per feature group.

## Initial Phase Plan

### Phase 0 - Inventory and Contracts

Exit criteria:
- Feature inventory V1 completed for all major plugin groups.
- Adapter dependency matrix drafted.
- Top 5 migration-risk features identified.

### Phase 1 - Core Contract Scaffolding

Exit criteria:
- Core interfaces defined for all grouped features.
- Compatibility adapter mapping completed for at least one pilot group.

### Phase 2 - Default WP Pilot Adapter

Pilot recommendation:
- Start with lower payment risk feature set:
  - category attributes
  - vendor profile data and rendering
  - media showcase delivery

Exit criteria:
- Pilot features run on default WP adapter with parity checks passing.

### Phase 3 - Transactional Flow Migration

Focus:
- booking/order/account flows currently tied to Woo/Bookings and Dokan dashboard behavior.

Exit criteria:
- Default WP adapter supports equivalent action flows and conditional logic.

### Phase 4 - Controlled Cutover

Exit criteria:
- Feature-level cutover toggles validated.
- Compatibility adapter retained only where still required.
- Operational runbooks updated.

## Adapter Contract Template (Per Feature)

Each feature spec should declare:

- Feature ID
- Business objective
- UX journey and user actions
- Inputs and outputs
- State transitions
- Conditional logic
- Side effects (emails, orders, metadata updates)
- Current dependencies (Dokan/Woo/Bookings)
- Core contract definition
- Compatibility adapter behavior
- Default WP adapter behavior
- Parity oracle (semantic validation)

## Validation Gates

A feature cannot switch to default adapter unless:

1. Contract parity is documented.
2. Semantic validation passes for happy path and critical edge cases.
3. Data migration path exists (or is proven unnecessary).
4. Rollback path exists.

## Cataloging Deliverables in this pass

This V1 pass includes:

- Plan document (this file).
- Plugin-group inventory scaffold under specs/app-features/catalog/plugin-groups/.
- Initial per-group feature breakdown with dependency and adapter-target notes.

## Next Immediate Action After V1

Start per-feature contract files for the first pilot group (`dokan-category-attributes` recommended), then define its Core Contract and both adapter definitions.

---

## Phase 4 — Controlled Cutover (Completed 2026-03-27)

### Exit Criteria Verification

**1. Feature-level cutover toggles validated**

All five adapter registries verified via `scripts/validate-adapter-toggles.php` (catalog: `adapter.toggle.validate`).

With Dokan + WooCommerce present (castingagency.co / local dev):
- TAP registry → `TAP_Compat_*` classes (auto-detected, no override constant)
- TVBM registry → `TVBM_Compat_*` classes (auto-detected, no override constant)
- THO registry → `THO_Compat_*` classes (auto-detected, no override constant)

Toggle override mechanism confirmed working via code review and test-injection methods on all registries.
To force default-WP mode: `define( 'TAP_ADAPTER', 'default-wp' )` in wp-config.php (see runbook).

**2. Compatibility adapter retained only where still required**

castingagency.co retains compat for TAP, TVBM, and THO because:
- Dokan and WooCommerce are active on the live site.
- No CPT migration scripts have been written to move existing Dokan/WC data to WP-native CPTs.
- Default-WP adapters are production-ready in terms of contract parity but require data migration before live cutover.

DCA and TMP (Phase 2 groups) have no live data dependency on Dokan/WC and are ready for default-WP cutover on any greenfield site.

**3. Operational runbooks updated**

Runbook created at `specs/operational-runbooks/adapter-cutover-runbook.md`.
Covers:
- Pre-cutover checklist (parity checks, health check, migration assessment)
- Per-group constant override procedure
- Rollback procedure
- Compatibility adapter retention policy
- Feature cutover status matrix
- Post-cutover monitoring checklist

### Next Steps (Post-Phase 4)

1. ~~**Write CPT migration scripts**~~ ✅ Done — `migrate-tap-cpt.php`, `migrate-tvbm-cpt.php`, `migrate-tho-cpt.php`
2. ~~**Validate migration on staging**~~ ✅ Done — 2026-03-28
   - CPT data: tm_order=19, tm_booking=28, tm_offer=5, tm_vendor=52, tm_invitation=0 (no tokens on staging)
   - Pre-flip parity: TAP 16/16, TVBM 14/14, THO 20/20 (compat mode)
   - Constants added: `TAP_ADAPTER`, `TVBM_ADAPTER`, `THO_ADAPTER` = `'default-wp'`
   - Post-flip parity: TAP 16/16, TVBM 14/14, THO 20/20 (default-wp mode)
   - HTTP 200: home, /my-account/, /talents/mhd/
   - Debug log: no fatal errors post-cutover
3. **Schedule live cutover** per group following the runbook — pending business approval
4. **Decommission compat adapters** after all groups have passed live cutover validation

