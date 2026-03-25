#!/usr/bin/env bash
# Git-Bash-native helper for local WordPress Docker operations.
# Usage: ./scripts/wp.sh <command> [args...]
#
# Commands:
#   wp <args>       Run a WP-CLI command inside the WordPress container
#   eval "<php>"    Execute PHP with full WordPress bootstrap (wpdb initialized)
#   db "<sql>"      Run a SQL query against the MySQL container
#   log [N]         Tail the WordPress debug log (default: 80 lines)
#   php <file>      Copy a local PHP file into the container and run it via wp eval-file
#   shell           Open an interactive bash shell inside the WordPress container
#
# Service names and DB credentials are read from .env if present.
# All patterns follow foundation/wp/docs/WP-LOCAL-OPS.md

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ── Defaults (override via .env) ──────────────────────────────────────────────
WP_SERVICE="wordpress"
DB_SERVICE="db"
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wp_user}"
DB_PASS="${DB_PASSWORD:-wp_pass_dev}"
LOG_PATH="$REPO_ROOT/logs/wp/debug.log"

# Load overrides from .env if present
if [[ -f "$REPO_ROOT/.env" ]]; then
  while IFS='=' read -r key value; do
    case "$key" in
      DB_NAME)     DB_NAME="$value" ;;
      DB_USER)     DB_USER="$value" ;;
      DB_PASSWORD) DB_PASS="$value" ;;
    esac
  done < <(grep -E '^(DB_NAME|DB_USER|DB_PASSWORD)=' "$REPO_ROOT/.env" || true)
fi

# ── Guards ────────────────────────────────────────────────────────────────────
if ! command -v docker >/dev/null 2>&1; then
  echo "Error: Docker CLI not found." >&2
  exit 2
fi

if [[ $# -eq 0 ]]; then
  cat <<'USAGE'
Usage: ./scripts/wp.sh <command> [args...]

Commands:
  wp <args>       Run WP-CLI inside the WordPress container
  eval "<php>"    Run PHP with full WordPress bootstrap (wpdb initialized)
  db "<sql>"      Run a SQL query against the MySQL container
  log [N]         Tail the debug log (default: 80 lines)
  php <file>      Copy local PHP file to container and run via wp eval-file
  shell           Open interactive bash shell inside WordPress container
USAGE
  exit 0
fi

# ── MSYS_NO_PATHCONV prevents Git Bash from mangling Linux absolute paths ─────
export MSYS_NO_PATHCONV=1

COMMAND="$1"
shift

case "$COMMAND" in

  wp)
    if [[ $# -eq 0 ]]; then
      echo "Usage: ./scripts/wp.sh wp <wp-cli-args>" >&2
      exit 1
    fi
    docker exec -i "$WP_SERVICE" wp "$@" --allow-root
    ;;

  eval)
    if [[ $# -eq 0 ]]; then
      echo "Usage: ./scripts/wp.sh eval \"<php code>\"" >&2
      exit 1
    fi
    docker exec -i "$WP_SERVICE" wp eval "$1" --allow-root
    ;;

  db)
    if [[ $# -eq 0 ]]; then
      echo "Usage: ./scripts/wp.sh db \"<sql query>\"" >&2
      exit 1
    fi
    docker exec -i "$DB_SERVICE" mysql \
      -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      --silent -e "$1"
    ;;

  log)
    N="${1:-80}"
    if [[ -f "$LOG_PATH" ]]; then
      tail -n "$N" "$LOG_PATH"
    else
      echo "Log file not found at $LOG_PATH" >&2
      echo "Trying container path..." >&2
      docker exec -i "$WP_SERVICE" tail -n "$N" /var/www/html/wp-content/debug.log 2>/dev/null \
        || echo "No debug.log found in container either. Ensure WP_DEBUG_LOG=true is set." >&2
    fi
    ;;

  php)
    if [[ $# -eq 0 ]]; then
      echo "Usage: ./scripts/wp.sh php <local-php-file>" >&2
      exit 1
    fi
    local_file="$1"
    if [[ ! -f "$local_file" ]]; then
      echo "File not found: $local_file" >&2
      exit 1
    fi
    tmp_name="/tmp/wpcli_eval_$(date +%s).php"
    docker cp "$local_file" "$WP_SERVICE:$tmp_name"
    docker exec -i "$WP_SERVICE" wp eval-file "$tmp_name" --allow-root
    docker exec -i "$WP_SERVICE" rm -f "$tmp_name"
    ;;

  shell)
    docker exec -it "$WP_SERVICE" bash
    ;;

  *)
    echo "Unknown command: $COMMAND" >&2
    echo "Run './scripts/wp.sh' for usage." >&2
    exit 2
    ;;

esac
