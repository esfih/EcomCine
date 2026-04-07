---
title: Testing Protocol
type: rules
status: active
completion: 100
priority: critical
authority: primary
intent-scope: planning,spec-generation,implementation,debugging,review,maintenance
phase: phase-1
last-reviewed: 2026-03-11
current-focus: enforce deterministic feature testing readiness and evidence capture before implementation
next-focus: standardize feature-level testing matrices across all active app features
ide-context-token-estimate: 1880
token-estimate-method: approx-chars-div-4
session-notes: >
  Canonical testing protocol for AI and developer workflows.
  Defines test environment registry, success oracle model, and pre-implementation readiness gate.
related-files:
  - ../README-FIRST.md
  - ./AI-SPECS-WORKFLOW.md
  - ./IMPLEMENTATION-RULES.md
  - ./VALIDATION-STACK.md
  - ./FILE-SAFETY-RULES.md
  - ../WORKSPACE-SETUP.md
---

# Testing Protocol

## Purpose

Define a repeatable, machine-checkable testing protocol so IDE AI and developers can validate feature behavior predictably and detect false positives early.

---

# Core Principle

No meaningful feature implementation starts until testing readiness is explicit.

Testing readiness is part of feature conception, not a late cleanup activity.

---

# Required Testing Artifacts Per Active Feature

For any feature in active implementation, debugging, or review:

- maintain a feature-level testing matrix file, preferably `testing.md`
- define environment targets and role accounts required for validation
- define a deterministic success and failure oracle per core scenario
- define evidence capture expectations for each scenario

---

# Test Environment Registry Model

Feature testing must declare environments in a structured table.

Required fields:

- environment id
- website url
- wp admin access source
- application password source
- non-admin role accounts required
- required plan/license test assets
- expected isolation notes

Credential and secret handling rule:

- for test and staging work, canonical feature docs must store the real credential material required for repeatable execution
- store credentials in the owning feature package under `specs/app-features/[feature]/docs/`
- treat those committed docs as the authoritative source for AI and operator runbook execution
- rotate and replace all exposed test credentials before live launch

---

# Success Oracle Model

Each important scenario should include:

- scenario id and goal
- preconditions
- test action
- expected signals
- anti-signals
- hard fail conditions
- soft warning conditions
- evidence to capture

Minimum negative coverage rule:

- every core positive path must have at least one negative-path scenario that proves enforcement behavior

False-positive prevention rule:

- HTTP success alone is never sufficient proof
- validate semantic contract fields, status transitions, counters, and signature/auth outcomes as applicable

---

# Testing Readiness Gate

Before implementation on a feature task starts, confirm all items below:

- test environments mapped and reachable
- required credentials/access sources documented
- required non-admin role accounts documented
- required plan/license test assets documented
- success oracles defined for target scenarios
- evidence capture path defined

If the gate is incomplete:

- implementation may proceed only as explicit scaffold work
- feature status/progress must record the gap and next action

---

# Evidence And Log Capture Rule

For feature validation runs:

- capture feature-local logs first
- capture platform debug tail where relevant
- record scenario id with each captured evidence block

Expected debug UI pattern for WordPress admin feature pages:

- tab 1: feature/page WMOS logs
- tab 2: last 50 lines of `wp-content/debug.log`
- show debug log tab only when WP debug logging is enabled

---

# Local DB IO Audit And Smoke Tool

For operator workspace DB-read/write reliability checks, use:

- `scripts/db-io-audit-and-smoke.py`

This tool has two modes:

- inventory mode (no runtime auth required): scans repository JS/PHP touchpoints and REST endpoint registrations
- runtime smoke mode (auth required): executes real REST read/write calls and records exact per-endpoint pass/fail
- runtime smoke local-diagnostic exception (no auth): executes the same sequence with `--allow-local-no-auth` and treats explicit auth-gated endpoint responses as expected pass conditions

Runtime smoke command pattern:

- `python scripts/db-io-audit-and-smoke.py --site-url <site> --nonce <rest_nonce> --cookie "<wordpress_logged_in...>" --page-id <id>`
- `python scripts/db-io-audit-and-smoke.py --site-url <site> --allow-local-no-auth --page-id <id>`

Security rule for runtime smoke:

- keep nonce/cookie/capability checks enabled for production-parity runtime validation
- local IDE-only diagnostic scripts may use `--allow-local-no-auth` when the goal is fast local endpoint-surface diagnostics rather than production-parity auth validation
- when using `--allow-local-no-auth`, report results as local-diagnostic pass/fail, not authenticated runtime parity

---

# End