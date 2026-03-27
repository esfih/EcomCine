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

EcomCiné is a productized suite of cinematic customizations for WordPress/WooCommerce/Dokan
marketplaces. Phase 1 is live at `castingagency.co`.

**GitHub:** https://github.com/esfih/EcomCine  
**Live site:** https://castingagency.co  
**Technical spec:** [technical-documentation.md](./technical-documentation.md)

---

## Repository Layout

| Folder | What it is | Committed? |
|---|---|---|
| `theme/` | `astra-child` child theme | Yes |
| `tm-media-player/` | Cinematic media player + showcase plugin | Yes |
| `tm-account-panel/` | Front-end login/registration + talent onboarding plugin | Yes |
| `tm-vendor-booking-modal/` | Frictionless booking/checkout modal plugin | Yes |
| `dokan-category-attributes/` | Dynamic category-specific vendor attributes plugin | Yes |
| `deps/` | Third-party plugin folders (Dokan Pro, WooCommerce, etc.) | **No** (gitignored) |
| `db/` | Scrubbed dev seed SQL | `seed.sql` only |
| `scripts/` | Dev tooling (wp.sh, setup-deps.sh, etc.) | Yes |
| `foundation/` | Shared layers pulled via `bootstrap-foundation.sh` | No (subtrees) |
| `specs/` | Product feature inventory | Yes |

---

## First-Time Local Setup

### Prerequisites
- Git + Git Bash
- Docker Desktop (running)
- GitHub CLI (`gh`) authenticated

### Steps

```bash
# 1. Bootstrap shared foundation layers (Docker runtime, WP-CLI, scripts)
./scripts/bootstrap-foundation.sh

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

- `theme/` -> `/var/www/html/wp-content/themes/astra-child`
- `tm-media-player/` -> `/var/www/html/wp-content/plugins/tm-media-player`
- `tm-account-panel/` -> `/var/www/html/wp-content/plugins/tm-account-panel`
- `tm-vendor-booking-modal/` -> `/var/www/html/wp-content/plugins/tm-vendor-booking-modal`
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
