#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: ./scripts/validate-remediation-commit-msg.sh <commit_msg_file>" >&2
  exit 2
fi

msg_file="$1"
if [[ ! -f "$msg_file" ]]; then
  echo "ERROR: commit message file not found: $msg_file" >&2
  exit 2
fi

# Ignore comments and blank lines for policy checks.
msg_content="$(sed -e '/^#/d' -e '/^[[:space:]]*$/d' "$msg_file")"

if ! grep -qi '^Remediation-Type:[[:space:]]*' <<<"$msg_content"; then
  cat >&2 <<'EOF'
ERROR: Missing required trailer: Remediation-Type
Required values:
  Remediation-Type: source-fix
  Remediation-Type: mitigation
See specs/AI-Root-Cause-Remediation-Policy.md
EOF
  exit 1
fi

rtype="$(grep -i '^Remediation-Type:[[:space:]]*' <<<"$msg_content" | tail -n1 | sed -E 's/^Remediation-Type:[[:space:]]*//I' | tr '[:upper:]' '[:lower:]')"

if [[ "$rtype" != "source-fix" && "$rtype" != "mitigation" ]]; then
  echo "ERROR: Remediation-Type must be 'source-fix' or 'mitigation' (got '$rtype')." >&2
  exit 1
fi

if [[ "$rtype" == "mitigation" ]]; then
  missing=0
  for field in "Root-Cause" "Mitigation-Reason" "Removal-Trigger" "Follow-Up-Issue"; do
    if ! grep -qi "^${field}:[[:space:]]*" <<<"$msg_content"; then
      echo "ERROR: mitigation commit missing required field: ${field}" >&2
      missing=1
    fi
  done
  if [[ "$missing" -ne 0 ]]; then
    echo "See specs/AI-Root-Cause-Remediation-Policy.md for required mitigation metadata." >&2
    exit 1
  fi
fi

exit 0
