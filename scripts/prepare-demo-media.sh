#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "Usage: $0 <source_media_dir> <converted_media_dir> <target_media_dir>" >&2
  exit 2
fi

SOURCE_MEDIA_DIR="$1"
CONVERTED_MEDIA_DIR="$2"
TARGET_MEDIA_DIR="$3"

if [[ ! -d "$SOURCE_MEDIA_DIR" ]]; then
  echo "ERROR: source media dir not found: $SOURCE_MEDIA_DIR" >&2
  exit 1
fi

if [[ ! -d "$CONVERTED_MEDIA_DIR" ]]; then
  echo "ERROR: converted media dir not found: $CONVERTED_MEDIA_DIR" >&2
  exit 1
fi

rm -rf "$TARGET_MEDIA_DIR"
mkdir -p "$TARGET_MEDIA_DIR"

while IFS= read -r -d '' file; do
  rel_path="${file#"$SOURCE_MEDIA_DIR"/}"
  case "$rel_path" in
    */video*.mp4|*/video*.webm)
      continue
      ;;
  esac

  mkdir -p "$TARGET_MEDIA_DIR/$(dirname "$rel_path")"
  cp "$file" "$TARGET_MEDIA_DIR/$rel_path"
done < <(find "$SOURCE_MEDIA_DIR" -type f -print0)

while IFS= read -r -d '' file; do
  rel_path="${file#"$CONVERTED_MEDIA_DIR"/}"
  mkdir -p "$TARGET_MEDIA_DIR/$(dirname "$rel_path")"
  cp "$file" "$TARGET_MEDIA_DIR/$rel_path"
done < <(find "$CONVERTED_MEDIA_DIR" -type f -name '*.webm' -print0)

vendor_count=$(find "$TARGET_MEDIA_DIR" -mindepth 1 -maxdepth 1 -type d | wc -l)
video_count=$(find "$TARGET_MEDIA_DIR" -type f -name '*.webm' | wc -l)

echo "[prepare-demo-media] Target : $TARGET_MEDIA_DIR"
echo "[prepare-demo-media] Vendors: $vendor_count"
echo "[prepare-demo-media] Videos : $video_count"
