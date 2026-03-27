---
title: IDE Context Compaction
type: protocol
status: active
completion: 100
priority: high
authority: primary
intent-scope: planning,implementation,debugging,maintenance,review
phase: setup
last-reviewed: 2026-03-11
current-focus: keep active IDE sessions compact, restartable, and grounded in durable repo memory
next-focus: make compaction a normal handoff habit rather than a rescue step when sessions are already noisy
ide-context-token-estimate: 1445
token-estimate-method: approx-chars-div-4
session-notes: >
  This document defines the repository's intentional context-compaction workflow.
  It turns active session memory into small durable files so the next IDE session can
  restart from high-signal state instead of from a long noisy chat transcript.
related-files:
  - ../README-FIRST.md
  - ../workspace-status.md
  - ../GIT-RELEASE-CONTEXT-HISTORY.md
  - ./IDE-PROMPT-CONTRACT.md
  - ./IDE-CONTEXT-OPERATIONS.md
  - ./app-features/README.md
  - ./_template/progress.md
---

# IDE Context Compaction

## Purpose

This document defines how IDE work should preserve durable progress without letting one long chat become the repository's de facto memory system.

The rule is simple:

- do not treat a long AI session as project memory
- periodically extract the durable signal into small repository files
- restart from that compacted state when the session gets noisy, heavy, or trajectory-damaged

## Why This Exists

LLM quality is governed heavily by the current context window.

As sessions fill with logs, corrections, dead ends, and repeated re-explanations, quality and trajectory tend to degrade.

Intentional compaction keeps context smaller, cleaner, and more restartable.

This repository prefers deliberate handoff state over transcript accumulation.

## Target Operating Range

When practical, keep effective context usage closer to a compact working band rather than near saturation.

Working guidance:

- compact early when a task is drifting into a noisy state
- compact before the session feels confused, repetitive, or correction-heavy
- prefer a fresh session built from compacted signal over continuing inside a degraded one

The practical target is not an exact percentage. The goal is to stay well clear of the dumb zone.

## The Three Compaction Horizons

### 1. Worktree Handoff

Use `/workspace-status.md` for local, branch-specific, worktree-specific handoff state.

Use it for:

- current end goal in this worktree
- current approach
- exact blocker
- what was tried
- what worked or failed
- next best step
- verification plan

This file is intentionally local and should remain uncommitted.

### 2. Feature Handoff

When work is clearly feature-scoped and spans multiple sessions, use:

- `/specs/app-features/[feature-name]/progress.md`

Use it only for active multi-session implementation handoff.

It is not a replacement for:

- `spec.md`
- `architecture.md`
- `tasks.md`
- `decisions.md`
- `STATUS.md`
- `feature-status.json`

Those remain canonical feature truth. `progress.md` is the active handoff layer.

### 3. Repo Continuity

Use `/GIT-RELEASE-CONTEXT-HISTORY.md` for short repo-wide semantic continuity only.

Do not use it for branch-local blockers or feature implementation scratch state.

## What To Capture

Capture the reasoning product, not the transcript.

Minimum durable signal:

- target outcome
- current understanding of the problem
- relevant files or components
- decisions already made
- what was tried
- what worked
- what failed or was rejected
- exact current blocker
- next best step
- how success will be verified

## What Not To Capture

Do not compact entire chat history.

Avoid storing:

- raw conversational back-and-forth
- giant terminal logs unless one exact excerpt is still operationally important
- repeated explanations of the same correction
- old dead ends that no longer affect the next step
- speculative branches that were already rejected

## When To Compact

Compact intentionally when one or more of these become true:

- the session has accumulated many corrections or false starts
- the same background is being re-explained repeatedly
- logs and archaeology are starting to dominate the useful state
- a meaningful milestone has been reached and the next session should restart cleanly
- branch handoff or user handoff is about to happen
- the task has narrowed to a specific blocker and the old conversation is now mostly noise

Compaction is a normal operating step, not only an emergency cleanup step.

## File Placement Rules

### `/workspace-status.md`

Use this when the state is primarily about:

- the current worktree
- current runtime mapping
- branch purpose
- current local blocker
- next local execution step

### `/specs/app-features/[feature-name]/progress.md`

Use this when the state is primarily about:

- one App-facing feature
- one implementation slice
- one debugging thread inside a feature
- one multi-session feature handoff

Create it only when the work actually needs it.

Do not create one preemptively for every feature package.

## Read Order Rule

When resuming work:

1. read `README-FIRST.md`
2. identify mode and feature
3. read `/workspace-status.md` if the task is worktree-local and the file is relevant
4. read target feature `progress.md` if it exists and the task is feature-local
5. then load only the minimum canonical docs needed for the task

## Compaction Quality Rule

A good compacted file should let the next session continue with minimal re-explanation.

If the next session still needs the whole old conversation, the compaction was too transcript-like or too vague.

## Final Rule

Do not let context grow by inertia.

Curate it.

Restart from durable signal whenever that is cleaner than carrying the full conversation forward.