#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-9191}"

exec "$REPO_ROOT/tools/server-side-converter/start.sh" "$PORT"