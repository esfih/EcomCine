---
title: WordPress Debug Infrastructure
type: operational-runbook
status: active
authority: primary
intent-scope: debugging,maintenance,live-site-diagnosis
phase: active
last-reviewed: 2026-03-31
related-files:
  - ../../wp-content/mu-plugins/ecomcine-debug.php
  - ../../scripts/install-debug-mu.sh
  - ../../scripts/wp.sh
  - ../IDE-AI-Command-Catalog.md
---

# WordPress Debug Infrastructure

## Overview

Standard `WP_DEBUG` / `WP_DEBUG_LOG` requires editing `wp-config.php`, which is unavailable on locked
shared hosting and risky to enable globally on production servers.  

The `ecomcine-debug.php` MU-plugin provides a **file-toggle structured logger** that:

- Activates via a `wp-config.php` constant **OR** a drop-file in `wp-content/`
- Logs to `wp-content/logs/ecomcine-debug.log` (one JSON line per entry)
- Captures PHP warnings, uncaught exceptions, REST API 400/500 responses, and slow DB queries
- Exposes the `ec_log()` helper so any plugin/theme can write structured entries
- Never outputs anything to the HTTP response (safe on production)

---

## Files

| File | Purpose |
|---|---|
| `wp-content/mu-plugins/ecomcine-debug.php` | The MU-plugin. Deploy to any WP `mu-plugins/` directory. |
| `scripts/install-debug-mu.sh` | Deploy + enable on local Docker instance. |
| `scripts/wp.sh log:debug [N]` | Tail `ecomcine-debug.log` from the local container. |
| `scripts/set-vendor-l1-complete.php` | Backfill `tm_l1_complete=1` for all approved Dokan vendors. |

---

## Activation

### Local (Docker)

```bash
# Deploy MU-plugin and enable logging immediately:
./scripts/install-debug-mu.sh --enable

# Tail the log:
./scripts/wp.sh log:debug
```

### Live Server (SFTP + SSH)

1. Upload `wp-content/mu-plugins/ecomcine-debug.php` to `wp-content/mu-plugins/` via SFTP (e.g. N0C file manager).
2. Enable logging via ONE of:  
   a. Add to `wp-config.php`:
      ```php
      define( 'ECOMCINE_DEBUG', true );
      ```
   b. Create an empty file at `wp-content/ecomcine-debug.txt` via SFTP.
3. Trigger the failing request (reload the broken page).
4. Download and inspect `wp-content/logs/ecomcine-debug.log`.

### Disable

```bash
# Remove the flag file (local):
./scripts/wp.sh wp eval "unlink( WP_CONTENT_DIR . '/ecomcine-debug.txt' );"
# OR remove define( 'ECOMCINE_DEBUG', true ) from wp-config.php.
```

---

## Log Format

Each line is a JSON object:

```json
{"ts":"2026-03-31T14:22:01+00:00","ctx":"rest","msg":"REST response 400","data":{"status":400,"data":{"code":"vendor_render_failed","message":"Failed to load vendor content.","data":{"message":"...","vendor_id":25}}},"url":"/wp-json/tm/v1/vendor-store-content?vendor_id=25","method":"GET"}
```

Fields:

| Field | Description |
|---|---|
| `ts` | ISO 8601 UTC timestamp |
| `ctx` | Log context: `rest`, `php`, `exception`, `fatal`, `slow-query`, or plugin-defined |
| `msg` | Human-readable message |
| `data` | Optional structured payload |
| `url` | Request URI (omitted in WP-CLI runs) |
| `method` | HTTP method (omitted in WP-CLI runs) |

---

## `ec_log()` API

```php
ec_log( string $context, string $message, array $data = [] ): void
```

Available everywhere after WP loads (MU-plugin runs before regular plugins).

**Examples:**

```php
// Log a REST handler failure:
ec_log( 'vendor-rest', 'store-header.php threw an exception', [
    'vendor_id' => $vendor_id,
    'error'     => $e->getMessage(),
    'file'      => $e->getFile(),
    'line'      => $e->getLine(),
] );

// Log a feature flag check:
ec_log( 'ecomcine', 'Module load skipped — standalone plugin active', [
    'module' => 'tm-store-ui',
] );

// Log a DB query result count:
ec_log( 'vendor-query', 'dokan_get_sellers result', [
    'args'  => $args,
    'count' => count( $result['users'] ?? [] ),
] );
```

**Security notice:** Never pass passwords, tokens, or PII into `ec_log()` data arrays.

---

## IDE AI Workflow

When diagnosing a live issue:

1. Deploy `ecomcine-debug.php` via SFTP to `wp-content/mu-plugins/`
2. Enable: create `wp-content/ecomcine-debug.txt` via SFTP or add `define('ECOMCINE_DEBUG', true)` to wp-config.php
3. Reproduce the failure (load the broken page or trigger the failing REST call)
4. Download `wp-content/logs/ecomcine-debug.log` and share with the IDE AI
5. IDE AI searches the log using `grep '[TM Vendor REST]\|"ctx":"rest"'`
6. After diagnosis, remove the flag file or the define to stop logging

---

## Vendor `tm_l1_complete` Backfill

If the Talents page shows **"No talent found!"**, run:

```bash
# Local:
./scripts/wp.sh php scripts/set-vendor-l1-complete.php

# Live (SSH/WP-CLI):
wp eval-file set-vendor-l1-complete.php --path=/path/to/wordpress
```

This sets `tm_l1_complete = 1` for every approved Dokan vendor that is missing the flag
(idempotent — safe to run multiple times).

**Root cause context:** `filter_dokan_seller_listing_args()` in `tm-store-ui` requires
`tm_l1_complete = 1` so only vendors who completed the L1 onboarding appear in listings.
New vendor imports or manual Dokan account creation bypasses onboarding and leaves this flag unset.

---

## Foundation Contribution Note

`wp-content/mu-plugins/ecomcine-debug.php` is designed as a portable drop-in for any WordPress
installation. To promote it to the shared foundation:

```bash
cp wp-content/mu-plugins/ecomcine-debug.php foundation/wp/templates/mu-plugins/ecomcine-debug.php
# Then commit in the foundation subtree repo.
```
