# EcomCine — Copilot Instructions

You are working in the **EcomCine** repository.

Read `README-FIRST.md` at the start of every meaningful response before taking action.

---

## What this project is

EcomCiné is a productized WordPress-native cinematic marketplace suite.
Phase 1 is live at `castingagency.co`.

Custom artifacts: `ecomcine/` (single unified plugin containing all modules), `ecomcine/ecomcine-base/` (`ecomcine-base` minimal theme), `dokan-category-attributes/`.

All feature modules live under `ecomcine/modules/` — there are no separate standalone plugin folders.

---

## Routing rules — where to look first

| Question type | Go to |
|---|---|
| Project structure, goals, setup steps | `README-FIRST.md` |
| Feature specs and product intent | `specs/` and `specs/app-features/` |
| Theme PHP / template logic | `ecomcine/ecomcine-base/` |
| Plugin logic | `ecomcine/modules/` or `dokan-category-attributes/` |
| Docker / WP-CLI / container ops | `foundation/wp/` |
| Shared workflow and AI-context rules | `foundation/core/` |
| Technical architecture | `technical-documentation.md` |
| Migration / new-computer setup | `New-Migrate-WP-Local-Setup.md` |
| Canonical IDE AI terminal commands | `specs/IDE-AI-Command-Catalog.md` |
| Root-cause remediation governance | `specs/AI-Root-Cause-Remediation-Policy.md` |
| GitHub authentication \u0026 release workflow | `specs/GITHUB-AUTH-REFERENCE.md` |

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

## Development & Release Workflow

**`ecomcine/` (Source Folder):**
- ✅ **EDIT HERE** during development
- ✅ Changes are **LIVE** in WordPress (Docker mounts this folder)
- ✅ No WordPress restart needed for most changes
- ✅ This is your **single source of truth** for plugin code
- ✅ **Directly used for GitHub releases** — zip this folder

**AI Action Rule:**
- Always edit files in `ecomcine/modules/`
- `ecomcine/` is used for both development and distribution
- No `dist/` folder needed — workflow is simplified

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

## No Dual Sources of Truth (Mandatory)

- **Never create a second copy of a file, folder, or plugin** that already exists elsewhere in the repo unless the User explicitly requests it AND it is documented as an approved architecture decision in a `specs/app-features/` entry.
- If implementing a feature requires modifying a module, modify it **in its single canonical location** — `ecomcine/modules/<module>/`.
- Do not create `tm-*/` root-level plugin folders. These were legacy staging directories that have been deleted. The canonical location for all module code is `ecomcine/modules/`.
- If a parity check reveals two copies of the same logical artifact have diverged, **stop and report it to the User before proceeding** — do not silently fix one without reconciling the other.
- This rule applies to: PHP files, JS files, CSS files, templates, config files, and any other artifact that represents executable or deployable content.

---

## Safety rules

- Never commit `.env`, `deps/`, `db/*.sql`, or live database exports
- Never change, reset, or reveal credentials without explicit user approval in the current conversation
- `MSYS_NO_PATHCONV=1` is required before `docker exec` commands with Linux absolute paths
- For Docker container patching: write a local file → `docker cp` → `docker exec`. Never inline heredocs into `docker exec`
- `isBackground: true` only for genuine long-running processes (servers, file watchers) — never for inspection commands
- Enforce repository hooks via `./scripts/install-git-hooks.sh` when setting up or migrating a workspace

---

## GitHub Authentication & Release Workflow (Mandatory)

**GitHub CLI (`gh`) is authenticated to account `esfih`** with scopes: `gist`, `read:org`, `repo`, `workflow`

**Token location**: `/root/.config/gh/hosts.yml`

**Repository**: `https://github.com/esfih/EcomCine`

**Release workflow**:
0. Pre-flight (MANDATORY — do not skip):
   - Both `* Version: <version>` header AND `define( 'ECOMCINE_VERSION', '<version>' )` constant in `ecomcine/ecomcine.php` must match before committing.
   - Commit the version bump. Get SHA: `git rev-parse HEAD`. The tag must point to THIS commit.
1. Verify auth: `gh auth status`
2. Create tag on the version-bumped commit: `gh api /repos/esfih/EcomCine/git/refs --method POST -f ref="refs/tags/v<version>" -f sha=<commit-sha>`
3. Build the distribution zip: `./scripts/build-ecomcine-release.sh`
4. Create release WITH zip attached in one command: `gh release create v<version> dist/ecomcine-<version>.zip --title "Release v<version>" --notes "<release-notes>"`
   - **Never** call `gh release create` without the zip path — WordPress auto-update will fail with "The package could not be installed".
5. Verify: `gh release view v<version> --json assets` must show the zip asset and `./scripts/verify-updater-package.sh` must exit 0.

**Note**: Local `git push origin v<version>` may fail due to repository validation hooks checking old commit hashes. Use GitHub API or `gh` CLI directly as fallback.

## IDE AI Command Contract Policy (Mandatory)

- Check `specs/IDE-AI-Command-Catalog.md` first for terminal operations.
- Prefer `./scripts/run-catalog-command.sh <command-id> [args...]` when a catalog command exists.
- If no catalog command exists for the task, create a new standardized command entry and implementation first, document it in the catalog, then use that command path for execution.
- Exit code is canonical pass/fail signal; warning text alone must not be treated as failure.
- Never run interactive package-manager installs (`apt install`, `npm install -g`, `pip install --user`) via the IDE AI integrated terminal path.
- For host tooling installs, use catalog command `host.tool.install` and run it from an external WSL terminal session.
- Exception: catalog command `qa.playwright.browsers.install` is approved in the IDE integrated terminal because local Playwright browser/system dependencies are part of the canonical self-test workflow.
- PreToolUse guardrail enforcement remains limited to Windows-mounted path usage and out-of-workspace file mutation attempts; operate only under the active WSL workspace root.

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

---

## AI Response Policy (Mandatory)

- **NO excessive summaries**: Provide concise summaries with very short explanation sentences.
- **NO automatic documentation**: Do NOT create documentation files unless explicitly requested by the user.
- **File changes only**: List the files/sections that were changed — that's it.
- **No comprehensive guides**: Do not create extensive documentation for each fix/action.
- **Be direct**: Get straight to the point without unnecessary elaboration.
