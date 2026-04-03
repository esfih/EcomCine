#!/usr/bin/env bash
# =============================================================================
# build-demos-release.sh
#
# Builds demo pack zip(s) from demos/<pack-id>/, creates a versioned GitHub
# Release, uploads the zips as release assets, and updates demos/manifest.json
# with the new zip URLs and version metadata.
#
# Usage:
#   ./scripts/build-demos-release.sh <version> [--push]
#
# Examples:
#   ./scripts/build-demos-release.sh 1.0.0          # dry-run (no upload)
#   ./scripts/build-demos-release.sh 1.0.1 --push   # build + release + update manifest
#
# Requirements:
#   - gh (GitHub CLI, authenticated)
#   - jq
#   - zip
#
# Release tag format: demos-v<version>        e.g. demos-v1.0.1
# Asset filename:     <pack-id>-demo-pack.zip e.g. topdoctorchannel-demo-pack.zip
# Manifest URL:       https://raw.githubusercontent.com/esfih/EcomCine/main/demos/manifest.json
# =============================================================================

set -euo pipefail

REPO="esfih/EcomCine"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEMOS_DIR="$REPO_ROOT/demos"
MANIFEST="$DEMOS_DIR/manifest.json"

# ── Args ──────────────────────────────────────────────────────────────────────

VERSION="${1:-}"
PUSH="${2:-}"

if [[ -z "$VERSION" ]]; then
    echo "Usage: $0 <version> [--push]"
    echo "Example: $0 1.0.1 --push"
    exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: version must be semver (e.g. 1.0.0, 1.0.1)"
    exit 1
fi

TAG="demos-v${VERSION}"

# ── Dependency checks ─────────────────────────────────────────────────────────

for cmd in gh jq zip; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "Error: '$cmd' is required but not installed."
        exit 1
    fi
done

# ── Setup tmp ─────────────────────────────────────────────────────────────────

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

echo "[demos-release] Version   : $VERSION"
echo "[demos-release] Tag       : $TAG"
echo "[demos-release] Mode      : $([ "$PUSH" == "--push" ] && echo "PUBLISH" || echo "DRY RUN")"
echo ""

# ── Read manifest ─────────────────────────────────────────────────────────────

if [[ ! -f "$MANIFEST" ]]; then
    echo "Error: manifest not found at $MANIFEST"
    exit 1
fi

manifest=$(cat "$MANIFEST")

# ── Build zip for each pack ───────────────────────────────────────────────────

zip_files=()

while IFS= read -r pack_id; do
    pack_dir="$DEMOS_DIR/$pack_id"

    if [[ ! -d "$pack_dir" ]]; then
        echo "[demos-release] SKIP  $pack_id — directory not found"
        continue
    fi

    if [[ ! -f "$pack_dir/vendor-data.json" ]]; then
        echo "[demos-release] SKIP  $pack_id — vendor-data.json missing"
        continue
    fi

    zip_name="${pack_id}-demo-pack.zip"
    zip_path="$TMP/$zip_name"
    zip_url="https://github.com/${REPO}/releases/download/${TAG}/${zip_name}"

    echo "[demos-release] Zipping $pack_id …"
    (cd "$pack_dir" && zip -r "$zip_path" vendor-data.json media/ -x "*.DS_Store" -x "__MACOSX/*" -q)

    size=$(du -sh "$zip_path" | cut -f1)
    echo "[demos-release] Done    $zip_name ($size)"

    zip_files+=("$zip_path")

    # Update pack entry in manifest: version, release_tag, zip_url
    manifest=$(echo "$manifest" | jq \
        --arg id       "$pack_id" \
        --arg version  "$VERSION" \
        --arg tag      "$TAG" \
        --arg zip_url  "$zip_url" \
        '.packs = [.packs[] | if .id == $id then . + {"version": $version, "release_tag": $tag, "zip_url": $zip_url} else . end]')

done < <(echo "$manifest" | jq -r '.packs[].id')

if [[ ${#zip_files[@]} -eq 0 ]]; then
    echo "[demos-release] No packs built. Nothing to release."
    exit 1
fi

# ── Publish or dry-run ────────────────────────────────────────────────────────

if [[ "$PUSH" == "--push" ]]; then
    echo ""
    echo "[demos-release] Creating GitHub Release $TAG …"

    pack_names=$(echo "$manifest" | jq -r '[.packs[].name] | join(", ")')

    gh release create "$TAG" \
        --repo "$REPO" \
        --title "Demo Data $VERSION" \
        --notes "Demo pack release $VERSION. Packs: $pack_names" \
        "${zip_files[@]}"

    echo "[demos-release] Updating $MANIFEST …"
    echo "$manifest" | jq . > "$MANIFEST"

    echo ""
    echo "[demos-release] SUCCESS. Next steps:"
    echo "  git add demos/manifest.json"
    echo "  git commit -m \"chore: bump demo manifest to $TAG\""
    echo "  git push"
else
    echo ""
    echo "[demos-release] DRY RUN complete. Built:"
    for f in "${zip_files[@]}"; do
        echo "  $(basename "$f")  ($(du -sh "$f" | cut -f1))"
    done
    echo ""
    echo "[demos-release] Updated manifest preview:"
    echo "$manifest" | jq .
    echo ""
    echo "[demos-release] Re-run with --push to create the GitHub Release."
fi
