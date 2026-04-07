#!/bin/bash
# Demo Data Update Script - Replace MP4 with WebM preserving structure
# This script updates demo data with WebM videos while maintaining folder structure

set -e

DEMO_DATA_DIR="${1:-demos/topdoctorchannel}"
MEDIA_DIR="$DEMO_DATA_DIR/media"
WEBM_DIR="$DEMO_DATA_DIR/media-webm-full"

echo "=========================================="
echo "Demo Data Update Script"
echo "=========================================="

# Check if WebM files exist
if [ ! -d "$WEBM_DIR" ]; then
    echo "ERROR: WebM directory not found: $WEBM_DIR"
    exit 1
fi

# Create backup
echo ""
echo "Creating backup of original videos..."
BACKUP_DIR="$DEMO_DATA_DIR/media-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Copy all media directories to backup
find "$MEDIA_DIR" -type d -exec cp -r {} "$BACKUP_DIR/" \; 2>/dev/null || true
echo "Backup created: $BACKUP_DIR"

# Replace MP4 with WebM maintaining folder structure
echo ""
echo "Replacing MP4 with WebM..."

# Find all MP4 files and replace with corresponding WebM
for MP4_FILE in $(find "$MEDIA_DIR" -name "*.mp4"); do
    # Get relative path
    REL_PATH="${MP4_FILE#$MEDIA_DIR/}"
    VIDEO_NAME=$(basename "$MP4_FILE" .mp4)
    
    # Find corresponding WebM file
    WEBM_FILE="$WEBM_DIR/${VIDEO_NAME}.webm"
    
    if [ ! -f "$WEBM_FILE" ]; then
        echo "WARNING: No WebM found for $REL_PATH"
        continue
    fi
    
    # Get target directory
    TARGET_DIR=$(dirname "$MP4_FILE")
    mkdir -p "$TARGET_DIR"
    
    # Replace MP4 with WebM (keeping .mp4 extension for compatibility)
    cp "$WEBM_FILE" "$MP4_FILE"
    
    echo "✅ Updated: $REL_PATH"
done

# Verify
echo ""
echo "Verifying update..."
TOTAL_VIDEOS=$(find "$MEDIA_DIR" -name "*.mp4" | wc -l)
echo "Total videos in demo data: $TOTAL_VIDEOS"

echo ""
echo "=========================================="
echo "Demo Data Update Complete!"
echo "=========================================="
echo ""
echo "Summary:"
echo "- Backup created: $BACKUP_DIR"
echo "- Videos updated: $TOTAL_VIDEOS"
echo "- WebM files used from: $WEBM_DIR"
echo ""
echo "Next steps:"
echo "1. Test locally"
echo "2. Create release zip"
echo "3. Upload to GitHub"
echo ""
