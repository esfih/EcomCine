#!/usr/bin/env bash
# install-debug-mu.sh
# Deploy ecomcine-debug.php MU-plugin to the local WordPress Docker container.
#
# Usage: ./scripts/install-debug-mu.sh [--enable]
#   --enable   Also create the wp-content/ecomcine-debug.txt flag file so logging
#              activates without needing a wp-config.php change.
#
# To disable logging later:
#   ./scripts/wp.sh wp eval "unlink( WP_CONTENT_DIR . '/ecomcine-debug.txt' );"
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MU_SRC="$REPO_ROOT/wp-content/mu-plugins/ecomcine-debug.php"
ENABLE_FLAG=false

for arg in "$@"; do
  case "$arg" in
    --enable) ENABLE_FLAG=true ;;
  esac
done

# Resolve container name from docker compose.
CONTAINER="$(docker compose -f "$REPO_ROOT/docker-compose.yml" ps -q wordpress 2>/dev/null | head -1)"
if [ -z "$CONTAINER" ]; then
  echo "ERROR: WordPress container not running. Run 'docker compose up -d' first." >&2
  exit 1
fi

echo "Deploying ecomcine-debug.php MU-plugin..."

# Ensure mu-plugins directory exists in the container.
MSYS_NO_PATHCONV=1 docker exec "$CONTAINER" mkdir -p /var/www/html/wp-content/mu-plugins

# Copy the MU-plugin into the container.
docker cp "$MU_SRC" "$CONTAINER:/var/www/html/wp-content/mu-plugins/ecomcine-debug.php"
echo "  → Copied to /var/www/html/wp-content/mu-plugins/ecomcine-debug.php"

# Ensure log directory exists with correct permissions.
MSYS_NO_PATHCONV=1 docker exec "$CONTAINER" bash -c \
  "mkdir -p /var/www/html/wp-content/logs && chown www-data:www-data /var/www/html/wp-content/logs"
echo "  → Log directory ready: /var/www/html/wp-content/logs/"

if [ "$ENABLE_FLAG" = true ]; then
  MSYS_NO_PATHCONV=1 docker exec "$CONTAINER" bash -c \
    "touch /var/www/html/wp-content/ecomcine-debug.txt && chown www-data:www-data /var/www/html/wp-content/ecomcine-debug.txt"
  echo "  → Debug flag enabled: wp-content/ecomcine-debug.txt created"
fi

echo ""
echo "Done. To enable logging (if not using --enable):"
echo "  Add to wp-config.php: define( 'ECOMCINE_DEBUG', true );"
echo "  OR run:               ./scripts/install-debug-mu.sh --enable"
echo ""
echo "To tail the log:"
echo "  ./scripts/wp.sh log:debug"
echo ""
echo "To disable:"
echo "  ./scripts/wp.sh wp eval \"unlink( WP_CONTENT_DIR . '/ecomcine-debug.txt' );\""
