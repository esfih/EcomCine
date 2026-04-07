#!/bin/bash
# Demo Data Update Script - Replace MP4 with WebM
# This script updates the demo data package with WebM video files

set -e

echo "=========================================="
echo "Demo Data Update Script"
echo "=========================================="

# Configuration
DEMO_DATA_DIR="${1:-demos/topdoctorchannel}"
MEDIA_DIR="$DEMO_DATA_DIR/media"
WEBM_DIR="$DEMO_DATA_DIR/media-webm"

# Check if WebM files exist
if [ ! -d "$WEBM_DIR" ]; then
    echo "ERROR: WebM directory not found: $WEBM_DIR"
    echo "Please run the video optimization script first."
    exit 1
fi

# Count original videos
ORIGINAL_COUNT=$(find "$MEDIA_DIR" -name "*.mp4" | wc -l)
WEBM_COUNT=$(find "$WEBM_DIR" -name "*.webm" | wc -l)

echo "Original MP4 videos: $ORIGINAL_COUNT"
echo "Converted WebM videos: $WEBM_COUNT"

if [ "$ORIGINAL_COUNT" -ne "$WEBM_COUNT" ]; then
    echo "WARNING: Mismatch in video counts!"
    echo "This may indicate incomplete conversion."
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Create backup of original videos
echo ""
echo "Creating backup of original videos..."
BACKUP_DIR="$DEMO_DATA_DIR/media-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r "$MEDIA_DIR" "$BACKUP_DIR/"
echo "Backup created: $BACKUP_DIR"

# Replace MP4 with WebM
echo ""
echo "Replacing MP4 with WebM..."
for WEBM_FILE in $(find "$WEBM_DIR" -name "*.webm"); do
    VIDEO_NAME=$(basename "$WEBM_FILE" .webm)
    
    # Find corresponding MP4 file
    MP4_FILE=$(find "$MEDIA_DIR" -name "${VIDEO_NAME}.mp4" | head -1)
    
    if [ -z "$MP4_FILE" ]; then
        echo "WARNING: No matching MP4 found for $VIDEO_NAME"
        continue
    fi
    
    # Get the relative path from media directory
    REL_PATH=$(dirname "$MP4_FILE" | sed "s|$MEDIA_DIR||")
    TARGET_DIR="$MEDIA_DIR$REL_PATH"
    TARGET_FILE="$TARGET_DIR/${VIDEO_NAME}.mp4"
    
    echo "Replacing: $TARGET_FILE -> $WEBM_FILE"
    
    # Create target directory if it doesn't exist
    mkdir -p "$TARGET_DIR"
    
    # Replace MP4 with WebM (rename to .mp4 extension for compatibility)
    # Note: We're keeping the .mp4 extension but using WebM content
    # This allows existing code to work without changes
    cp "$WEBM_FILE" "$TARGET_FILE"
    
    echo "✅ Replaced: $TARGET_FILE"
done

# Verify replacement
echo ""
echo "Verifying replacement..."
NEW_MP4_COUNT=$(find "$MEDIA_DIR" -name "*.mp4" | wc -l)
echo "Total MP4 files after replacement: $NEW_MP4_COUNT"

if [ "$NEW_MP4_COUNT" -ne "$ORIGINAL_COUNT" ]; then
    echo "ERROR: File count mismatch after replacement!"
    exit 1
fi

echo ""
echo "=========================================="
echo "Demo Data Update Complete!"
echo "=========================================="
echo ""
echo "Summary:"
echo "- Original videos backed up to: $BACKUP_DIR"
echo "- WebM files integrated into: $MEDIA_DIR"
echo "- Total videos updated: $ORIGINAL_COUNT"
echo ""
echo "Next steps:"
echo "1. Test the updated demo data locally"
echo "2. Create a new release zip with updated demo data"
echo "3. Upload to GitHub"
echo ""
