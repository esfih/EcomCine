---
title: Validation Stack
type: rules
status: active
completion: 100
priority: critical
authority: primary
intent-scope: workspace-setup,implementation,debugging,review,maintenance
phase: setup
last-reviewed: 2026-03-10
current-focus: make validation explicit, local-tool aware, and useful for both dev richness and client-runtime realism
next-focus: add repository scripts that automate the core checks defined here
ide-context-token-estimate: 2190
token-estimate-method: approx-chars-div-4
session-notes: >
  Updated to align with workspace setup mode, scripts planning, and WordPress-specific environment realities.
related-files:
  - ../README-FIRST.md
  - ../DEVOPS-TECH-STACK.md
  - ../WORKSPACE-SETUP.md
  - ./SECURITY-VALIDATION-PROTOCOL.md
  - ./TERMINAL-RULES.md
  - ./FILE-SAFETY-RULES.md
---

# Validation Stack

## Purpose

This document defines the local validation and tooling stack expected for this repository.

Its purpose is to reduce wasted debugging caused by:

- missing CLI tools
- syntax errors
- encoding problems
- line ending problems
- false feature failures caused by file-format issues
- lack of repeatable scripts
- confusion between local dev capability and customer runtime capability

---

# Core Principle

Validation is part of implementation.

Do not rely on visual inspection alone.

For feature behavior checks, use TESTING-PROTOCOL.md to define test environments and semantic success oracles.

---

# Workspace Setup Link

This file is especially important during:

- workspace setup mode
- implementation mode
- debugging mode

If the environment is new, this file should be used together with:

- README-FIRST.md
- WORKSPACE-SETUP.md

---

# Expected Local Tools

Preferred baseline local tools:

- Git
- Git Bash
- PHP CLI
- Composer
- Node.js
- npm
- Python 3

These are local development tools.

They are not assumptions about the customer runtime.

---

# Customer Runtime Rule

The final customer environment may not provide:
- SSH
- WP-CLI
- Node
- Python
- broad filesystem access

Therefore:
- these tools are for local development quality
- shipped plugin behavior must not depend on them being present on the client server unless explicitly optional

---

# Required Validation Categories

The local environment should support:

- PHP syntax lint
- JS syntax validation
- JSON validation
- BOM detection
- line ending checks
- trailing whitespace/final newline consistency
- WordPress plugin release ZIP structure checks before publishing
- WordPress coding standards checks where configured
- static analysis where practical
- repository helper scripts
- security-focused checks for new/changed attack surfaces

Additional mandatory process checks for active feature work:

- test environment registry completeness
- success/failure oracle completeness for target scenarios
- evidence capture completeness per scenario
- attack-surface review completeness for changed feature paths

---

# Scripts Rule

The repository should have a /scripts directory.

Workspace setup should check:
- whether /scripts exists
- which core scripts are already present
- which scripts are missing but useful for current project needs

Recommended script categories:
- lint changed PHP files
- validate JSON files
- detect BOM
- detect wrong line endings
- validate changed JS files
- inspect changed files
- safe replace helper
- local WP sync/path verification
- Docker/WP health verification
- workstation readiness audit

Current starter-pack filenames:

- `scripts/validate-changed.sh`
- `scripts/lint-php.sh`
- `scripts/validate-js.sh`
- `scripts/check-json.sh`
- `scripts/check-bom.py`
- `scripts/check-line-endings.py`
- `scripts/inspect-changed.sh`
- `scripts/safe-replace.py`
- `scripts/verify-plugin-sync.sh`
- `scripts/check-local-wp.sh`
- `scripts/check-devops-readiness.ps1`
- `scripts/global-sync-and-handover.sh`
- `scripts/takeover-pull.sh`
- `scripts/security-validate-changed.sh`

Sender/receiver workflow standard:

- sender-side continuity update and push: `scripts/global-sync-and-handover.sh`
- receiver-side takeover pull and validation: `scripts/takeover-pull.sh`

---

