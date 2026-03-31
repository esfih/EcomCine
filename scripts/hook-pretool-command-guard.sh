#!/usr/bin/env bash
set -euo pipefail

payload="$(cat || true)"

WORKSPACE_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

WIN_MOUNT_PATTERN='/m'"nt/"'[a-z]/'
WIN_DRIVE_PATTERN='[A-Za-z]:\\\\'

json_deny() {
  local reason="$1"
  local stop_reason="$2"
  local system_message="$3"
  cat <<JSON
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "$reason"
  },
  "stopReason": "$stop_reason",
  "systemMessage": "$system_message"
}
JSON
}

is_terminal_tool=false
if echo "$payload" | grep -Eiq '"toolName"\s*:\s*"run_in_terminal"|"tool_name"\s*:\s*"run_in_terminal"'; then
  is_terminal_tool=true
fi

is_mutating_file_tool=false
if echo "$payload" | grep -Eiq '"toolName"\s*:\s*"(apply_patch|create_file|create_directory|edit_notebook_file|create_new_jupyter_notebook)"|"tool_name"\s*:\s*"(apply_patch|create_file|create_directory|edit_notebook_file|create_new_jupyter_notebook)"'; then
  is_mutating_file_tool=true
fi

is_path_field_tool=false
if echo "$payload" | grep -Eiq '"filePath"\s*:|"dirPath"\s*:|"path"\s*:|"new_path"\s*:|"old_path"\s*:'; then
  is_path_field_tool=true
fi

if [[ "$is_path_field_tool" == true ]]; then
  if echo "$payload" | grep -Eiq "$WIN_MOUNT_PATTERN|$WIN_DRIVE_PATTERN"; then
    json_deny \
      "Windows-mounted or Windows-style paths are blocked. Use the WSL workspace path only." \
      "Blocked non-WSL path pattern." \
      "Operate only under the WSL repo path (for example /home/<user>/dev/... or /root/dev/...)."
    exit 0
  fi
fi

if [[ "$is_mutating_file_tool" == true ]]; then
  # Require mutating file tools to target this workspace path.
  # This prevents accidental writes outside the active WSL workspace.
  if ! echo "$payload" | grep -q "$WORKSPACE_ROOT"; then
    json_deny \
      "Mutating file operations must target the active WSL workspace root: $WORKSPACE_ROOT" \
      "Blocked out-of-workspace file mutation." \
      "Retry with file paths under $WORKSPACE_ROOT."
    exit 0
  fi
fi

if [[ "$is_terminal_tool" == true ]]; then
  # Block terminal commands that explicitly navigate or reference Windows mounts.
  if echo "$payload" | grep -Eiq "cd[[:space:]]+$WIN_MOUNT_PATTERN|[[:space:]]$WIN_MOUNT_PATTERN|$WIN_DRIVE_PATTERN"; then
    json_deny \
      "Terminal commands referencing Windows paths are blocked in this repo." \
      "Blocked Windows-path terminal command." \
      "Run commands only from WSL paths under the repository root."
    exit 0
  fi

  # Block terminal commands when current working directory itself is outside WSL project roots.
  case "$PWD" in
    /home/*/dev/*|/root/dev/*)
      ;;
    *)
      json_deny \
        "Terminal command blocked: current directory is outside approved WSL project roots (/home/*/dev/* or /root/dev/*)." \
        "Blocked terminal outside WSL project root." \
        "Change directory to the WSL project path and retry."
      exit 0
      ;;
  esac

  if echo "$payload" | grep -Eiq '(^|[^A-Za-z])(apt|apt-get)[^"]*install|npm[^"]*install[[:space:]]+-g|pip3?[^"]*install[^"]*--user'; then
    json_deny \
      "Interactive package-manager installs are blocked in IDE terminal flow. Use ./scripts/run-catalog-command.sh host.tool.install <tool> from an external WSL terminal." \
      "Blocked unsafe terminal command pattern." \
      "Use host.tool.install instead of apt/npm -g/pip --user in IDE agent terminal flow."
    exit 0
  fi
fi

cat <<'JSON'
{
  "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "allow",
    "permissionDecisionReason": "No blocked package-manager command pattern detected."
  }
}
JSON
