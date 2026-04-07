#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="$REPO_ROOT/dist"
PLUGIN_TREE="HEAD:ecomcine"

if ! git -C "$REPO_ROOT" cat-file -e "$PLUGIN_TREE" 2>/dev/null; then
  echo "ERROR: missing plugin tree at ${PLUGIN_TREE}" >&2
  exit 1
fi

VERSION="$(git -C "$REPO_ROOT" show HEAD:ecomcine/ecomcine.php | grep -E '^ \* Version:' | head -n1 | sed -E 's/^ \* Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  echo "ERROR: failed to resolve plugin version from committed HEAD" >&2
  exit 1
fi

mkdir -p "$DIST_DIR"

ARCHIVE_BASENAME="ecomcine-${VERSION}"
ARCHIVE_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.zip"
MANIFEST_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.manifest.json"

rm -f "$ARCHIVE_PATH" "$DIST_DIR/${ARCHIVE_BASENAME}.tar.gz" "$MANIFEST_PATH"

git -C "$REPO_ROOT" archive \
  --format=zip \
  --prefix=ecomcine/ \
  --output="$ARCHIVE_PATH" \
  "$PLUGIN_TREE"

SHA256="$(sha256sum "$ARCHIVE_PATH" | awk '{print $1}')"
BUILD_TIME="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

cat > "$MANIFEST_PATH" <<JSON
{
  "plugin": "ecomcine",
  "version": "${VERSION}",
  "artifact": "$(basename "$ARCHIVE_PATH")",
  "artifact_type": "zip",
  "sha256": "${SHA256}",
  "built_at_utc": "${BUILD_TIME}",
  "source": "git-head"
}
JSON

echo "Built clean HEAD release artifact: $ARCHIVE_PATH"
echo "Manifest: $MANIFEST_PATH"