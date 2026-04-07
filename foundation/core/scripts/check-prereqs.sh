#!/usr/bin/env bash
# Verify prerequisites for the repository tooling and provide actionable installation hints.
# This helps AI agents and humans avoid silent fallback behavior when a required tool is missing.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/.."
cd "$repo_root"

missing=0
recommended_missing=0

check_cmd_required() {
  local cmd="$1";
  local hint="$2";
  if ! command -v "$cmd" >/dev/null 2>&1; then
    printf "[MISSING] %s\n" "$cmd"
    if [ -n "$hint" ]; then
      printf "        %s\n" "$hint"
    fi
    missing=1
  fi
}

check_cmd_recommended() {
  local cmd="$1";
  local hint="$2";
  if ! command -v "$cmd" >/dev/null 2>&1; then
    printf "[RECOMMENDED MISSING] %s\n" "$cmd"
    if [ -n "$hint" ]; then
      printf "        %s\n" "$hint"
    fi
    recommended_missing=1
  fi
}

# Core tools used by scripts and validation.
check_cmd_required python "Install Python 3 (recommended: https://www.python.org/downloads/)"
check_cmd_required git "Install Git (https://git-scm.com/downloads)"
check_cmd_required awk "Install GNU awk (often preinstalled on Unix-like systems)"
check_cmd_required sed "Install sed (often preinstalled on Unix-like systems)"
check_cmd_required grep "Install grep (often preinstalled on Unix-like systems)"
check_cmd_required curl "Install curl (https://curl.se/)"

# Required tools (essential for the repo workflow).
check_cmd_required shellcheck "Install shellcheck (https://www.shellcheck.net/) for shell script linting."
check_cmd_required shfmt "Install shfmt (https://github.com/mvdan/sh) for shell formatting."

# Recommended tools (improve developer experience, but are not strictly required).
check_cmd_recommended jq "Install jq (https://stedolan.github.io/jq/) for JSON parsing in shell scripts."
check_cmd_recommended node "Install Node.js (https://nodejs.org/) if you work with JS tooling or run validate-js.sh."
check_cmd_recommended php "Install PHP CLI (https://www.php.net/) if you work with WordPress/PHP code."

# tiktoken used by ai-context-budget.sh for exact token counts.
if command -v python >/dev/null 2>&1; then
  if ! python -c 'import tiktoken' >/dev/null 2>&1; then
    printf "[RECOMMENDED MISSING] tiktoken (Python module)\n"
    printf "        Install via: pip install tiktoken\n"
    recommended_missing=1
  fi
  if ! python -c 'import bs4' >/dev/null 2>&1; then
    printf "[RECOMMENDED MISSING] beautifulsoup4 (Python module)\n"
    printf "        Install via: pip install beautifulsoup4\n"
    recommended_missing=1
  fi
fi

if [ $missing -eq 0 ]; then
  echo "All required tooling is present."
  if [ $recommended_missing -ne 0 ]; then
    echo "\nNote: Some recommended tools are missing; install them to improve workflow robustness." >&2
  fi
else
  echo "\nOne or more required tools are missing. Install them and rerun this script." >&2
  exit 1
fi
