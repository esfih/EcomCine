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

echo "==> Validating WSL2 runtime baseline..."
"$REPO_ROOT/scripts/check-local-dev-infra.sh"

echo "==> [1/4] Activating WooCommerce..."
"$WP" wp plugin activate woocommerce

echo "==> [2/4] Activating Dokan Lite (must come before Dokan Pro)..."
"$WP" wp plugin activate dokan-lite

echo "==> [3/4] Activating premium plugins (Dokan Pro, WooCommerce Bookings, GreenShift when available)..."
for slug in dokan-pro woocommerce-bookings greenshift; do
  if "$WP" wp plugin is-installed "$slug" --allow-root >/dev/null 2>&1; then
    "$WP" wp plugin activate "$slug"
    echo "    Activated: $slug"
  else
    echo "    WARN: $slug not found in container — verify deps/$slug is present and volume is mounted." >&2
  fi
done

echo "==> [4/4] Activating EcomCine custom plugins and canonical theme..."
"$WP" wp plugin activate dokan-category-attributes
"$WP" wp plugin activate ecomcine

if "$WP" wp plugin is-installed ecomcine-control-plane --allow-root >/dev/null 2>&1; then
  "$WP" wp plugin activate ecomcine-control-plane
  echo "    Activated: ecomcine-control-plane"
else
  echo "    INFO: ecomcine-control-plane not installed in this environment."
fi

for legacy_slug in tm-media-player tm-account-panel tm-vendor-booking-modal; do
  if "$WP" wp plugin is-active "$legacy_slug" --allow-root >/dev/null 2>&1; then
    "$WP" wp plugin deactivate "$legacy_slug"
    echo "    Deactivated legacy module plugin: $legacy_slug"
  fi
done

"$WP" wp theme activate ecomcine-base

echo ""
echo "Done. Run ./scripts/check-local-wp.sh to verify."
