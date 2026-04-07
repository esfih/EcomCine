#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/release-upload-canonical-assets.sh <tag> <version> [slug]
# Example: scripts/release-upload-canonical-assets.sh v0.1.2 0.1.2 ecomcine

if [[ $# -lt 2 ]]; then
  echo "Usage: scripts/release-upload-canonical-assets.sh <tag> <version> [slug]" >&2
  exit 2
fi

TAG="$1"
VERSION="$2"
SLUG="${3:-ecomcine}"

ZIP_SRC="dist/${SLUG}-${VERSION}.zip"
MANIFEST_SRC="dist/${SLUG}-${VERSION}.manifest.json"

if [[ ! -f "$ZIP_SRC" ]]; then
  echo "ERROR: missing zip artifact: $ZIP_SRC" >&2
  exit 3
fi

if [[ ! -f "$MANIFEST_SRC" ]]; then
  echo "ERROR: missing manifest artifact: $MANIFEST_SRC" >&2
  exit 3
fi

ZIP_ALIAS="${SLUG}-${VERSION}.zip"
MANIFEST_ALIAS="${SLUG}-${VERSION}.manifest.json"

echo "[release-upload] tag: ${TAG}"
echo "[release-upload] zip: ${ZIP_SRC} -> ${ZIP_ALIAS}"
echo "[release-upload] manifest: ${MANIFEST_SRC} -> ${MANIFEST_ALIAS}"

gh release upload "$TAG" \
  "$ZIP_SRC#$ZIP_ALIAS" \
  "$MANIFEST_SRC#$MANIFEST_ALIAS" \
  --clobber

echo "[release-upload] PASS"
