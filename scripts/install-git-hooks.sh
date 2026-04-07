#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

chmod +x .githooks/commit-msg .githooks/pre-push scripts/validate-remediation-commit-msg.sh
git config core.hooksPath .githooks

echo "Configured git hooks path: .githooks"
echo "Active policy: specs/AI-Root-Cause-Remediation-Policy.md"
