#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: ./scripts/install-host-tool.sh <tool>" >&2
  echo "Allowed tools: ripgrep jq yq php-cli nodejs" >&2
  exit 2
fi

TOOL="$1"

case "$TOOL" in
  ripgrep|jq|yq|php-cli|nodejs)
    ;;
  *)
    echo "ERROR: Unsupported tool '$TOOL'." >&2
    echo "Allowed tools: ripgrep jq yq php-cli nodejs" >&2
    exit 2
    ;;
esac

if [[ "${TERM_PROGRAM:-}" == "vscode" || -n "${VSCODE_IPC_HOOK_CLI:-}" ]]; then
  echo "ERROR: Refusing to run apt install from VS Code integrated terminal." >&2
  echo "This can freeze the IDE renderer due to interactive/package-manager output volume." >&2
  echo "Run this from an external WSL terminal instead:" >&2
  echo "  ./scripts/install-host-tool.sh $TOOL" >&2
  exit 10
fi

export DEBIAN_FRONTEND=noninteractive

echo "[host.tool.install] Installing $TOOL ..."
sudo apt-get update -qq
sudo apt-get install -y -qq "$TOOL"

if [[ "$TOOL" == "php-cli" ]]; then
  command -v php >/dev/null
  echo "Installed: $(command -v php)"
elif [[ "$TOOL" == "nodejs" ]]; then
  command -v node >/dev/null 2>&1 || command -v nodejs >/dev/null
  if command -v node >/dev/null 2>&1; then
    echo "Installed: $(command -v node)"
  else
    echo "Installed: $(command -v nodejs)"
  fi
else
  command -v "$TOOL" >/dev/null
  echo "Installed: $(command -v "$TOOL")"
fi
