# WordPress Plugin Updater Bootstrap Checklist (15-Minute Setup)

## Goal

Stand up a production-ready self-hosted updater path for a new plugin project with deterministic validation before first customer rollout.

## Inputs You Need

- `plugin_slug` (example: `my-plugin`)
- `plugin_main_file` (example: `my-plugin/my-plugin.php`)
- `updates_endpoint` (example: `https://updates.example.com/update-server.php`)
- GitHub repository owner and name
- GitHub token on updates host (runtime/config only, never committed)

## Step-by-Step Setup

1. Add plugin header update URI
- In plugin main file, add:
- `Update URI: https://updates.example.com/update-server.php`

2. Add updater client integration
- Implement hooks:
- `pre_set_site_transient_update_plugins`
- `plugins_api`
- `upgrader_process_complete`
- Add plugin row action link: `Check Update`

3. Create update server endpoint
- Implement routes:
- `action=info`
- `action=download`
- `action=diag`

4. Implement release metadata retrieval
- Fetch `releases/latest` from GitHub API.
- Resolve package URL from release assets (`browser_download_url`).
- Do not construct guessed release asset filenames.

5. Implement package delivery guard
- For `action=download`, fetch binary and validate ZIP signature (`PK` magic bytes).
- Return explicit JSON error if package fetch/format is invalid.

6. Add release packaging script
- Build zip with top-level folder exactly matching plugin slug.
- Output `<slug>-<version>.zip` and a manifest file.

7. Add update-server deployment packaging
- Produce clean deployment bundle for update host files.
- Exclude Windows ADS/Zone artifacts from deployment zips.

8. Configure token securely on updates host
- Preferred runtime variables:
- `ECOMCINE_GITHUB_TOKEN`
- fallback `GITHUB_TOKEN`
- optional host-only config fallback

9. Add validation script and command
- Add reusable script equivalent to:
- `scripts/verify-updater-package.sh`
- Validate:
- `action=info` JSON fields
- package URL HTTP status
- downloaded bytes ZIP signature

10. Add command-catalog contracts
- Add IDs for:
- release build
- updater package clean build
- updater package verify

## Release Day Sequence

1. Bump plugin version in main plugin file and version constant.
2. Commit and push.
3. Build release artifact.
4. Publish release assets to GitHub tag.
5. Build and deploy updater host bundle.
6. Clear update server cache file.
7. Run updater verification command.
8. Validate WP Admin update flow from old version to new version.

## Acceptance Criteria

1. `action=diag` reports GitHub probe HTTP 200 and token configured.
2. `action=info` returns higher version than installed plugin.
3. `download_url` is reachable and resolves to a real ZIP.
4. WP Admin shows update and update completes successfully.
5. Plugin remains active after upgrade and reports new version.

## Fast Triage Matrix

1. Error: `Could not fetch release data` + HTTP 401
- Cause: token invalid/missing/revoked
- Fix: set valid host token and retest diag

2. Error: `Download failed. Not Found`
- Cause: package URL does not match actual asset name
- Fix: derive URL from release assets API

3. Error: `PCLZIP_ERR_BAD_FORMAT`
- Cause: non-ZIP response or corrupted stream
- Fix: validate ZIP magic and return binary ZIP bytes only

4. Error: `The package could not be installed`
- Cause: generic upgrader failure after package/structure mismatch
- Fix: verify package URL, ZIP integrity, and top-level plugin folder structure

## Reusable Reference Files (EcomCine)

- `specs/operational-runbooks/wordpress-self-hosted-plugin-updates.md`
- `scripts/verify-updater-package.sh`
- `updates.domain.com/update-server.php`
- `ecomcine/includes/core/class-plugin-updater.php`
