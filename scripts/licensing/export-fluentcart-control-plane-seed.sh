#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

PREFIX="$(./scripts/wp.sh wp db prefix 2>/dev/null | tail -n 1 | tr -d '\r\n')"
if [[ -z "$PREFIX" ]]; then
  echo "ERROR: Could not resolve WordPress table prefix." >&2
  exit 1
fi

OUT_SQL="${1:-db/fluentcart-control-plane-seed.sql}"
TMP_SQL="/tmp/ecomcine_cp_seed_raw.sql"

TABLES=(
  "${PREFIX}fct_product_details"
  "${PREFIX}fct_product_variations"
  "${PREFIX}fct_product_meta"
  "${PREFIX}fct_customers"
  "${PREFIX}fct_customer_addresses"
  "${PREFIX}fct_orders"
  "${PREFIX}fct_order_addresses"
  "${PREFIX}fct_order_items"
  "${PREFIX}fct_order_meta"
  "${PREFIX}fct_order_operations"
  "${PREFIX}fct_order_transactions"
  "${PREFIX}fct_subscriptions"
  "${PREFIX}fct_meta"
  "${PREFIX}fct_licenses"
  "${PREFIX}fct_license_sites"
  "${PREFIX}fct_license_activations"
  "${PREFIX}fct_license_meta"
  "${PREFIX}fluentcart_licenses"
)

TABLE_LIST="${TABLES[*]}"

docker compose exec -T db sh -lc "mysqldump --no-tablespaces --skip-triggers --single-transaction --no-create-info --skip-add-locks --skip-lock-tables --extended-insert --quick -u\"\$MYSQL_USER\" -p\"\$MYSQL_PASSWORD\" \"\$MYSQL_DATABASE\" $TABLE_LIST" > "$TMP_SQL"

docker compose exec -T db sh -lc "mysqldump --no-tablespaces --skip-triggers --single-transaction --no-create-info --skip-add-locks --skip-lock-tables --extended-insert --quick -u\"\$MYSQL_USER\" -p\"\$MYSQL_PASSWORD\" \"\$MYSQL_DATABASE\" ${PREFIX}options --where=\"option_name IN ('fluent_cart_store_settings','fluent_cart_modules_settings','fluent_cart_plugin_once_activated')\"" >> "$TMP_SQL"

mkdir -p "$(dirname "$OUT_SQL")"

{
  echo "-- EcomCine reusable FluentCart/control-plane seed"
  echo "-- Generated from validated local baseline on $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "-- Table prefix token: __WP_PREFIX__"
  echo "-- Apply with: ./scripts/licensing/import-fluentcart-control-plane-seed.sh"
  echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';"
  echo "START TRANSACTION;"
  for table in "${TABLES[@]}"; do
    short="${table#$PREFIX}"
    echo "DELETE FROM \`__WP_PREFIX__${short}\`;"
  done
  echo "DELETE FROM \`__WP_PREFIX__options\` WHERE option_name IN ('fluent_cart_store_settings','fluent_cart_modules_settings','fluent_cart_plugin_once_activated');"
} > "$OUT_SQL"

sed -E \
  -e '/@OLD_/d' \
  -e '/^\/\*!40101 SET NAMES/d' \
  -e '/^\/\*!40103 SET TIME_ZONE/d' \
  -e '/^\/\*!40014 SET UNIQUE_CHECKS/d' \
  -e '/^\/\*!40014 SET FOREIGN_KEY_CHECKS/d' \
  -e '/^\/\*!40101 SET SQL_MODE/d' \
  -e '/^\/\*!40111 SET SQL_NOTES/d' \
  -e '/^-- Dump completed on/d' \
  -e "s/\`${PREFIX}/\`__WP_PREFIX__/g" \
  "$TMP_SQL" >> "$OUT_SQL"

echo "COMMIT;" >> "$OUT_SQL"

echo "Generated seed SQL: $OUT_SQL"
