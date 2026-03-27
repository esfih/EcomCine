#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

if [[ $# -lt 1 ]]; then
  echo "Usage: ./scripts/run-catalog-command.sh <command-id> [args...]" >&2
  exit 2
fi

COMMAND_ID="$1"
shift || true

case "$COMMAND_ID" in
  infra.check)
    ./scripts/check-local-dev-infra.sh
    ;;

  git.hooks.install)
    ./scripts/install-git-hooks.sh
    ;;

  wp.health.check)
    ./scripts/check-local-wp.sh
    ;;

  stack.up)
    docker compose up -d
    ;;

  wp.plugins.list)
    ./scripts/wp.sh wp plugin list "$@"
    ;;

  wp.themes.list)
    ./scripts/wp.sh wp theme list "$@"
    ;;

  wp.option.get)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: wp.option.get requires <option_name>" >&2
      exit 2
    fi
    ./scripts/wp.sh wp option get "$1"
    ;;

  wp.eval)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: wp.eval requires <php_code>" >&2
      exit 2
    fi
    ./scripts/wp.sh wp eval "$1"
    ;;

  wp.eval.file)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: wp.eval.file requires <local_php_file>" >&2
      exit 2
    fi
    ./scripts/wp.sh php "$1"
    ;;

  db.seed.import.core)
    ./scripts/wp.sh wp db import db/seed.sql
    ;;

  db.seed.import.fluentcart_cp)
    ./scripts/licensing/import-fluentcart-control-plane-seed.sh "$@"
    ;;

  db.seed.export.fluentcart_cp)
    ./scripts/licensing/export-fluentcart-control-plane-seed.sh "$@"
    ;;

  db.query)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: db.query requires <sql_query>" >&2
      exit 2
    fi
    ./scripts/wp.sh db "$1"
    ;;

  licensing.seed.clone_wmos)
    ./scripts/wp.sh php scripts/licensing/seed-fluentcart-from-wmos-clone.php
    ;;

  release.build.ecomcine)
    ./scripts/build-ecomcine-release.sh
    ;;

  git.status)
    git status --short
    ;;

  git.push.master)
    git push origin master
    ;;

  git.commitmsg.validate)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: git.commitmsg.validate requires <commit_msg_file>" >&2
      exit 2
    fi
    ./scripts/validate-remediation-commit-msg.sh "$1"
    ;;

  *)
    echo "ERROR: Unknown command catalog ID: $COMMAND_ID" >&2
    echo "No improvisation allowed. Propose a new entry in specs/IDE-AI-Command-Catalog.md and request user approval." >&2
    exit 3
    ;;
esac
