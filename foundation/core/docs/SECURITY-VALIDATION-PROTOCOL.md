---
title: Security Validation Protocol
type: rules
status: active
completion: 100
priority: critical
authority: primary
intent-scope: implementation,debugging,review,maintenance,release
phase: phase-1
last-reviewed: 2026-03-11
current-focus: enforce security-first validation gates for every feature update before commit or push
next-focus: expand automated checks and reduce false positives while keeping strict security posture
related-files:
  - ../README-FIRST.md
  - ./IMPLEMENTATION-RULES.md
  - ./VALIDATION-STACK.md
  - ../WORKSPACE-SETUP.md
  - ../scripts/security-validate-changed.sh
---

# Security Validation Protocol

## Purpose

Define mandatory security validation for all feature development and updates.

No feature update should be committed or pushed unless related security validation passes.

---

## Core Gate

Security validation is a hard gate before git integration.

Required outcomes:

- changed-file validation passes
- security validation passes
- security validation evidence exists and matches the current changed file set
- unresolved security findings are documented and explicitly waived by user decision

Without these outcomes, commit/push flow is incomplete.

---

## Required Security Checks Per Change

At minimum, validate:

- credential/secret/token exposure in code, logs, and docs
- public and authenticated entry points (forms, REST, AJAX)
- capability checks and nonce/CSRF protection for state-changing actions
- sanitization/escaping at input and output boundaries
- brute-force and abuse-prone paths where applicable
- SQL/query safety and script injection risk indicators

---

## Attack Surface Identification Requirement

For each meaningful feature/task update, identify attack surfaces touched by the change:

- anonymous/public endpoints
- logged-in user actions
- admin actions and settings writes
- webhook/remote-request paths
- debug/telemetry/logging paths

If attack surface changed, include security notes in feature docs (`testing.md`, `progress.md`, or runbook report).

---

## Tooling And Scripts

Mandatory scripts:

- `scripts/validate-changed.sh`
- `scripts/security-validate-changed.sh`

Hook-based enforcement:

- `.githooks/pre-commit` runs security validation for staged changes
- `.githooks/pre-push` runs changed-file validation plus security validation
- `scripts/require-security-validation.sh` refuses commit/push when validation evidence is missing, stale, or mismatched

Install hooks with:

- `powershell -ExecutionPolicy Bypass -File scripts/install-git-hooks.ps1`

---

## Reporting Rule

After non-security task output, AI must append a short security note with:

- found risks (if any)
- risk severity hint (high/medium/low)
- mitigation status

If none found, state "no material security finding observed" and list residual uncertainty if present.

---

## End
