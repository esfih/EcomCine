---
title: EcomCine Core Wave 1 Implementation Advisory
type: advisory
status: active
completion: 100
priority: critical
authority: advisory
intent-scope: implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: guide the executing agent through optimized Wave 1 implementation
next-focus: consume this file at the start of the first Wave 1 execution session
ide-context-token-estimate: 1800
token-estimate-method: approx-chars-div-4
session-notes: >
  Written after a full review of the Blueprint, Ownership Matrix, Cutover Plan,
  Feature Flag Plan, Parity Oracle Checklist, Rollback Checklist, Deployment README,
  and the live codebase (ecomcine.php, all modules, includes/, core adapters).
  These are actionable recommendations to improve execution efficiency and stability.
known-issues:
  - Decision Log did not exist and has been created as specs/EcomCine-Core-Decision-Log.md.
do-not-repeat:
  - Do not skip reading the Decision Log before starting implementation.
  - Do not collapse the recommendations below into one giant PR — they are sequenced.
related-files:
  - ./specs/EcomCine-Core-Decision-Log.md
  - ./specs/EcomCine-Core-Deployment-README.md
  - ./specs/EcomCine-Core-Rebuild-Blueprint.md
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
  - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
  - ./specs/EcomCine-Core-Subsystem-Ownership-Matrix.md
  - ./ecomcine/ecomcine.php
  - ./ecomcine/includes/functions.php
  - ./ecomcine/includes/core/runtime/class-runtime-adapters.php
---

# EcomCine Core Wave 1 — Implementation Advisory

## Context

This advisory was produced after reviewing all Wave 1 planning documents and the live codebase. It identifies gaps, risks, and optimization opportunities. The executing agent should read this file alongside the Deployment README before writing any code.

---

## Advisory 1 — Decision Log Now Exists

**Gap found:** `specs/EcomCine-Core-Decision-Log.md` was referenced by all planning docs but did not exist.

**Action taken:** Created with all seven closed decisions (CD-01 through CD-07) recorded with rationale, dates, and downstream impact.

**Executing agent rule:** Read the Decision Log before starting. Do not reopen any closed decision without following the reopening protocol documented in that file.

---

## Advisory 2 — Split Cutover Plan Packet 5 Into Two Passes

**Risk:** Packet 5 ("Vertical Slice Cutover") attempts route + listing lookup + query + rendering in one pass. This is too wide for a first proof point.

**Recommendation:** Split into:

- **Packet 5a — Identity Slice:** Route resolution + Listing lookup only. Prove that one `profile/{slug}` URL resolves to the correct `tm_vendor` record through the new Listing service, with correct ownership. No query engine changes yet.
- **Packet 5b — Directory Slice:** Query/filter cutover for one public directory surface, consuming the Listing service from 5a.

**Rationale:** 5a gives an earlier rollback boundary. If route ownership alone surfaces issues, you fix them before touching the query layer.

**Where to update:** `specs/EcomCine-Core-Wave-1-Cutover-Plan.md` — split the Packet 5 section.

---

## Advisory 3 — Create EcomCine_Listing_Service as the First Code Artifact

**Gap:** The plan says "Listing service boundary" but does not define a concrete class. Without this, each module will continue making independent `tm_vendor` / user-meta assumptions.

**Recommendation:** Create `ecomcine/includes/core/class-listing-service.php` as a single class that owns:

1. **Listing lookup by ID** — wraps `get_post()` for `tm_vendor` type
2. **Listing lookup by slug** — canonical slug resolution
3. **Ownership lookup** — who owns/manages this listing
4. **URL generation** — canonical `profile/{slug}` URL, respecting the route authority flag
5. **Listing type resolution** — `person`, `company`, or `venue`
6. **Publish/visibility state** — is this listing public?
7. **Feature-flag awareness** — checks `ecomcine_core_listing_authority` flag internally

**Key rule:** This class wraps `tm_vendor` CPT access. It does NOT create a new CPT. It is the seam that all modules should consume instead of direct post/meta queries.

**Why this is highest-leverage:** Every module currently has its own way of finding and interpreting `tm_vendor` data. Centralizing this is what makes feature-flag gating, parity checking, and rollback possible at all.

---

## Advisory 4 — Core-Level Service Before Module-Level Adapters

**Current state:** Each module (`tm-media-player`, `tm-account-panel`, `tm-store-ui`, `tm-vendor-booking-modal`) has its own `Adapter_Registry` that independently resolves runtime behavior.

**Problem for Wave 1:** If route/listing/query authority lives at the core level but each module still independently queries `tm_vendor`, the feature flags are meaningless — modules bypass them.

**Recommendation execution order:**

1. Build `EcomCine_Listing_Service` in `ecomcine/includes/core/`
2. Build `EcomCine_Route_Service` in `ecomcine/includes/core/` (owns canonical URL generation and route resolution)
3. Build `EcomCine_Query_Service` in `ecomcine/includes/core/` (owns listing collection queries)
4. Refactor module adapter registries to delegate to these core services for listing identity, routing, and queries
5. Keep module-specific adapters (media source, booking form, checkout) — those are correctly module-scoped

