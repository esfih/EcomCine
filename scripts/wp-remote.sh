#!/usr/bin/env bash
# Remote WP-CLI runner for app.topdoctorchannel.us (N0C hosting)
#
# Usage:
#   ./scripts/wp-remote.sh eval 'some php code;'
#   ./scripts/wp-remote.sh user list
#   ./scripts/wp-remote.sh option get siteurl
#
# Env overrides (optional):
#   REMOTE_USER   — SSH user (default: efttsqrtff)
#   REMOTE_HOST   — SSH host (default: 209.16.158.249)
#   REMOTE_PORT   — SSH port (default: 5022)
#   REMOTE_WPATH  — WP root path (default: /home/efttsqrtff/app.topdoctorchannel.us)
#   REMOTE_KEY    — SSH key path (default: ~/.ssh/ecomcine_n0c)

set -euo pipefail

REMOTE_USER="${REMOTE_USER:-efttsqrtff}"
REMOTE_HOST="${REMOTE_HOST:-209.16.158.249}"
REMOTE_PORT="${REMOTE_PORT:-5022}"
REMOTE_WPATH="${REMOTE_WPATH:-/home/efttsqrtff/app.topdoctorchannel.us}"
REMOTE_KEY="${REMOTE_KEY:-$HOME/.ssh/ecomcine_n0c}"

if [[ $# -eq 0 ]]; then
  echo "Usage: $0 <wp-cli args...>"
  echo "Example: $0 eval 'echo home_url();'"
  exit 1
fi

REMOTE_CMD="wp --path=${REMOTE_WPATH} --allow-root"
for arg in "$@"; do
  REMOTE_CMD+=" $(printf '%q' "$arg")"
done

ssh \
  -i "$REMOTE_KEY" \
  -p "$REMOTE_PORT" \
  -o StrictHostKeyChecking=accept-new \
  -o ConnectTimeout=10 \
  "${REMOTE_USER}@${REMOTE_HOST}" \
  "$REMOTE_CMD"
