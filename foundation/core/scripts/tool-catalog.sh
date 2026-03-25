#!/usr/bin/env bash
# Lists the repo’s canonical tooling scripts and their short descriptions.
# Designed to be used by humans and by AI agents before running shell commands.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
script_dir="$repo_root"

printf "\nRepo tooling catalog (scripts/*):\n"
for file in "$script_dir"/*; do
  [ -f "$file" ] || continue
  name="$(basename "$file")"
  # Ignore helper catalogs and non-script artifacts
  [[ "$name" =~ \.(md|txt|json)$ ]] && continue

  desc="(no description found)"
  # Find first comment line after any shebang
  while IFS= read -r line; do
    # skip empty lines
    [[ -z "$line" ]] && continue
    # skip shebang
    [[ "$line" =~ ^#! ]] && continue
    # match comment prefixes for common script types
    case "$line" in
      "" )
        continue
        ;;
      "#"* )
        desc="${line#*# }"
        break
        ;;
      "//"* )
        desc="${line#*// }"
        break
        ;;
      ";"* )
        desc="${line#*; }"
        break
        ;;
      REM\ * )
        desc="${line#*REM }"
        break
        ;;
    esac
  done < <(head -n 40 "$file")

  printf "  • %-30s %s\n" "$name" "$desc"
done

cat <<'EOF'

Tip: Use this catalog before running ad-hoc terminal commands; the scripts are designed to enforce repo conventions.
EOF
