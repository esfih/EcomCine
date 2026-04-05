#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <pack-id> [converted_media_dir] [target_media_dir] [quality] [crf]" >&2
  exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

PACK_ID="$1"
PACK_DIR="$REPO_ROOT/demos/$PACK_ID"
SOURCE_MEDIA_DIR="$PACK_DIR/media-original"
CONVERTED_MEDIA_DIR="${2:-$PACK_DIR/media-webm}"
TARGET_MEDIA_DIR="${3:-$PACK_DIR/media}"
QUALITY="${4:-23}"
CRF="${5:-30}"
VENDOR_JSON="$PACK_DIR/vendor-data.json"

resolve_source_media_dir() {
  local pack_dir="$1"
  local candidate=""

  if find "$pack_dir/media-original" -type f -name '*.mp4' | grep -q .; then
    printf '%s' "$pack_dir/media-original"
    return 0
  fi

  while IFS= read -r candidate; do
    if find "$candidate" -type f -name '*.mp4' | grep -q .; then
      printf '%s' "$candidate"
      return 0
    fi
  done < <(find "$pack_dir" -maxdepth 1 -mindepth 1 -type d -name 'media-backup-*' | sort -r)

  return 1
}

if [[ ! -d "$PACK_DIR" ]]; then
  echo "ERROR: demo pack not found: $PACK_DIR" >&2
  exit 1
fi

if [[ ! -d "$SOURCE_MEDIA_DIR" ]]; then
  echo "ERROR: canonical source media dir not found: $SOURCE_MEDIA_DIR" >&2
  exit 1
fi

if [[ ! -f "$VENDOR_JSON" ]]; then
  echo "ERROR: vendor-data.json missing: $VENDOR_JSON" >&2
  exit 1
fi

for cmd in jq find wc; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $cmd" >&2
    exit 1
  fi
done

SOURCE_MEDIA_DIR="$(resolve_source_media_dir "$PACK_DIR")" || {
  echo "ERROR: no source media tree with MP4 inputs found under $PACK_DIR (checked media-original/ and media-backup-*/)." >&2
  exit 1
}

echo "[rebuild-demo-media] Pack      : $PACK_ID"
echo "[rebuild-demo-media] Source    : $SOURCE_MEDIA_DIR"
echo "[rebuild-demo-media] Converted : $CONVERTED_MEDIA_DIR"
echo "[rebuild-demo-media] Target    : $TARGET_MEDIA_DIR"

bash "$SCRIPT_DIR/convert-videos-fast.sh" "$SOURCE_MEDIA_DIR" "$CONVERTED_MEDIA_DIR" "$QUALITY" "$CRF"
bash "$SCRIPT_DIR/prepare-demo-media.sh" "$SOURCE_MEDIA_DIR" "$CONVERTED_MEDIA_DIR" "$TARGET_MEDIA_DIR"

source_video_count=$(find "$SOURCE_MEDIA_DIR" -type f -name '*.mp4' | wc -l | tr -d ' ')
converted_video_count=$(find "$CONVERTED_MEDIA_DIR" -type f -name '*.webm' | wc -l | tr -d ' ')
target_video_count=$(find "$TARGET_MEDIA_DIR" -type f -name '*.webm' | wc -l | tr -d ' ')

if [[ "$source_video_count" != "$converted_video_count" ]]; then
  echo "ERROR: converted video count ($converted_video_count) does not match source mp4 count ($source_video_count)" >&2
  exit 1
fi

if [[ "$converted_video_count" != "$target_video_count" ]]; then
  echo "ERROR: target webm count ($target_video_count) does not match converted webm count ($converted_video_count)" >&2
  exit 1
fi

missing_refs=()
while IFS= read -r ref; do
  [[ -z "$ref" ]] && continue
  rel_path="${ref#media/}"
  if [[ ! -f "$TARGET_MEDIA_DIR/$rel_path" ]]; then
    missing_refs+=("$ref")
  fi
done < <(jq -r '
  .vendors[]
  | (.media // {}) as $media
  | [($media.banner // empty), ($media.gravatar // empty)]
  | .[]
' "$VENDOR_JSON")

if (( ${#missing_refs[@]} > 0 )); then
  echo "ERROR: rebuilt target media tree is missing vendor-data.json references:" >&2
  printf '  %s\n' "${missing_refs[@]}" >&2
  exit 1
fi

echo "[rebuild-demo-media] Source MP4s : $source_video_count"
echo "[rebuild-demo-media] WebMs       : $target_video_count"
echo "[rebuild-demo-media] Validation  : vendor-data.json banner/gravatar paths resolved in target media/"
echo "[rebuild-demo-media] READY       : run ./scripts/run-catalog-command.sh demos.release <version> --push"