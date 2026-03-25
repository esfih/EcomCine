#!/usr/bin/env bash
# Bootstrap shared foundation layers via git subtree.
# Pulls master-core (core devops) and wp-overlay (WordPress runtime + licensing).
#
# Run once after creating this repository.
# See WORKSPACE-SETUP.md for the full setup guide.

set -euo pipefail

CORE_REMOTE_NAME="${CORE_REMOTE_NAME:-master-core}"
WP_REMOTE_NAME="${WP_REMOTE_NAME:-wp-overlay}"
CORE_REMOTE_URL="${CORE_REMOTE_URL:-https://github.com/esfih/master-core.git}"
WP_REMOTE_URL="${WP_REMOTE_URL:-https://github.com/esfih/wp-overlay.git}"
BRANCH="${FOUNDATION_BRANCH:-main}"

add_or_update_remote() {
  local name="$1"
  local url="$2"
  if [[ -z "$url" ]]; then
    return 0
  fi

  if git remote get-url "$name" >/dev/null 2>&1; then
    git remote set-url "$name" "$url"
  else
    git remote add "$name" "$url"
  fi
}

pull_subtree() {
  local prefix="$1"
  local remote="$2"
  git fetch "$remote" "$BRANCH"
  git subtree add --prefix "$prefix" "$remote" "$BRANCH" --squash
}

add_or_update_remote "$CORE_REMOTE_NAME" "$CORE_REMOTE_URL"
add_or_update_remote "$WP_REMOTE_NAME" "$WP_REMOTE_URL"

if [[ ! -d foundation/core ]]; then
  echo "==> Pulling foundation/core from $CORE_REMOTE_NAME..."
  pull_subtree foundation/core "$CORE_REMOTE_NAME"
  echo "    Done."
fi

if [[ ! -d foundation/wp ]]; then
  echo "==> Pulling foundation/wp from $WP_REMOTE_NAME..."
  pull_subtree foundation/wp "$WP_REMOTE_NAME"
  echo "    Done."
fi

echo ""
echo "Foundation bootstrap complete."
echo ""
echo "Next steps:"
echo "  1. docker compose up -d"
echo "  2. ./scripts/wp.sh wp db import db/seed.sql"
echo "  3. ./scripts/wp.sh wp search-replace 'https://castingagency.co' 'http://localhost:8180' --all-tables"
echo "  4. ./scripts/wp.sh wp user update 1 --user_pass=admin"
echo "  5. ./scripts/wp.sh wp rewrite structure '/%postname%/' --hard"
echo "  6. ./scripts/setup-deps.sh"
echo "  7. ./scripts/check-local-wp.sh"
echo ""
echo "See README-FIRST.md for the full setup guide."
