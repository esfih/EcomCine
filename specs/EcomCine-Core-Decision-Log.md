---
title: EcomCine Core Decision Log
type: decision-log
status: active
completion: 100
priority: critical
authority: primary
intent-scope: planning,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: record all closed architecture decisions for the EcomCine Core rebuild
next-focus: maintain as living record during Wave 1 execution
ide-context-token-estimate: 900
token-estimate-method: approx-chars-div-4
session-notes: >
  This file is the single source of truth for closed architecture decisions.
  Do not reopen a closed decision during a routine implementation session
  unless a hard blocker is discovered and documented here first.
known-issues:
  - None. All recorded decisions were reviewed and confirmed closed on 2026-04-09.
do-not-repeat:
  - Do not reopen closed decisions without documenting a hard blocker rationale in this file first.
related-files:
  - ./specs/EcomCine-Core-Rebuild-Blueprint.md
  - ./specs/EcomCine-Core-Deployment-README.md
  - ./specs/EcomCine-Core-Subsystem-Ownership-Matrix.md
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
---

# EcomCine Core Decision Log

## Purpose

Single record of all closed architecture decisions for the EcomCine Core rebuild. Referenced by all planning and implementation artifacts. Every decision here is final unless a hard blocker is discovered and the reopening is documented below before any reversal.

---

## Closed Decisions

### CD-01 — Canonical Public URL Base

- **Decision:** Canonical base is `profile`; legacy bases (`person`, `talent`, prior terminology) redirect as aliases.
- **Rationale:** Listing route stays system-owned and alias-safe. Neutral term avoids coupling to one listing type.
- **Closed:** 2026-04-09
- **Affects:** Route authority, alias redirect contract, page-context classification.

### CD-02 — Canonical Listing Storage Object

- **Decision:** Phase 1 uses `tm_vendor` as the single canonical Listing storage object under a Listing service boundary. No second Listing CPT in Wave 1.
- **Rationale:** Avoids data migration nightmare and dual-source-of-truth bugs. User meta remains compatibility projection, not a separate source of truth.
- **Closed:** 2026-04-09
- **Affects:** Listing authority, ownership lookup, all modules that read `tm_vendor`.

### CD-03 — Tier 1 Commerce Adapters

- **Decision:** WooCommerce is Tier 1 commerce; WooCommerce Bookings is Tier 1 booking path. FluentCart and EDD move to Tier 2.
- **Rationale:** First deployment path stays narrow. WooCommerce has the largest install base and existing adapter code.
- **Closed:** 2026-04-09
- **Affects:** Adapter development priority, Wave 3 scope.

### CD-04 — Default Checkout Posture

- **Decision:** Branded handoff is the default posture; embedded flows are explicit upgrades per adapter grade.
- **Rationale:** Not every commerce system can be fully embedded. Explicit > pretending.
- **Closed:** 2026-04-09
- **Affects:** CTA orchestration, adapter grade declarations.

### CD-05 — Core Only Transaction Substitute

- **Decision:** Every Listing supports EcomCine-owned ContactIntent CTA in Core Only mode. No fake checkout in Core Only.
- **Rationale:** Contact/inquiry/lead capture is the honest baseline when no commerce adapter exists.
- **Closed:** 2026-04-09
- **Affects:** CTA model, Wave 3 ContactIntent implementation.

### CD-06 — V1 Listing Types

- **Decision:** `person`, `company`, and `venue` are first-class in V1. `practice`, `brand`, `team`, `agency` remain future types.
- **Rationale:** Three types cover the immediate product need. Keeping scope narrow reduces Wave 1 risk.
- **Closed:** 2026-04-09
- **Affects:** Listing type registry, onboarding flows, type-specific rendering.

### CD-07 — Licensing and Packaging Structure

- **Decision:** Internal capability packs, external outcome-based product language. Marketplace remains an additive pack/preset.
- **Rationale:** Clean internal architecture, user-friendly external framing.
- **Closed:** 2026-04-09
- **Affects:** Licensing system, feature gating, commercial packaging.

---

## Reopening Protocol

To reopen a closed decision:

1. Document the hard blocker discovered during implementation.
2. Record the blocker here with the decision ID, date, and description.
3. Do not proceed with reversal until the reopening is reviewed.
4. Update all downstream artifacts if the decision changes.

### Reopened Decisions

_None._
