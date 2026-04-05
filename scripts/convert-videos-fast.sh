#!/bin/bash
# Fast Video Conversion Script - Convert MP4 to WebM with Progress
# Optimized for speed with parallel processing

set -e

INPUT_DIR="${1:-demos/topdoctorchannel/media}"
OUTPUT_DIR="${2:-demos/topdoctorchannel/media-webm-full}"
QUALITY="${3:-23}"
CRF="${4:-30}"

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "=========================================="
echo "Fast Video Conversion Script"
echo "=========================================="
echo "Input: $INPUT_DIR"
echo "Output: $OUTPUT_DIR"
echo "Quality: $QUALITY | CRF: $CRF"
echo "=========================================="

# Count videos
TOTAL_VIDEOS=$(find "$INPUT_DIR" -name "*.mp4" | wc -l)
echo "Found $TOTAL_VIDEOS videos to convert"

get_file_size() {
    local FILE_PATH="$1"
    local SIZE=""

    if SIZE=$(stat -c%s "$FILE_PATH" 2>/dev/null); then
        printf '%s' "$SIZE"
        return 0
    fi

    if SIZE=$(stat -f %z "$FILE_PATH" 2>/dev/null); then
        printf '%s' "$SIZE"
        return 0
    fi

    return 1
}

# Function to convert a single video
convert_video() {
    local INPUT="$1"
    local OUTPUT="$2"
    local VIDEO_NAME=$(basename "$INPUT" .mp4)

    mkdir -p "$(dirname "$OUTPUT")"
    
    echo "Converting: $VIDEO_NAME"
    
    rm -f "$OUTPUT"

    if ! ffmpeg -y -nostdin -i "$INPUT" \
        -c:v libvpx-vp9 \
        -b:v 0 \
        -crf $CRF \
        -deadline good \
        -cpu-used 4 \
        -row-mt 1 \
        -pix_fmt yuv420p \
        -c:a libopus \
        -b:a 96k \
        "$OUTPUT" >/dev/null 2>&1; then
        return 1
    fi

    if [[ ! -s "$OUTPUT" ]]; then
        return 1
    fi
    
    local NEW_SIZE="$(get_file_size "$OUTPUT")"
    local ORIGINAL_SIZE="$(get_file_size "$INPUT")"
    
    if [[ "$ORIGINAL_SIZE" =~ ^[0-9]+$ ]] && [ "$ORIGINAL_SIZE" -gt 0 ]; then
        local COMPRESSION=$(( (ORIGINAL_SIZE - NEW_SIZE) * 100 / ORIGINAL_SIZE ))
        echo "  ✅ $VIDEO_NAME: $(numfmt --to=iec $NEW_SIZE) (${COMPRESSION}% smaller)"
    else
        echo "  ✅ $VIDEO_NAME: $(numfmt --to=iec $NEW_SIZE)"
    fi
}

# Convert all videos (sequential for stability)
CONVERTED=0
FAILED=0

while IFS= read -r -d '' VIDEO; do
    RELATIVE_PATH="${VIDEO#"$INPUT_DIR"/}"
    OUTPUT_FILE="$OUTPUT_DIR/${RELATIVE_PATH%.mp4}.webm"

    if convert_video "$VIDEO" "$OUTPUT_FILE"; then
        CONVERTED=$((CONVERTED + 1))
    else
        echo "  ❌ Failed: $RELATIVE_PATH"
        FAILED=$((FAILED + 1))
    fi
done < <(find "$INPUT_DIR" -type f -name "*.mp4" -print0)

echo ""
echo "=========================================="
echo "Conversion Complete!"
echo "=========================================="
echo "Successfully converted: $CONVERTED videos"
echo "Failed: $FAILED videos"
echo "Output directory: $OUTPUT_DIR"
echo "=========================================="

# Generate summary
echo ""
echo "File sizes:"
find "$OUTPUT_DIR" -type f -name "*.webm" -print0 | xargs -0 ls -lh 2>/dev/null | head -10
