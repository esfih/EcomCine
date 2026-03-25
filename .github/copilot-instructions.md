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

---

## Runtime baseline (always on)

- **Shell:** Git Bash for all repository work; PowerShell only for Windows-specific tasks
- **Docker:** containers `ecomcine_dev-wordpress-1` (port 8180), `ecomcine_dev-db-1`, `ecomcine_dev-phpmyadmin-1` (port 8181)
- **WP-CLI:** always via `./scripts/wp.sh` — never call `wp` directly
- **MSYS_NO_PATHCONV=1** — set before any `docker exec` command using Linux absolute paths
- **One shared Git Bash terminal** for ordinary work; do not spawn extra terminals for short commands
- **No repo-local .venv** unless the repo explicitly requires it

---

## What is NOT in this repo (gitignored)

- `deps/` — premium plugin folders (dokan-pro, woocommerce-bookings, etc.) — must be copied manually
- `.env` — credentials; copy from `.env.example`
- `db/*.sql` — database dumps; re-export from live server via SSH
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
