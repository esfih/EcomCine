---
title: IDE Prompt Contract
type: protocol
status: active
completion: 100
priority: high
authority: primary
intent-scope: workspace-setup,planning,implementation,maintenance
phase: setup
last-reviewed: 2026-03-11
current-focus: keep one small stable permanent IDE prompt contract across supported IDEs
next-focus: keep prompt routing rules small while preserving parity across tools
ide-context-token-estimate: 1530
token-estimate-method: approx-chars-div-4
session-notes: >
  This document holds the deeper permanent-prompt rules that were previously mixed into
  WORKSPACE-SETUP.md. It exists so setup guidance can stay focused on environment and
  runtime verification.
related-files:
  - ../README-FIRST.md
  - ../.github/copilot-instructions.md
  - ./IDE-CONTEXT-COMPACTION.md
  - ./OFFICIAL-ABBREVIATIONS.md
  - ./app-features/README.md
  - ./IDE-CONTEXT-BUDGET.json
---

# IDE Prompt Contract

## Purpose

This document defines the permanent IDE-side prompt contract for the repository.

Its job is to keep the always-on prompt small, stable, and focused on discovery rather than loading large policy text up front.

## Canonical Source Of Truth

Use `/.github/copilot-instructions.md` as the canonical repository copy of the minimal permanent prompt.

If an IDE cannot read that file directly, mirror its content into the IDE's workspace custom instructions or system prompt field without inventing a second repository-specific variant.

## Minimum Contract

The permanent IDE prompt should enforce these non-negotiable rules:

- read `README-FIRST.md` first
- load only the minimum additional canonical files needed for the current mode
- treat `/specs` and `/specs/app-features/` as canonical repository context
- use `/specs/OFFICIAL-ABBREVIATIONS.md` when short names or SKUs matter
- check `STATUS.md` and `feature-status.json` before meaningful feature work
- early-capture newly discovered unmapped App-facing features into `/specs/app-features/`
- compact noisy session state into `/workspace-status.md` or feature-local `progress.md` instead of letting one long chat become project memory
- default to DB-first state for App workflows

The contract should route into canonical docs. It should not duplicate their full content.

## Prompt Shape Rule

Keep the permanent prompt:

- short
- stable across sessions
- focused on routing and hard constraints
- free of feature-by-feature detail

Do not copy large workflow documents into the system prompt.

## Supported IDE Pattern

### VS Code With GitHub Copilot

Preferred setup:

1. keep the canonical prompt in `/.github/copilot-instructions.md`
2. let the workspace consume that file directly when available
3. update the repo file first when the contract changes

### OpenAI Codex

Preferred setup:

1. use `/.github/copilot-instructions.md` as the source of truth
2. mirror the same minimal text into workspace instructions when direct file consumption is unavailable
3. keep wording aligned unless the tool requires a format constraint

### Google Antigravity

Preferred setup:

1. use `/.github/copilot-instructions.md` as the source of truth
2. mirror the same minimal text into workspace instructions or system prompt settings when needed
3. keep tool-specific additions out of the shared contract unless they are repository-wide rules

## Prompt Parity Maintenance

When the permanent prompt changes:

1. update `/.github/copilot-instructions.md`
2. update any IDE that mirrors it manually
3. avoid tool-specific wording drift unless a formatting constraint forces it

Prompt parity matters more than tool-specific phrasing.

## Personal Global Reinforcement

For belt-and-suspenders reliability, the IDE developer may also keep a short personal global Copilot reinforcement prompt at the user level.

Use this when:

- starting chats in other repositories that do not yet have their own repo instruction file
- reinforcing repository pickup when tool behavior is inconsistent
- keeping a small repeated runtime baseline visible even when the repository router is not freshly loaded

Recommended file format for this repository:

```md
---
description: Global reinforcement prompt for repository-aware coding chats in VS Code.
---
Read README-FIRST.md first for every meaningful response. Follow ./.github/copilot-instructions.md. Treat the runtime baseline as always-on: Git Bash by default, PowerShell only for Windows-specific tasks, one host Python 3 interpreter only, no repo-local .venv unless the repo later explicitly requires it, and prefer host tools over WSL or container entry unless the task explicitly requires a different runtime. Treat /specs and /specs/app-features as canonical and load only the minimum files needed for the current task.
```

This prompt is reinforcement only.

The canonical project prompt remains `/.github/copilot-instructions.md`.

Formatting rule:

- use a valid `.instructions.md` file
- keep only the frontmatter block plus the prompt body
- do not leave generator placeholder text or template comments in the file

## Where To Put It

For VS Code with GitHub Copilot, the best place is user-level Copilot custom instructions so the reminder is applied across new chats without manual pasting every time.

Practical paths:

1. open the Chat view in VS Code
2. open the Configure Chat gear menu
3. use the custom instructions or Chat Customizations editor entry
4. add the prompt as a user-level always-on instruction
5. if a file is created for you, replace the template with the full valid format shown above

Alternative fallback:

- save it as your own reusable note or snippet and paste it into the first turn of a chat when needed

Use the user-level prompt as a backup layer, not as a replacement for repository-scoped instructions.

## Budget Awareness

Use `/specs/IDE-CONTEXT-BUDGET.json` when deciding whether a new always-on rule belongs in the permanent prompt or should stay in a just-in-time canonical file.

## Final Rule

The permanent prompt is a discovery ramp.

If a rule only matters in one mode or one feature, it belongs in a canonical document, not in the always-on prompt.