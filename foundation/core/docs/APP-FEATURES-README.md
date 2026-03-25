---
title: App Feature Specs Registry
type: registry-guide
status: active
completion: 60
priority: high
authority: primary
intent-scope: workspace-setup,planning,spec-generation,implementation,review,maintenance
phase: phase-1
last-reviewed: 2026-03-10
current-focus: centralize App-facing feature packages and early-capture rules under one canonical folder
next-focus: expand the mapped inventory until specs and App feature presence align fully while adding clean feature-local handoff notes only when needed
ide-context-token-estimate: 859
token-estimate-method: approx-chars-div-4
session-notes: >
  Created after deciding that App-facing feature specs should no longer live directly
  under /specs and should instead be grouped under /specs/app-features.
related-files:
  - ../../README-FIRST.md
  - ../FEATURE-REQUEST.md
  - ../AI-SPECS-WORKFLOW.md
  - ./feature-inventory.json
  - ../OFFICIAL-ABBREVIATIONS.md
---

# App Feature Specs Registry

## Purpose

This folder is the canonical home for App-facing feature packages.

It exists to make feature discovery, status checks, and early capture predictable for both the IDE developer and IDE AI.

## Folder Rule

Each App-facing feature or major subsystem should live in:

- `/specs/app-features/[feature-name]/`

with the standard package files:

- `spec.md`
- `architecture.md`
- `tasks.md`
- `decisions.md`
- `task-graph.json`
- `STATUS.md`
- `feature-status.json`

Recommended when active implementation or debugging is underway:

- `testing.md` (feature-level test environment registry and success/failure oracle)

Optional active-work handoff file:

- `progress.md`

Use `progress.md` only when work on that feature spans multiple sessions or needs a compact active handoff.

It is not a replacement for status, tasks, decisions, or the core spec files.

## Inventory Rule

The machine-readable index for this folder is:

- `/specs/app-features/feature-inventory.json`

That file should state whether the current map is complete or still partial.

## Pre-Capture Rule

If IDE AI notices an App feature or major UI subsystem in code or discussion that does not yet exist here:

1. create a new feature folder immediately when practical
2. fill the package minimally using the template
3. mark the package honestly as early capture, partial, or planning
4. add a capture timestamp
5. add or update the feature entry in `feature-inventory.json`

## Reminder Rule

When the App feature map is known to be incomplete and that incompleteness may reduce IDE AI efficiency, keep the gap visible through the reminder section in `README-FIRST.md` until the mapping is complete.

## Feature Progress Rule

If a feature is under active multi-session implementation, debugging, or maintenance and the current state would be expensive to reconstruct from chat history alone:

1. create or update `/specs/app-features/[feature-name]/progress.md`
2. keep it short and blocker-oriented
3. capture durable signal rather than transcript history
4. delete or shrink stale sections instead of appending indefinitely

## Feature Testing Rule

For features in active implementation, debugging, or review:

1. define or update `testing.md` before meaningful code work
2. capture environment IDs, role accounts, and plan/license test assets needed for validation
3. define deterministic success and failure oracles for the next slice
4. include evidence expectations that can detect false positives