#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

MODE="dry-run"
MAX_COMMITS=8
SINCE_REF=""
BRANCH="$(git branch --show-current)"
NOW_UTC="$(date -u +"%Y-%m-%d %H:%M UTC")"

usage() {
  cat <<'EOF'
Global sync + handover automation.

Usage:
  scripts/global-sync-and-handover.sh [options]

Options:
  --apply                 Run mutations (sync worktrees, update files).
  --max-commits N         Number of most recent commits to summarize (default: 8).
  --since-ref REF         Summarize commits in REF..HEAD instead of --max-commits.
  --branch NAME           Branch label written into feature progress notes.
  -h, --help              Show help.

What it does:
  1) Audits all git worktrees and reports dirty/ahead/behind state.
  2) In --apply mode, fast-forwards clean/behind worktrees and pushes ahead worktrees.
  3) Derives touched features from recent commits and appends compact updates to
     specs/app-features/<feature>/progress.md when present.
  4) Prepends missing commit continuity entries to GIT-RELEASE-CONTEXT-HISTORY.md.

Safety defaults:
  - Dry-run by default.
  - Never pulls dirty worktrees.
  - Uses pull --ff-only only.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply)
      MODE="apply"
      shift
      ;;
    --max-commits)
      MAX_COMMITS="${2:-}"
      shift 2
      ;;
    --since-ref)
      SINCE_REF="${2:-}"
      shift 2
      ;;
    --branch)
      BRANCH="${2:-}"
      shift 2
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

if ! [[ "$MAX_COMMITS" =~ ^[0-9]+$ ]]; then
  echo "--max-commits must be an integer." >&2
  exit 2
fi

log() {
  printf '%s\n' "$*"
}

worktree_paths=()
while IFS= read -r line; do
  case "$line" in
    worktree\ *)
      worktree_paths+=("${line#worktree }")
      ;;
  esac
done < <(git worktree list --porcelain)

log "[1/4] Worktree sync audit (${MODE})"
for wt in "${worktree_paths[@]}"; do
  log "--- $wt"
  git -C "$wt" fetch --prune >/dev/null 2>&1 || true

  branch_name="$(git -C "$wt" branch --show-current)"
  upstream="$(git -C "$wt" rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null || echo none)"
  if [[ "$upstream" != "none" ]]; then
    ahead_behind="$(git -C "$wt" rev-list --left-right --count HEAD...@{u} 2>/dev/null || echo "0 0")"
  else
    ahead_behind="0 0"
  fi
  normalized_ab="$(echo "$ahead_behind" | tr '\t' ' ')"
  read -r ahead behind <<< "$normalized_ab"
  ahead="${ahead:-0}"
  behind="${behind:-0}"

  dirty="0"
  if [[ -n "$(git -C "$wt" status --porcelain)" ]]; then
    dirty="1"
  fi

  log "branch=${branch_name} upstream=${upstream} ahead=${ahead} behind=${behind} dirty=${dirty}"

  if [[ "$MODE" == "apply" && "$upstream" != "none" ]]; then
    if [[ "$behind" -gt 0 ]]; then
      if [[ "$dirty" == "1" ]]; then
        log "skip pull: dirty worktree"
      else
        log "pull --ff-only"
        git -C "$wt" pull --ff-only
      fi
    fi

    if [[ "$ahead" -gt 0 ]]; then
      log "push"
      git -C "$wt" push
    fi
  fi
done

log "[2/4] Build recent commit set"
commit_lines=()
if [[ -n "$SINCE_REF" ]]; then
  while IFS= read -r line; do
    [[ -n "$line" ]] && commit_lines+=("$line")
  done < <(git log --date=short --pretty='%H|%ad|%s' "${SINCE_REF}..HEAD")
else
  while IFS= read -r line; do
    [[ -n "$line" ]] && commit_lines+=("$line")
  done < <(git log --date=short --pretty='%H|%ad|%s' -n "$MAX_COMMITS")
fi

if [[ "${#commit_lines[@]}" -eq 0 ]]; then
  log "No commits found in selected range."
  exit 0
fi

