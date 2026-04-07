#!/usr/bin/env bash
# foundation/ollama/scripts/check-ollama-prereqs.sh
# Verify all prerequisites for the local Ollama + no-think proxy stack.
# Usage: bash foundation/ollama/scripts/check-ollama-prereqs.sh
# Exit code 0 = all checks passed; 1 = one or more checks failed.

set -euo pipefail

PASS=0
FAIL=0

ok()   { echo "  [OK]  $1"; PASS=$((PASS + 1)); }
fail() { echo "  [FAIL] $1"; FAIL=$((FAIL + 1)); }
info() { echo "  [INFO] $1"; }

echo ""
echo "=== Ollama Local AI Prereq Check ==="
echo ""

# ── Node.js ────────────────────────────────────────────────────────────────
echo "1. Node.js"
if command -v node >/dev/null 2>&1; then
  NODE_VER=$(node --version)
  MAJOR=$(echo "$NODE_VER" | sed 's/v\([0-9]*\).*/\1/')
  if [ "$MAJOR" -ge 18 ]; then
    ok "node $NODE_VER (>= 18 required)"
  else
    fail "node $NODE_VER — version 18 or higher required"
  fi
else
  fail "node not found — install Node.js 18+"
fi

# ── Ollama binary ──────────────────────────────────────────────────────────
echo ""
echo "2. Ollama binary"
if command -v ollama >/dev/null 2>&1; then
  OLLAMA_VER=$(ollama --version 2>/dev/null || echo "unknown")
  ok "ollama found ($OLLAMA_VER)"
else
  fail "ollama not found — install from https://ollama.com"
fi

# ── Ollama server reachable ────────────────────────────────────────────────
echo ""
echo "3. Ollama server (127.0.0.1:11434)"
if curl -sf http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then
  ok "Ollama server is running"
else
  fail "Ollama server not reachable — run: ollama serve"
fi

# ── At least one Qwen model present ───────────────────────────────────────
echo ""
echo "4. Qwen model availability"
if curl -sf http://127.0.0.1:11434/api/tags 2>/dev/null | grep -qi "qwen"; then
  MODELS=$(curl -sf http://127.0.0.1:11434/api/tags 2>/dev/null \
           | grep -o '"name":"[^"]*"' | grep -i qwen | sed 's/"name":"//;s/"//')
  ok "Qwen model(s) found:"
  while IFS= read -r m; do
    info "  $m"
  done <<< "$MODELS"
else
  fail "No Qwen models found — run: ollama pull qwen3.5:27b-q4_K_M"
fi

# ── Proxy reachable ────────────────────────────────────────────────────────
echo ""
echo "5. No-think proxy (127.0.0.1:11435)"
if curl -sf http://127.0.0.1:11435/api/tags >/dev/null 2>&1; then
  ok "Proxy is running on port 11435"
else
  fail "Proxy not reachable on port 11435 — run: node tools/ollama-nothink-proxy.js"
fi

# ── Log directory ──────────────────────────────────────────────────────────
echo ""
echo "6. Log directory"
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || echo ".")"
if [ -d "$REPO_ROOT/logs" ]; then
  ok "logs/ directory exists"
else
  fail "logs/ directory missing — create it: mkdir logs"
fi

# ── Summary ───────────────────────────────────────────────────────────────
echo ""
echo "=== Summary ==="
echo "  Passed: $PASS"
echo "  Failed: $FAIL"
echo ""

if [ "$FAIL" -gt 0 ]; then
  echo "Fix the failed checks before starting Copilot local-AI mode."
  exit 1
else
  echo "All checks passed. Local AI stack is ready."
  exit 0
fi
