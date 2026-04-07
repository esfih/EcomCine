#!/bin/bash
# Video Optimization Script - Convert MP4 to WebM
# This script converts MP4 videos to WebM format for better web performance

set -e

# Configuration
INPUT_DIR="${1:-wp-content/uploads/ecomcine-demo}"
OUTPUT_DIR="${2:-wp-content/uploads/ecomcine-demo-webm}"
QUALITY="${3:-23}"  # WebM quality (0-63, lower = better quality, larger file)
CRF="${4:-30}"      # Constant Rate Factor for VP8/VP9

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "=========================================="
echo "Video Optimization Script"
echo "=========================================="
echo "Input directory: $INPUT_DIR"
echo "Output directory: $OUTPUT_DIR"
echo "Quality: $QUALITY"
echo "CRF: $CRF"
echo "=========================================="

# Check if ffmpeg is available
if ! command -v ffmpeg &> /dev/null; then
    echo "ERROR: ffmpeg is not installed. Please install it first."
    echo "Ubuntu/Debian: sudo apt-get install ffmpeg"
    echo "macOS: brew install ffmpeg"
    exit 1
fi

# Count total videos
TOTAL_VIDEOS=$(find "$INPUT_DIR" -name "*.mp4" | wc -l)
echo "Found $TOTAL_VIDEOS MP4 videos to convert"

# Convert each video
CONVERTED=0
FAILED=0

for VIDEO in $(find "$INPUT_DIR" -name "*.mp4"); do
    VIDEO_NAME=$(basename "$VIDEO" .mp4)
    OUTPUT_FILE="$OUTPUT_DIR/${VIDEO_NAME}.webm"
    
    echo ""
    echo "Converting: $VIDEO_NAME"
    echo "-------------------------------------------"
    
    # Get original file size
    ORIGINAL_SIZE=$(stat -f%z "$VIDEO" 2>/dev/null || stat -c%s "$VIDEO" 2>/dev/null)
    echo "Original size: $(numfmt --to=iec $ORIGINAL_SIZE)"
    
    # Convert to WebM with VP9 codec
    # Using CRF for quality control and preset for encoding speed
    ffmpeg -i "$VIDEO" \
        -c:v libvpx-vp9 \
        -b:v 0 \
        -crf $CRF \
        -cpu-used 4 \
        -row-mt 1 \
        -tile-columns 2 \
        -tile-rows 1 \
        -c:a libopus \
        -b:a 128k \
        -strict experimental \
        "$OUTPUT_FILE" 2>&1 | grep -E "(frame|time|speed|size)" | tail -5
    
    # Get new file size
    NEW_SIZE=$(stat -f%z "$OUTPUT_FILE" 2>/dev/null || stat -c%s "$OUTPUT_FILE" 2>/dev/null)
    echo "New size: $(numfmt --to=iec $NEW_SIZE)"
    
    # Calculate compression ratio
    if [ $ORIGINAL_SIZE -gt 0 ]; then
        COMPRESSION=$(( (ORIGINAL_SIZE - NEW_SIZE) * 100 / ORIGINAL_SIZE ))
        echo "Compression: ${COMPRESSION}% smaller"
    fi
    
    # Check if conversion was successful
    if [ -f "$OUTPUT_FILE" ] && [ -s "$OUTPUT_FILE" ]; then
        echo "✅ Successfully converted: $VIDEO_NAME"
        CONVERTED=$((CONVERTED + 1))
    else
        echo "❌ Failed to convert: $VIDEO_NAME"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
echo "=========================================="
echo "Conversion Summary"
echo "=========================================="
echo "Successfully converted: $CONVERTED videos"
echo "Failed: $FAILED videos"
echo "Output directory: $OUTPUT_DIR"
echo "=========================================="

# Generate comparison report
echo ""
echo "Generating comparison report..."
cat > "$OUTPUT_DIR/comparison_report.txt" << EOF
Video Conversion Report
=======================
Generated: $(date)
Quality Setting: $QUALITY
CRF: $CRF

Original Videos:
$(ls -lh "$INPUT_DIR"/*.mp4 2>/dev/null | awk '{print $9, $5}')

Converted Videos:
$(ls -lh "$OUTPUT_DIR"/*.webm 2>/dev/null | awk '{print $9, $5}')

Compression Summary:
- Total videos processed: $((CONVERTED + FAILED))
- Successfully converted: $CONVERTED
- Failed: $FAILED

Note: WebM files are typically 30-50% smaller than MP4 while maintaining similar quality.
EOF

echo "Report saved to: $OUTPUT_DIR/comparison_report.txt"
echo ""
echo "Next steps:"
echo "1. Review the comparison report"
echo "2. Test WebM videos on target server"
echo "3. If compression is good, update demo data package"
echo "4. Re-upload optimized videos to live instance"
