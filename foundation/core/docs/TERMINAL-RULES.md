---
title: Terminal Rules
type: rules
status: active
completion: 100
priority: high
authority: primary
intent-scope: workspace-setup,implementation,debugging,maintenance
phase: setup
last-reviewed: 2026-03-10
current-focus: keep terminal usage predictable, previewable, and safe across Windows local development
next-focus: align script-first command guidance with the repository starter pack
ide-context-token-estimate: 1956
token-estimate-method: approx-chars-div-4
session-notes: >
	This file favors Git Bash for repo automation and PowerShell for Windows-specific operations.
related-files:
	- ../README-FIRST.md
	- ../WORKSPACE-SETUP.md
	- ./FILE-SAFETY-RULES.md
	- ./VALIDATION-STACK.md
---

# Terminal Rules

## Purpose

This document defines how terminal commands must be executed for this repository.

Its goal is to reduce:

- quoting failures
- multiline command corruption
- shell-specific parsing issues
- accidental destructive commands
- wasted AI turns caused by terminal instability

This repository prefers predictable terminal behavior over clever shell usage.

This file is especially relevant in:
- workspace setup mode
- implementation mode
- debugging mode

It is not usually required for pure planning or spec-generation tasks.
---

# Preferred Shell Strategy

## Default shell for repository work

Preferred default shell:

Git Bash

Use Git Bash for most repository operations such as:

- git
- file inspection
- grep/find
- basic scripting
- npm commands
- composer commands
- php lint loops
- diff-oriented tasks

## Secondary shell

Use PowerShell only when the task is specifically Windows-oriented, such as:

- process management
- Windows services
- environment variables
- registry-related tasks
- Windows-specific filesystem behavior

## Rule

Do not switch shells casually during one task unless necessary.

State clearly which shell is being used when behavior may differ.

Default terminal discipline:

- use one shared Git Bash terminal session for ordinary repository work
- do not spawn extra bash terminals for short-lived commands such as `curl`, `grep`, `tail`, `wc`, or one-off `wp` inspection
- use separate background terminals only for true long-running processes such as dev servers, watchers, or streaming logs
- if a terminal becomes wedged, awaits input, or gets corrupted by quoting, clean up that shell first instead of opening more terminals as a workaround

---

# Deterministic Git Bash Startup Rule

If VS Code Git Bash terminals terminate at startup with errors like:

- `bash.exe '--login', '-i' terminated with exit code 1`

use the repository-safe Git Bash launch profile:

- `bash.exe --noprofile --norc`

Reason:

- `--login -i` can fail because of user-level shell startup files (`~/.bashrc`, `~/.bash_profile`, `~/.profile`)
- repository work should not depend on machine-specific interactive shell customizations

Repository expectation:

- workspace terminal default should point to the safe Git Bash profile
- automation terminals should also use the safe profile for deterministic script runs

---

# Git Bash Edge-Case Guidance

When login-shell startup fails:

1. run `scripts/check-devops-readiness.ps1` and inspect `Git Bash safe launch` and `Git Bash login launch`
2. if safe launch passes but login launch fails, keep using safe profile and fix user startup files separately
3. if both fail, repair Git for Windows installation path and PATH order

Common causes:

- `set -e` in user startup files with failing command
- missing binaries referenced from startup files
- invalid shell syntax in profile scripts
- startup scripts assuming WSL-only tools

AI behavior requirement:

- prefer safe profile commands first
- escalate to login-shell debugging only when user explicitly asks to debug shell startup files

---

# General Command Rules

## Prefer short safe commands

Prefer small commands with one purpose.

Avoid giant one-liners that combine:

- file edits
- validation
- deletion
- replacement
- git state changes

in one step.

---

## Quote paths consistently

Always quote paths that may contain:

- spaces
- parentheses
- special characters

Examples:

"c:/Users/Name/My Project/file.php"

"./path with spaces/file.js"

---

## Avoid inline multiline command blobs

Do not paste large multiline commands directly into the terminal unless they are extremely simple.

If command content includes:

- JSON
- regex
- nested quotes
- many arguments
- heredoc-like content
- long replacement text

then write it into a script file first.

---

## Prefer script files for complex terminal work

If terminal logic is more than a few simple commands, create a script file.

Examples:

- scripts/check-php.sh
- scripts/validate-all.sh
- scripts/patch-feature.sh
- scripts/find-bom.py

This reduces shell parsing errors and makes runs repeatable.

---

# PowerShell Safety Rules

## Avoid PowerShell for complex quoting tasks

Do not use PowerShell by default for commands containing:

- regex-heavy replacements
- many nested quotes
- JSON literals
- inline code injection
- multiline string transformations

These are frequent sources of accidental failure.

---

## If PowerShell must be used

Prefer:

- explicit variables
- one command per line
- script files
- clear path quoting

Avoid compact single-line PowerShell expressions for risky operations.

---

# Git Bash Safety Rules

## Prefer Bash for repeatable repo automation

When using Bash:

- use explicit quoted paths
- avoid destructive wildcards
- prefer scripts over long inline loops
- use clear exit checks after validation steps

---

## Do not assume GNU tools beyond what is actually available

If a script depends on tools such as:

- sed
- awk
- xargs
- find
- shellcheck

the script should state that clearly.

---

# Destructive Command Rules

## Never run destructive commands without inspection first

Before any command involving:

- rm
- mv over existing files
- bulk rename
- recursive replacement
- mass format
- overwrite redirection

perform a preview step first.

Examples of preview steps:

- list matching files
- show git diff
- print command target list
- write to .new file first

---

## No blind recursive deletes

Never run recursive delete commands casually.

Do not remove directories or file groups unless:

- the task explicitly requires it
- affected files are listed
- the deletion scope has been inspected first

---

## No chained destructive commands

Avoid commands such as:

delete + recreate + commit + push

in a single terminal action.

---

# Validation Before Replacement

If a command generates or modifies code files:

1. write new output separately if practical
2. validate syntax
3. inspect diff
4. then replace original

Do not overwrite first and inspect later.

---

# Terminal Output Rules For AI Agents

AI should not assume a command succeeded just because it ran.

AI must inspect:

- exit code when visible
- output text
- created file state
- syntax validation result

---

## On command failure

If a command fails:

- identify whether it is shell parsing, path, missing tool, or code failure
- do not immediately assume the source code is wrong
- check shell syntax and encoding issues first when failure looks suspicious

---

# Script-First Rule

Use repository scripts for repeated operations.

Examples of good script targets:

- lint all PHP files
- validate JS syntax
- detect BOM
- check line endings
- inspect changed files
- run staged validation
- safe replace workflow

This reduces repeated prompt waste.

---

# Path Handling Rule

Always assume project paths may include spaces.

Never write commands that only work on space-free paths.

---

# Command Length Rule

If a command is long enough that it becomes hard to visually verify, it should become a script.

Readable scripts are preferred over hard-to-debug terminal blobs.

---

# Logging Rule

Terminal automation scripts should print:

- what is being checked
- which files are affected
- whether validation passed or failed

Avoid silent scripts for risky operations.

---

# Recommended Terminal Philosophy

Prefer:

inspect  
validate  
patch  
recheck  

over:

overwrite  
hope  
debug later

---

# Final Principle

The terminal is an execution surface, not a brainstorming surface.

Use it conservatively, predictably, and repeatably.

---

# End