# EcomCine — Copilot Instructions

You are working in the **EcomCine** repository.

Read `README-FIRST.md` at the start of every meaningful response before taking action.

---

## What this project is

EcomCiné is a productized suite of cinematic customizations for WordPress / WooCommerce / Dokan marketplaces.
Phase 1 is live at `castingagency.co`.

Custom artifacts: `theme/` (astra-child), `tm-media-player/`, `tm-account-panel/`, `tm-vendor-booking-modal/`, `dokan-category-attributes/`.

---

## Routing rules — where to look first

| Question type | Go to |
|---|---|
| Project structure, goals, setup steps | `README-FIRST.md` |
| Feature specs and product intent | `specs/` and `specs/app-features/` |
| Theme PHP / template logic | `theme/` |
| Plugin logic | `tm-*/` or `dokan-category-attributes/` |
| Docker / WP-CLI / container ops | `foundation/wp/` |
| Shared workflow and AI-context rules | `foundation/core/` |
| Technical architecture | `technical-documentation.md` |
| Migration / new-computer setup | `New-Migrate-WP-Local-Setup.md` |
| Canonical IDE AI terminal commands | `specs/IDE-AI-Command-Catalog.md` |
| Root-cause remediation governance | `specs/AI-Root-Cause-Remediation-Policy.md` |

---

## Runtime baseline (always on)

- **Shell:** Ubuntu WSL2 for all repository work; PowerShell only for Windows-specific tasks
- **Workspace path:** Active repos must live on WSL filesystem (`/home/<user>/dev/...`), never `C:\...` or `/mnt/c/...`
- **Docker:** containers `ecomcine_dev-wordpress-1` (port 8180), `ecomcine_dev-db-1`, `ecomcine_dev-phpmyadmin-1` (port 8181)
- **WP-CLI:** always via `./scripts/wp.sh` — never call `wp` directly
- **MSYS_NO_PATHCONV=1** — set before any `docker exec` command using Linux absolute paths
- **One shared WSL terminal** for ordinary work; do not spawn extra terminals for short commands
- **No repo-local .venv** unless the repo explicitly requires it

---

## What is NOT in this repo (gitignored)

- `deps/` — premium plugin folders (dokan-pro, woocommerce-bookings, etc.) — must be copied manually
- `.env` — credentials; copy from `.env.example`
- `db/*.sql` — database dumps; re-export from live server via SSH (except approved scrubbed fixtures such as `db/seed.sql` and `db/fluentcart-control-plane-seed.sql`)
- `castingagency-uploads.tar.gz` — WordPress media library archive

See `New-Migrate-WP-Local-Setup.md` → "EcomCine — Re-Setup on a New Computer" for full transfer instructions.

---

## Canonical authority

- `specs/` and `specs/app-features/` are the single source of truth for product requirements
- `foundation/core/` contains reusable workflow, AI-context, and security guidance
- `foundation/wp/` contains WordPress-specific runtime and packaging patterns
- Never modify files in `foundation/` directly — they are managed as git subtrees

---

## Safety rules

- Never commit `.env`, `deps/`, `db/*.sql`, or live database exports
- Never change, reset, or reveal credentials without explicit user approval in the current conversation
- `MSYS_NO_PATHCONV=1` is required before `docker exec` commands with Linux absolute paths
- For Docker container patching: write a local file → `docker cp` → `docker exec`. Never inline heredocs into `docker exec`
- `isBackground: true` only for genuine long-running processes (servers, file watchers) — never for inspection commands
- Enforce repository hooks via `./scripts/install-git-hooks.sh` when setting up or migrating a workspace

---

## IDE AI Command Contract Policy (Mandatory)

- Use only commands defined in `specs/IDE-AI-Command-Catalog.md` for terminal operations.
- Prefer `./scripts/run-catalog-command.sh <command-id> [args...]` when executing catalog entries.
- Do not improvise arbitrary shell commands for task execution.
- If no catalog command exists for the task: stop, report the missing command ID, and ask the user to approve creating a new catalog entry before proceeding.
- Exit code is canonical pass/fail signal; warning text alone must not be treated as failure.
- Never run interactive package-manager installs (`apt install`, `npm install -g`, `pip install --user`) via the IDE AI integrated terminal path.
- For host tooling installs, use catalog command `host.tool.install` and run it from an external WSL terminal session.
- PreToolUse runtime enforcement for this rule is defined in `.github/hooks/block-interactive-package-installs.json`.
- PreToolUse guardrail enforcement also denies Windows-mounted path usage and out-of-workspace file mutation attempts; operate only under the active WSL workspace root.

### IDE AI Self-Test + Debug Policy (Mandatory)

- Do not wait for user-provided browser console logs as a first step.
- Use cataloged local tooling first: `qa.playwright.install`, `qa.playwright.test.smoke`, `qa.playwright.test.debug`, `debug.snapshot.collect`, `wp.debug.log.tail`, `wp.debug.php.info`.
- Always include artifact paths (`tools/playwright/playwright-report`, `tools/playwright/test-results`, `logs/debug-snapshots`) in remediation updates.
- Ask the user for manual browser evidence only if local reproduction is not possible after running the canonical workflow.
- Canonical runbook: `specs/operational-runbooks/ide-ai-playwright-debug-workflow.md`.

---

## Root-Cause Decision Gate (Mandatory)

- Before implementing a fix, classify remediation as `source-fix` or `mitigation`.
- Prefer `source-fix`; do not ship cosmetic/hiding changes as final remediation.
- If `mitigation` is unavoidable, require explicit fields: `Root-Cause`, `Mitigation-Reason`, `Removal-Trigger`, `Follow-Up-Issue`.
- Do not mark work complete on transport success alone; require semantic validation against the expected contract/state outcome.
