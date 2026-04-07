#!/usr/bin/env bash
# AI Toolkit helper for structured workflows.
#
# This script runs a curated set of repo tooling and emits a JSON summary of results.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

usage() {
  cat <<'EOF'
Usage: ./scripts/ai-toolkit.sh [--range <base> <head>] [--json <path>] [--quiet]

Options:
  --range <base> <head>    Run range-aware checks (passed through to validate-changed.sh).
  --json <path>            Write machine-readable JSON output to the given path.
  --quiet                  Suppress most human-readable output.
  --help                   Show this help.
EOF
}

RANGE_BASE=""
RANGE_HEAD=""
JSON_PATH=""
QUIET=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --range)
      RANGE_BASE="$2"
      RANGE_HEAD="$3"
      shift 3
      ;;
    --json)
      JSON_PATH="$2"
      shift 2
      ;;
    --quiet)
      QUIET=1
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 2
      ;;
  esac
done

results=()

if [ $QUIET -eq 0 ]; then
  echo "[step] prereqs"
fi
if out=$(./scripts/check-prereqs.sh 2>&1); then
  results+=("{\"name\":\"check-prereqs\",\"status\":0,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
else
  ec=$?
  results+=("{\"name\":\"check-prereqs\",\"status\":$ec,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
fi

if [[ -n "$RANGE_BASE" && -n "$RANGE_HEAD" ]]; then
  if [ $QUIET -eq 0 ]; then
    echo "[step] validate-changed --range $RANGE_BASE $RANGE_HEAD"
  fi
  if out=$(./scripts/validate-changed.sh --range "$RANGE_BASE" "$RANGE_HEAD" 2>&1); then
    results+=("{\"name\":\"validate-changed\",\"status\":0,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
  else
    ec=$?
    results+=("{\"name\":\"validate-changed\",\"status\":$ec,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
  fi
fi

for script in ./scripts/lint-php.sh ./scripts/validate-js.sh ./scripts/lint-shell.sh; do
  if [ -x "$script" ]; then
    name=$(basename "$script")
    if [ $QUIET -eq 0 ]; then
      echo "[step] $name"
    fi
    if out=$("$script" 2>&1); then
      results+=("{\"name\":\"$name\",\"status\":0,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
    else
      ec=$?
      results+=("{\"name\":\"$name\",\"status\":$ec,\"output\":$(python -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<<\"$out\")}")
    fi
  fi
done

json_output="{\"results\":[${results[*]}]}"

if [[ -n "$JSON_PATH" ]]; then
  printf "%s\n" "$json_output" > "$JSON_PATH"
  [ $QUIET -eq 0 ] && echo "Wrote report to $JSON_PATH"
else
  echo "$json_output"
fi
