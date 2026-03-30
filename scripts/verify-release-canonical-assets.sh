#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/verify-release-canonical-assets.sh <tag> <version> [slug]
# Example: scripts/verify-release-canonical-assets.sh v0.1.2 0.1.2 ecomcine

if [[ $# -lt 2 ]]; then
  echo "Usage: scripts/verify-release-canonical-assets.sh <tag> <version> [slug]" >&2
  exit 2
fi

TAG="$1"
VERSION="$2"
SLUG="${3:-ecomcine}"

EXPECTED_ZIP="${SLUG}-${VERSION}.zip"
EXPECTED_MANIFEST="${SLUG}-${VERSION}.manifest.json"

ASSET_NAMES="$(gh release view "$TAG" --json assets --jq '.assets[].name')"

if ! printf '%s\n' "$ASSET_NAMES" | grep -Fxq "$EXPECTED_ZIP"; then
  echo "[release-verify] FAIL: missing canonical zip asset: $EXPECTED_ZIP" >&2
  exit 4
fi

if ! printf '%s\n' "$ASSET_NAMES" | grep -Fxq "$EXPECTED_MANIFEST"; then
  echo "[release-verify] FAIL: missing canonical manifest asset: $EXPECTED_MANIFEST" >&2
  exit 4
fi

URL="https://github.com/esfih/EcomCine/releases/download/${TAG}/${EXPECTED_ZIP}"
HTTP_CODE="$(curl -sSIL -o /dev/null -w '%{http_code}' "$URL")"

if [[ "$HTTP_CODE" -lt 200 || "$HTTP_CODE" -ge 400 ]]; then
  echo "[release-verify] FAIL: canonical zip URL not reachable: $URL (HTTP $HTTP_CODE)" >&2
  exit 5
fi

echo "[release-verify] PASS"
echo "[release-verify] zip: $URL"
