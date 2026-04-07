#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

MODE="dry-run"
AUTHORITATIVE_RESET="0"
SKIP_RUNTIME_CHECKS="0"
RUN_READINESS="1"
REFRESH_CONTEXT_BUDGET="0"

usage() {
  cat <<'EOF'
Receiver-side takeover automation.

Usage:
  scripts/takeover-pull.sh [options]

Options:
  --apply                  Run mutations.
  --authoritative-reset    Discard all tracked changes and untracked non-ignored files
                           before pull. Use only when GitHub is the intended source of truth.
  --refresh-context-budget Run scripts/update-ide-context-budget.py after pull.
                           Restore metadata-only refresh output automatically so the
                           receiver worktree stays clean unless unexpected drift appears.
  --skip-runtime-checks    Skip verify-plugin-sync.sh and check-local-wp.sh.
  --skip-readiness         Skip check-devops-readiness.ps1.
  -h, --help               Show help.

What it does:
  1) Audits current branch, upstream, ahead/behind, and local drift.
  2) Auto-classifies approved receiver-side drift:
     - GIT-RELEASE-CONTEXT-HISTORY.md
     - context-budget/token-estimate only diffs in canonical docs
  3) In --apply mode, restores only approved drift and blocks on unexpected edits.
  4) Pulls with --ff-only.
    5) Optionally refreshes IDE context budget metadata and restores approved
      metadata-only refresh output.
    6) Runs readiness and local runtime verification checks.

Safety defaults:
  - Dry-run by default.
  - Never pulls through unexpected dirty state.
  - Uses pull --ff-only only.
  - Authoritative reset is opt-in only.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply)
      MODE="apply"
      shift
      ;;
    --authoritative-reset)
      AUTHORITATIVE_RESET="1"
      shift
      ;;
    --refresh-context-budget)
      REFRESH_CONTEXT_BUDGET="1"
      shift
      ;;
    --skip-runtime-checks)
      SKIP_RUNTIME_CHECKS="1"
      shift
      ;;
    --skip-readiness)
      RUN_READINESS="0"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 2
      ;;
  esac
done

log() {
  printf '%s\n' "$*"
}

detect_python() {
  if command -v python >/dev/null 2>&1; then
    printf '%s\n' python
    return 0
  fi

  if command -v python3 >/dev/null 2>&1; then
    printf '%s\n' python3
    return 0
  fi

  return 1
}

contains_exact() {
  local needle="$1"
  shift || true

  local item
  for item in "$@"; do
    if [[ "$item" == "$needle" ]]; then
      return 0
    fi
  done

  return 1
}

is_approved_restore_file() {
  local path="$1"

  case "$path" in
    GIT-RELEASE-CONTEXT-HISTORY.md)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

is_metadata_only_diff() {
  local path="$1"
  local diff_output
  local line

  diff_output="$(git diff --no-ext-diff --unified=0 -- "$path")"

  if [[ -z "$diff_output" ]]; then
    return 1
  fi

  while IFS= read -r line; do
    case "$line" in
      diff\ --git*|index\ *|---\ *|+++\ *|@@\ *)
        continue
        ;;
      -ide-context-token-estimate:*|+ide-context-token-estimate:*)
        continue
        ;;
      *'"approx_tokens": '*|*'"tracked_total_approx_tokens": '*|*'"ide_context_token_estimate": '*)
        continue
        ;;
      -*)
        return 1
        ;;
      +*)
        return 1
        ;;
      *)
        continue
        ;;
    esac
  done <<< "$diff_output"

  return 0
}

run_check() {
  local label="$1"
  shift

  log "== ${label} =="
  if "$@"; then
    log "status=ok label=${label}"
    return 0
  fi

  local exit_code=$?
  log "status=warn label=${label} exit=${exit_code}"
  return 0
}

collect_git_state() {
  tracked_files=()
  while IFS= read -r path; do
    [[ -n "$path" ]] && tracked_files+=("$path")
  done < <(git diff --name-only)

  staged_files=()
  while IFS= read -r path; do
    [[ -n "$path" ]] && staged_files+=("$path")
  done < <(git diff --cached --name-only)

  untracked_files=()
  while IFS= read -r path; do
    [[ -n "$path" ]] && untracked_files+=("$path")
  done < <(git ls-files --others --exclude-standard)
}

classify_drift() {
  approved_restore_files=()
  approved_metadata_files=()
  unexpected_tracked_files=()

  for path in "${tracked_files[@]}"; do
    if contains_exact "$path" "${staged_files[@]}"; then
      unexpected_tracked_files+=("$path")
      continue
    fi

    if is_approved_restore_file "$path"; then
      approved_restore_files+=("$path")
      continue
    fi

    if is_metadata_only_diff "$path"; then
      approved_metadata_files+=("$path")
      continue
    fi

    unexpected_tracked_files+=("$path")
  done
}

print_drift_summary() {
  log "tracked_dirty=${#tracked_files[@]} staged_dirty=${#staged_files[@]} untracked_nonignored=${#untracked_files[@]}"

  if [[ "${#approved_restore_files[@]}" -gt 0 ]]; then
    log "approved_restore_files=${approved_restore_files[*]}"
  fi

  if [[ "${#approved_metadata_files[@]}" -gt 0 ]]; then
    log "approved_metadata_files=${approved_metadata_files[*]}"
  fi

  if [[ "${#unexpected_tracked_files[@]}" -gt 0 ]]; then
    log "unexpected_tracked_files=${unexpected_tracked_files[*]}"
  fi

  if [[ "${#untracked_files[@]}" -gt 0 ]]; then
    log "untracked_nonignored_files=${untracked_files[*]}"
  fi
}

