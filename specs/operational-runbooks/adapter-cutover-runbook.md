# Adapter Cutover Runbook — EcomCine WP Default Adapter

Last updated: 2026-03-27
Phase: 4 — Controlled Cutover
Owner: Architecture / DevOps

---

## Overview

EcomCine uses a two-layer adapter architecture per feature group.  
Each group has:

- **Compatibility adapter** — wraps existing Dokan / WooCommerce / WC Bookings APIs.
- **Default-WP adapter** — WordPress-native implementation using CPTs, meta, and taxonomy.

At runtime each adapter registry auto-detects which layer to activate, but can be forced
via a `wp-config.php` constant.

| Feature Group | Registry Class | Override Constant | Auto-detect Trigger |
|---|---|---|---|
| Theme Orchestration (THO) | `THO_Adapter_Registry` | `THO_ADAPTER` | `dokan_is_store_page()` present → compat |
| Account Panel (TAP) | `TAP_Adapter_Registry` | `TAP_ADAPTER` | `dokan_is_store_page()` present → compat |
| Vendor Booking Modal (TVBM) | `TVBM_Adapter_Registry` | `TVBM_ADAPTER` | `wc_get_product()` present → compat |
| Media Player (TMP) | `TMP_Adapter_Registry` | `TMP_ADAPTER` | Phase-2 pilot; same pattern |
| Category Attributes (DCA) | `DCA_Adapter_Registry` | `DCA_ADAPTER` | Phase-2 pilot; same pattern |

---

## Environment Reference

| Environment | Dokan | WooCommerce | Default adapter mode |
|---|---|---|---|
| `castingagency.co` (live) | Active | Active | compat (auto-detected) |
| Local dev (`localhost:8180`) | Active | Active | compat (auto-detected) |
| New WP site (without Dokan/WC) | Absent | Absent | default-wp (auto-detected) |
| Forced test / staging | Any | Any | Override constant required |

---

## Pre-Cutover Checklist

Run all steps before flipping any adapter to `default-wp` in a non-dev environment.

### 1. Confirm site health

```bash
./scripts/wp.sh wp core is-installed
# HTTP check
curl -s -o /dev/null -w "%{http_code}" http://localhost:8180/ | grep 200
```

### 2. Run all parity suites

All three suites must report full PASS before cutover.

```bash
# TAP — expect: TAP PARITY: 16/16
./scripts/wp.sh wp eval 'TAP_Parity_Check::run();'

# TVBM — expect: TVBM PARITY: 14/14
./scripts/wp.sh wp eval 'TVBM_Parity_Check::run();'

# THO — expect: THO PARITY: 20/20
./scripts/wp.sh wp eval 'THO_Parity_Check::run();'
```

Reference catalog commands: `parity.check.tap`, `parity.check.tvbm`, `parity.check.tho`

### 3. Validate current toggle state

Confirms auto-detect selects the expected adapter for the target environment.

```bash
./scripts/wp.sh php scripts/validate-adapter-toggles.php
# Expect: ADAPTER TOGGLES: ALL PASS
```

Reference catalog command: `adapter.toggle.validate`

### 4. Review CPT migration status

Default-WP adapters store data in WordPress CPTs, not in Dokan/WC tables.
For a site with existing live Dokan/WC data, a CPT migration script is required
before cutting over to ensure no data is silently dropped.

| Feature Group | CPTs Created by Default-WP Adapter | Migration Required? |
|---|---|---|
| TAP | `tm_invitation`, `tm_order`, `tm_booking` | Yes — if existing WC orders/bookings must be preserved |
| TVBM | `tm_offer` | Yes — if existing WC booking products must remain discoverable |
| THO | `vm_vendor` (CPT-based vendor profiles) | Yes — if Dokan vendor profile data must migrate to `vm_vendor` CPT |
| TMP | Showcase CPTs (Phase 2) | Minimal — media showcase metadata |
| DCA | Attribute taxonomy | Minimal — category attribute data is taxonomy-based |

**castingagency.co cutover prerequisite:** Data migration scripts for TAP, TVBM, and THO
must be written, tested, and reviewed before switching those groups to default-wp on live.

---

## Cutover Procedure — Per Feature Group

### Step 1 — Force toggle in wp-config.php

Add the following constants to `wp-config.php` **before** the `/* That's all, stop editing! */` line.
Replace `<GROUP>` with the target constant name.

```php
// EcomCine adapter override — remove after validation
define( 'TAP_ADAPTER',  'default-wp' ); // Account Panel
define( 'TVBM_ADAPTER', 'default-wp' ); // Vendor Booking Modal
define( 'THO_ADAPTER',  'default-wp' ); // Theme Orchestration
```

To cut over individual groups only, add just the constant(s) needed.

**Local container — add via WP-CLI (dev/staging only):**

```bash
# Add a single constant
./scripts/wp.sh wp config set TAP_ADAPTER 'default-wp' --raw
```

