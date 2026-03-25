#!/usr/bin/env bash
# Install and activate all EcomCine WordPress dependencies.
#
# Third-party plugins are mounted via Docker volumes from deps/ (gitignored).
# This script activates them in the correct dependency order, then activates
# all custom EcomCine plugins and theme.
#
# Run AFTER docker compose up -d and wp core install.
# Usage:
#   ./scripts/setup-deps.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
WP="$SCRIPT_DIR/wp.sh"

echo "==> [1/4] Activating WooCommerce..."
"$WP" wp plugin activate woocommerce

echo "==> [2/4] Activating Dokan Lite (must come before Dokan Pro)..."
"$WP" wp plugin activate dokan-lite

echo "==> [3/4] Activating premium plugins (Dokan Pro, WooCommerce Bookings)..."
for slug in dokan-pro woocommerce-bookings; do
  if "$WP" wp plugin is-installed "$slug" --allow-root >/dev/null 2>&1; then
    "$WP" wp plugin activate "$slug"
    echo "    Activated: $slug"
  else
    echo "    WARN: $slug not found in container — verify deps/$slug is present and volume is mounted." >&2
  fi
done

echo "==> [4/4] Activating EcomCine custom plugins and theme..."
"$WP" wp plugin activate dokan-category-attributes
"$WP" wp plugin activate tm-media-player
"$WP" wp plugin activate tm-account-panel
"$WP" wp plugin activate tm-vendor-booking-modal
"$WP" wp theme activate astra-child

echo ""
echo "Done. Run ./scripts/check-local-wp.sh to verify."
