---
title: Feature Lifecycle Rules
type: root-guidance
status: active
completion: 100
priority: high
authority: primary
intent-scope: planning,spec-generation,implementation,maintenance,extension
last-reviewed: 2026-03-10
next-focus: enforce STATUS.md and feature-status.json on all active features
ide-context-token-estimate: 1084
token-estimate-method: approx-chars-div-4
session-notes: >
  This file defines standardized lifecycle states and completion semantics for features.
  It prevents AI from rebuilding completed work or ignoring maintenance/debug context.
related-files:
  - ./README-FIRST.md
  - ./METADATA-HEADER-TEMPLATE.md
  - ./specs/AI-SPECS-WORKFLOW.md
  - ./specs/FEATURE-REQUEST.md
---

# Feature Lifecycle

## Purpose

This file defines the lifecycle states and completion semantics for feature work in this repository.

It exists to help humans and AI models understand:

- whether a feature is draft or active
- how complete it is
- whether the current work is planning, implementation, maintenance, or extension
- what should happen next
- what should not be repeated

---

# Required Feature Status Files

Each meaningful feature folder should include:

- STATUS.md
- feature-status.json

These files are the first source of truth for the feature’s current state.

---

# Standard Lifecycle States

Use one of these values for feature status:

- draft
- planning
- active
- blocked
- review
- stable
- maintenance
- completed
- deprecated

Do not invent too many custom states.

---

# Meaning of Each State

## draft
Early idea only. Not implementation-ready.

## planning
Actively being framed or specified.

## active
Implementation in progress.

## blocked
Progress halted by missing dependency, unresolved decision, or environment issue.

## review
Implementation exists and is being checked.

## stable
Usable and accepted for current scope, but still open to normal development.

## maintenance
Feature is mostly done; work is limited to bugfixes, diagnostics, compatibility, and minor polish.

## completed
Feature is 100% complete against a clearly named reference scope or dated requirement set.

## deprecated
Feature or spec is no longer current and should not drive new work.

---

# Completion Percentage Rule

Use completion as an integer percentage.

Examples:
- 0
- 25
- 50
- 75
- 90
- 100

Completion must refer to a specific known scope.

A feature cannot honestly be 100% complete unless the reference scope is explicit.

---

# Completion Reference Rule

If a feature is marked completed, state what it is complete against.

Examples:

- spec-v1
- phase-1 scope dated 2026-03-10
- requirements set from issue X
- milestone M1

This prevents false “100% complete” labels.

---

# Recommended Extra Fields

Feature status should ideally include:

- status
- completion
- phase
- priority
- authority
- last-reviewed
- current-focus
- known-issues
- next-primary-dev
- next-secondary-dev
- maintenance-focus
- do-not-repeat

---

# Phase Rule

Recommended phase labels:

- setup
- phase-1
- phase-2
- phase-3
- maintenance

These help separate core delivery from future expansion.

---

# Next Work Semantics

## next-primary-dev
The next highest-value work that should probably happen soon.

## next-secondary-dev
Optional future improvements or user-facing expansion ideas.

## maintenance-focus
What to watch if the feature is already stable or completed.

## do-not-repeat
Past mistakes or already rejected directions that the model should not repeat.

---

# Maintenance Rule

If a feature is in maintenance or completed state, new work should default to:

- bugfix
- compatibility
- diagnostics
- polish
- safe extension

not large redesign.

---

# Extension Rule

If the user wants new capabilities on a completed feature, treat it as:

- extension
- phase-2+
- secondary development

Do not silently reopen phase-1 architecture unless required.

---

# Former Session Notes Rule

Feature status should include brief continuity notes from prior sessions when useful.

This helps the next model know:

- what was already done
- where attention is needed
- what confusion to avoid

Keep these notes concise and operational.

---

# Final Principle

Feature status is part of the architecture of AI collaboration.

A feature without clear lifecycle state invites wasted work.

---

# End