---
title: EcomCine — Repository Context
type: root-guidance
status: active
authority: primary
intent-scope: all
phase: active-development
last-reviewed: 2026-03-25
related-files:
  - ./docker-compose.yml
  - ./.env.example
  - ./technical-documentation.md
  - ./New-Migrate-WP-Local-Setup.md
---

# EcomCine — README FIRST

EcomCiné is a productized WordPress-native cinematic marketplace suite.
Phase 1 is live at `castingagency.co`.

The canonical runtime target is bare WordPress + `ecomcine-base` + EcomCine plugins.
WooCommerce, Dokan, and WooCommerce Bookings are legacy compatibility layers for parity and migration work, not the long-term required stack.

**GitHub:** https://github.com/esfih/EcomCine  
**Live site:** https://castingagency.co  
**Technical spec:** [technical-documentation.md](./technical-documentation.md)

---

## Repository Layout

| Folder | What it is | Committed? |
|---|---|---|
| `ecomcine/` | Single unified plugin — contains all modules under `ecomcine/modules/` | Yes |
| `ecomcine/ecomcine-base/` | Canonical minimal `ecomcine-base` theme shipped by the plugin | Yes |
| `ecomcine/modules/tm-media-player/` | Cinematic media player + showcase module | Yes |
| `ecomcine/modules/tm-account-panel/` | Front-end login/registration + talent onboarding module | Yes |
| `ecomcine/modules/tm-vendor-booking-modal/` | Frictionless booking/checkout modal module | Yes |
| `ecomcine/modules/tm-store-ui/` | Cinematic store UI, filter bar, vendor profiles, shortcodes | Yes |
| `dokan-category-attributes/` | Dynamic category-specific vendor attributes plugin | Yes |
| `deps/` | Third-party plugin folders (Dokan Pro, WooCommerce, etc.) | **No** (gitignored) |
| `db/` | Scrubbed dev seed SQL | `seed.sql` only |
| `scripts/` | Dev tooling (wp.sh, setup-deps.sh, etc.) | Yes |
| `foundation/` | Shared layers pulled via `bootstrap-foundation.sh` | No (subtrees) |
| `specs/` | Product feature inventory | Yes |
| `dist/` | **DELETED** — No longer needed. GitHub releases are created directly from `ecomcine/` folder. | No (removed) |

---

## Development & Release Workflow

**`ecomcine/` (Source Folder):**
- ✅ **EDIT HERE** during development
- ✅ Changes are **LIVE** in WordPress (Docker mounts this folder)
- ✅ No WordPress restart needed for most changes
- ✅ This is your **single source of truth** for plugin code
- ✅ **Directly used for GitHub releases** — zip this folder

**Workflow:**
1. Develop in `ecomcine/` → Changes appear live in WordPress
2. When ready to release: Zip `ecomcine/` directly
3. Upload zip to GitHub Releases

**Why This Matters:**
- Single folder for both development and distribution
- No sync overhead or confusion about which folder to edit
- Simplified workflow: edit → zip → release

---

## First-Time Local Setup

### Prerequisites
- Git + Git Bash
- Docker Desktop (running)
- GitHub CLI (`gh`) authenticated to `esfih` account

### GitHub Authentication Verification
```bash
gh auth status  # Should show: ✓ Logged in to github.com account esfih
```

**Token location**: `/root/.config/gh/hosts.yml`
**Repository**: `https://github.com/esfih/EcomCine`

### Steps

```bash
# 1. Bootstrap shared foundation layers (Docker runtime, WP-CLI, scripts)
./scripts/bootstrap-foundation.sh

# 1.1 Enable repository policy hooks (commit-msg, pre-push)
./scripts/install-git-hooks.sh

# 2. Start WordPress
docker compose up -d

# 3. Import the scrubbed DB seed
./scripts/wp.sh wp db import db/seed.sql

# 4. Replace live URL with local dev URL
./scripts/wp.sh wp search-replace 'https://castingagency.co' 'http://localhost:8180' --all-tables

# 5. Reset admin password to something known locally
./scripts/wp.sh wp user update 1 --user_pass=admin

# 6. Set pretty permalinks (required for WooCommerce/Dokan REST API)
./scripts/wp.sh wp rewrite structure '/%postname%/' --hard

# 7. Activate all plugins and theme in dependency order
./scripts/setup-deps.sh

# 8. Verify
./scripts/check-local-wp.sh
```