### Step 2 — Verify toggle applied

```bash
./scripts/wp.sh php scripts/validate-adapter-toggles.php
# Lines for the switched group should now read:
#   [PASS] TAP override constant — TAP_ADAPTER = 'default-wp'
#   [PASS] TAP expected mode — default-wp
#   [PASS] TAP context_resolver class — TAP_WP_Page_Context_Resolver
```

### Step 3 — Run parity suite for the switched group

```bash
# Example for TAP
./scripts/wp.sh wp eval 'TAP_Parity_Check::run();'
# Expect: TAP PARITY: 16/16
```

### Step 4 — Smoke test critical pages

| Group | Pages to test |
|---|---|
| TAP | `/my-account/`, `/login/`, talent profile pages |
| TVBM | Any `/store/<vendor>/` page with a booking modal |
| THO | `/store/<vendor>/`, store listing, talent showcase |

Check: HTTP 200, correct layout, no PHP errors in `./scripts/wp.sh log 20`.

### Step 5 — Monitor debug log

```bash
./scripts/wp.sh log 50
# Look for: PHP Fatal, PHP Warning, any adapter class not found
```

---

## Rollback Procedure

**Immediate rollback** — remove or comment the override constant in `wp-config.php`:

```php
// define( 'TAP_ADAPTER', 'default-wp' ); // DISABLED — rollback in effect
```

Or via WP-CLI (dev/staging):

```bash
./scripts/wp.sh wp config delete TAP_ADAPTER
```

After removing the constant, auto-detect re-activates.
On sites where Dokan/WC are present, this automatically restores compat adapter.

Verify rollback:

```bash
./scripts/wp.sh php scripts/validate-adapter-toggles.php
# Should show: [PASS] TAP expected mode — compat
```

---

## Compatibility Adapter Retention Policy

### When to retain compat (do NOT cut over)

| Scenario | Rationale |
|---|---|
| Live Dokan/WC orders exist and no CPT migration has run | Default-WP adapter reads from CPTs, not WC orders; historical data would be invisible |
| Dokan vendor dashboard functionality is still in use | THO compat wraps Dokan store URLs and dashboard hooks |
| WooCommerce Bookings calendar forms are in use | TVBM compat renders WC Bookings forms; default-WP renders a native stub |
| Pre-existing WC booking products are referenced by URL | TVBM compat discovers products via `get_posts( post_type = 'product' )`; default-WP uses `tm_offer` CPT |

### When it is safe to cut over

| Scenario | Rationale |
|---|---|
| Greenfield WP site (no Dokan/WC data) | Default-WP adapter is the only active data source; no migration needed |
| Post-migration staging environment | CPT migration scripts have moved all relevant data to WP-native tables |
| Feature A/B test on dev | Check parity first, then switch |

### Current production status (castingagency.co)

All five feature groups remain on **compat** adapter.
Dokan and WooCommerce are both active on the live site.
No CPT migration scripts have been written yet.

**Required before live cutover:**
1. Write and test CPT migration scripts (TAP, TVBM, THO).
2. Run migration in staging with data copy from live.
3. Verify parity + smoke tests in staging.
4. Schedule maintenance window for live migration.

---

## Feature Cutover Status Matrix

| Group | Compat | Default-WP | Parity | CPT Migration | Production-ready |
|---|---|---|---|---|---|
| DCA | ✅ Active | ✅ Passing | Phase 2 | Not required | ✅ Ready |
| TMP | ✅ Active | ✅ Passing | Phase 2 | Not required | ✅ Ready |
| TAP | ✅ Active | ✅ Passing | 16/16 | Required | ❌ Not yet |
| TVBM | ✅ Active | ✅ Passing | 14/14 | Required | ❌ Not yet |
| THO | ✅ Active | ✅ Passing | 20/20 | Required | ❌ Not yet |

---

## Post-Cutover Monitoring Checklist

- [ ] HTTP 200 on all critical store/account pages
- [ ] No PHP Fatal or Warning in debug.log
- [ ] All parity suites still report full PASS
- [ ] `validate-adapter-toggles.php` shows correct class names for switched groups
- [ ] CPT records visible in wp-admin (if using default-WP adapter)
- [ ] User-facing flows functional: login, booking modal, store page render

---

## Related Files

| File | Purpose |
|---|---|
| `specs/WP-Default-Adapter-Refactoring-Plan.md` | Master architecture plan and phase exit criteria |
| `scripts/validate-adapter-toggles.php` | Toggle validation script (catalog: `adapter.toggle.validate`) |
| `specs/IDE-AI-Command-Catalog.md` | Canonical command contracts |
| `theme/includes/adapters/class-adapter-registry.php` | THO registry |
| `ecomcine/modules/tm-account-panel/includes/adapters/class-adapter-registry.php` | TAP registry |
| `ecomcine/modules/tm-vendor-booking-modal/includes/adapters/class-adapter-registry.php` | TVBM registry |
