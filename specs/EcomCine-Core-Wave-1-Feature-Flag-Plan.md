---
title: EcomCine Core Wave 1 Feature Flag Plan
type: feature-tasks
status: planning
completion: 80
priority: high
authority: primary
intent-scope: planning,spec-generation,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: define the named runtime gates required for Wave 1 deployment safety
next-focus: implement these flags only when Wave 1 execution begins
ide-context-token-estimate: 1400
token-estimate-method: approx-chars-div-4
session-notes: >
  This document names the minimum runtime gates required to deploy Wave 1 safely.
  It is a planning packet, not an implementation record.
known-issues:
  - Route, Listing lookup, and query cutovers have different failure modes and must not share one giant switch.
  - Shadow comparison may be needed before any user-visible authority change is made.
do-not-repeat:
  - Do not collapse all Wave 1 cutover behavior behind one boolean.
  - Do not ship a cutover path that lacks a named rollback gate.
related-files:
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
  - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
---

# EcomCine Core Wave 1 Feature Flag Plan

## Purpose

Wave 1 requires small, explicit runtime gates so rollout and rollback stay precise.

## Required Flags

### `ecomcine_core_route_authority`

Controls:

- canonical Listing route resolution
- alias redirect authority
- public page-context ownership for Listing profile routes

States:

- `legacy`
- `shadow`
- `core`

### `ecomcine_core_listing_authority`

Controls:

- canonical Listing record lookup
- ownership lookup source of truth
- user-meta compatibility projection usage

States:

- `legacy`
- `shadow`
- `core`

### `ecomcine_core_query_authority`

Controls:

- Listing search and filter lookup
- collection result shaping
- pagination and filter authority

States:

- `legacy`
- `shadow`
- `core`

## Optional Support Flag

### `ecomcine_core_wave1_observe_only`

Use only if the implementation wants comparison logging without changing visible authority.

## Rollout Order

1. Route authority
2. Listing authority
3. Query authority

Reason:

- route ownership must stabilize before lookup and query can be trusted
- query is the broadest user-facing blast radius and should move last

## Rollback Order

1. Query authority
2. Listing authority
3. Route authority

Reason:

- reverse blast radius from widest to narrowest

## Flag Rules

1. Each flag must have an observable success criterion.
2. Each flag must have a documented rollback condition.
3. No Wave 1 implementation may assume `core` state globally without checking the relevant authority flag.
4. Shadow mode must never silently mutate canonical behavior.