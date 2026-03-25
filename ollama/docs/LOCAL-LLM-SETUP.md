---
title: Local LLM Setup
type: setup-guide
status: active
authority: primary
intent-scope: workspace-setup
phase: setup
last-reviewed: 2026-03-21
ide-context-token-estimate: 1020
token-estimate-method: approx-chars-div-4
related-files:
  - ./OLLAMA-LOCAL-AI-README.md
  - ./MODEL-OPTIMIZATION.md
  - ./IDE-COPILOT-INTEGRATION.md
  - ./NO-THINK-PROXY.md
  - ../scripts/check-ollama-prereqs.sh
  - ../templates/Modelfile.template
  - ../templates/vscode-tasks.template.json
---

# Local LLM Setup

## Purpose

This guide walks through the full setup to run Qwen models locally via Ollama and
wire them into VS Code GitHub Copilot through the no-think proxy.

---

## Prerequisites

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| GPU VRAM    | 16 GB   | 24 GB (RTX 3090) |
| RAM         | 32 GB   | 64 GB |
| Disk        | 30 GB free | 60 GB free |
| Ollama      | latest  | latest |
| Node.js     | 18+     | 20+ |

Verify with:

```bash
./foundation/ollama/scripts/check-ollama-prereqs.sh
```

---

## Step 1 — Install Ollama

Download from https://ollama.com and confirm the server can start:

```bash
ollama serve
```

The default listen address is `127.0.0.1:11434`.

---

## Step 2 — Pull the Target Model

Choose based on available VRAM:

| Model tag               | VRAM needed | Use case                  |
|-------------------------|-------------|---------------------------|
| `qwen3.5:27b-q4_K_M`   | ~18 GB      | Best balance (27B)        |
| `qwen3.5:9b-q8_0`      | ~10 GB      | Fast, smaller footprint   |

Pull the model:

```bash
ollama pull qwen3.5:27b-q4_K_M
```

---

## Step 3 — Create Custom Context-Window Models

Default context windows are often too small for IDE code tasks. Use a Modelfile to
create a named variant with a larger context window.

See `../templates/Modelfile.template` for the pattern. Example for 27B:

```
FROM qwen3.5:27b-q4_K_M
PARAMETER num_ctx 32768
```

Create it:

```bash
ollama create qwen3.5:27b-32k -f Modelfile-qwen35-27b-32k
```

Verify it is listed:

```bash
ollama list
```

---

## Step 4 — Enable Flash Attention and KV Cache

Set these environment variables before starting `ollama serve`:

```bash
export OLLAMA_FLASH_ATTENTION=1
export OLLAMA_KV_CACHE_TYPE=q8_0
ollama serve
```

These reduce memory bandwidth pressure and improve latency on long-context requests.

---

## Step 5 — Start the No-Think Proxy

The proxy sits on port `11435` and injects reasoning-disable flags into every request.

**Preferred — detached Windows process (survives terminal resets):**

```powershell
$wd = (Resolve-Path "C:/dev/WebMasterOS-main").Path
$script = Join-Path $wd "tools\ollama-nothink-proxy.js"
Start-Process -WindowStyle Hidden -FilePath "node" -ArgumentList @($script) -WorkingDirectory $wd
```

Or use the VS Code task:

> **Start Ollama No-Think Proxy**

The proxy auto-starts on VS Code folder open via the `On Folder Open` task (see
`../templates/vscode-tasks.template.json`).

> **Warning:** Do NOT start the proxy with `node tools/ollama-nothink-proxy.js &` in Git Bash.
> That creates a child of the bash session and dies when the terminal resets or VS Code
> reloads the shell. Always use `Start-Process` or the VS Code task.

---

## Step 6 — Configure VS Code Copilot

Point Copilot at the proxy endpoint, not the raw Ollama port.

See [IDE-COPILOT-INTEGRATION.md](IDE-COPILOT-INTEGRATION.md) for the full Copilot
settings block.

---

## Verification

1. Confirm proxy is running:

```bash
curl http://127.0.0.1:11435/api/tags
```

2. Run a quick benchmark:

```bash
./foundation/ollama/scripts/benchmark-proxy.sh
```

Expected result: proxy response under 10 s for a short code prompt.

---

## Teardown

Kill the proxy if needed:

```bash
# Windows
netstat -ano | findstr 11435
taskkill /PID <pid> /F

# Unix
lsof -i :11435
kill <pid>
```
