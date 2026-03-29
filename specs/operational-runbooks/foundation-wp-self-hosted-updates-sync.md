# Foundation WP Sync Handoff: Self-Hosted Plugin Updates

## Why this handoff exists

`foundation/` is a managed subtree and must not be edited directly in this repository.

This document captures what should be upstreamed into the foundation/wp source repository so other WordPress projects can reuse the same proven updater pipeline.

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
