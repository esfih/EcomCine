#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
PORT="${1:-9191}"
NODE_BIN="$(command -v node 2>/dev/null || command -v nodejs 2>/dev/null || true)"

if [[ -z "$NODE_BIN" ]]; then
  echo "Node.js was not found on PATH." >&2
  exit 1
fi

if curl -sf "http://127.0.0.1:${PORT}/api/config" >/dev/null 2>&1; then
  echo "Standalone server-side converter is already running on http://127.0.0.1:${PORT}"
  exit 0
fi

echo "Starting standalone server-side converter on http://127.0.0.1:${PORT}"
exec "$NODE_BIN" "$DIR/server.js" "$PORT"