**Do not try to unify all module registries into one.** Just give them a shared core dependency for the three Wave 1 concerns.

---

## Advisory 5 — Extract Canonical API from functions.php

**Current state:** `ecomcine/includes/functions.php` contains both canonical EcomCine API functions and Dokan fallback/compatibility logic mixed together.

**Problem:** During Wave 1, this file becomes a constant source of bugs because callers can't tell whether they're hitting the new path or the old path.

**Recommendation:**

1. Move canonical listing helpers (`ecomcine_get_person_*`, URL generation, ownership checks) into `EcomCine_Listing_Service` methods
2. Keep `functions.php` as a thin **facade** that delegates to the service
3. Feature-flag logic lives in the service, not in `functions.php`
4. Existing callers continue working through the facade — no breaking changes needed

This is a refactor, not a rewrite. The public API surface stays the same.

---

## Advisory 6 — Build Automated Parity Oracle as a WP-CLI Command

**Gap:** The Parity Oracle Checklist defines what to compare but not how. Manual comparison will not scale and defeats the goal of reducing reactive debugging.

**Recommendation:** Implement as a WP-CLI command:

```bash
./scripts/wp.sh wp ecomcine parity-check wave1
```

**What it should do:**

1. Run legacy query path and core query path for the same inputs
2. Compare result IDs, ordering, pagination, and visibility
3. Compare route resolution for a sample of listing slugs
4. Output a structured diff (JSON or table) showing matches, mismatches, and intentional divergences
5. Exit 0 if parity holds, exit 1 if unexpected divergences exist

**When to build:** During Packet 4 (Query and Search Shadow Comparison), before any hard cutover.

**Where to place:** `ecomcine/includes/core/cli/class-parity-check-command.php` or similar.

---

## Advisory 7 — Shadow Mode Must Log to a Reviewable Target

**Gap:** Shadow mode is referenced in the Feature Flag Plan but no logging destination is specified.

**Recommendation:**

- Shadow mode comparison results should log to a dedicated option or transient (e.g., `ecomcine_wave1_shadow_log`) that can be reviewed in WP Admin or via WP-CLI
- Each shadow comparison entry should record: timestamp, subsystem (route/listing/query), input, legacy result, core result, match/mismatch
- Add a WP-CLI command to dump the shadow log: `./scripts/wp.sh wp ecomcine shadow-log`
- Clear the log on authority flag changes to avoid stale data

---

## Advisory 8 — Feature Flags Should Live in wp_options with Admin UI

**Recommendation for flag storage:**

- Store in `wp_options` as `ecomcine_core_route_authority`, `ecomcine_core_listing_authority`, `ecomcine_core_query_authority`
- Default all to `legacy` on fresh install during development
- Add a simple admin UI section under the existing EcomCine settings page for toggling flags
- Add WP-CLI support: `./scripts/wp.sh wp option update ecomcine_core_route_authority shadow`

This keeps flags observable and controllable without code deploys.

---

## Optimized Execution Sequence

The executing agent should follow this order:

| Step | Action | Artifact |
|------|--------|----------|
| 1 | Read Decision Log, confirm all 7 decisions are still closed | `specs/EcomCine-Core-Decision-Log.md` |
| 2 | Implement three feature flags in `wp_options`, default `legacy` | `ecomcine/includes/core/` |
| 3 | Create `EcomCine_Listing_Service` wrapping `tm_vendor` access | `ecomcine/includes/core/class-listing-service.php` |
| 4 | Create `EcomCine_Route_Service` for canonical URL generation + resolution | `ecomcine/includes/core/class-route-service.php` |
| 5 | Implement `profile/{slug}` canonical route, gated behind route authority flag | Route service + rewrite rules |
| 6 | Refactor `functions.php` to delegate to Listing service (facade pattern) | `ecomcine/includes/functions.php` |
| 7 | Refactor module registries to use core Listing service for identity/ownership | Module adapter registries |
| 8 | Build parity CLI command | `ecomcine/includes/core/cli/` |
| 9 | Implement shadow mode logging | Core services |
| 10 | Run parity checks in shadow mode, fix divergences | CLI + logs |
| 11 | Create `EcomCine_Query_Service` for listing collection queries | `ecomcine/includes/core/class-query-service.php` |
| 12 | Cutover one directory surface to core query, gated behind query authority flag | Query service |
| 13 | Run full parity oracle, validate, document divergences | CLI |
| 14 | Flip flags to `core` for the first vertical slice | Admin UI / WP-CLI |

**Rule:** Do not advance to step N+1 if step N introduced regressions. Fix forward, don't skip.

---

## What NOT to Do in Wave 1

1. Do not create a second Listing CPT alongside `tm_vendor`
2. Do not touch checkout, booking, or marketplace logic
3. Do not rewrite templates beyond what the vertical slice requires
4. Do not remove compatibility adapters — they stay as fallback
5. Do not collapse all three feature flags into one boolean
6. Do not accept "page loads without error" as proof of parity — run the oracle
