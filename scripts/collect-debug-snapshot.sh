#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
OUT_DIR="$REPO_ROOT/logs/debug-snapshots"
TS="$(date +%Y%m%d-%H%M%S)"
OUT_FILE="$OUT_DIR/snapshot-$TS.md"
LINES="${1:-200}"

mkdir -p "$OUT_DIR"

{
  echo "# Debug Snapshot"
  echo
  echo "- Timestamp: $(date -Is)"
  echo "- Repo: $REPO_ROOT"
  echo
  echo "## Runtime Health"
  echo
  if ./scripts/check-local-dev-infra.sh >/tmp/ecomcine-infra-check.log 2>&1; then
    echo "infra.check: PASS"
  else
    echo "infra.check: FAIL"
  fi
  sed -n '1,120p' /tmp/ecomcine-infra-check.log || true
  echo
  if ./scripts/check-local-wp.sh >/tmp/ecomcine-wp-check.log 2>&1; then
    echo "wp.health.check: PASS"
  else
    echo "wp.health.check: FAIL"
  fi
  sed -n '1,120p' /tmp/ecomcine-wp-check.log || true
  echo

  echo "## WordPress Debug Log (tail $LINES)"
  echo
  ./scripts/wp.sh log "$LINES" || true
  echo

  echo "## Docker WordPress Logs (tail $LINES)"
  echo
  docker compose logs --tail "$LINES" wordpress || true
  echo

  echo "## WP-CLI Info"
  echo
  ./scripts/wp.sh wp --info || true
  echo

  echo "## Git Status"
  echo
  git status --short || true
} > "$OUT_FILE"

echo "$OUT_FILE"
