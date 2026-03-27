#!/usr/bin/env bash
# Check that the local EcomCine WordPress Docker stack is healthy and custom plugins are active.
# Reads WP_PORT and PLUGIN_SLUG from .env if present.
# Checks:
#   - docker compose services are running
#   - WordPress site is reachable on the expected port
#   - Primary custom plugin (PLUGIN_SLUG) is active
#
# Usage:
#   ./scripts/check-local-wp.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$REPO_ROOT/.env"

WP_SERVICE="wordpress"
WP_PORT="8180"
PLUGIN_SLUG=""

if [[ -f "$ENV_FILE" ]]; then
  while IFS='=' read -r key value; do
    case "$key" in
      WP_PORT)     WP_PORT="$value" ;;
      PLUGIN_SLUG) PLUGIN_SLUG="$value" ;;
    esac
  done < <(grep -E '^(WP_PORT|PLUGIN_SLUG)=' "$ENV_FILE" || true)
fi

export MSYS_NO_PATHCONV=1

echo "==> Checking local dev infrastructure baseline..."
"$REPO_ROOT/scripts/check-local-dev-infra.sh"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker CLI not found." >&2
  exit 2
fi

echo "==> Checking Docker services..."
if ! docker compose -f "$REPO_ROOT/docker-compose.yml" ps --status running --services 2>/dev/null | grep -qx "$WP_SERVICE"; then
  echo "FAIL: '$WP_SERVICE' service is not running. Run: docker compose up -d" >&2
  exit 1
fi
echo "OK: $WP_SERVICE is running."

echo "==> Checking WordPress HTTP response (port $WP_PORT)..."
status="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${WP_PORT}/" || true)"
if [[ "$status" != "200" && "$status" != "301" && "$status" != "302" ]]; then
  echo "WARN: http://localhost:${WP_PORT}/ returned HTTP $status (expected 200/301/302)." >&2
else
  echo "OK: WordPress responds on port $WP_PORT (HTTP $status)."
fi

echo "==> Checking WP-CLI is available..."
if ! docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp --info --allow-root >/dev/null 2>&1; then
  echo "WARN: WP-CLI not available in container." >&2
else
  echo "OK: WP-CLI available."
fi

echo "==> Checking custom plugin activation..."
if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp plugin is-active "ecomcine" --allow-root >/dev/null 2>&1; then
  echo "OK: ecomcine is active (unified Phase 1 plugin)."
else
  echo "WARN: ecomcine is not active; checking legacy TM plugin set..."
  LEGACY_TM_PLUGINS=(
    "tm-media-player"
    "tm-account-panel"
    "tm-vendor-booking-modal"
  )
  for slug in "${LEGACY_TM_PLUGINS[@]}"; do
    if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp plugin is-active "$slug" --allow-root >/dev/null 2>&1; then
      echo "OK: $slug is active."
    else
      echo "WARN: $slug is not active. Run: ./scripts/wp.sh wp plugin activate $slug"
    fi
  done
fi

if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp plugin is-active "dokan-category-attributes" --allow-root >/dev/null 2>&1; then
  echo "OK: dokan-category-attributes is active."
else
  echo "WARN: dokan-category-attributes is not active. Run: ./scripts/wp.sh wp plugin activate dokan-category-attributes"
fi

if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp plugin is-installed "ecomcine-control-plane" --allow-root >/dev/null 2>&1; then
  if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp plugin is-active "ecomcine-control-plane" --allow-root >/dev/null 2>&1; then
    echo "OK: ecomcine-control-plane is active (billing control-plane)."
  else
    echo "WARN: ecomcine-control-plane is installed but inactive. Run: ./scripts/wp.sh wp plugin activate ecomcine-control-plane"
  fi
else
  echo "INFO: ecomcine-control-plane is not installed (expected on non-billing environments)."
fi

echo "==> Checking theme activation..."
if docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T "$WP_SERVICE" wp theme is-active astra-child --allow-root >/dev/null 2>&1; then
  echo "OK: astra-child theme is active."
else
  echo "WARN: astra-child theme is not active. Run: ./scripts/wp.sh wp theme activate astra-child"
fi

echo ""
echo "Check complete. Resolve any FAIL/WARN items above before starting feature work."
