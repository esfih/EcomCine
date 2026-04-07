#!/usr/bin/env bash
# Validate local IDE/DevOps infrastructure baseline for EcomCine.
# Fails when repository or Docker bind mounts are sourced from Windows paths.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker-compose.yml"
WP_SERVICE="wordpress"

fail() {
  echo "FAIL: $1" >&2
  exit 1
}

warn() {
  echo "WARN: $1" >&2
}

echo "==> Checking shell runtime..."
if [[ -z "${WSL_DISTRO_NAME:-}" ]] && ! grep -qiE 'microsoft|wsl' /proc/version 2>/dev/null; then
  fail "Not running inside WSL. Open Ubuntu WSL2 and run this script there."
fi
echo "OK: Running in WSL distro '${WSL_DISTRO_NAME:-unknown}'."

echo "==> Checking repository path..."
if [[ "$REPO_ROOT" =~ ^/mnt/[a-z]/ ]]; then
  fail "Repo is on a Windows-mounted path ($REPO_ROOT). Move it to /home/<user>/dev/..."
fi
echo "OK: Repo is on Linux filesystem: $REPO_ROOT"

echo "==> Checking Docker CLI..."
command -v docker >/dev/null 2>&1 || fail "Docker CLI not found in current shell."
context="$(docker context show 2>/dev/null || true)"
if [[ -z "$context" ]]; then
  warn "Unable to detect Docker context."
else
  echo "OK: Docker context is '$context'."
fi

echo "==> Checking compose service mounts (if running)..."
container_id="$(docker compose -f "$COMPOSE_FILE" ps -q "$WP_SERVICE" 2>/dev/null || true)"
if [[ -z "$container_id" ]]; then
  warn "WordPress service is not running; mount source check skipped."
  echo "Run 'docker compose up -d' then rerun this script."
  echo ""
  echo "Infrastructure check complete with warnings."
  exit 0
fi

mount_sources="$(docker inspect "$container_id" --format '{{range .Mounts}}{{if eq .Type "bind"}}{{println .Source}}{{end}}{{end}}' 2>/dev/null || true)"
if [[ -z "$mount_sources" ]]; then
  warn "No bind mounts found for wordpress service."
else
  if echo "$mount_sources" | grep -Eqi '^[A-Za-z]:\\|^/mnt/[a-z]/'; then
    echo "Detected bind mount sources:" >&2
    echo "$mount_sources" >&2
    fail "WordPress bind mounts are sourced from Windows filesystem. Start compose from /home/<user>/dev/..."
  fi
  echo "OK: All bind mounts are Linux paths."
fi

echo ""
echo "Infrastructure check complete."