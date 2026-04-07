#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$REPO_ROOT"

if command -v python3 >/dev/null 2>&1; then
	PYTHON_CMD=python3
elif command -v python >/dev/null 2>&1; then
	PYTHON_CMD=python
else
	echo "Python is required to validate JSON files." >&2
	exit 2
fi

collect_files() {
	if [[ $# -gt 0 ]]; then
		printf '%s\n' "$@"
	else
		git diff --name-only --diff-filter=ACMR HEAD -- '*.json'
	fi
}

mapfile -t files < <(collect_files "$@")

if [[ ${#files[@]} -eq 0 ]]; then
	echo "No JSON files to validate."
	exit 0
fi

status=0

for file in "${files[@]}"; do
	[[ -n "$file" ]] || continue

	if [[ ! -f "$file" ]]; then
		echo "SKIP $file (missing in working tree)"
		continue
	fi

	if "$PYTHON_CMD" -m json.tool "$file" >/dev/null; then
		echo "OK   $file"
	else
		echo "FAIL $file"
		status=1
	fi
done

exit $status
