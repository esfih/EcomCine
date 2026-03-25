#!/usr/bin/env bash
# foundation/ollama/scripts/benchmark-proxy.sh
# Compare response time: direct Ollama vs. no-think proxy.
# Usage: bash foundation/ollama/scripts/benchmark-proxy.sh [model]
# Default model: qwen3.5:27b-32k

set -euo pipefail

MODEL="${1:-qwen3.5:27b-32k}"
PROMPT="Write a 1-line PHP function that returns hello"

DIRECT_URL="http://127.0.0.1:11434/v1/chat/completions"
PROXY_URL="http://127.0.0.1:11435/v1/chat/completions"

PAYLOAD=$(printf '{"model":"%s","messages":[{"role":"user","content":"%s"}],"stream":false}' \
  "$MODEL" "$PROMPT")

echo ""
echo "=== Ollama Proxy Benchmark ==="
echo "Model : $MODEL"
echo "Prompt: $PROMPT"
echo ""

# ── Direct Ollama ──────────────────────────────────────────────────────────
echo "--- Direct Ollama (${DIRECT_URL}) ---"
if curl -sf "$DIRECT_URL" -o /dev/null --head >/dev/null 2>&1 || true; then
  DIRECT_START=$(date +%s%3N)
  HTTP_CODE=$(curl -s -o /tmp/_bm_direct.json -w "%{http_code}" \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD" \
    "$DIRECT_URL" 2>/dev/null)
  DIRECT_END=$(date +%s%3N)
  DIRECT_MS=$(( DIRECT_END - DIRECT_START ))
  echo "  HTTP $HTTP_CODE — ${DIRECT_MS} ms"
  if grep -qi '"thinking"\|<think>' /tmp/_bm_direct.json 2>/dev/null; then
    echo "  [WARN] thinking tokens detected in direct response"
  fi
else
  echo "  [SKIP] direct Ollama not reachable"
  DIRECT_MS=0
fi

echo ""

# ── Proxy ──────────────────────────────────────────────────────────────────
echo "--- No-Think Proxy (${PROXY_URL}) ---"
if curl -sf "$PROXY_URL" -o /dev/null --head >/dev/null 2>&1 || true; then
  PROXY_START=$(date +%s%3N)
  HTTP_CODE=$(curl -s -o /tmp/_bm_proxy.json -w "%{http_code}" \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD" \
    "$PROXY_URL" 2>/dev/null)
  PROXY_END=$(date +%s%3N)
  PROXY_MS=$(( PROXY_END - PROXY_START ))
  echo "  HTTP $HTTP_CODE — ${PROXY_MS} ms"
  if grep -qi '"thinking"\|<think>' /tmp/_bm_proxy.json 2>/dev/null; then
    echo "  [WARN] thinking tokens detected — proxy injection may have failed"
  else
    echo "  [OK]  no thinking tokens in proxy response"
  fi
else
  echo "  [SKIP] proxy not reachable — run: node tools/ollama-nothink-proxy.js"
  PROXY_MS=0
fi

echo ""

# ── Comparison ─────────────────────────────────────────────────────────────
if [ "$DIRECT_MS" -gt 0 ] && [ "$PROXY_MS" -gt 0 ]; then
  echo "=== Result ==="
  echo "  Direct : ${DIRECT_MS} ms"
  echo "  Proxy  : ${PROXY_MS} ms"
  RATIO=$(awk "BEGIN { printf \"%.1f\", $DIRECT_MS / $PROXY_MS }")
  echo "  Speedup: ${RATIO}x"
  if awk "BEGIN { exit ($PROXY_MS < $DIRECT_MS) ? 0 : 1 }"; then
    echo "  [PASS] proxy is faster than direct"
  else
    echo "  [WARN] proxy was not faster — check proxy logs"
  fi
fi

echo ""
rm -f /tmp/_bm_direct.json /tmp/_bm_proxy.json
