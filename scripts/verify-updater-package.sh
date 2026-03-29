#!/usr/bin/env bash
set -euo pipefail

ENDPOINT="${1:-https://updates.ecomcine.com/update-server.php}"
SLUG="${2:-ecomcine}"

INFO_URL="${ENDPOINT}?action=info&slug=${SLUG}&debug=1"

echo "[verify] info url: ${INFO_URL}"
INFO_JSON="$(curl -fsS "${INFO_URL}")"

VERSION="$(printf '%s' "${INFO_JSON}" | grep -o '"version":"[^"]*"' | head -n1 | sed -E 's/"version":"([^"]*)"/\1/')"
DOWNLOAD_URL="$(printf '%s' "${INFO_JSON}" | grep -o '"download_url":"[^"]*"' | head -n1 | sed -E 's/"download_url":"([^"]*)"/\1/')"

if [[ -z "${VERSION}" || -z "${DOWNLOAD_URL}" ]]; then
  echo "[verify] FAIL: missing version or download_url in info JSON" >&2
  exit 12
fi

echo "[verify] reported version: ${VERSION}"
echo "[verify] download url: ${DOWNLOAD_URL}"

HTTP_CODE="$(curl -sSIL -o /dev/null -w '%{http_code}' "${DOWNLOAD_URL}")"
echo "[verify] download url HEAD status: ${HTTP_CODE}"
if [[ "${HTTP_CODE}" -lt 200 || "${HTTP_CODE}" -ge 400 ]]; then
  echo "[verify] FAIL: download URL is not reachable" >&2
  exit 13
fi

TMP_ZIP="$(mktemp /tmp/updater-package-XXXXXX.zip)"
trap 'rm -f "${TMP_ZIP}"' EXIT

curl -fsSL "${DOWNLOAD_URL}" -o "${TMP_ZIP}"
MAGIC="$(od -An -tx1 -N4 "${TMP_ZIP}" | tr -d ' \n')"
SIZE_BYTES="$(wc -c < "${TMP_ZIP}" | tr -d ' ')"

echo "[verify] downloaded bytes: ${SIZE_BYTES}"
echo "[verify] zip magic: ${MAGIC}"

if [[ "${MAGIC}" != "504b0304" && "${MAGIC}" != "504b0506" && "${MAGIC}" != "504b0708" ]]; then
  echo "[verify] FAIL: payload is not a ZIP archive" >&2
  exit 14
fi

echo "[verify] PASS: updater info + package URL + ZIP signature are valid"