### Access
- WordPress: http://localhost:8180
- WP Admin: http://localhost:8180/wp-admin (user: admin / pass: admin)
- phpMyAdmin: http://localhost:8181

---

## Daily Dev Workflow

```bash
# Start containers
docker compose up -d

# WP-CLI commands
./scripts/wp.sh wp plugin list
./scripts/wp.sh wp cache flush

# Tail debug log
./scripts/wp.sh log

# Health check
./scripts/check-local-wp.sh
```

### Canonical IDE AI Commands

For deterministic IDE AI behavior, terminal operations must follow the command contracts in `specs/IDE-AI-Command-Catalog.md`.

Preferred execution path:

```bash
./scripts/run-catalog-command.sh <command-id> [args...]
```

If a required task has no catalog entry, stop and create/approve a new catalog command contract first.

### Demo Data Packaging Workflow

Use the canonical runbook: `specs/operational-runbooks/demo-data-release-workflow.md`

Required command path:

```bash
./scripts/run-catalog-command.sh demos.media.rebuild <pack-id>
./scripts/run-catalog-command.sh demos.release <version> --push
./scripts/run-catalog-command.sh data.vendors.import.demo <zip-url>
./scripts/run-catalog-command.sh qa.playwright.test.debug tests/demo-data-import-response.spec.ts
```

The release-ready archive must always be built from `demos/<pack-id>/media/`, not directly from `media-original/`, and vendor-relative video paths must be preserved during WebM conversion.

### IDE Stability Notice (Mandatory)

- Never run interactive package-manager installs (for example `apt install`, `npm install -g`, `pip install --user`) from the IDE AI integrated terminal flow.
- Use catalog command `host.tool.install` via `./scripts/run-catalog-command.sh host.tool.install <tool>` from an external WSL terminal session.
- Exception: `./scripts/run-catalog-command.sh qa.playwright.browsers.install` is approved in the IDE integrated terminal so Playwright self-tests can fully bootstrap their local browser runtime.
- This guardrail prevents VS Code renderer freezes caused by high-volume interactive terminal output.
- Runtime enforcement is active via `.github/hooks/block-interactive-package-installs.json` (PreToolUse command guard).

### IDE AI Self-Test + Debug (Mandatory)

Before asking users for browser console screenshots/HTML exports, IDE AI must run local self-test/debug tools:

```bash
./scripts/run-catalog-command.sh infra.check
./scripts/run-catalog-command.sh wp.health.check
./scripts/run-catalog-command.sh qa.playwright.install
./scripts/run-catalog-command.sh qa.playwright.test.smoke
./scripts/run-catalog-command.sh qa.playwright.test.interactions
./scripts/run-catalog-command.sh qa.playwright.test.debug
./scripts/run-catalog-command.sh debug.snapshot.collect 200
./scripts/run-catalog-command.sh wp.debug.log.tail 200
./scripts/run-catalog-command.sh wp.debug.php.info
```

Canonical runbook: `specs/operational-runbooks/ide-ai-playwright-debug-workflow.md`.

Interactions reuse guide (cross-project): `specs/operational-runbooks/playwright-interactions-reuse-guide.md`.

### GitHub Release Workflow

**Repository**: `https://github.com/esfih/EcomCine`

**Authentication**: `gh` CLI authenticated to `esfih` account with scopes: `gist`, `read:org`, `repo`, `workflow`

**Token location**: `/root/.config/gh/hosts.yml`

**Release process**:
1. Verify auth: `gh auth status`
2. Create tag: `gh api /repos/esfih/EcomCine/git/refs --method POST -f ref="refs/tags/v<version>" -f sha=<commit-sha>`
3. Create release: `gh release create v<version> --title "Release v<version>" --notes "<release-notes>"`
4. Push tag: `git push origin v<version>` (may fail if tag exists remotely)

**Note**: Local `git push origin v<version>` may fail due to repository validation hooks checking old commit hashes. Use GitHub API or `gh` CLI directly as fallback.

