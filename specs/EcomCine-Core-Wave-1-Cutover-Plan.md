---
title: EcomCine Core Wave 1 Cutover Plan
type: feature-tasks
status: planning
completion: 25
priority: critical
authority: primary
intent-scope: planning,spec-generation,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: define the Wave 1 cutover sequence for Listing sovereignty without starting implementation
next-focus: break Wave 1 into parity, rollback, and feature-flag packets once planning is approved
ide-context-token-estimate: 2400
token-estimate-method: approx-chars-div-4
session-notes: >
  This document converts the frozen Wave 1 boundary into a detailed cutover plan.
  It remains planning-only and is intended to sequence work safely before any plugin
  implementation begins.
known-issues:
  - Route, Listing object, and query ownership are tightly coupled, so Wave 1 must be staged carefully.
  - Existing person-centric helper names and `tm_vendor` naming remain in the repo and will persist through Phase 1 for compatibility.
  - Public-runtime regressions are likely if route cutover happens before parity-oracle and rollback paths are prepared.
do-not-repeat:
  - Do not start Wave 1 by creating a second Listing object in parallel with `tm_vendor`.
  - Do not cut over public query paths before route rollback and parity comparison exist.
related-files:
  - ./specs/EcomCine-Core-Rebuild-Blueprint.md
  - ./specs/EcomCine-Core-Subsystem-Ownership-Matrix.md
  - ./specs/EcomCine-Core-Decision-Log.md
  - ./specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
  - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
  - ./specs/EcomCine-Core-Deployment-README.md
  - ./ecomcine/includes/functions.php
  - ./ecomcine/modules/tm-media-player/includes/adapters/default-wp/class-wp-vendor-cpt.php
  - ./ecomcine/modules/tm-store-ui/
  - ./ecomcine/modules/tm-account-panel/
---

# EcomCine Core Wave 1 Cutover Plan

## Purpose

Wave 1 is the first real structural cutover of the EcomCine Core rebuild.

Its purpose is to make EcomCine sovereign over three inseparable concerns:

1. public Listing URL generation and route resolution
2. the canonical Listing storage and ownership boundary
3. Listing lookup, search, and filter behavior

Wave 1 should prove that EcomCine owns identity, route resolution, and query semantics together.

---

## Locked Inputs

Wave 1 planning assumes the following decisions are closed and not re-opened inside this plan:

1. Canonical public base for fresh installs is `profile`, with legacy aliases redirected.
2. Phase 1 canonical Listing storage remains the existing `tm_vendor` CPT under a Listing service boundary.
3. V1 first-class Listing types are `person`, `company`, and `venue`.

If any of those change, this plan must be revised before implementation begins.

---

## Wave 1 Scope

### In Scope

- canonical Listing URL contract
- alias redirect contract
- page-context normalization for Listing routes
- canonical Listing lookup and ownership lookup contract
- Listing query, search, and filter service design
- parity oracle for Listing lookup and query results
- rollback and feature-flag policy for Wave 1 surfaces

### Out of Scope

- CTA redesign
- booking and checkout behavior
- account activity persistence redesign
- final template de-legacy work beyond what is required for one vertical slice
- marketplace capability logic

---

## Wave 1 Success Condition

Wave 1 is successful only when all of the following are true:

1. EcomCine can generate and resolve canonical Listing profile URLs without depending on Dokan or Woo page assumptions.
2. EcomCine has one explicit canonical Listing record per public Listing in scope, with ownership rules defined against that record.
3. One public directory slice can load, search, and filter Listings through an EcomCine-owned query path.
4. The new path can be compared against compatibility mode using a parity oracle.
5. Rollback to the previous runtime path is documented and gateable.

Anything less is not a true Wave 1 cutover.

---

## Cutover Strategy

Wave 1 should be staged in five packets.

## Packet 1 - Contract Freeze

Goal:

- freeze route, Listing object, and query contracts before changing any runtime behavior

Planning outputs:

- canonical route policy
- Listing ownership policy
- Wave 1 feature-flag names
- parity-oracle inputs and comparison rules

Exit gate:

- no packet advances until the route contract, canonical storage contract, and V1 type scope are all reflected in the anchor docs

## Packet 2 - Route and Context Shadow Path

Goal:

- prepare EcomCine-owned route resolution and page-context handling to run in shadow mode before it becomes authoritative

Planning requirements:

