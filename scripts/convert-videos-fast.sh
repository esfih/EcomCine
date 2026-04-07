#!/bin/bash
# Fast Video Conversion Script - Convert MP4 to player-optimized WebM
# Tuned for browser playback, faster seeks, clean startup, and sidecar posters.

set -e

INPUT_DIR="${1:-demos/topdoctorchannel/media}"
OUTPUT_DIR="${2:-demos/topdoctorchannel/media-webm-full}"
QUALITY="${3:-23}"
CRF="${4:-32}"
POSTER_WIDTH="${POSTER_WIDTH:-1280}"
POSTER_OFFSET="${POSTER_OFFSET:-0.35}"
KEYFRAME_INTERVAL="${KEYFRAME_INTERVAL:-48}"
VIDEO_THREADS="${VIDEO_THREADS:-4}"
AUDIO_BITRATE="${AUDIO_BITRATE:-96k}"
AUDIO_VBR_MODE="${AUDIO_VBR_MODE:-constrained}"

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "=========================================="
echo "Fast Video Conversion Script"
echo "=========================================="
echo "Input: $INPUT_DIR"
echo "Output: $OUTPUT_DIR"
echo "Quality: $QUALITY | CRF: $CRF"
echo "Poster width: $POSTER_WIDTH | Poster offset: ${POSTER_OFFSET}s"
echo "Keyframe interval: $KEYFRAME_INTERVAL | Threads: $VIDEO_THREADS"
echo "Audio bitrate: $AUDIO_BITRATE | Audio VBR: $AUDIO_VBR_MODE"
echo "=========================================="

if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "ERROR: ffmpeg is not installed or not in PATH" >&2
    exit 1
fi

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
    local POSTER_OUTPUT="${OUTPUT%.webm}.poster.webp"

    mkdir -p "$(dirname "$OUTPUT")"
    
    echo "Converting: $VIDEO_NAME"
    
    rm -f "$OUTPUT"
    rm -f "$POSTER_OUTPUT"

    if ! ffmpeg -y -nostdin -i "$INPUT" \
        -c:v libvpx-vp9 \
        -b:v 0 \
        -crf "$CRF" \
        -deadline good \
        -cpu-used 3 \
        -row-mt 1 \
        -tile-columns 1 \
        -frame-parallel 1 \
        -lag-in-frames 16 \
        -g "$KEYFRAME_INTERVAL" \
        -keyint_min "$KEYFRAME_INTERVAL" \
        -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2,format=yuv420p" \
        -pix_fmt yuv420p \
        -map_metadata -1 \
        -map_chapters -1 \
        -c:a libopus \
        -b:a "$AUDIO_BITRATE" \
        -vbr "$AUDIO_VBR_MODE" \
        -compression_level 10 \
        -threads "$VIDEO_THREADS" \
        "$OUTPUT" >/dev/null 2>&1; then
        return 1
    fi

    if [[ ! -s "$OUTPUT" ]]; then
        return 1
    fi

	ffmpeg -y -nostdin -ss "$POSTER_OFFSET" -i "$INPUT" \
		-frames:v 1 \
		-vf "scale='min(${POSTER_WIDTH},iw)':-2" \
		-c:v libwebp \
		-quality 80 \
		-compression_level 6 \
		-preset photo \
		-an \
		-map_metadata -1 \
		"$POSTER_OUTPUT" >/dev/null 2>&1 || true
    
    local NEW_SIZE="$(get_file_size "$OUTPUT")"
    local ORIGINAL_SIZE="$(get_file_size "$INPUT")"
    
    if [[ "$ORIGINAL_SIZE" =~ ^[0-9]+$ ]] && [ "$ORIGINAL_SIZE" -gt 0 ]; then
        local COMPRESSION=$(( (ORIGINAL_SIZE - NEW_SIZE) * 100 / ORIGINAL_SIZE ))
        echo "  ✅ $VIDEO_NAME: $(numfmt --to=iec $NEW_SIZE) (${COMPRESSION}% smaller)"
    else
        echo "  ✅ $VIDEO_NAME: $(numfmt --to=iec $NEW_SIZE)"
    fi

	if [[ -s "$POSTER_OUTPUT" ]]; then
		echo "     poster: $(basename "$POSTER_OUTPUT")"
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
echo ""
echo "Posters:"
find "$OUTPUT_DIR" -type f -name "*.poster.webp" -print0 | xargs -0 ls -lh 2>/dev/null | head -10
