---
title: Data Source Authority Protocol
type: rules
status: active
completion: 100
priority: critical
authority: primary
intent-scope: planning,spec-generation,implementation,debugging,review,maintenance
phase: phase-1
last-reviewed: 2026-03-12
current-focus: prevent hardcoded variable data in runtime code and enforce authoritative storage mapping
next-focus: expand automated checks and reduce false positives while preserving strict data-source discipline
related-files:
  - ../README-FIRST.md
  - ./IMPLEMENTATION-RULES.md
  - ./AI-SPECS-WORKFLOW.md
  - ./VALIDATION-STACK.md
  - ../scripts/check-data-source-discipline.sh
---

# Data Source Authority Protocol

## Purpose

Define deterministic rules for variable data sources.

Runtime variable data must come from an officially agreed storage location.

Do not hardcode runtime variable values in production code paths.

---

## Core Rule

For runtime behavior and user-visible contract values, AI and developers must:

- identify the authoritative source before implementation
- read values from that source at runtime or at controlled synchronization time
- persist values only to approved storage locations
- avoid embedding mutable business values directly in code

Examples of variable data that must not be hardcoded:

- activation limits
- plan quotas
- offer entitlements
- feature flags intended to vary by plan or tenant
- product-to-offer runtime values expected to change over time

---

## Official Source Mapping Requirement

Before meaningful feature implementation, create or update a source map in the feature package.

Minimum source map fields:

- variable name
- authoritative source system
- storage location (table/option/endpoint)
- read path (class/function)
- write/sync path (class/function)
- fallback policy
- stale-data detection signal

Recommended location:

- specs/app-features/[feature]/testing.md
- or specs/app-features/[feature]/spec.md when no testing matrix exists yet

---

## Dummy Data Rule

If dummy data is needed for development or testing:

- write dummy values into the same official storage shape used by production flows
- do not inject dummy runtime values as hardcoded constants in shipped code paths
- mark dummy records with explicit non-production markers when practical

---

## Fallback Rule

Fallback values are allowed only when all conditions are true:

- authoritative source is unavailable for a bounded reason
- fallback is documented in feature source map
- fallback behavior is observable in logs/diagnostics
- fallback does not silently override known authoritative values

---

## Waiver Rule

Any exception must include:

- inline waiver marker: DATA_SOURCE_WAIVER
- short reason
- expiration or removal condition
- reference to feature task or decision note

Waivers are temporary and should be removed after source integration is ready.

---

## Validation Gate

Changed-file validation should include data-source discipline checks.

Required outcome before commit/push:

- no new hardcoded variable-data patterns in runtime code
- or explicit, documented waiver markers for temporary exceptions

---

## Legacy Code Baseline Rule

For repositories or feature areas that predate this protocol:

- run a full baseline scan using `scripts/check-data-source-baseline.sh`
- store approved historical findings in `specs/data-source-baseline-allowlist.txt`
- treat this baseline as visible technical debt, not as proof of correctness
- fail validation on new baseline deltas until fixed or intentionally waived

This keeps pre-existing risk observable while preventing silent growth of hardcoded variable data.

---

## End