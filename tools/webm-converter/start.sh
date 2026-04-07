#!/usr/bin/env bash
# Start the WebM Converter dev server
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
PORT="${1:-9000}"

NODE_BIN="$(command -v node || command -v nodejs || true)"
if [ -z "$NODE_BIN" ]; then
  echo "Node.js not found. Install it with: apt install nodejs"
  exit 1
fi

echo "Starting WebM Converter on http://localhost:${PORT}"
echo "Logs → ${DIR}/logs/"
exec "$NODE_BIN" "${DIR}/server.js" "$PORT"