block_on_remaining_local_state() {
  collect_git_state

  if [[ "${#tracked_files[@]}" -gt 0 || "${#staged_files[@]}" -gt 0 || "${#untracked_files[@]}" -gt 0 ]]; then
    log "Takeover blocked after approved cleanup. Remaining tracked=${#tracked_files[@]} staged=${#staged_files[@]} untracked=${#untracked_files[@]}"
    if [[ "${#tracked_files[@]}" -gt 0 ]]; then
      log "remaining_tracked_files=${tracked_files[*]}"
    fi
    if [[ "${#staged_files[@]}" -gt 0 ]]; then
      log "remaining_staged_files=${staged_files[*]}"
    fi
    if [[ "${#untracked_files[@]}" -gt 0 ]]; then
      log "remaining_untracked_files=${untracked_files[*]}"
    fi
    log "Use --authoritative-reset only when this receiver workspace should fully trust GitHub over local state."
    exit 3
  fi
}

restore_approved_drift() {
  if [[ "${#approved_restore_files[@]}" -gt 0 ]]; then
    git restore --worktree -- "${approved_restore_files[@]}"
  fi

  if [[ "${#approved_metadata_files[@]}" -gt 0 ]]; then
    git restore --worktree -- "${approved_metadata_files[@]}"
  fi
}

branch_name="$(git branch --show-current)"
upstream="$(git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null || echo none)"

log "[1/5] Takeover audit (${MODE})"
log "branch=${branch_name} upstream=${upstream}"

collect_git_state
classify_drift
print_drift_summary

log "[2/5] Remote state"
git fetch --prune

if [[ "$upstream" != "none" ]]; then
  ahead_behind="$(git rev-list --left-right --count HEAD...@{u} 2>/dev/null || echo '0 0')"
  ahead_behind="$(echo "$ahead_behind" | tr '\t' ' ')"
  read -r ahead behind <<< "$ahead_behind"
  ahead="${ahead:-0}"
  behind="${behind:-0}"
  log "ahead=${ahead} behind=${behind}"
else
  ahead="0"
  behind="0"
  log "ahead=0 behind=0"
fi

if [[ "$MODE" != "apply" ]]; then
  log "Dry-run complete. Re-run with --apply to restore approved drift, pull, and validate."
  if [[ "${#unexpected_tracked_files[@]}" -gt 0 || "${#untracked_files[@]}" -gt 0 ]]; then
    log "Receiver takeover is currently blocked by unexpected local state."
  fi
  exit 0
fi

log "[3/5] Receiver cleanup"
if [[ "$AUTHORITATIVE_RESET" == "1" ]]; then
  log "authoritative_reset=1"
  git reset --hard HEAD
  git clean -fd
else
  restore_approved_drift
  block_on_remaining_local_state
fi

log "[4/5] Pull latest canonical state"
if [[ "$upstream" == "none" ]]; then
  log "No upstream configured. Skipping pull."
else
  git pull --ff-only
fi

if [[ "$REFRESH_CONTEXT_BUDGET" == "1" ]]; then
  log "[5/6] Refresh IDE context metadata"
  if [[ -f "scripts/update-ide-context-budget.py" ]]; then
    if python_cmd="$(detect_python)"; then
      "$python_cmd" ./scripts/update-ide-context-budget.py
      collect_git_state
      classify_drift

      if [[ "${#unexpected_tracked_files[@]}" -gt 0 || "${#staged_files[@]}" -gt 0 || "${#untracked_files[@]}" -gt 0 ]]; then
        log "Context refresh introduced unexpected local state."
        print_drift_summary
        exit 4
      fi

      if [[ "${#approved_restore_files[@]}" -gt 0 || "${#approved_metadata_files[@]}" -gt 0 ]]; then
        log "Restoring approved refresh drift so the receiver worktree stays clean."
        restore_approved_drift
        block_on_remaining_local_state
      else
        log "Context refresh produced no local drift."
      fi
    else
      log "status=warn label=Context refresh details=Python not found; context refresh skipped"
    fi
  else
    log "status=warn label=Context refresh details=scripts/update-ide-context-budget.py not found; context refresh skipped"
  fi
fi

log "[6/6] Validation and runtime mapping"
if [[ "$RUN_READINESS" == "1" && -f "scripts/check-devops-readiness.ps1" ]]; then
  if command -v powershell >/dev/null 2>&1; then
    run_check "DevOps readiness" powershell -ExecutionPolicy Bypass -File ./scripts/check-devops-readiness.ps1
  elif command -v pwsh >/dev/null 2>&1; then
    run_check "DevOps readiness" pwsh -File ./scripts/check-devops-readiness.ps1
  else
    log "status=warn label=DevOps readiness details=PowerShell not found; readiness audit skipped"
  fi
fi

if [[ "$SKIP_RUNTIME_CHECKS" != "1" ]]; then
  if [[ -x "scripts/verify-plugin-sync.sh" ]]; then
    run_check "Plugin sync verification" ./scripts/verify-plugin-sync.sh
  elif [[ -f "scripts/verify-plugin-sync.sh" ]]; then
    run_check "Plugin sync verification" bash ./scripts/verify-plugin-sync.sh
  fi

  if [[ -x "scripts/check-local-wp.sh" ]]; then
    run_check "Local WordPress check" ./scripts/check-local-wp.sh
  elif [[ -f "scripts/check-local-wp.sh" ]]; then
    run_check "Local WordPress check" bash ./scripts/check-local-wp.sh
  fi
fi

log "Takeover complete. Review warnings above if any, then start code work."
