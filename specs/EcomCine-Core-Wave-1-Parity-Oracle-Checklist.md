---
title: EcomCine Core Wave 1 Parity Oracle Checklist
type: feature-tasks
status: planning
completion: 80
priority: high
authority: primary
intent-scope: planning,spec-generation,implementation,review
phase: phase-1
last-reviewed: 2026-04-09
current-focus: define the semantic parity checks required before Wave 1 cutover is allowed
next-focus: translate this checklist into runtime assertions and test coverage during implementation
ide-context-token-estimate: 1500
token-estimate-method: approx-chars-div-4
session-notes: >
  This checklist defines what must be compared before Wave 1 route and query cutover
  can be called safe. It is a planning artifact only.
known-issues:
  - Visual parity alone is not enough because route and query ownership are the real structural risks.
  - Some divergences may be intentional, but they must be named explicitly.
do-not-repeat:
  - Do not accept transport success or a passing page load as proof of parity.
  - Do not leave intentional divergences undocumented.
related-files:
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
  - ./tools/playwright/tests/player-regression.spec.ts
---

# EcomCine Core Wave 1 Parity Oracle Checklist

## Purpose

Wave 1 cutover is blocked until parity can be evaluated semantically.

## Route Parity Checklist

Confirm all of the following:

1. Canonical fresh-install Listing URLs resolve under `profile/{slug}`.
2. Legacy aliases redirect to the canonical `profile/{slug}` target.
3. Page-context classification for Listing profile routes is identical between shadow and intended core mode.
4. Non-Listing pages are not misclassified as Listing pages.
5. Slug collisions do not route attachments or unrelated content into Listing profile authority.

## Listing Lookup Parity Checklist

Confirm all of the following:

1. The same public Listing is identified from the canonical record in both legacy and core comparison paths.
2. Ownership lookup resolves the expected primary owner.
3. Missing or unpublished Listing states degrade intentionally.
4. Compatibility projection data does not override canonical Listing identity.

## Query Parity Checklist

Confirm all of the following:

1. Result IDs match expected semantic scope.
2. Ordering rules are stable or intentional deviations are documented.
3. Pagination counts and page boundaries are acceptable.
4. Category filters behave consistently.
5. Attribute filters behave consistently.
6. Visibility, publish-state, and completeness handling are consistent.
7. Empty-state behavior is intentional.

## Allowed Divergence Rules

An observed difference is only acceptable if one of these is true:

1. It fixes a known legacy bug.
2. It follows a closed product decision.
3. It reduces third-party dependency without harming the intended user outcome.

Every accepted divergence must be logged by name.

## Cutover Gate

Wave 1 cutover is blocked if any of these remain unresolved:

1. route misclassification
2. Listing identity mismatch
3. query scope mismatch without documented intent
4. visibility or publish-state regression