### Root-Cause Remediation Policy

All fixes must follow the mandatory decision gate in `specs/AI-Root-Cause-Remediation-Policy.md`.

Enforcement intent:

- prefer source-fix over mitigation
- require explicit mitigation metadata when source-fix is blocked
- require semantic validation, not only transport success

Policy hooks:

- `commit-msg` validates required remediation trailers
- `pre-push` validates remediation trailers for pushed commits

---

## Premium Plugins (deps/)

The `deps/` folder is gitignored. Required folders on every developer machine:

| Folder | Source |
|---|---|
| `deps/dokan-lite/` | WordPress.org free — from wp-content/plugins backup |
| `deps/dokan-pro/` | WeDevs account — https://wedevs.com |
| `deps/woocommerce/` | WordPress.org free — from wp-content/plugins backup |
| `deps/woocommerce-bookings/` | WooCommerce account — https://woocommerce.com |
| `deps/greenshift/` | GreenShift plugin package — from your licensed download/source backup |

---

## Runtime Rules

- Use **Ubuntu WSL2 shell** for all repository work; PowerShell only for Windows-only host tasks
- Keep active repositories on the WSL filesystem (`/home/<user>/dev/...`), not on `C:\` or `/mnt/c/...`
- One shared WSL terminal session for normal work
- `MSYS_NO_PATHCONV=1` is set automatically by scripts that call `docker exec`
- Never commit `.env` or anything in `deps/`

### Exclusive Runtime Workflow (Only Supported Mode)

Use this flow every time. No Windows-path or mixed-shell workflow is supported.

```bash
# 1) Enter Ubuntu WSL2
wsl -d Ubuntu

# 2) Work only from Linux repo path
cd /home/<user>/dev/EcomCine

# 3) Validate runtime baseline (hard requirement)
./scripts/check-local-dev-infra.sh

# 4) Run stack and operations from this shell/path only
docker compose up -d
./scripts/check-local-wp.sh
```

Forbidden states (hard fail):
- Running from `C:\...` or `/mnt/c/...`
- Running project Docker/WordPress commands from PowerShell
- Maintaining dual active repo copies (Windows + WSL) for the same runtime

### Required WSL Bind Inventory

The following runtime sources must resolve to Linux paths under `/home/<user>/dev/EcomCine`:

- `ecomcine/ecomcine-base/` -> `/var/www/html/wp-content/themes/ecomcine-base`
- `ecomcine/` -> `/var/www/html/wp-content/plugins/ecomcine`
- `dokan-category-attributes/` -> `/var/www/html/wp-content/plugins/dokan-category-attributes`
- `deps/dokan-lite/` -> `/var/www/html/wp-content/plugins/dokan-lite`
- `deps/dokan-pro/` -> `/var/www/html/wp-content/plugins/dokan-pro`
- `deps/woocommerce/` -> `/var/www/html/wp-content/plugins/woocommerce`
- `deps/woocommerce-bookings/` -> `/var/www/html/wp-content/plugins/woocommerce-bookings`
- `deps/greenshift/` -> `/var/www/html/wp-content/plugins/greenshift`
- `wp-content/uploads/` -> `/var/www/html/wp-content/uploads`

### Source-Level Engineering Policy (Mandatory)

- Fix regressions at source: plugin install/mount, PHP logic, template output, data, hooks, and runtime configuration.
- Do **not** use cosmetic CSS/JS fallbacks, hide-rules, or overlay patches as a substitute for source fixes.
- If a temporary mitigation is unavoidable, mark it as temporary, document the root cause, and schedule removal immediately after source remediation.

### Required Infra Diagnostics

Run these checks before feature work or machine handoff:

```bash
./scripts/check-local-dev-infra.sh
./scripts/check-local-wp.sh
```

`check-local-dev-infra.sh` fails if the repo is running from a Windows-mounted path or if
Docker bind mounts are still sourced from Windows paths.

Agent runtime enforcement note:
- `.github/hooks/block-interactive-package-installs.json` invokes `scripts/hook-pretool-command-guard.sh`.
- The guard denies Windows-style path usage and out-of-workspace file mutation attempts for IDE AI tool calls.
