#!/bin/bash
# Convert all MP4 videos to WebM with VP9 codec
# This script processes videos one by one to avoid subshell issues

set -e

INPUT_DIR="${1:-demos/topdoctorchannel/media-original}"
OUTPUT_DIR="${2:-demos/topdoctorchannel/media-webm}"
QUALITY="${3:-23}"
CRF="${4:-30}"

mkdir -p "$OUTPUT_DIR"

echo "====== Video Conversion Script ======"
echo "Input:  $INPUT_DIR"
echo "Output: $OUTPUT_DIR"
echo "Quality: $QUALITY | CRF: $CRF"
echo "======================================"

# Get list of videos
VIDEOS=$(find "$INPUT_DIR" -name "*.mp4" -o -name "*.webm" | sort)
TOTAL=$(echo "$VIDEOS" | wc -l)
COUNT=0

for INPUT in $VIDEOS; do
    COUNT=$((COUNT + 1))
    echo "[$COUNT/$TOTAL] Converting: $INPUT"
    
    # Get relative path and output filename
    REL_PATH="${INPUT#$INPUT_DIR/}"
    OUTPUT="$OUTPUT_DIR/$REL_PATH"
    OUTPUT_DIR_NAME=$(dirname "$OUTPUT")
    
    mkdir -p "$OUTPUT_DIR_NAME"
    
    # Convert video
    ffmpeg -i "$INPUT" \
        -c:v libvpx-vp9 \
        -b:v 0 \
        -crf $CRF \
        -cpu-used 6 \
        -row-mt 1 \
        -tune vp9 \
        -c:a libopus \
        -b:a 96k \
        -strict experimental \
        "$OUTPUT" 2>&1 | tail -5
    
    # Check output size
    if [ -f "$OUTPUT" ]; then
        SIZE=$(stat -c%s "$OUTPUT" 2>/dev/null || stat -f%z "$OUTPUT" 2>/dev/null)
        ORIG_SIZE=$(stat -c%s "$INPUT" 2>/dev/null || stat -f%z "$INPUT" 2>/dev/null)
        
        if [ $ORIG_SIZE -gt 0 ]; then
            PERCENT=$((SIZE * 100 / ORIG_SIZE))
            echo "  ✅ $REL_PATH: $(du -h "$OUTPUT" | cut -f1) ($PERCENT% of original)"
        else
            echo "  ✅ $REL_PATH: $(du -h "$OUTPUT" | cut -f1)"
        fi
    else
        echo "  ❌ Failed to convert: $REL_PATH"
    fi
done

echo "======================================"
echo "Conversion complete!"
echo "Total: $TOTAL videos"
echo "Output: $OUTPUT_DIR"
echo "======================================"