# PHP Validation

At minimum:
- changed PHP files should be linted

Preferred:
- repository-level script for changed-file PHP lint
- WordPress coding standards checks where configured
- static analysis where maturity allows

---

# JavaScript Validation

At minimum:
- changed JS files should be syntax-checked

Preferred:
- standardized formatter/linter stack
- targeted validation on changed files first

---

# JSON Validation

All changed JSON files must be validated immediately.

This includes:
- package files
- task graph files
- config files

---

# Encoding and Line Ending Validation

The repository should detect and prevent:

- unexpected UTF-8 BOM
- mixed CRLF/LF issues
- trailing whitespace drift
- missing final newline

These often create fake debugging sessions.

---

# Missing Tool Rule

If a validation tool is not available locally:

- do not pretend validation passed
- report it explicitly
- use the strongest available fallback
- note it as setup debt if relevant

On Windows, also treat broken app-execution aliases as missing tools.

Examples:

- `python.exe` pointing only to `WindowsApps` Store alias
- `bash.exe` pointing to a WSL relay when WSL itself is not usable

These should not be counted as valid Python or Bash availability for repository script execution.

---

# Fast Validation Order

Preferred practical order after code edits:

1. syntax check
2. encoding / BOM / line ending check
3. JSON validity if relevant
4. style / lint checks
5. static analysis if configured
6. feature-specific validation if available
7. for plugin releases: validate ZIP structure and internal paths before GitHub upload
8. security review pass for new/changed entry points and sensitive data handling

---

# Security Validation Expectations

For feature changes that add or modify entry points, validate at minimum:

- capability/permission enforcement exists and is correct
- nonce/CSRF protection exists for state-changing authenticated actions
- input sanitization and output escaping are applied at boundaries
- SQL/query construction avoids injection-prone patterns
- credential/token/secret values are not exposed in logs, debug views, or responses
- brute-force and abuse-prone paths have proportional controls where applicable

If a security validation step cannot be executed, report it explicitly as a validation gap.

---

# WordPress Release Artifact Validation

When preparing plugin release assets, validate archive structure explicitly.

Required checks:

- use release asset ZIPs for installation, not auto-generated source archives
- for shipped plugin updates, publish a GitHub Release and attach the validated ZIP asset before handoff
- exactly one top-level plugin folder exists
- plugin bootstrap file is present at expected path under that folder
- internal archive entry paths use `/` separators
- no local temp folders or machine-specific files are included

Reference command:

```bash
scripts/build-release-zip.sh \
  --source <plugin-source-dir> \
  --plugin-slug <plugin-slug> \
  --version <version> \
  --bootstrap <bootstrap-file>
```

Or validate an existing ZIP directly:

```bash
python scripts/verify-release-zip-paths.py output/releases/<artifact>.zip \
  --plugin-slug <plugin-slug> \
  --bootstrap <bootstrap-file>
```

If any check fails, do not publish the asset. If the asset is not published on a GitHub Release, the release flow is incomplete.

Private-repo release URL verification note:

- anonymous HEAD checks on `browser_download_url` may return `404` for private repos
- use authenticated GitHub release-asset API validation as canonical proof instead
- expected result for authenticated asset API request with `Accept: application/octet-stream` is HTTP `302`
- do not mark release publish as failed solely because anonymous browser-url checks return `404`

---

# AI Agent Rule

AI must not mistake local validation-tool failure for proof of code failure.

Likewise, AI must not mistake missing validation tools for proof that the code is correct.

Differentiate:
- shell/tooling issue
- path issue
- formatting issue
- actual code issue

Also differentiate:

- transport success (request returned)
- semantic success (state and contract outcomes matched oracle)

Do not mark feature validation complete when only transport success was observed.

Security gate reminder:

- do not commit or push feature updates when `scripts/security-validate-changed.sh` fails

---

# Final Principle

Strong local validation reduces fake debugging and protects the repository, while the shipped plugin remains realistic for restricted customer environments.

---

# End