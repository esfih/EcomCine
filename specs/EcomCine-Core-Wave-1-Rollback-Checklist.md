---
title: EcomCine Core Wave 1 Rollback Checklist
type: feature-tasks
status: planning
completion: 80
priority: high
authority: primary
intent-scope: planning,spec-generation,implementation,maintenance
phase: phase-1
last-reviewed: 2026-04-09
current-focus: define rollback expectations before Wave 1 execution starts
next-focus: bind each rollback path to concrete runtime gates during implementation
ide-context-token-estimate: 1300
token-estimate-method: approx-chars-div-4
session-notes: >
  This checklist names the minimum rollback expectations for Wave 1 route, Listing,
  and query authority changes. It is a safety packet for the deployment phase.
known-issues:
  - Route, Listing, and query failures have different user-visible blast radii.
  - Fast rollback is impossible if each path does not map to an explicit gate.
do-not-repeat:
  - Do not rely on emergency code edits as the first rollback path.
  - Do not cut over a subsystem whose rollback condition is undefined.
related-files:
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
---

# EcomCine Core Wave 1 Rollback Checklist

## Purpose

Wave 1 deployment is allowed only if rollback is preplanned.

## Route Rollback

Confirm all of the following before route authority changes:

1. `ecomcine_core_route_authority` can be returned to `legacy` without code edits.
2. Alias redirects remain safe after rollback.
3. Canonical route rollback does not create attachment or unrelated-content collisions.
4. Page-context behavior after rollback is known.

## Listing Authority Rollback

Confirm all of the following before Listing authority changes:

1. `ecomcine_core_listing_authority` can be returned to `legacy` without code edits.
2. Compatibility lookup paths still exist while rollback is needed.
3. Ownership lookup regressions can be observed quickly.
4. Rollback does not orphan public Listings from their current owner view.

## Query Rollback

Confirm all of the following before query authority changes:

1. `ecomcine_core_query_authority` can be returned to `legacy` without code edits.
2. Search and filter regressions can be detected quickly.
3. Pagination and visibility regressions have an agreed rollback threshold.
4. Rollback restores the prior query path cleanly.

## Rollback Triggers

Rollback must be initiated if any of the following occur:

1. canonical Listing routes misresolve or misclassify a public page
2. Listing identity or ownership mismatches occur in the cutover slice
3. query parity fails without an accepted intentional divergence
4. public runtime behavior becomes unavailable in Core Only mode for the targeted slice

## Deployment Rule

If rollback is not named, tested, and gateable, the subsystem is not deployment-ready.