---
title: Metadata Header Template
type: reference
status: active
completion: 100
priority: medium
authority: primary
intent-scope: planning,spec-generation,implementation,maintenance
phase: setup
last-reviewed: 2026-03-10
current-focus: keep repository and feature metadata headers minimal, consistent, and useful for AI routing
next-focus: align template files and major guidance docs to this schema
ide-context-token-estimate: 525
token-estimate-method: approx-chars-div-4
session-notes: >
  This template is the canonical metadata starter for repository guidance and feature package files.
related-files:
  - ./README-FIRST.md
  - ./FEATURE-LIFECYCLE.md
  - ./specs/AI-SPECS-WORKFLOW.md
---

# Metadata Header Template

Use this header at the top of important repository documents and feature-local files when helpful.

```md
---
title: [Document title]
type: [root-guidance | feature-spec | feature-architecture | feature-tasks | feature-status | feature-decisions | workflow | rules | reference]
status: [draft | planning | active | blocked | review | stable | maintenance | completed | deprecated]
completion: [0-100]
priority: [low | medium | high | critical]
authority: [primary | secondary | legacy | reference-only]
intent-scope: [all | workspace-setup | planning | spec-generation | implementation | debugging | review | maintenance | refactor | extension]
phase: [setup | phase-1 | phase-2 | phase-3 | maintenance]
last-reviewed: [YYYY-MM-DD]
current-focus: [short current attention area]
next-focus: [short next attention area]
ide-context-token-estimate: [integer approximate tokens or n/a]
token-estimate-method: [approx-chars-div-4 | measured-with-tool | n/a]
session-notes: >
  [brief summary from prior session if useful]
known-issues:
  - [issue 1]
  - [issue 2]
do-not-repeat:
  - [mistake or rejected path 1]
  - [mistake or rejected path 2]
related-files:
  - [path]
  - [path]
---
```

For IDE-side canonical guidance files, token estimates should also be tracked in `/specs/IDE-CONTEXT-BUDGET.json` so AI can reason about combined loading cost before adding more context.