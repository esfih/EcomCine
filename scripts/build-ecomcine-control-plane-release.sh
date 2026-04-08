#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_DIR="$REPO_ROOT/ecomcine-control-plane"
DIST_DIR="$REPO_ROOT/dist"
STAGE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/ecomcine-cp-release.XXXXXX")"
STAGE_DIR="$STAGE_ROOT/ecomcine-control-plane"

cleanup() {
  rm -rf "$STAGE_ROOT"
}

trap cleanup EXIT

if [[ ! -f "$PLUGIN_DIR/ecomcine-control-plane.php" ]]; then
  echo "ERROR: missing plugin bootstrap at $PLUGIN_DIR/ecomcine-control-plane.php" >&2
  exit 1
fi

mkdir -p "$DIST_DIR"
mkdir -p "$STAGE_DIR"

rsync -a --delete \
  --exclude '.git/' \
  --exclude '.DS_Store' \
  --exclude 'node_modules/' \
  --exclude 'dist/' \
  "$PLUGIN_DIR/" "$STAGE_DIR/"

VERSION="$(grep -E '^ \* Version:' "$PLUGIN_DIR/ecomcine-control-plane.php" | head -n1 | sed -E 's/^ \* Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  VERSION="unknown"
fi

ARCHIVE_BASENAME="ecomcine-control-plane-${VERSION}"
ARCHIVE_EXT="zip"
ARCHIVE_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.zip"
MANIFEST_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.manifest.json"

rm -f "$DIST_DIR/${ARCHIVE_BASENAME}.zip" "$DIST_DIR/${ARCHIVE_BASENAME}.tar.gz" "$MANIFEST_PATH"

if command -v zip >/dev/null 2>&1; then
  (
    cd "$STAGE_ROOT"
    zip -rq "$ARCHIVE_PATH" ecomcine-control-plane
  )
  ARCHIVE_EXT="zip"
  ARCHIVE_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.zip"
elif command -v python3 >/dev/null 2>&1; then
  python3 - "$STAGE_ROOT" "$ARCHIVE_PATH" <<'PY'
import os
import sys
import zipfile

stage_root = sys.argv[1]
zip_path = sys.argv[2]
plugin_dir = os.path.join(stage_root, 'ecomcine-control-plane')

with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
    for root, _, files in os.walk(plugin_dir):
        for file_name in files:
            file_path = os.path.join(root, file_name)
            arcname = os.path.relpath(file_path, stage_root)
            zf.write(file_path, arcname)
PY
  ARCHIVE_EXT="zip"
  ARCHIVE_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.zip"
else
  (
    cd "$STAGE_ROOT"
    tar -czf "$DIST_DIR/${ARCHIVE_BASENAME}.tar.gz" ecomcine-control-plane
  )
  ARCHIVE_EXT="tar.gz"
  ARCHIVE_PATH="$DIST_DIR/${ARCHIVE_BASENAME}.tar.gz"
fi

SHA256="$(sha256sum "$ARCHIVE_PATH" | awk '{print $1}')"
BUILD_TIME="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

cat > "$MANIFEST_PATH" <<JSON
{
  "plugin": "ecomcine-control-plane",
  "version": "${VERSION}",
  "artifact": "$(basename "$ARCHIVE_PATH")",
  "artifact_type": "${ARCHIVE_EXT}",
  "sha256": "${SHA256}",
  "built_at_utc": "${BUILD_TIME}",
  "source": "local"
}
JSON

echo "Built release artifact: $ARCHIVE_PATH"
echo "Manifest: $MANIFEST_PATH"