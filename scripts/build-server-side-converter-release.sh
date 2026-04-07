#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_ROOT="$REPO_ROOT/tools/server-side-converter"
DIST_ROOT="$REPO_ROOT/dist/server-side-converter"
PLATFORM_SLUG="$(uname -s | tr '[:upper:]' '[:lower:]')-$(uname -m)"
VERSION="${1:-$(date +%Y.%m.%d-%H%M%S)}"
STAGE_DIR="$DIST_ROOT/stage/server-side-converter-${PLATFORM_SLUG}"
ZIP_PATH="$DIST_ROOT/server-side-converter-${PLATFORM_SLUG}-${VERSION}.zip"
MANIFEST_PATH="$DIST_ROOT/server-side-converter-${PLATFORM_SLUG}-${VERSION}.manifest.json"
STATIC_CACHE_DIR="$DIST_ROOT/cache"
STATIC_FFMPEG_URL="${STATIC_FFMPEG_URL:-https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz}"
FFMPEG_SRC=""
FFPROBE_SRC=""
TEMP_DIR=""

cleanup() {
  if [[ -n "$TEMP_DIR" && -d "$TEMP_DIR" ]]; then
    rm -rf "$TEMP_DIR"
  fi
}

trap cleanup EXIT

resolve_ffmpeg_binaries() {
  if [[ "$PLATFORM_SLUG" == "linux-x86_64" ]]; then
    local archive_path="$STATIC_CACHE_DIR/$(basename "$STATIC_FFMPEG_URL")"
    mkdir -p "$STATIC_CACHE_DIR"

    if [[ ! -f "$archive_path" ]]; then
      curl -fsSL "$STATIC_FFMPEG_URL" -o "$archive_path"
    fi

    TEMP_DIR="$(mktemp -d)"
    tar -xJf "$archive_path" -C "$TEMP_DIR"

    local extracted_root
    extracted_root="$(find "$TEMP_DIR" -maxdepth 1 -mindepth 1 -type d | head -n 1)"

    if [[ -z "$extracted_root" ]]; then
      echo "ERROR: Could not extract static ffmpeg bundle from $archive_path" >&2
      exit 4
    fi

    FFMPEG_SRC="$extracted_root/ffmpeg"
    FFPROBE_SRC="$extracted_root/ffprobe"
    return
  fi

  FFMPEG_SRC="$(command -v ffmpeg 2>/dev/null || true)"
  FFPROBE_SRC="$(command -v ffprobe 2>/dev/null || true)"
}

resolve_ffmpeg_binaries

if [[ -z "$FFMPEG_SRC" || -z "$FFPROBE_SRC" ]]; then
  echo "ERROR: ffmpeg and ffprobe must be available on PATH to build the standalone package." >&2
  exit 4
fi

mkdir -p "$DIST_ROOT/stage"
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

rsync -a \
  --exclude 'logs/' \
  --exclude 'tmp/' \
  --exclude 'bin/' \
  "$APP_ROOT/" "$STAGE_DIR/"

mkdir -p "$STAGE_DIR/bin/$PLATFORM_SLUG"
cp "$FFMPEG_SRC" "$STAGE_DIR/bin/$PLATFORM_SLUG/ffmpeg"
cp "$FFPROBE_SRC" "$STAGE_DIR/bin/$PLATFORM_SLUG/ffprobe"
chmod +x "$STAGE_DIR/bin/$PLATFORM_SLUG/ffmpeg" "$STAGE_DIR/bin/$PLATFORM_SLUG/ffprobe" "$STAGE_DIR/start.sh"

cat > "$MANIFEST_PATH" <<EOF
{
  "name": "server-side-converter",
  "version": "$VERSION",
  "platform": "$PLATFORM_SLUG",
  "zip": "$(basename "$ZIP_PATH")",
  "bundled_binaries": [
    "bin/$PLATFORM_SLUG/ffmpeg",
    "bin/$PLATFORM_SLUG/ffprobe"
  ],
  "entrypoints": [
    "start.sh",
    "start.bat",
    "server.js"
  ]
}
EOF

rm -f "$ZIP_PATH"
if command -v zip >/dev/null 2>&1; then
  (
    cd "$(dirname "$STAGE_DIR")"
    zip -rq "$ZIP_PATH" "$(basename "$STAGE_DIR")"
  )
else
  python3 - "$(dirname "$STAGE_DIR")" "$(basename "$STAGE_DIR")" "$ZIP_PATH" <<'PY'
import pathlib
import sys
import zipfile

root = pathlib.Path(sys.argv[1])
folder = root / sys.argv[2]
zip_path = pathlib.Path(sys.argv[3])

with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
    for path in folder.rglob('*'):
        if path.is_file():
            zf.write(path, path.relative_to(root))
PY
fi

echo "Built standalone converter package: $ZIP_PATH"
echo "Manifest: $MANIFEST_PATH"