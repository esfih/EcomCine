#!/usr/bin/env bash
# Measure the approximate token budget usage for key repository docs.
# This is useful for understanding how much of an LLM context window is consumed by
# canonical prompt documents.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)/.."
cd "$repo_root"

# prefer python tool if available
if command -v python >/dev/null 2>&1; then
  python - <<'PY'
import os, sys

try:
    import tiktoken
except ImportError:
    print('tiktoken not installed; using approximate char/4 token estimate')
    sys.exit(10)

enc = tiktoken.encoding_for_model('gpt-4o-mini')
files=[
    '.github/copilot-instructions.md',
    'README-FIRST.md',
    'WORKSPACE-SETUP.md',
    'DEVOPS-TECH-STACK.md',
    'foundation/core/docs/TERMINAL-RULES.md',
]

for fp in files:
    if not os.path.exists(fp):
        continue
    txt = open(fp,'r',encoding='utf-8',errors='replace').read()
    print(f"{fp}: {len(enc.encode(txt))} tokens, {len(txt)} chars")

combo=''
for fp in files:
    if os.path.exists(fp):
        combo += open(fp,'r',encoding='utf-8',errors='replace').read() + '\n'
print('--- combined:', len(enc.encode(combo)), 'tokens,', len(combo), 'chars')
PY
  exit_code=$?
  if [ $exit_code -eq 10 ]; then
    echo "Install tiktoken (pip install tiktoken) for exact token counts. Falling back to approx."
  else
    exit $exit_code
  fi
fi

# Fallback if python/tiktoken isn't available.
python - <<'PY'
import os, math

def approx(text):
    return math.ceil(len(text) / 4.0)

files=[
    '.github/copilot-instructions.md',
    'README-FIRST.md',
    'WORKSPACE-SETUP.md',
    'DEVOPS-TECH-STACK.md',
    'foundation/core/docs/TERMINAL-RULES.md',
]

for fp in files:
    if not os.path.exists(fp):
        continue
    txt=open(fp,'r',encoding='utf-8',errors='replace').read()
    print(f"{fp}: {approx(txt)} tokens (approx), {len(txt)} chars")

combo=''
for fp in files:
    if os.path.exists(fp):
        combo += open(fp,'r',encoding='utf-8',errors='replace').read() + '\n'
print('--- combined:', approx(combo), 'tokens (approx),', len(combo), 'chars')
PY