---
title: IDE Context Operations
type: protocol
status: active
completion: 100
priority: high
authority: primary
intent-scope: workspace-setup,maintenance,doc-authoring
phase: setup
last-reviewed: 2026-03-11
current-focus: keep IDE context metadata measurable, refreshable, and operationally discoverable
next-focus: keep the tracked file list and bundle estimates aligned with actual routing practice
ide-context-token-estimate: 1548
token-estimate-method: approx-chars-div-4
session-notes: >
  This document holds the context-budget and release-continuity operations that were
  previously mixed into WORKSPACE-SETUP.md. It exists so setup guidance stays focused on
  environment bootstrap rather than metadata maintenance.
related-files:
  - ../README-FIRST.md
  - ../GIT-RELEASE-CONTEXT-HISTORY.md
  - ./IDE-CONTEXT-COMPACTION.md
  - ./IDE-CONTEXT-BUDGET.json
  - ../scripts/update-ide-context-budget.py
  - ../scripts/install-git-hooks.ps1
---

# IDE Context Operations

## Purpose

This document defines how the repository tracks, refreshes, and uses IDE-side context metadata.

It also defines the short release-continuity layer that helps IDE sessions understand recent changes without reading full git history every time.

## Token Budget Rule

Use `/specs/IDE-CONTEXT-BUDGET.json` as the machine-readable source of truth for:

- approximate token cost per tracked IDE-side file
- approximate token cost per common loading bundle
- file loading patterns such as always-on, mode-specific, or feature-local-only

The current repository-standard method is approximate and lightweight.

Until a tokenizer-backed method is adopted, use the documented `approx-chars-div-4` method.

## Per-File Metadata Rule

Every tracked IDE-side context file should carry local token metadata.

- markdown files use `ide-context-token-estimate` and `token-estimate-method` in frontmatter
- json files use `ide_context_token_estimate` and `token_estimate_method` at top level

## Automatic Refresh Rule

Install the repository hooks so metadata refresh happens when repository state changes.

Setup command:

```powershell
./scripts/install-git-hooks.ps1
```

Current hook behavior:

- `post-merge` refreshes token metadata after local pull or merge completion
- `post-checkout` refreshes token metadata after branch or worktree checkout
- `post-commit` refreshes token metadata after local commit creation
- `pre-push` refreshes token metadata immediately before push because standard Git does not provide a local `post-push` hook

If refresh changes tracked files, review and commit those metadata updates as appropriate.

## Manual Refresh Entry Points

Use either of these when needed:

```powershell
python scripts/update-ide-context-budget.py
```

or the VS Code task:

- `Refresh IDE Context Budget`

## Workspace-Launch Automation

VS Code folder-open automation is configured through:

- `/.vscode/tasks.json`
- `/.vscode/settings.json`

Git lifecycle hooks plus workspace-open automation are the preferred baseline here.

Time-based scheduling is intentionally not the primary mechanism.

## Release Continuity Rule

Use `/GIT-RELEASE-CONTEXT-HISTORY.md` as the short semantic continuity file for recent pushes, commits, and releases.

Keep it short and operational.

Each meaningful entry should include:

- commit or tag reference
- one-line semantic summary
- key finding, bug source, or main challenge
- solution, mitigation, or chosen direction

Do not turn it into a long changelog.

Operational entrypoint:

- `scripts/global-sync-and-handover.sh` provides one command path for worktree sync audit plus continuity updates.
- run dry-run first, then run with `--apply` when ready to write feature progress and release history summaries.
- `scripts/takeover-pull.sh` provides the receiver-side path for approved drift cleanup, fast-forward pull, and setup validation.

## Receiver Drift Rule

Receiver-side takeover should treat GitHub as canonical only after local drift is classified.

Allowed automatic cleanup during takeover:

- `workspace-status.md` stays local-only and uncommitted
- `GIT-RELEASE-CONTEXT-HISTORY.md` may be restored on the receiver before pull
- canonical documents may be restored only when the local diff is limited to context-budget metadata drift such as `ide-context-token-estimate` or `approx_tokens`
- if the receiver runs `scripts/takeover-pull.sh --apply --refresh-context-budget`, the script may restore metadata-only refresh output after verification so the worktree remains clean

Blocked by default during takeover:

- staged changes
- unexpected tracked edits
- untracked non-ignored files

If a receiver workspace is intentionally disposable and GitHub should fully replace local state, use the explicit authoritative reset path rather than silently broadening normal cleanup rules.

## Context Compaction Rule

Use `/specs/IDE-CONTEXT-COMPACTION.md` for the durable session-handoff workflow.

Operational split:

- `/workspace-status.md` holds local worktree handoff state
- `/specs/app-features/[feature-name]/progress.md` holds feature-local active handoff state when needed
- `/GIT-RELEASE-CONTEXT-HISTORY.md` holds short repo-wide semantic continuity only

These files serve different horizons and should not collapse into one generic progress dump.

## Compaction Trigger Rule

Compact intentionally when:

- active context is getting noisy or repetitive
- the task has narrowed to one blocker and the earlier conversation is mostly residue
- a clean restart would be higher signal than continuing the same session
- a human or branch handoff is about to happen

Do not wait until the session is already degraded.

## When To Read This Doc

Read this file when:

- a canonical context document is added, removed, or restructured
- prompt weight or bundle size matters
- token metadata looks stale or inconsistent
- hook or workspace-open refresh behavior needs verification
- recent release continuity matters but a full git archaeology pass is unnecessary

## Final Rule

Context operations should stay measurable and boring.

If a canonical document is important enough to keep, it should also be visible to the budget and refresh system.