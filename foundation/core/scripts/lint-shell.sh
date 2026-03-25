#!/usr/bin/env bash
# Lint shell scripts in the repo using shellcheck and shfmt.
# This helps avoid subtle script bugs and ensures consistent formatting.
#
# This script is intentionally strict: missing tools are treated as failures
# so the developer (or AI) can install them and rerun.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

ensure_tool() {
  local cmd="$1";
  local hint="$2";
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "[MISSING] $cmd is required for linting shell scripts. $hint" >&2
    return 1
  fi
  return 0
}

# Collect target files
collect_files() {
  if [[ $# -gt 0 ]]; then
    printf '%s\n' "$@"
  else
    git ls-files '*.sh' | grep -v '^scripts/tool-catalog\.sh$' || true
  fi
}

mapfile -t files < <(collect_files "$@")

if [[ ${#files[@]} -eq 0 ]]; then
  echo "No shell scripts found to lint."
  exit 0
fi

status=0

# Ensure required tooling is present.
if ! ensure_tool shellcheck "Install from https://www.shellcheck.net/ or via your package manager."; then
  status=1
fi
if ! ensure_tool shfmt "Install from https://github.com/mvdan/sh or via your package manager."; then
  status=1
fi

# Run checks only if the tools are present.
if command -v shellcheck >/dev/null 2>&1; then
  echo "Running shellcheck..."
  if ! shellcheck "${files[@]}"; then
    status=1
  fi
fi

if command -v shfmt >/dev/null 2>&1; then
  echo "Running shfmt (format check)..."
  # shfmt returns non-zero if it would rewrite files.
  if ! shfmt -d -w "${files[@]}"; then
    status=1
  fi
fi

if [[ $status -ne 0 ]]; then
  echo "One or more tools are missing or errors were detected. Install the missing tools and rerun." >&2
fi

exit $status