- canonical route-generation path identified
- alias redirect rules identified
- page-context wrapper points identified
- rollback switch named for route ownership

Shadow outcome:

- route resolution can be compared to legacy behavior without becoming the only live path immediately

## Packet 3 - Listing Storage and Ownership Freeze

Goal:

- make the Listing object boundary explicit around the existing `tm_vendor` CPT without duplicating storage

Planning requirements:

- source-of-truth rule for Listing identity
- source-of-truth rule for ownership lookup
- compatibility projection rules for user meta
- future manager/collaborator relation placeholder defined even if not implemented in Wave 1

Shadow outcome:

- every public Listing in the cutover slice can be described in terms of one canonical Listing record and one ownership contract

## Packet 4 - Query and Search Shadow Comparison

Goal:

- define the EcomCine-owned Listing query path and compare it against compatibility results before hard cutover

Planning requirements:

- query inputs defined
- result-shape contract defined
- parity oracle fields defined
- acceptable divergence policy defined

Shadow outcome:

- the team can measure where legacy and core results differ and decide whether the divergence is a bug, a tolerated change, or a product decision

## Packet 5a - Identity Slice Cutover

Goal:

- cut over the smallest public Listing identity slice that proves route resolution and Listing lookup are EcomCine-owned together before query ownership moves

Required slice:

- one canonical Listing URL
- one Listing lookup path
- one Listing detail resolution path

Exit gate:

- parity oracle exists
- rollback path exists
- route and Listing flags can be reversed independently if needed

## Packet 5b - Directory Slice Cutover

Goal:

- cut over one public directory slice that proves route, identity, query, and rendering are all EcomCine-owned together after Packet 5a is stable

Required slice:

- one canonical Listing URL
- one Listing lookup path
- one Listing card view model
- one Listing detail page view model
- one filterable Listing collection surface

Exit gate:

- parity oracle exists
- rollback path exists
- route, Listing, and query flags can be reversed independently if needed

---

## Feature-Flag Planning Model

Wave 1 should not use one giant boolean.

Plan three separate flags:

1. route ownership flag
2. Listing ownership lookup flag
3. query ownership flag

Reason:

- route resolution, storage lookup, and query cutover have different failure modes
- separate flags keep rollback smaller and diagnostics clearer

Add one optional shadow-mode flag if the team wants side-by-side comparison without switching user-visible ownership yet.

---

## Parity Oracle Planning Model

Wave 1 requires parity checks at two levels.

### Route Parity

Compare:

- canonical URL generation
- alias redirect result
- page-context classification

### Query Parity

Compare:

- result IDs
- result ordering rules
- pagination behavior
- category and attribute filter behavior
- visibility and publish-state handling

Rule:

- parity is semantic, not literal
- any intentional divergence must be recorded explicitly, not discovered accidentally after cutover

---

## Rollback Planning Model

Wave 1 must define rollback before implementation begins.

### Route Rollback

- restore prior route authority while keeping alias redirects safe

### Listing Lookup Rollback

- restore prior ownership or lookup resolution if canonical Listing lookups misclassify records

### Query Rollback

- restore compatibility query path if search/filter parity fails in live validation

Rollback rule:

- each rollback path must map to a named flag or gate, not to an emergency code edit under pressure

---

## Operational Deliverables

Before Wave 1 implementation starts, planning should produce these concrete artifacts:

1. route contract note
2. Listing ownership contract note
3. query contract note
4. feature-flag naming sheet
5. parity-oracle checklist
6. rollback checklist
7. vertical-slice definition

Some may be folded into one spec if the team prefers, but none of the content may be skipped.

Status:

- all seven planning deliverables now exist

---

## Recommended Public Slice for First Cutover

Use one directory-oriented Listing surface first, not a transaction-heavy path.

Recommended slice:

1. one canonical `profile/{slug}` route
2. one Listing directory card surface
3. one Listing detail surface
4. one filterable Listing collection surface

Reason:

- this proves core ownership of identity and discovery before CTA complexity obscures the result

Do not use checkout or booking as the first proof point.

---

## Exit Criteria for Planning Completion

This cutover plan is ready to hand off into implementation planning only when:

1. the three locked inputs remain accepted
2. the matrix reflects the frozen Wave 1 boundary
3. the blueprint reflects the closed Wave 1 decisions
4. the parity model is accepted
5. the rollback model is accepted

Until then, Wave 1 remains planning-only by design.