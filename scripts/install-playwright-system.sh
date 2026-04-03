#!/usr/bin/env bash
# install-playwright-system.sh
#
# Installs Playwright Chromium browser + all Linux system dependencies
# at the system/user level so every workspace can use it.
#
# This script is approved for both external WSL terminals and the IDE
# integrated terminal via catalog command qa.playwright.browsers.install.
#
# Usage:
#   ./scripts/install-playwright-system.sh
#
# Catalog ID: qa.playwright.browsers.install

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PW_DIR="$REPO_ROOT/tools/playwright"
NODE_BIN="$(command -v node || command -v nodejs || true)"
SUDO_BIN=""

if [[ "$(id -u)" -ne 0 ]]; then
  SUDO_BIN="sudo"
fi

# ── Verify node is available ─────────────────────────────────────────────────
if [[ -z "$NODE_BIN" ]]; then
  echo "ERROR: Node.js runtime not found. Install nodejs first:" >&2
  echo "  sudo apt-get install -y nodejs" >&2
  exit 4
fi

echo "[playwright-system-install] Node: $NODE_BIN ($("$NODE_BIN" --version))"
if [[ -n "$SUDO_BIN" ]]; then
  echo "[playwright-system-install] Using sudo for system package installation"
else
  echo "[playwright-system-install] Running as root; sudo not required"
fi

# ── Verify node_modules exist ────────────────────────────────────────────────
if [[ ! -f "$PW_DIR/node_modules/playwright/cli.js" ]]; then
  echo "[playwright-system-install] node_modules missing — running npm install..."
  # Use linux npm if available; fall back to npm in PATH
  LINUX_NPM="$(command -v npm 2>/dev/null | grep -v '/mnt/c' || true)"
  if [[ -z "$LINUX_NPM" ]]; then
    echo "ERROR: Linux npm not found. Install nodejs/npm first." >&2
    exit 4
  fi
  (cd "$PW_DIR" && "$LINUX_NPM" install --no-audit --no-fund)
fi

# ── Install Linux system dependencies for Chromium ───────────────────────────
echo "[playwright-system-install] Installing Chromium system dependencies..."
export DEBIAN_FRONTEND=noninteractive
${SUDO_BIN:+$SUDO_BIN }apt-get update -qq
# Install deps via playwright's own installer (most reliable approach)
PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright \
  ${SUDO_BIN:+$SUDO_BIN }"$NODE_BIN" "$PW_DIR/node_modules/playwright/cli.js" install-deps chromium

# ── Install Chromium browser to shared cache ─────────────────────────────────
echo "[playwright-system-install] Downloading Chromium browser..."
PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright \
  "$NODE_BIN" "$PW_DIR/node_modules/playwright/cli.js" install chromium

# ── Persist the browser path so playwright-selftest.sh always finds it ───────
BROWSERS_ENV_LINE='export PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright'
PROFILE_FILE="/root/.profile"
if ! grep -qF 'PLAYWRIGHT_BROWSERS_PATH' "$PROFILE_FILE" 2>/dev/null; then
  echo "$BROWSERS_ENV_LINE" >> "$PROFILE_FILE"
  echo "[playwright-system-install] Added PLAYWRIGHT_BROWSERS_PATH to $PROFILE_FILE"
fi

# Also export for the current shell session
export PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright

# ── Verify ───────────────────────────────────────────────────────────────────
echo "[playwright-system-install] Verifying installation..."
CHROMIUM_DIR="$(ls -d /root/.cache/ms-playwright/chromium* 2>/dev/null | head -1 || true)"
if [[ -z "$CHROMIUM_DIR" ]]; then
  echo "ERROR: Chromium not found in /root/.cache/ms-playwright/" >&2
  exit 1
fi

echo "[playwright-system-install] DONE"
echo "  Chromium: $CHROMIUM_DIR"
echo ""
echo "You can now run Playwright tests from VS Code:"
echo "  ./scripts/run-catalog-command.sh qa.playwright.test.smoke"
