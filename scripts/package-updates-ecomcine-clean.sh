#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

SOURCE_DIR="$REPO_ROOT/updates.domain.com"
OUT_ROOT="$REPO_ROOT/deploy"
OUT_DIR="$OUT_ROOT/updates-ecomcine-clean"
ZIP_PATH="$OUT_ROOT/updates-ecomcine-clean.zip"

if [[ ! -f "$SOURCE_DIR/update-server.php" || ! -f "$SOURCE_DIR/config.php" || ! -f "$SOURCE_DIR/index.html" ]]; then
  echo "ERROR: expected source files missing in $SOURCE_DIR" >&2
  exit 1
fi

mkdir -p "$OUT_ROOT"
rm -rf "$OUT_DIR"
mkdir -p "$OUT_DIR/cache"

# Copy only canonical deploy files to guarantee no ADS/Zone.Identifier artifacts are carried over.
cp "$SOURCE_DIR/update-server.php" "$OUT_DIR/update-server.php"
cp "$SOURCE_DIR/config.php" "$OUT_DIR/config.php"
cp "$SOURCE_DIR/index.html" "$OUT_DIR/index.html"

# Remove any accidental metadata artifacts if present.
find "$OUT_DIR" -type f \( -name '*:Zone.Identifier' -o -name '*Zone.Identifier' \) -delete

rm -f "$ZIP_PATH"
if command -v zip >/dev/null 2>&1; then
  (
    cd "$OUT_ROOT"
    zip -rq "$(basename "$ZIP_PATH")" "$(basename "$OUT_DIR")"
  )
  echo "Built clean updater bundle: $ZIP_PATH"
elif command -v python3 >/dev/null 2>&1; then
  python3 - "$OUT_ROOT" "$(basename "$OUT_DIR")" "$ZIP_PATH" <<'PY'
import os
import sys
import zipfile

out_root = sys.argv[1]
folder_name = sys.argv[2]
zip_path = sys.argv[3]
base_dir = os.path.join(out_root, folder_name)

with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
    for root, _, files in os.walk(base_dir):
        for file_name in files:
            file_path = os.path.join(root, file_name)
            arcname = os.path.relpath(file_path, out_root)
            zf.write(file_path, arcname)
PY
  echo "Built clean updater bundle: $ZIP_PATH"
else
  echo "Built clean updater folder: $OUT_DIR"
  echo "zip not found; upload folder contents directly."
fi

echo "Deploy files:"
echo " - $OUT_DIR/update-server.php"
echo " - $OUT_DIR/config.php"
echo " - $OUT_DIR/index.html"
echo " - $OUT_DIR/cache/"
