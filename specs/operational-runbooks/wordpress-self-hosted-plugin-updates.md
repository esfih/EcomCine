# WordPress Self-Hosted Plugin Updates (Canonical Pattern)

## Purpose

Provide a repeatable, production-safe method for shipping plugin updates in WP Admin for private/commercial plugins without exposing source-control credentials to plugin end users.

## Repository Policy

- Do not edit `foundation/` subtree files directly from this repository.
- Store canonical guidance in `specs/operational-runbooks/`.
- Upstream accepted patterns to the foundation source repository as a separate sync action.

## Proven Architecture

1. Plugin updater client inside the plugin:
- Hooks `pre_set_site_transient_update_plugins` to inject update metadata.
- Hooks `plugins_api` to provide "View details" payload.
- Provides an explicit "Check Update" plugin row action to force refresh.

2. Update server endpoint:
- `action=info`: returns version metadata and package URL.
- `action=download`: serves the package bytes as a real ZIP stream and validates ZIP magic.
- `action=diag`: returns safe environment and GitHub probe diagnostics.

3. Release source:
- GitHub Releases is authoritative release metadata.
- Update server resolves the actual release asset URL from the GitHub release API.

## Canonical Response Contract (`action=info`)

Required fields:
- `version`
- `download_url`
- `requires`
- `requires_php`
- `tested`
- `sections.changelog`

Recommended fields:
- `name`, `slug`, `author`, `homepage`, `last_updated`, `changelog_url`

## Asset Naming Rule (Critical)

Do not assume package filename patterns in server code.

Always resolve the package URL from release assets (`browser_download_url`) instead of constructing path strings manually.

Reason:
- Release assets may include path-like names from upload source (`dist/...`) or renamed labels.
- Hardcoded URLs can produce `404 Not Found` and failed WP updates.

## Security and Credentials

- Keep GitHub token only on update host runtime/config.
- Never commit real token values.
- Preferred token resolution order on server:
	1. `ECOMCINE_GITHUB_TOKEN`
	2. `GITHUB_TOKEN`
	3. `config.php` fallback

## Cache Policy (Canonical)

- Default: cache disabled (`cache_ttl = 0`) to avoid stale-release drift during active release cycles.
- Optional: enable cache only when needed for API quota control by setting `cache_ttl > 0`.
- Debug mode (`debug=1`) bypasses cache reads.
- Update server exposes secure `action=clear_cache` endpoint (requires keyed auth).

## Release and Deploy Workflow (EcomCine)

1. Build plugin artifact:
- `./scripts/run-catalog-command.sh release.build.ecomcine`

2. Publish release assets to target tag:
- `./scripts/run-catalog-command.sh github.release.upload <tag> dist/ecomcine-<version>.zip dist/ecomcine-<version>.manifest.json`

2.a Canonical upload gate (script-level, required):
- `./scripts/run-catalog-command.sh release.upload.ecomcine.canonical <tag> <version> ecomcine`

2.1. Enforce canonical release asset names (required):
- Upload alias names that do not include build-path prefixes.
- Canonical names must be exactly:
	- `ecomcine-<version>.zip`
	- `ecomcine-<version>.manifest.json`
- Command pattern:
	- `./scripts/run-catalog-command.sh github.release.upload <tag> dist/ecomcine-<version>.zip#ecomcine-<version>.zip dist/ecomcine-<version>.manifest.json#ecomcine-<version>.manifest.json`

2.2. Direct URL smoke test (required):
- Verify direct asset URL returns HTTP 200 before rollout:
	- `https://github.com/esfih/EcomCine/releases/download/<tag>/ecomcine-<version>.zip`

2.3 Canonical asset verification gate (tool-level, required):
- `./scripts/run-catalog-command.sh release.verify.canonical.assets <tag> <version> ecomcine`

3. Build updater deployment bundle:
- `./scripts/run-catalog-command.sh updates.package.clean`

4. Deploy update server files:
- Upload `deploy/updates-ecomcine-clean/update-server.php`
- Upload `deploy/updates-ecomcine-clean/config.php` (host-only real secrets)

5. Clear server cache once:
- Preferred automation:
	- `./scripts/run-catalog-command.sh updates.cache.clear`
- Optional remote clear:
	- `./scripts/run-catalog-command.sh updates.cache.clear https://updates.ecomcine.com/update-server.php <clear_cache_key>`
	- where `clear_cache_key = hash_hmac('sha256', 'clear_cache', download_secret)`
- Manual fallback:
	- remove `updates.domain.com/cache/latest-release.json`

## Validation Gates (Mandatory)

Before production rollout:

1. Endpoint correctness:
- `https://updates.ecomcine.com/update-server.php?action=diag&slug=ecomcine`
- `https://updates.ecomcine.com/update-server.php?action=info&slug=ecomcine&debug=1`

2. Package integrity check:
- `./scripts/run-catalog-command.sh updates.verify.package`

3. WP Admin behavior:
- Plugin row shows `Check Update`.
- Update appears when installed version is lower than `info.version`.
- Update installs without manual ZIP upload.

## Failure Matrix (Observed and Resolved)

1. Symptom: `Could not fetch release data (HTTP 401)`
- Root cause: invalid/missing/revoked GitHub token on updates host.
- Source-fix: set valid token on host runtime/config; verify with `action=diag`.

2. Symptom: `Download failed. Not Found`
- Root cause: hardcoded package URL did not match actual release asset name.
- Source-fix: resolve `download_url` from release assets API, not string interpolation.

3. Symptom: `PCLZIP_ERR_BAD_FORMAT`
- Root cause: updater downloaded non-ZIP content (HTML/error page/redirect mismatch).
- Source-fix: validate payload magic bytes and serve verified ZIP bytes.

4. Symptom: `The package could not be installed`
- Root cause: generic upgrader failure after invalid package retrieval.
- Source-fix: verify package URL reachable, payload ZIP signature valid, and plugin ZIP structure valid.

5. Symptom: endpoint reports old version immediately after release publish
- Root cause: stale update-server cache file persisted.
- Source-fix: keep cache disabled by default (`cache_ttl = 0`) or clear via `updates.cache.clear`.

6. Symptom: update check says successful but no update appears
- Root cause: latest release version equals installed version.
- Source-fix: publish higher semantic version and force update check.

## Reusable Assets in This Repository

- Updater server implementation:
	- `updates.domain.com/update-server.php`
	- `updates.domain.com/config.php`
- Plugin updater client:
	- `ecomcine/includes/core/class-plugin-updater.php`
- Packaging scripts:
	- `scripts/build-ecomcine-release.sh`
	- `scripts/package-updates-ecomcine-clean.sh`
	- `scripts/verify-updater-package.sh`
	- `scripts/clear-updater-cache.sh`

## Porting Checklist for Another Plugin Project

1. Replace slug and endpoint constants in plugin updater client.
2. Update release build script to output `<slug>-<version>.zip`.
3. Configure update server owner/repo/slug and token on host.
4. Publish first release and verify `action=info` reports the correct higher version.
5. Run `updates.verify.package` and complete WP Admin install validation.
