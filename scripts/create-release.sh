#!/bin/bash
# EcomCine Release Script - Create release zip with WebM demo data
# This script creates a release zip including the updated demo data

set -e

VERSION="${1:-0.1.57}"
OUTPUT_DIR="dist"
PLUGIN_DIR="ecomcine"

echo "=========================================="
echo "EcomCine Release Script"
echo "=========================================="
echo "Version: $VERSION"
echo "Output directory: $OUTPUT_DIR"
echo "=========================================="

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Copy plugin files
echo ""
echo "Copying plugin files..."
cp -r "$PLUGIN_DIR" "$OUTPUT_DIR/"

# Copy updated demo data
echo ""
echo "Copying updated demo data..."
if [ -d "demos/topdoctorchannel/media" ]; then
    mkdir -p "$OUTPUT_DIR/demos/topdoctorchannel"
    cp -r "demos/topdoctorchannel/media" "$OUTPUT_DIR/demos/topdoctorchannel/"
    echo "✅ Demo data copied"
else
    echo "⚠️  Demo data not found, skipping..."
fi

# Create release zip
echo ""
echo "Creating release zip..."
cd "$OUTPUT_DIR"
ZIP_NAME="ecomcine-$VERSION.zip"
zip -r "$ZIP_NAME" ecomcine/ demos/ 2>/dev/null || zip -r "$ZIP_NAME" ecomcine/

echo "✅ Release zip created: $OUTPUT_DIR/$ZIP_NAME"

# Show file sizes
echo ""
echo "Release contents:"
ls -lh "$OUTPUT_DIR/$ZIP_NAME"

# Generate manifest
echo ""
echo "Generating manifest..."
cat > "$OUTPUT_DIR/ecomcine-$VERSION.manifest.json" << EOF
{
  "version": "$VERSION",
  "created": "$(date -Iseconds)",
  "files": [
    "ecomcine/",
    "demos/topdoctorchannel/media/"
  ],
  "features": [
    "Complete Dokan Eradication",
    "EcomCine Native Implementation",
    "WebM Video Optimization",
    "Loading Indicator for Slow Connections",
    "Progressive Video Loading"
  ],
  "compression": {
    "video_format": "WebM",
    "average_compression": "82%",
    "codec": "VP9"
  }
}
EOF

echo "✅ Manifest created: $OUTPUT_DIR/ecomcine-$VERSION.manifest.json"

echo ""
echo "=========================================="
echo "Release Ready!"
echo "=========================================="
echo ""
echo "Files created:"
ls -lh "$OUTPUT_DIR/"
echo ""
echo "Next steps:"
echo "1. Test the release zip locally"
echo "2. Upload to GitHub: gh release create v$VERSION $OUTPUT_DIR/$ZIP_NAME"
echo "3. Update version in ecomcine.php to $VERSION"
echo ""
