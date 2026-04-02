#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PW_DIR="$REPO_ROOT/tools/playwright"
PW_DOCKER_IMAGE="mcr.microsoft.com/playwright:v1.54.2-noble"

if [[ $# -lt 1 ]]; then
  echo "Usage: ./scripts/playwright-selftest.sh <install|smoke|interactions|debug|headed|report>" >&2
  exit 2
fi

ACTION="$1"
shift || true

# Resolve Linux-native node (exclude Windows-mounted paths under /mnt/c).
NODE_BIN="$(command -v node 2>/dev/null | grep -v '/mnt/c' || command -v nodejs 2>/dev/null | grep -v '/mnt/c' || true)"

has_local_node=false
if [[ -n "$NODE_BIN" && -f "$PW_DIR/node_modules/playwright/cli.js" ]]; then
  has_local_node=true
fi

local_runtime_ready_cache=""

probe_local_playwright_runtime() {
  if [[ "$has_local_node" != true ]]; then
    return 1
  fi

  if [[ -n "$local_runtime_ready_cache" ]]; then
    [[ "$local_runtime_ready_cache" == "ok" ]]
    return
  fi

  if "$NODE_BIN" - "$PW_DIR" >/dev/null 2>&1 <<'NODE'
const path = require('path');
const pwDir = process.argv[2];
const playwright = require(path.join(pwDir, 'node_modules', 'playwright'));

(async () => {
  const browser = await playwright.chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('about:blank');
  await browser.close();
})();
NODE
  then
    local_runtime_ready_cache="ok"
    return 0
  fi

  local_runtime_ready_cache="fail"
  return 1
}

# Use the repo-local Playwright CLI — avoids relying on npx which may resolve
# to the Windows-hosted npm on WSL2 development machines.
PW_CLI="$PW_DIR/node_modules/playwright/cli.js"

# Ensure Playwright finds its browser cache regardless of calling environment.
export PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-/root/.cache/ms-playwright}"

run_in_docker() {
  docker run --rm \
    --network host \
    -v "$PW_DIR:/work" \
    -w /work \
    "$PW_DOCKER_IMAGE" \
    bash -lc "$1"
}

cd "$PW_DIR"

case "$ACTION" in
  install)
    if [[ "$has_local_node" == true ]]; then
      # npm install not needed if node_modules already present; browser install is the key step.
      "$NODE_BIN" "$PW_CLI" install chromium
    else
      echo "[playwright-selftest] Local Linux Node not found or node_modules missing."
      echo "Run from an external WSL terminal: ./scripts/install-playwright-system.sh"
      echo "Falling back to Docker image $PW_DOCKER_IMAGE ..."
      run_in_docker "node node_modules/playwright/cli.js install chromium"
    fi
    ;;

  smoke)
    if [[ "$has_local_node" == true ]] && probe_local_playwright_runtime; then
      "$NODE_BIN" "$PW_CLI" test --project=chromium --grep @smoke "$@"
    else
      echo "[playwright-selftest] Local browser runtime is unavailable; using Docker image $PW_DOCKER_IMAGE"
      run_in_docker "node node_modules/playwright/cli.js test --project=chromium --grep @smoke $*"
    fi
    ;;

  interactions)
    SCENARIO_FILE=""
    if [[ $# -gt 0 && "${1:0:1}" != "-" ]]; then
      SCENARIO_FILE="$1"
      shift

      if [[ "$SCENARIO_FILE" == "tools/playwright/"* ]]; then
        SCENARIO_FILE="${SCENARIO_FILE#tools/playwright/}"
      elif [[ "$SCENARIO_FILE" == "$PW_DIR/"* ]]; then
        SCENARIO_FILE="${SCENARIO_FILE#"$PW_DIR/"}"
      fi

      if [[ ! -f "$SCENARIO_FILE" ]]; then
        echo "ERROR: Interaction scenario file not found: $SCENARIO_FILE" >&2
        exit 12
      fi
    fi

    if [[ "$has_local_node" == true ]] && probe_local_playwright_runtime; then
      if [[ -n "$SCENARIO_FILE" ]]; then
        ECOMCINE_INTERACTIONS_FILE="$SCENARIO_FILE" "$NODE_BIN" "$PW_CLI" test --project=chromium --grep @interactions "$@"
      else
        "$NODE_BIN" "$PW_CLI" test --project=chromium --grep @interactions "$@"
      fi
    else
      echo "[playwright-selftest] Local browser runtime is unavailable; using Docker image $PW_DOCKER_IMAGE"
      if [[ -n "$SCENARIO_FILE" ]]; then
        run_in_docker "ECOMCINE_INTERACTIONS_FILE='$SCENARIO_FILE' node node_modules/playwright/cli.js test --project=chromium --grep @interactions $*"
      else
        run_in_docker "node node_modules/playwright/cli.js test --project=chromium --grep @interactions $*"
      fi
    fi
    ;;

  debug)
    if [[ "$has_local_node" == true ]] && probe_local_playwright_runtime; then
      "$NODE_BIN" "$PW_CLI" test --project=chromium --trace on --workers=1 "$@"
    else
      echo "[playwright-selftest] Local browser runtime is unavailable; using Docker image $PW_DOCKER_IMAGE"
      run_in_docker "node node_modules/playwright/cli.js test --project=chromium --trace on --workers=1 $*"
    fi
    ;;

  headed)
    if [[ "$has_local_node" == true ]] && probe_local_playwright_runtime; then
      "$NODE_BIN" "$PW_CLI" test --project=chromium --headed --workers=1 "$@"
    else
      echo "[playwright-selftest] Local browser runtime is unavailable; using Docker image $PW_DOCKER_IMAGE"
      run_in_docker "node node_modules/playwright/cli.js test --project=chromium --headed --workers=1 $*"
    fi
    ;;

  report)
    if [[ "$has_local_node" == true ]]; then
      "$NODE_BIN" "$PW_CLI" show-report
    else
      echo "[playwright-selftest] Local Linux Node not found; using Docker image $PW_DOCKER_IMAGE"
      run_in_docker "node node_modules/playwright/cli.js show-report"
    fi
    ;;

  *)
    echo "ERROR: Unknown action '$ACTION'" >&2
    echo "Allowed actions: install smoke interactions debug headed report" >&2
    exit 2
    ;;
esac
