#!/usr/bin/env bash
# Clean known generated runtime artifacts to keep the workspace clean for tooling and AI analysis.
# Use this before running validation tasks or when you want to reduce noise in git status.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/.."
cd "$repo_root"

echo "Cleaning runtime artifacts..."

# These are known generated outputs that should not be committed.
# If you add new runtime output folders, add them here.
rm -rf output/
rm -rf logs/
rm -rf vendor-research/wordpress-ai/
rm -f public-plugins/*/index-output.html

echo "Done. Run 'git status' to verify workspace cleanliness."