# App Feature Catalog (V1)

This folder is the structured inventory for migration from current Dokan/Woo/Bookings stack to dual-adapter architecture:

- compatibility adapter (current stack)
- default WP adapter (target stack)

## Structure

- plugin-groups/tm-media-player/
- plugin-groups/tm-account-panel/
- plugin-groups/tm-vendor-booking-modal/
- plugin-groups/dokan-category-attributes/
- plugin-groups/theme-orchestration/

Each group inventory should capture:

1. Current feature behavior and UX/action flow
2. Current third-party dependencies
3. Core business logic contract candidate
4. Default WP adapter implementation target
5. Parity validation oracle

## Structure (Expanded)

```
catalog/
  inventory.json                          — machine-readable summary of all 21 features
  README.md                               — this file
  plugin-groups/
    dokan-category-attributes/
      inventory.md
      features/
        dca-001.md  — Attribute schema and storage tables
        dca-002.md  — Vendor dashboard dynamic field rendering
        dca-003.md  — Storefront vendor profile attribute display
        dca-004.md  — Store listing filters by attributes
    tm-media-player/
      inventory.md
      features/
        tmp-001.md  — Vendor playlist extraction from biography shortcodes
        tmp-002.md  — Fallback media strategy (banner video / banner image)
        tmp-003.md  — REST endpoint for vendor store content payload
        tmp-004.md  — AJAX navigation list and store content loaders
    tm-account-panel/
      inventory.md
      features/
        tap-001.md  — Store-page account panel shell and modal UX
        tap-002.md  — AJAX login and session bootstrap
        tap-003.md  — Admin-assisted talent onboarding and claim/share flows
        tap-004.md  — Vendor account management panel (orders, bookings, IP)
    tm-vendor-booking-modal/
      inventory.md
      features/
        tvbm-001.md — Vendor store booking product discovery (half-day category)
        tvbm-002.md — Modal booking form rendering and booking asset loading
        tvbm-003.md — Add-to-cart and checkout modal actions
        tvbm-004.md — Checkout field, privacy and terms customization filters
    theme-orchestration/
      inventory.md
      features/
        tho-001.md  — Store/listing template override orchestration
        tho-002.md  — Asset governance for vendor pages
        tho-003.md  — Vendor profile meta synchronization and custom fields
        tho-004.md  — Product/store vendor identity presentation hooks
        tho-005.md  — Social metrics, vendor completeness, and map modules
```

## Contract Schema (per feature file)

Each feature contract file includes:

- YAML frontmatter: `feature_id`, `title`, `group`, `migration_risk`, `migration_phase`, `adapter_status`
- Business objective
- Actors
- UX journey / user actions
- Inputs and outputs tables
- State transitions
- Conditional logic
- Side effects
- Current dependencies table
- Core contract definition (language-agnostic function signatures)
- Compatibility adapter behavior (current Dokan/WC/Bookings implementation)
- Default WP adapter behavior (target native WP implementation)
- Parity oracle table

## inventory.json

`catalog/inventory.json` provides a machine-readable summary of all 21 features with:

- `id`, `title`, `group`, `group_short`
- `business_objective` (one-line)
- `actors` array
- `migration_risk` (low / medium / high)
- `migration_phase` (0–4)
- `current_deps` array
- `adapter_status` (compatibility, default_wp)
- `parity_oracle_short` (one-line)
- `contract_file` (relative path)

Plus a `migration_summary` section with features grouped by phase and risk, and a `recommended_pilot_order`.

## Migration Risk Legend

| Risk | Meaning |
|---|---|
| low | No transactional or payment coupling; pure data or display |
| medium | Dokan/WC coupling but no payment flow; replaceable with moderate effort |
| high | WC Bookings / Dokan vendor model / payment deeply coupled; requires full adapter build |

## Status

- V1 scaffold and inventories: complete
- Per-feature contract files (21 features): complete
- inventory.json: complete
- Per-feature Core Contract interfaces (Workstream B): pending
- Compatibility adapter boundary wrappers (Workstream C): pending
- Default WP adapter implementation (Workstream D): not started
