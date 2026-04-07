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
# Canonical source workflow before publishing a pack whose videos changed:
#   1. ./scripts/run-catalog-command.sh demos.media.rebuild <pack-id>
#   2. ./scripts/run-catalog-command.sh demos.release <version> --push
#
# The release zip must always be built from a canonical media/ tree whose
# vendor-relative video paths are preserved. Do not flatten vendor video
# filenames into a shared output directory.
#
# Requirements:
#   - gh (GitHub CLI, authenticated)
#   - jq
#   - zip
#
# Release tag format: v<version>-demo-data    e.g. v0.1.57-demo-data
# Asset filename:     ecomcine-demo-data-<version>.zip
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

DEFAULT_TAG="v${VERSION}-demo-data"

resolve_media_source_dir() {
    local pack_dir="$1"

    if [[ -d "$pack_dir/media" ]]; then
        echo "$pack_dir/media"
        return 0
    fi

    if [[ -d "$pack_dir/media-original" ]]; then
        echo "$pack_dir/media-original"
        return 0
    fi

    return 1
}

validate_pack_media_refs() {
    local pack_dir="$1"
    local media_dir="$2"
    local vendor_json="$pack_dir/vendor-data.json"
    local -a missing=()

    while IFS= read -r ref; do
        [[ -z "$ref" ]] && continue
        local rel_path="${ref#media/}"
        if [[ ! -f "$media_dir/$rel_path" ]]; then
            missing+=("$ref")
        fi
    done < <(jq -r '
        .vendors[]
        | (.media // {}) as $media
        | [($media.banner // empty), ($media.gravatar // empty)]
        | .[]
    ' "$vendor_json")

    if (( ${#missing[@]} > 0 )); then
        echo "[demos-release] ERROR $pack_dir — missing referenced media files:" >&2
        printf '  %s\n' "${missing[@]}" >&2
        return 1
    fi

    return 0
}

detect_release_tag() {
    local manifest_json="$1"
    local version="$2"
    local tag

    tag=$(echo "$manifest_json" | jq -r --arg version "$version" '
        .packs[]
        | select((.version // "") == $version and (.release_tag // "") != "")
        | .release_tag
    ' | head -n 1)

    if [[ -n "$tag" ]]; then
        echo "$tag"
    else
        echo "$DEFAULT_TAG"
    fi
}

detect_zip_name() {
    local manifest_json="$1"
    local pack_id="$2"
    local version="$3"
    local zip_url

    zip_url=$(echo "$manifest_json" | jq -r --arg id "$pack_id" --arg version "$version" '
        .packs[]
        | select(.id == $id and (.version // "") == $version and (.zip_url // "") != "")
        | .zip_url
    ' | head -n 1)

    if [[ -n "$zip_url" ]]; then
        basename "$zip_url"
    else
        echo "ecomcine-demo-data-${version}.zip"
    fi
}

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
echo "[demos-release] Mode      : $([ "$PUSH" == "--push" ] && echo "PUBLISH" || echo "DRY RUN")"
echo ""

# ── Read manifest ─────────────────────────────────────────────────────────────

if [[ ! -f "$MANIFEST" ]]; then
    echo "Error: manifest not found at $MANIFEST"
    exit 1
fi

manifest=$(cat "$MANIFEST")
source_manifest="$manifest"
TAG="$(detect_release_tag "$source_manifest" "$VERSION")"

echo "[demos-release] Tag       : $TAG"

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

    media_dir="$(resolve_media_source_dir "$pack_dir")" || {
        echo "[demos-release] SKIP  $pack_id — no media source directory found (expected media/ or media-original/)"
        continue
    }

    validate_pack_media_refs "$pack_dir" "$media_dir"

    zip_name="$(detect_zip_name "$source_manifest" "$pack_id" "$VERSION")"
    zip_path="$TMP/$zip_name"
    zip_url="https://github.com/${REPO}/releases/download/${TAG}/${zip_name}"
    stage_dir="$TMP/stage-$pack_id"

    rm -rf "$stage_dir"
    mkdir -p "$stage_dir"
    cp "$pack_dir/vendor-data.json" "$stage_dir/vendor-data.json"
    cp -a "$media_dir" "$stage_dir/media"

    echo "[demos-release] Zipping $pack_id from $(basename "$media_dir") …"
    (cd "$stage_dir" && zip -r "$zip_path" vendor-data.json media/ -x "*.DS_Store" -x "__MACOSX/*" -q)

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
    echo "[demos-release] Publishing GitHub Release $TAG …"

    pack_names=$(echo "$manifest" | jq -r '[.packs[].name] | join(", ")')

    if gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1; then
        gh release upload "$TAG" \
            --repo "$REPO" \
            --clobber \
            "${zip_files[@]}"

        gh release edit "$TAG" \
            --repo "$REPO" \
            --title "Demo Data $VERSION" \
            --notes "Demo pack release $VERSION. Packs: $pack_names" \
            --prerelease
    else
        gh release create "$TAG" \
            --repo "$REPO" \
            --title "Demo Data $VERSION" \
            --notes "Demo pack release $VERSION. Packs: $pack_names" \
            --prerelease \
            "${zip_files[@]}"
    fi

    echo "[demos-release] Updating $MANIFEST …"
    echo "$manifest" | jq . > "$MANIFEST"

    echo ""
    echo "[demos-release] SUCCESS. Next steps:"
    echo "  git add demos/manifest.json and any source/media workflow changes used for this release"
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
