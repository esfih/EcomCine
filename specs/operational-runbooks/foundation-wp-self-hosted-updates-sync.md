# Foundation WP Sync Handoff: Self-Hosted Plugin Updates

## Why this handoff exists

`foundation/` is a managed subtree and must not be edited directly in this repository.

This document captures what should be upstreamed into the foundation/wp source repository so other WordPress projects can reuse the same proven updater pipeline.

## Exact Upstream Patch Plan

Apply these changes in the foundation/wp source repository (not in this subtree copy).

### 1) Update overlay index doc

Target file:
- `foundation/wp/docs/WP-OVERLAY-README.md`

Patch intent:
- Add a new section named `Self-Hosted Plugin Updates`.
- Link to the new runbook `SELF-HOSTED-PLUGIN-UPDATES.md`.
- Add one-line policy note: package URL must be validated before release.

Proposed section body:

```
## Self-Hosted Plugin Updates

Use the canonical runbook in `SELF-HOSTED-PLUGIN-UPDATES.md` for private/commercial plugin update flows.

Mandatory release gate:
- validate `action=info` output and package ZIP signature before publishing updater endpoints.
```

### 2) Add new reusable runbook

Target file:
- `foundation/wp/docs/SELF-HOSTED-PLUGIN-UPDATES.md`

Patch intent:
- Include architecture, endpoint contract, deployment, validation, and failure matrix.
- Include explicit anti-patterns discovered in production.

Required runbook sections:
- Purpose and scope
- Client hooks (`pre_set_site_transient_update_plugins`, `plugins_api`)
- Update server routes (`action=info`, `action=download`, `action=diag`)
- GitHub release asset resolution rule (`browser_download_url`)
- Credential handling (`ECOMCINE_GITHUB_TOKEN`/`GITHUB_TOKEN` style precedence)
- Validation gate using script
- Failure matrix with root-cause mappings

### 3) Add reusable validation script

Target file:
- `foundation/wp/scripts/verify-updater-package.sh`

Patch intent:
- Implement endpoint/package verification with no PHP CLI dependency.
- Check `version` and `download_url` in `action=info` JSON.
- Check package URL HTTP status.
- Download sample package and verify ZIP magic (`PK`).

Script contract:
- Args: `[endpoint] [slug]`
- Exit `0` only when all checks pass.
- Emit explicit fail reason for each gate.

### 4) Add template starter notes

Target location:
- `foundation/wp/templates/plugin-skeleton/`

Patch intent:
- Add optional updater integration notes/snippets:
  - plugin row `Check Update` action
  - updater client cache invalidation hook after upgrade
  - endpoint override filter for staging (`*_update_server_url`)

## Source-to-Target Mapping (Copy Matrix)

Use this mapping when preparing the upstream PR:

1. Source:
- `specs/operational-runbooks/wordpress-self-hosted-plugin-updates.md`
Target:
- `foundation/wp/docs/SELF-HOSTED-PLUGIN-UPDATES.md`

2. Source:
- `scripts/verify-updater-package.sh`
Target:
- `foundation/wp/scripts/verify-updater-package.sh`

3. Source:
- `ecomcine/includes/core/class-plugin-updater.php`
Target:
- `foundation/wp/templates/plugin-skeleton/` (snippetized guidance, not project-specific class name)

4. Source:
- `updates.domain.com/update-server.php`
Target:
- `foundation/wp/docs/SELF-HOSTED-PLUGIN-UPDATES.md` (implementation pattern excerpts)

## Upstream PR Checklist

1. Docs compile and links resolve in foundation/wp docs set.
2. `verify-updater-package.sh` passes shellcheck in foundation pipeline (if configured).
3. Runbook includes all known failure mappings:
- HTTP 401 on releases API
- 404 package URL mismatch
- `PCLZIP_ERR_BAD_FORMAT`
- generic `The package could not be installed`
4. Runbook states: never hardcode release asset filename paths.
5. Runbook states: never store live token in committed project files.

## Proposed Foundation Targets

1. Docs update target:
- `foundation/wp/docs/WP-OVERLAY-README.md`
- Add a section: "Self-Hosted Plugin Updates (GitHub Releases Pattern)"

2. New runbook target:
- `foundation/wp/docs/SELF-HOSTED-PLUGIN-UPDATES.md`
- Include architecture, contracts, validation gates, and failure matrix.

3. Template target:
- `foundation/wp/templates/plugin-skeleton/`
- Add optional updater client class template and plugin row `Check Update` action template.

4. Script target:
- `foundation/wp/scripts/`
- Add `verify-updater-package.sh` equivalent to validate:
  - `action=info` JSON shape
  - package URL reachability
  - ZIP signature (`PK..`)

## Canonical Source Artifacts (From EcomCine)

Use these files as implementation references:

- `specs/operational-runbooks/wordpress-self-hosted-plugin-updates.md`
- `scripts/verify-updater-package.sh`
- `updates.domain.com/update-server.php`
- `ecomcine/includes/core/class-plugin-updater.php`

## Required Acceptance Criteria (Foundation)

1. Documentation includes exact troubleshooting mappings for:
- HTTP 401 from release API
- package URL 404
- `PCLZIP_ERR_BAD_FORMAT`
- generic `The package could not be installed`

2. Template guidance requires asset URL resolution from release assets API (`browser_download_url`) instead of hardcoded release paths.

3. Validation script proves package integrity pre-release by checking ZIP magic bytes.

4. Credential handling guidance states token is host-runtime/config only and never committed.

## Suggested Upstream PR Title

`wp-overlay: add canonical self-hosted plugin updater runbook + package verification script`

## Suggested Upstream PR Description

```
Summary
- add canonical self-hosted plugin updater runbook for WP projects
- add reusable package verification script for pre-release gates
- add overlay index link to updater runbook

Why
- prevents recurring production failures in private plugin update flows
- standardizes diagnostics for 401/404/bad-zip install failures

Validation
- verify-updater-package.sh returns PASS against a live endpoint
- runbook includes deterministic root-cause matrix and remediation steps
```
