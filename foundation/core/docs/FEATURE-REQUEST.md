---
title: Feature Request Protocol
type: workflow
status: active
completion: 100
priority: high
authority: primary
intent-scope: planning,spec-generation,extension
phase: phase-1
last-reviewed: 2026-03-10
current-focus: generate context-aware, status-aware, WordPress-realistic feature packages
next-focus: improve extension handling for stable/completed features while preserving compact active-work handoff when needed
ide-context-token-estimate: 2381
token-estimate-method: approx-chars-div-4
session-notes: >
  Updated to support lifecycle-aware feature generation and low-resource shared-hosting constraints.
related-files:
  - ../README-FIRST.md
  - ./OFFICIAL-ABBREVIATIONS.md
  - ./AI-SPECS-WORKFLOW.md
  - ../FEATURE-LIFECYCLE.md
  - ./ARCHITECTURE-MAP.md
  - ./app-features/README.md
  - ./app-features/feature-inventory.json
---

# Feature Request Protocol

## Purpose

This file defines how AI should transform a feature idea into a complete implementation-ready feature package for this repository.

It should be used after reading:

- README-FIRST.md

This file is primarily for:
- planning mode
- spec generation mode
- extension mode

---

# AI Mission

When a new feature request is provided, AI must:

1. identify whether the request is for a new feature, a refinement, or an extension
2. identify WordPress-specific operational constraints
3. detect hidden technical implications
4. break the work into safe phases
5. produce a complete feature package
6. avoid coding unless explicitly requested

---

# New Feature vs Extension Rule

Before generating specs, AI must determine:

- Is this a new feature?
- Is this a refinement of an active feature?
- Is this a phase-2+ extension of a stable/completed feature?

If the feature already exists, check:
- STATUS.md
- feature-status.json

Do not regenerate a feature from scratch if the correct task is extension or maintenance.

---

# Dual-AI Context Rule

This repository has two distinct AI context targets:

1. IDE AI context for developer-facing repository work
2. App AI context for the model(s) serving end users inside WebmasterOS

When capturing or relocating information, first decide which target it belongs to.

Rules:

- IDE AI context belongs in root guidance and canonical repository specs
- App AI context belongs in plugin prompt builders, blueprint/context builders, or feature-specific App documentation
- do not keep App-only behavior instructions in the IDE guidance stack unless they directly affect implementation of the App AI system

---

# Early Capture Rule

Because the specs architecture was introduced after significant App/plugin work already existed, stale docs or legacy notes may still contain valuable feature information.

When valuable feature information is found outside the canonical feature-spec structure:

1. check whether a feature folder already exists under `/specs/app-features/[feature-name]/`
2. if it does not exist, create an early-capture feature package there
3. mark it clearly as early capture only, not yet a verified final spec
4. record that the feature still needs code scan, status review, and a later developer discussion before being treated as authoritative

The purpose of early capture is preservation and routing, not false certainty.

---

# App Feature Inventory Rule

App-facing feature knowledge should not remain scattered.

The canonical feature map lives under `/specs/app-features/`.

Rules:

- each App-facing feature or major UI subsystem should have a canonical folder or an early-capture placeholder under `/specs/app-features/`
- `/specs/app-features/feature-inventory.json` should reflect whether the current map is complete or partial
- if AI notices an App feature in source code or discussion that is not yet captured, pre-capture it immediately before proceeding with normal feature work when practical
- if that gap may reduce IDE AI efficiency, keep a visible reminder in `README-FIRST.md` or the current workspace setup flow until the mapping is complete

---

# Official Abbreviation And SKU Rule

To reduce ambiguity in IDE-to-developer and IDE-to-IDE communication, App features and major UI subsystems may define:

- an official abbreviation
- a feature SKU or explicit short name

Rules:

- abbreviations and short names must be registered in `/specs/OFFICIAL-ABBREVIATIONS.md` before broad use
- if a feature already has a short explicit name such as `CSS-Mixer` or `TXT-Mixer`, no extra acronym is required
- feature packages should carry their canonical short identifiers in the spec schema
- if no official abbreviation exists yet, prefer the full canonical feature name over inventing ad hoc shorthand in active implementation work

---

# App-AI Check Rule

When stale or foreign material contains App-side behavior guidance, also inspect the current App AI context-building path.

