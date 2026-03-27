#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

SEED_SQL="${1:-db/fluentcart-control-plane-seed.sql}"
if [[ ! -f "$SEED_SQL" ]]; then
  echo "ERROR: Seed SQL not found: $SEED_SQL" >&2
  exit 1
fi

PREFIX="$(./scripts/wp.sh wp db prefix 2>/dev/null | tail -n 1 | tr -d '\r\n')"
if [[ -z "$PREFIX" ]]; then
  echo "ERROR: Could not resolve WordPress table prefix." >&2
  exit 1
fi

TMP_SQL="/tmp/ecomcine_cp_seed_apply.sql"
sed "s/__WP_PREFIX__/${PREFIX}/g" "$SEED_SQL" > "$TMP_SQL"

# Use the DB container mysql client directly to avoid host TLS defaults.
docker compose exec -T db sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$TMP_SQL"

echo "Imported control-plane seed SQL from $SEED_SQL with prefix ${PREFIX}"
