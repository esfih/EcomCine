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

  wp.remote.app.inspect)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: wp.remote.app.inspect requires remote WP-CLI args" >&2
      exit 2
    fi
    REMOTE_KEY_PATH="$HOME/.ssh/ecomcine-dev"
    if [[ ! -f "$REMOTE_KEY_PATH" ]]; then
      REMOTE_KEY_PATH="$HOME/.ssh/ecomcine_n0c"
    fi
    REMOTE_USER="efttsqrtff" \
    REMOTE_HOST="209.16.158.249" \
    REMOTE_PORT="5022" \
    REMOTE_WPATH="/home/efttsqrtff/app.topdoctorchannel.us" \
    REMOTE_KEY="$REMOTE_KEY_PATH" \
    ./scripts/wp-remote.sh "$@"
    ;;

  wp.remote.app.deploy.ecomcine)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: wp.remote.app.deploy.ecomcine requires <version> [slug]" >&2
      exit 2
    fi
    VERSION="$1"
    SLUG="${2:-ecomcine}"
    PACKAGE_URL="https://github.com/esfih/EcomCine/releases/download/v${VERSION}/${SLUG}-${VERSION}.zip"
    REMOTE_KEY_PATH="$HOME/.ssh/ecomcine-dev"
    if [[ ! -f "$REMOTE_KEY_PATH" ]]; then
      REMOTE_KEY_PATH="$HOME/.ssh/ecomcine_n0c"
    fi
    REMOTE_USER="efttsqrtff" \
    REMOTE_HOST="209.16.158.249" \
    REMOTE_PORT="5022" \
    REMOTE_WPATH="/home/efttsqrtff/app.topdoctorchannel.us" \
    REMOTE_KEY="$REMOTE_KEY_PATH" \
    ./scripts/wp-remote.sh plugin install "$PACKAGE_URL" --force --activate
    ;;

  db.seed.import.core)
    ./scripts/wp.sh wp db import db/seed.sql
    ;;

  qa.playwright.install)
    bash ./scripts/playwright-selftest.sh install "$@"
    ;;

  qa.playwright.browsers.install)
    bash ./scripts/install-playwright-system.sh "$@"
    ;;

  qa.playwright.test.smoke)
    bash ./scripts/playwright-selftest.sh smoke "$@"
    ;;

  qa.playwright.test.interactions)
    bash ./scripts/playwright-selftest.sh interactions "$@"
    ;;

  qa.playwright.test.debug)
    bash ./scripts/playwright-selftest.sh debug "$@"
    ;;

  qa.playwright.test.headed)
    bash ./scripts/playwright-selftest.sh headed "$@"
    ;;

  qa.playwright.report)
    bash ./scripts/playwright-selftest.sh report "$@"
    ;;

  wp.debug.log.tail)
    if [[ $# -gt 0 ]]; then
      ./scripts/wp.sh log "$1"
    else
      ./scripts/wp.sh log
    fi
    ;;

  wp.debug.php.info)
    ./scripts/wp.sh wp --info
    ;;

  debug.snapshot.collect)
    if [[ $# -gt 0 ]]; then
      bash ./scripts/collect-debug-snapshot.sh "$1"
    else
      bash ./scripts/collect-debug-snapshot.sh
    fi
    ;;

  db.seed.import.fluentcart_cp)
    ./scripts/licensing/import-fluentcart-control-plane-seed.sh "$@"
    ;;

  db.seed.export.fluentcart_cp)
    ./scripts/licensing/export-fluentcart-control-plane-seed.sh "$@"
    ;;

  data.vendors.import.demo)
    if [[ $# -gt 0 ]]; then
      ./scripts/wp.sh wp eval "EcomCine_Demo_Importer::run_remote_cli('$1');"
    else
      ./scripts/wp.sh wp eval 'EcomCine_Demo_Importer::run_remote_cli();'
    fi
    ;;

  wp.data.migration.dokan)
    ./scripts/wp.sh wp eval 'if(class_exists("EcomCine_Dokan_Data_Migration",false)){$r=EcomCine_Dokan_Data_Migration::run();echo json_encode($r);}'
    ;;

  demos.release)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: demos.release requires <version> [--push]" >&2
      exit 2
    fi
    ./scripts/build-demos-release.sh "$@"
    ;;

  demos.media.convert.fast)
    if [[ $# -lt 2 ]]; then
      echo "ERROR: demos.media.convert.fast requires <input_dir> <output_dir> [quality] [crf]" >&2
      exit 2
    fi
    ./scripts/convert-videos-fast.sh "$@"
    ;;

  demos.media.rebuild)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: demos.media.rebuild requires <pack-id> [converted_media_dir] [target_media_dir] [quality] [crf]" >&2
      exit 2
    fi
    bash ./scripts/rebuild-demo-media.sh "$@"
    ;;

  demos.media.prepare)
    if [[ $# -lt 3 ]]; then
      echo "ERROR: demos.media.prepare requires <source_media_dir> <converted_media_dir> <target_media_dir>" >&2
      exit 2
    fi
    bash ./scripts/prepare-demo-media.sh "$@"
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

  release.build.ecomcine.clean-head)
    bash ./scripts/build-ecomcine-release-from-head.sh
    ;;

  release.upload.ecomcine.canonical)
    if [[ $# -lt 2 ]]; then
      echo "ERROR: release.upload.ecomcine.canonical requires <tag> <version> [slug]" >&2
      exit 2
    fi
    bash ./scripts/release-upload-canonical-assets.sh "$@"
    ;;

  release.verify.canonical.assets)
    if [[ $# -lt 2 ]]; then
      echo "ERROR: release.verify.canonical.assets requires <tag> <version> [slug]" >&2
      exit 2
    fi
    bash ./scripts/verify-release-canonical-assets.sh "$@"
    ;;

  updates.package.clean)
    bash ./scripts/package-updates-ecomcine-clean.sh
    ;;

  updates.verify.package)
    bash ./scripts/verify-updater-package.sh "$@"
    ;;

  updates.cache.clear)
    bash ./scripts/clear-updater-cache.sh "$@"
    ;;

  git.stage.paths)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: git.stage.paths requires one or more file paths" >&2
      exit 2
    fi
    git add -- "$@"
    ;;

  git.commit.create)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: git.commit.create requires <commit_message>" >&2
      exit 2
    fi
    git commit -m "$1"
    ;;

  github.release.create)
    if [[ $# -lt 3 ]]; then
      echo "ERROR: github.release.create requires <tag> <title> <notes> [asset_paths...]" >&2
      exit 2
    fi
    TAG="$1"
    TITLE="$2"
    NOTES="$3"
    shift 3
    if [[ $# -gt 0 ]]; then
      gh release create "$TAG" "$@" --title "$TITLE" --notes "$NOTES"
    else
      gh release create "$TAG" --title "$TITLE" --notes "$NOTES"
    fi
    ;;

  github.release.upload)
    if [[ $# -lt 2 ]]; then
      echo "ERROR: github.release.upload requires <tag> <asset_paths...>" >&2
      exit 2
    fi
    TAG="$1"
    shift
    gh release upload "$TAG" "$@" --clobber
    ;;

  gh.auth.status)
    gh auth status
    ;;

  gh.auth.login.web)
    gh auth login --hostname github.com --git-protocol https --web
    ;;

  gh.auth.setup-git)
    gh auth setup-git
    ;;

  gh.repo.set-default)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: gh.repo.set-default requires <owner/repo>" >&2
      exit 2
    fi
    gh repo set-default "$1"
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

  host.tool.install)
    if [[ $# -lt 1 ]]; then
      echo "ERROR: host.tool.install requires <tool>" >&2
      echo "Allowed tools: ripgrep jq yq php-cli nodejs" >&2
      exit 2
    fi
    bash ./scripts/install-host-tool.sh "$1"
    ;;

  node.path.detect)
    command -v node || command -v nodejs || echo "Node not found"
    ;;

  ollama.path.detect)
    command -v ollama || echo "Ollama not found"
    ;;

  ollama.service.start)
    mkdir -p logs && nohup ollama serve >> logs/ollama-server.log 2>&1 &
    ;;

  ollama.service.stop)
    pkill -f 'ollama serve' || true
    pkill -f '/ollama' || true
    ;;

  ollama.service.log.tail)
    mkdir -p logs && touch logs/ollama-server.log && tail -n 80 logs/ollama-server.log
    ;;

  ollama.windows.probe)
    HOST="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"
    echo "WSL->Windows host: ${HOST:-unknown}"
    echo '--- probe localhost ---'
    curl -sS -m 3 http://127.0.0.1:11434/api/version || echo 'down'
    echo
    echo '--- probe windows-host-ip ---'
    if [[ -n "${HOST}" ]]; then
      curl -sS -m 3 "http://${HOST}:11434/api/version" || echo 'down'
    else
      echo 'down'
    fi
    ;;

  ollama.windows.probe.full)
    set -e
    C1="127.0.0.1"
    C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"
    C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"
    C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"
    echo "Candidates: $C1 ${C2:-} ${C3:-} ${C4:-}"
    for H in "$C1" "$C2" "$C3" "$C4"; do
      [[ -z "$H" ]] && continue
      echo "--- probe $H ---"
      curl -sS -m 3 "http://$H:11434/api/version" || echo down
      echo
    done
    ;;

  ollama.windows.query.tags)
    set -e
    C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"
    C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"
    C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"
    for H in 127.0.0.1 "$C2" "$C3" "$C4"; do
      [[ -z "$H" ]] && continue
      if curl -fsS -m 3 "http://$H:11434/api/version" >/dev/null; then
        echo "Using host: $H"
        curl -fsS -m 8 "http://$H:11434/api/tags"
        exit 0
      fi
    done
    echo "No reachable Windows Ollama host from WSL" >&2
    exit 7
    ;;

  ollama.windows.query.generate)
    set -e
    MODEL="${1:-qwen3.5:9b-slim-256k}"
    PROMPT="${2:-Reply with OK}"
    C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"
    C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"
    C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"
    for H in 127.0.0.1 "$C2" "$C3" "$C4"; do
      [[ -z "$H" ]] && continue
      if curl -fsS -m 3 "http://$H:11434/api/version" >/dev/null; then
        echo "Using host: $H"
        curl -fsS -m 30 "http://$H:11434/api/generate" \
          -H 'Content-Type: application/json' \
          -d "{\"model\":\"${MODEL}\",\"prompt\":\"${PROMPT}\",\"stream\":false,\"options\":{\"num_predict\":32}}"
        exit 0
      fi
    done
    echo "No reachable Windows Ollama host from WSL" >&2
    exit 7
    ;;

  ollama.proxy.manager.start)
    NODE_BIN="$(command -v node || command -v nodejs || true)"
    if [[ -z "$NODE_BIN" ]]; then
      echo "ERROR: Node runtime not found (expected 'node' or 'nodejs')." >&2
      exit 4
    fi
    mkdir -p logs && nohup "$NODE_BIN" tools/ollama-proxy-manager.js >> logs/ollama-proxy-manager.log 2>&1 &
    ;;

  ollama.proxy.manager.stop)
    pkill -f 'tools/ollama-proxy-manager.js' || true
    pkill -f 'tools/ollama-nothink-proxy.js' || true
    ;;

  ollama.proxy.status)
    C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"
    C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"
    C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"
    echo '--- Ollama localhost ---'
    curl -sf http://127.0.0.1:11434/api/version || echo 'down'
    echo
    for H in "$C2" "$C3" "$C4"; do
      [[ -z "$H" ]] && continue
      echo "--- Ollama $H ---"
      curl -sf "http://$H:11434/api/version" || echo 'down'
      echo
    done
    echo '--- Proxy ---'
    curl -sf http://127.0.0.1:11435/proxy/status || echo 'down'
    ;;

  ollama.proxy.mode.nothink)
    curl -sf -X POST http://127.0.0.1:11435/proxy/mode -H 'Content-Type: application/json' -d '{"mode":"nothink"}'
    ;;

  ollama.proxy.mode.passthrough)
    curl -sf -X POST http://127.0.0.1:11435/proxy/mode -H 'Content-Type: application/json' -d '{"mode":"passthrough"}'
    ;;

  ollama.proxy.log.manager.tail)
    mkdir -p logs && touch logs/ollama-proxy-manager.log && tail -n 80 logs/ollama-proxy-manager.log
    ;;

  ollama.proxy.log.proxy.tail)
    mkdir -p logs && touch logs/ollama-nothink-proxy.log && tail -n 80 logs/ollama-nothink-proxy.log
    ;;

  ollama.proxy.smoke)
    MODEL="${1:-qwen3.5:9b}"
    curl -sf http://127.0.0.1:11435/v1/chat/completions \
      -H 'Content-Type: application/json' \
      -d "{\"model\":\"${MODEL}\",\"messages\":[{\"role\":\"user\",\"content\":\"Reply with OK\"}],\"stream\":false}"
    ;;

  *)
    if [[ "${ECOMCINE_CATALOG_UNKNOWN_MODE:-ask}" == "ask" ]]; then
      echo "APPROVAL_REQUIRED: Unknown command catalog ID: $COMMAND_ID" >&2
      echo "Reason: this task needs a non-cataloged command. Please approve adding a catalog entry or provide a temporary one-time command." >&2
      echo "Reference: specs/IDE-AI-Command-Catalog.md" >&2
      exit 4
    fi
    echo "ERROR: Unknown command catalog ID: $COMMAND_ID" >&2
    echo "No improvisation allowed. Propose a new entry in specs/IDE-AI-Command-Catalog.md and request user approval." >&2
    exit 3
    ;;
esac