# Identify touched features from path patterns.
declare -A touched_features=()
declare -A commit_files=()
for entry in "${commit_lines[@]}"; do
  hash="${entry%%|*}"
  files="$(git show --pretty='' --name-only "$hash" | sed '/^$/d')"
  commit_files["$hash"]="$files"

  while IFS= read -r f; do
    [[ -z "$f" ]] && continue

    if [[ "$f" =~ ^specs/app-features/([^/]+)/ ]]; then
      touched_features["${BASH_REMATCH[1]}"]=1
      continue
    fi

    case "$f" in
      private-plugins/wmos-control-plane/*|webmasteros/includes/Licensing/*|webmasteros/includes/Admin/SettingsPage.php)
        touched_features["licensing-control-plane"]=1
        ;;
    esac
  done <<< "$files"
done

log "[3/4] Feature compaction targets"
if [[ "${#touched_features[@]}" -eq 0 ]]; then
  log "No feature-scoped changes detected from path mapping."
else
  for feature in "${!touched_features[@]}"; do
    log "feature=${feature}"
  done
fi

if [[ "$MODE" == "apply" && "${#touched_features[@]}" -gt 0 ]]; then
  for feature in "${!touched_features[@]}"; do
    progress_path="specs/app-features/${feature}/progress.md"
    if [[ ! -f "$progress_path" ]]; then
      log "skip ${feature}: no progress.md"
      continue
    fi

    summary_tmp="$(mktemp)"
    {
      echo
      echo "## Session Update ${NOW_UTC}"
      echo
      echo "- Branch: ${BRANCH}"
      echo "- Commit window: $(printf '%s\n' "${commit_lines[@]}" | wc -l | tr -d ' ') recent commit(s)"
      echo "- Key commits:"
      for entry in "${commit_lines[@]}"; do
        hash="${entry%%|*}"
        date_rest="${entry#*|}"
        date_part="${date_rest%%|*}"
        subject="${entry##*|}"
        short_hash="${hash:0:7}"
        echo "  - ${date_part} ${short_hash} ${subject}"
      done
      echo "- Next checkpoint: verify STATUS.md and tasks.md remain aligned with this update."
    } > "$summary_tmp"

    cat "$summary_tmp" >> "$progress_path"
    rm -f "$summary_tmp"
    log "updated ${progress_path}"
  done
fi

log "[4/4] Global release continuity history"
history_path="GIT-RELEASE-CONTEXT-HISTORY.md"
if [[ "$MODE" == "apply" ]]; then
  history_block_tmp="$(mktemp)"
  {
    for entry in "${commit_lines[@]}"; do
      hash="${entry%%|*}"
      date_rest="${entry#*|}"
      date_part="${date_rest%%|*}"
      subject="${entry##*|}"
      short_hash="${hash:0:7}"

      if grep -q "${short_hash}" "$history_path"; then
        continue
      fi

      printf '### %s · `%s` · `%s`\n' "$date_part" "$short_hash" "$subject"
      echo
      echo "- Semantic summary: ${subject}."

      file_preview="$(printf '%s\n' "${commit_files[$hash]}" | head -n 4 | tr '\n' '; ' | sed 's/; $//')"
      if [[ -n "$file_preview" ]]; then
        echo "- Key change paths: ${file_preview}."
      else
        echo "- Key change paths: none captured."
      fi

      echo "- Continuity note: verify feature progress handoff and release asset status after this commit window."
      echo
    done
  } > "$history_block_tmp"

  if [[ -s "$history_block_tmp" ]]; then
    history_out_tmp="$(mktemp)"
    awk -v block_file="$history_block_tmp" '
      BEGIN {
        while ((getline line < block_file) > 0) {
          block = block line "\n"
        }
        inserted = 0
      }
      {
        print $0
        if (!inserted && $0 ~ /^## Recent Context$/) {
          print ""
          printf "%s", block
          inserted = 1
        }
      }
    ' "$history_path" > "$history_out_tmp"
    mv "$history_out_tmp" "$history_path"
    log "updated ${history_path}"
  else
    log "no new history entries to add"
  fi

  rm -f "$history_block_tmp"
fi

log "Done (${MODE})."
