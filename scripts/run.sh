#!/usr/bin/env bash
# Unified runner for repository tooling.
#
# This wrapper ensures prerequisites are validated and provides a consistent
# entrypoint for both humans and AI agents to run repo tooling.
#
# Usage:
#   ./scripts/run.sh <tool> [args...]
#
# Examples:
#   ./scripts/run.sh validate-changed --range origin/main
#   ./scripts/run.sh clean-runtime-artifacts
#   ./scripts/run.sh ai-context-budget

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/.."
cd "$repo_root"

if [ $# -lt 1 ]; then
  echo "Usage: $0 <tool> [args...]"
  echo "Available tools:" 
  ./scripts/tool-catalog.sh
  exit 1
fi

tool="$1"
shift

# Always allow `help` to run even when some tooling prerequisites are missing.
if [[ "$tool" =~ ^(help|-h|--help)$ ]]; then
  ./scripts/tool-catalog.sh
  exit 0
fi

# Validate prerequisites before running tool commands.
./scripts/check-prereqs.sh

case "$tool" in
  help|-h|--help)
    ./scripts/tool-catalog.sh
    exit 0
    ;;
  *)
    if [ -x "./scripts/$tool" ]; then
      exec "./scripts/$tool" "$@"
    else
      echo "Unknown tool: $tool" >&2
      echo "Run '$0 help' to list available tools." >&2
      exit 2
    fi
    ;;
esac
