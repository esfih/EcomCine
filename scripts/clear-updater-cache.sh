#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

LOCAL_A="$REPO_ROOT/updates.domain.com/cache/latest-release.json"
LOCAL_B="$REPO_ROOT/updates/cache/latest-release.json"

clear_local_cache() {
  local removed=0

  if [[ -f "$LOCAL_A" ]]; then
    rm -f "$LOCAL_A"
    removed=$((removed + 1))
  fi

  if [[ -f "$LOCAL_B" ]]; then
    rm -f "$LOCAL_B"
    removed=$((removed + 1))
  fi

  echo "[cache-clear] local cache removed files: ${removed}"
}

clear_remote_cache() {
  local endpoint="$1"
  local key="$2"

  echo "[cache-clear] remote endpoint: ${endpoint}"
  curl -fsS -X POST \
    --data-urlencode "action=clear_cache" \
    --data-urlencode "key=${key}" \
    "$endpoint"
  echo
}

# Always clear local cache files first.
clear_local_cache

# Optional remote clear usage:
#   scripts/clear-updater-cache.sh <endpoint> <clear_cache_key>
if [[ $# -gt 0 ]]; then
  if [[ $# -lt 2 ]]; then
    echo "Usage: scripts/clear-updater-cache.sh [endpoint clear_cache_key]" >&2
    exit 2
  fi

  clear_remote_cache "$1" "$2"
fi
