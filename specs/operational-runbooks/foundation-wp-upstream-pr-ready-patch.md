# Foundation WP Upstream PR: Ready-to-Apply Patch

Use this patch in the **foundation/wp source repository** (not in this subtree copy).

## 1) Update overlay index doc

Target: `docs/WP-OVERLAY-README.md`

```diff
*** Begin Patch
*** Update File: docs/WP-OVERLAY-README.md
@@
 ## Local Migration Rule
@@
 - add reusable WordPress assets here first
 - keep current root/runtime files authoritative until adapters are in place
 - do not move product plugin code into this folder
+
+## Self-Hosted Plugin Updates
+
+Use the canonical runbook in `SELF-HOSTED-PLUGIN-UPDATES.md` for private/commercial plugin update flows.
+
+Mandatory release gate:
+- validate `action=info` payload shape and package ZIP signature before production rollout.
*** End Patch
```

## 2) Add canonical updater runbook

Target: `docs/SELF-HOSTED-PLUGIN-UPDATES.md`

```diff
*** Begin Patch
*** Add File: docs/SELF-HOSTED-PLUGIN-UPDATES.md
+# Self-Hosted Plugin Updates (Canonical)
+
+## Purpose
+
+Provide a reusable, production-safe pattern for WordPress plugin updates outside WordPress.org.
+
+## Architecture
+
+1. Plugin updater client:
+- hook `pre_set_site_transient_update_plugins`
+- hook `plugins_api`
+- optional plugin row action `Check Update`
+
+2. Update server routes:
+- `action=info` returns update metadata JSON
+- `action=download` returns ZIP payload bytes
+- `action=diag` returns safe diagnostics for token/API connectivity
+
+3. Release source:
+- GitHub Releases metadata and assets
+
+## Critical Rule: Asset URL Resolution
+
+Do not construct release package URLs with string interpolation.
+
+Always resolve package URL from release assets (`browser_download_url`) so uploaded asset names and URL paths match exactly.
+
+## Security
+
+- Store source-control token only on updates host runtime/config.
+- Never commit live token values.
+- Recommended env precedence: project token env var -> generic token env var -> host-only config fallback.
+
+## Validation Gates
+
+1. `action=diag` confirms GitHub API probe status and token availability.
+2. `action=info` contains `version` and `download_url`.
+3. Package URL responds with HTTP 2xx and ZIP signature (`PK`).
+4. WP Admin update flow succeeds from prior version to current release.
+
+## Failure Matrix
+
+1. `HTTP 401` on release API:
+- cause: invalid/missing token
+- fix: host token configuration
+
+2. `Download failed. Not Found`:
+- cause: package URL does not match release asset path
+- fix: use release assets API URL
+
+3. `PCLZIP_ERR_BAD_FORMAT`:
+- cause: non-ZIP payload (HTML/error page/redirect chain)
+- fix: ZIP signature validation before serving package
+
+4. `The package could not be installed`:
+- cause: generic upgrader failure from invalid package or structure mismatch
+- fix: verify URL reachability, ZIP integrity, and plugin top-level folder structure
*** End Patch
```

## 3) Add reusable updater verification script

Target: `scripts/verify-updater-package.sh`

```diff
*** Begin Patch
*** Add File: scripts/verify-updater-package.sh
+#!/usr/bin/env bash
+set -euo pipefail
+
+if [[ $# -lt 2 ]]; then
+  echo "Usage: scripts/verify-updater-package.sh <endpoint> <slug>" >&2
+  exit 2
+fi
+
+ENDPOINT="$1"
+SLUG="$2"
+
+INFO_URL="${ENDPOINT}?action=info&slug=${SLUG}&debug=1"
+echo "[verify] info url: ${INFO_URL}"
+
+INFO_JSON="$(curl -fsS "${INFO_URL}")"
+VERSION="$(printf '%s' "${INFO_JSON}" | grep -o '"version":"[^"]*"' | head -n1 | sed -E 's/"version":"([^"]*)"/\1/')"
+DOWNLOAD_URL="$(printf '%s' "${INFO_JSON}" | grep -o '"download_url":"[^"]*"' | head -n1 | sed -E 's/"download_url":"([^"]*)"/\1/')"
+
+if [[ -z "${VERSION}" || -z "${DOWNLOAD_URL}" ]]; then
+  echo "[verify] FAIL: missing version or download_url" >&2
+  exit 12
+fi
+
+echo "[verify] reported version: ${VERSION}"
+echo "[verify] download url: ${DOWNLOAD_URL}"
+
+HTTP_CODE="$(curl -sSIL -o /dev/null -w '%{http_code}' "${DOWNLOAD_URL}")"
+echo "[verify] download url HEAD status: ${HTTP_CODE}"
+if [[ "${HTTP_CODE}" -lt 200 || "${HTTP_CODE}" -ge 400 ]]; then
+  echo "[verify] FAIL: download URL is not reachable" >&2
+  exit 13
+fi
+
+TMP_ZIP="$(mktemp /tmp/updater-package-XXXXXX.zip)"
+trap 'rm -f "${TMP_ZIP}"' EXIT
+
+curl -fsSL "${DOWNLOAD_URL}" -o "${TMP_ZIP}"
+MAGIC="$(od -An -tx1 -N4 "${TMP_ZIP}" | tr -d ' \n')"
+SIZE_BYTES="$(wc -c < "${TMP_ZIP}" | tr -d ' ')"
+
+echo "[verify] downloaded bytes: ${SIZE_BYTES}"
+echo "[verify] zip magic: ${MAGIC}"
+
+if [[ "${MAGIC}" != "504b0304" && "${MAGIC}" != "504b0506" && "${MAGIC}" != "504b0708" ]]; then
+  echo "[verify] FAIL: payload is not a ZIP archive" >&2
+  exit 14
+fi
+
+echo "[verify] PASS"
*** End Patch
```

## Suggested PR metadata

Title:
- `wp-overlay: add canonical self-hosted plugin updater runbook and verification script`

Body:
- add reusable updater runbook covering architecture, diagnostics, and failure matrix
- add package verification script for pre-release gates
- link overlay readme to updater runbook
