#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$REPO_ROOT"

usage() {
	cat <<'EOF'
Usage:
  scripts/list-changed-files.sh
  scripts/list-changed-files.sh --staged
  scripts/list-changed-files.sh --range <base> <head>
EOF
}

mode="worktree"
range_base=""
range_head=""

while [[ $# -gt 0 ]]; do
	case "$1" in
		--staged)
			mode="staged"
			shift
			;;
		--range)
			if [[ $# -lt 3 ]]; then
				echo "--range requires <base> and <head>." >&2
				usage >&2
				exit 2
			fi
			mode="range"
			range_base="$2"
			range_head="$3"
			shift 3
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage >&2
			exit 2
			;;
	esac
done

case "$mode" in
	worktree)
		git diff --name-only --diff-filter=ACMR HEAD
		;;
	staged)
		git diff --cached --name-only --diff-filter=ACMR
		;;
	range)
		git diff --name-only --diff-filter=ACMR "$range_base" "$range_head"
		;;
esac | awk 'NF { print }' | sort -u