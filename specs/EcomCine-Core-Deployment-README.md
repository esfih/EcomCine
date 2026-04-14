---
title: EcomCine Core Deployment README
type: workflow
status: active
completion: 100
priority: critical
authority: primary
intent-scope: planning,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: serve as the single handoff file for starting the deployment and implementation phase
next-focus: guide the first execution session through the approved Wave 1 packet set
ide-context-token-estimate: 1900
token-estimate-method: approx-chars-div-4
session-notes: >
  This file closes the planning and preparation phase for the EcomCine Core rebuild
  and acts as the future-session handoff entry point for execution.
known-issues:
  - Planning is closed at the architecture and deployment-prep level, but implementation has not started.
  - Wave 1 is approved only within the constraints recorded here and in the linked packet docs.
do-not-repeat:
  - Do not reopen closed architecture decisions during a routine implementation session.
  - Do not start with checkout, bookings, or broad template rewrites before the approved Wave 1 slice is in motion.
related-files:
  - ./README-FIRST.md
  - ./specs/EcomCine-Core-Rebuild-Blueprint.md
  - ./specs/EcomCine-Core-Decision-Log.md
  - ./specs/EcomCine-Core-Subsystem-Ownership-Matrix.md
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md
  - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
  - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
  - ./specs/EcomCine-Core-Wave-1-Implementation-Advisory.md
---

# EcomCine Core Deployment README

## Planning Closure

Planning and preparation are now sufficiently closed to start the deployment and implementation phase.

Decision:

- `GO` for Wave 1 execution

Why this is enough:

1. The canonical public route decision is closed.
2. The canonical Listing storage direction is closed.
3. V1 Listing type scope is closed.
4. Core Only CTA posture is closed.
5. Adapter posture is closed.
6. Tier 1 commerce scope is closed.
7. Capability-pack and external packaging structure are closed.
8. Wave 1 cutover sequencing, feature-flag planning, parity criteria, and rollback criteria now exist as explicit packet docs.

What is still intentionally not closed:

- detailed implementation design inside each code file
- lower-priority post-Wave-1 expansion work
- Tier 2 adapter expansion

Those are execution concerns, not blockers to start.

---

## Approved Start Scope

The first execution session should work only on Wave 1.

Start with:

1. Packet 1 - contract freeze confirmation in code-facing terms
2. Packet 2 - route and context shadow path
3. Packet 3 - Listing storage and ownership freeze

Do not start with:

1. checkout redesign
2. booking redesign
3. marketplace behavior
4. large template rewrites outside the first vertical slice

---

## Required Reading Order

In a future session, the execution agent should read these files in this order:

1. `README-FIRST.md`
2. `specs/EcomCine-Core-Deployment-README.md`
3. `specs/EcomCine-Core-Wave-1-Implementation-Advisory.md` ← read before Blueprint; contains optimized execution sequence and gap fixes
4. `specs/EcomCine-Core-Rebuild-Blueprint.md`
5. `specs/EcomCine-Core-Decision-Log.md` ← now exists; all 7 closed decisions recorded
6. `specs/EcomCine-Core-Subsystem-Ownership-Matrix.md`
7. `specs/EcomCine-Core-Wave-1-Cutover-Plan.md`
8. `specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md`
9. `specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md`
10. `specs/EcomCine-Core-Wave-1-Rollback-Checklist.md`

Execution note:

- if the Wave 1 Implementation Advisory refines or narrows execution sequencing, follow the advisory for the first implementation pass
- treat the advisory as the execution-optimization layer on top of the approved planning packet set, not as a reopening of closed architecture decisions

---

## Future-Session Prompt

Use this prompt to start the actual deployment and implementation phase in a future session:

```text
Read README-FIRST.md first, then read specs/EcomCine-Core-Deployment-README.md and follow it as the execution contract.

We are now starting the implementation/deployment phase of the EcomCine Core rebuild.

Constraints:
- Do not reopen closed planning decisions unless you find a hard blocker.
- Work only in the canonical source under ecomcine/.
- Follow specs/EcomCine-Core-Wave-1-Implementation-Advisory.md before applying the broader Wave 1 cutover plan where sequencing detail differs.
- Follow the closed decisions in specs/EcomCine-Core-Decision-Log.md.
- Follow the scope and sequencing in specs/EcomCine-Core-Wave-1-Cutover-Plan.md.
- Follow the gates in specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md, specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md, and specs/EcomCine-Core-Wave-1-Rollback-Checklist.md.
- Start with Wave 1 only.
- Do not start with checkout, booking redesign, or marketplace work.

Execution goal:
Implement the first approved Wave 1 slice for Listing sovereignty: canonical route authority, Listing authority, and the first query-owned directory slice, using feature-gated rollout and explicit rollback paths.

Before editing code, summarize the exact Wave 1 packet you are starting, the files you expect to touch, the parity checks you will use, and the rollback gate you will preserve.
```

---

## Operator Note

If you want one file to hand to a future session, use this file:

- `specs/EcomCine-Core-Deployment-README.md`

That file plus `README-FIRST.md` is the correct restart point.