Examples:

- prompt builders
- blueprint summaries
- context capture payloads
- feature-specific UI or agent docs

If the information is still useful, move it into the right App-side seam instead of leaving it only in stale notes.

---

# Deletion Confirmation Rule

After stale information has been safely captured into canonical specs and, when needed, into the App-side implementation/context path, ask the IDE user to confirm deletion of the original stale file or folder unless deletion was already explicitly requested.

---

# Active Handoff Rule

For feature work that is expected to span multiple sessions, debugging loops, or implementation handoffs, AI may also create or update:

- `/specs/app-features/[feature-name]/progress.md`

Use it only as an active handoff note.

It should capture durable signal such as:

- current narrow objective
- relevant files
- decisions already made
- what was tried
- what worked or failed
- exact blocker
- next best step
- verification plan

Do not use it as a second spec or a transcript dump.

---

# Required Output Files

For a new or majorly updated feature package, generate:

- spec.md
- architecture.md
- tasks.md
- decisions.md
- task-graph.json
- STATUS.md
- feature-status.json

inside:

/specs/app-features/[feature-name]/

---

# Mandatory Analysis Before Writing Specs

AI must identify:

## Functional Objective
What exact user value is delivered?

## WordPress Impact
Which layers are touched?

Examples:
- admin UI
- floating panel
- REST API
- PHP service layer
- hooks
- settings
- diagnostics
- compatibility logic
- client-environment adaptation

## Hosting Constraints
Assume final runtime may be:
- shared hosting
- low resources
- restricted access
- no SSH
- no WP-CLI

## Local vs Customer Runtime
Separate:
- local development capabilities
- customer runtime assumptions

## Existing Diagnostic Synergy
Check whether the feature should interact with the plugin’s diagnostics and health-check behavior.

## Safety Constraints
Avoid:
- breaking plugin stability
- unsafe file operations
- privilege escalation
- architecture drift
- unrealistic server assumptions

## State And Cache Constraints
Default to DB-first state.

Temporary browser or machine-local storage is allowed only when it creates real value that remains meaningful at roughly 100 active users or more and does not introduce stale-data risk on critical flows.

Do not rely on stale-prone cache layers for:

- login
- registration
- admin settings
- checkout or transaction decisions
- other live user decision points where correctness matters more than micro-optimization

---

# Output Rules

## spec.md must include
- goal
- problem solved
- scope
- non-scope
- official identifiers
- user stories
- acceptance criteria
- constraints
- risks
- future extensions

Official identifiers should include, when available:

- feature SKU or explicit short name
- official abbreviation or `n/a`
- discovery or capture status
- first capture timestamp

## architecture.md must include
- PHP classes
- JS modules
- REST endpoints
- hooks
- data storage
- security layer
- fallback logic
- shared-hosting compatibility
- customer runtime adaptation assumptions where relevant

## tasks.md must include
Tasks must be:
- small
- ordered
- executable independently

Each task should define:
- status
- owner
- dependencies
- files
- acceptance criteria

## decisions.md must include
At least one meaningful technical decision with reason and rejected alternative.

## task-graph.json must include
Dependencies only.

## STATUS.md and feature-status.json must include
- lifecycle status
- completion
- phase
- current focus
- known issues
- next work guidance

---

# AI Behavior Rules

## Do not over-engineer phase 1
Prefer the smallest stable version.

## Detect hidden prerequisites
If a feature implies prerequisites such as:
- nonce checks
- capabilities
- data schema
- UI mounting
- diagnostics hooks
then make them explicit.

## Respect existing plugin architecture
Do not assume blank-project freedom.

## Keep phase 1 deployable
Phase 1 should be meaningful and stable on its own.

## Stay realistic for WordPress
The feature must fit WordPress plugin realities and the target customer hosting context.

---

# Example Invocation

Read README-FIRST.md, FEATURE-REQUEST.md, ARCHITECTURE-MAP.md, and FEATURE-LIFECYCLE.md.

Generate a feature package for:
[Floating AI panel DJ-style CSS controls for selected WordPress element]

Return full spec package.
No code yet.

---

# Final Principle

AI must behave like a senior WordPress product architect operating under real hosting constraints, not like a generic idea expander.

---

# End