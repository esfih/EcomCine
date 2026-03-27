---
title: File Safety Rules
type: rules
status: active
completion: 100
priority: critical
authority: primary
intent-scope: implementation,debugging,maintenance,refactor
phase: phase-1
last-reviewed: 2026-03-10
current-focus: keep edits patch-first, validated, and resilient against corruption of large stable files
next-focus: align risky-file replacement guidance with repository helper scripts
ide-context-token-estimate: 2104
token-estimate-method: approx-chars-div-4
session-notes: >
	This file defines repository-safe editing behavior for AI and human contributors.
related-files:
	- ../README-FIRST.md
	- ./IMPLEMENTATION-RULES.md
	- ./TERMINAL-RULES.md
	- ./VALIDATION-STACK.md
---

# File Safety Rules

## Purpose

This document defines how files must be created, modified, replaced, moved, or deleted inside this repository.

Its goal is to reduce:

- accidental corruption
- broken large-file rewrites
- partial replacements
- encoding damage
- syntax regressions caused by file handling
- unnecessary debugging caused by unsafe edit operations

This file is especially important in:
- implementation mode
- debugging mode
- refactor mode
- maintenance mode

Any task involving large files, replacements, deletes, or generated output should load this file early.

---

# Core Principle

Patch first.

Replace last.

Large delete-and-recreate operations are high risk and should be avoided unless truly necessary.

---

# Safe Edit Hierarchy

Preferred order of file modification methods:

1. minimal in-place patch
2. targeted block replacement
3. write validated .new file then replace atomically
4. full file rewrite only when explicitly justified

Do not jump directly to full replacement.

---

# Large File Rule

## Never casually replace a large stable file

If a file is already functional and large, do not delete and regenerate it unless:

- task explicitly requires architectural rewrite
- patching is clearly impractical
- replacement has been validated before swap

---

## Large file edits must be staged

For risky files:

1. create a temporary replacement file
2. validate syntax
3. compare diff
4. only then replace original

Recommended temporary names:

- filename.new
- filename.tmp
- filename.bak

---

# Backup Rule

Before risky replacement of important files, create a local backup.

Examples:

- file.php.bak
- feature-panel.js.bak

Especially for:

- bootstrap files
- loader files
- REST controllers
- shared services
- complex UI scripts

---

# Atomic Replacement Rule

When possible:

- write new content to a separate file
- validate it
- replace the original in one final step

Do not partially stream-edit important files with risky broad operations.

---

# Delete Rule

## No blind delete-and-recreate workflow

Do not delete a file first and plan to recreate it afterward unless absolutely necessary.

This is a common cause of corruption and lost stable logic.

---

## No mass delete during feature implementation

Feature tasks should not delete many files unless the task is explicitly cleanup-focused.

---

# Move and Rename Rule

Renames and moves must be treated as architectural changes.

Do not casually move files because a new structure feels cleaner.

Only rename or move when:

- spec requires it
- architecture requires it
- impact is reviewed

---

# Multi-File Edit Rule

If many files are being changed:

- ensure each change has a clear purpose
- avoid mixing refactor and bugfix and formatting in one pass
- validate each risky file type before moving on

---

# Generated File Rule

If generating files through scripts or AI:

- declare which files are expected
- validate each output
- avoid silent overwrite of hand-maintained files

---

# Credential Material Rule

When documenting test environments, access paths, and test assets:

- store real test credential material in the owning feature package docs under `specs/app-features/[feature]/docs/`
- keep one authoritative credential file per feature and reference it from testing and runbook docs
- allow mirrors only when a scenario requires direct inline values in a canonical test matrix
- rotate and replace all exposed test credentials before live launch

If credential values are missing from the feature docs, mark the scenario as blocked until the canonical credential file is updated.

---

# Output Folder Rule

## `output/` is read-only repository context

The top-level `output/` folder is not a functional source folder.

Its files must be treated as read-only context artifacts.

Do not modify files under `output/` unless the user explicitly asks for that exact action.

Do not treat `output/` files as implementation targets during normal coding, debugging, refactor, or maintenance work.

---

## `output/` files are contextual snapshots, not canonical truth

Files inside `output/` may contain extracted rendered HTML, diagnostics, or other captured page/state artifacts shared by the user for AI context.

These files are useful for situational understanding, such as:

- debugging a specific rendered page state
- understanding DOM shape or page composition
- reviewing captured diagnostics
- providing richer context for an AI session

But they are not guaranteed to be a live reflection of the current site or current application state.

Therefore they must not be treated as permanent truth, architectural truth, or authoritative runtime truth.

---

## Relevance rule for `output/`

`output/` files become relevant only when one of these is true:

- the user explicitly asks the AI to read or inspect a specific `output/` file
- the active task is clearly about a captured artifact stored in `output/`
- the user refers to an extracted page snapshot, rendered HTML source, or diagnostic artifact that lives in `output/`

If none of those apply, AI should not proactively treat `output/` as required reading.

---

## Implementation safety rule for `output/`

Do not infer code changes directly from `output/` alone.

Use `output/` only as supporting situational evidence.

Any actual implementation change must still be grounded in the canonical source files, current runtime mapping, and active repository architecture.

---

# Encoding Preservation Rule

When editing files, preserve repository encoding rules.

Do not introduce:

- UTF-8 BOM unexpectedly
- mixed line endings
- trailing whitespace damage
- missing final newline
- accidental tab/space drift

---

# Syntax Validation Rule

After editing code files, validate before considering the edit complete.

Examples:

- PHP syntax
- JS syntax
- JSON parse validity
- CSS sanity where applicable

A file is not considered safely edited until syntax is checked.

---

# Partial Corruption Awareness

If a system suddenly reports many strange errors after a file operation, check first for:

- truncated file content
- duplicated fragments
- missing closing brace
- missing PHP closing structure
- bad encoding
- line ending damage
- BOM before output
- malformed JSON

Do not immediately assume the underlying logic is wrong.

---

# Replace vs Patch Rule For AI

AI must prefer:

small targeted edits

over:

full file regeneration

unless the task explicitly calls for replacement.

---

# Stable Code Preservation Rule

Existing stable code is valuable.

Do not destroy working sections just because they are stylistically imperfect.

Correctness and continuity are more important than elegance.

---

# Formatting Sweep Rule

Do not combine:

- major formatting cleanup
- logic change
- feature addition

in the same edit pass.

That makes debugging harder and increases corruption risk.

---

# Temporary File Rule

Temporary files used during replacement must be removed only after:

- validation passes
- final replacement is confirmed
- no useful backup is still needed

---

# Git Diff Inspection Rule

Before finalizing risky file operations, inspect the diff.

Look specifically for:

- unexpectedly large deletions
- encoding artifacts
- duplicated blocks
- missing endings
- broad unrelated changes

---

# Safe Recovery Rule

If a replacement causes failure:

1. stop further edits
2. restore previous file or backup
3. confirm baseline works again
4. then retry with smaller patch approach

Do not continue stacking edits on top of a corrupted base.

---

# Final Principle

The safest file operation is the smallest one that solves the task.

Preserve working code whenever possible.

---

# End