---
title: IDE Copilot Integration
type: setup-guide
status: active
authority: primary
intent-scope: workspace-setup,maintenance
phase: setup
last-reviewed: 2026-03-21
ide-context-token-estimate: 880
token-estimate-method: approx-chars-div-4
related-files:
  - ./OLLAMA-LOCAL-AI-README.md
  - ./LOCAL-LLM-SETUP.md
  - ./NO-THINK-PROXY.md
  - ../templates/vscode-tasks.template.json
---

# IDE Copilot Integration

## Purpose

This document defines how to wire VS Code GitHub Copilot to Ollama via the no-think proxy,
and how to configure VS Code tasks for automatic proxy lifecycle management.

---

## Copilot Settings

Add the following to your VS Code user or workspace `settings.json` to route Copilot
through the local proxy:

```json
{
  "github.copilot.chat.byok.ollamaEndpoint": "http://127.0.0.1:11435"
}
```

Point at the **proxy port 11435**, not the raw Ollama port 11434.

If your Copilot extension exposes a model selector, choose the custom context-window
variant (e.g. `qwen3.5:27b-32k`) to ensure the correct `num_ctx` is active.

---

## VS Code Tasks

Three tasks are required. Use `../templates/vscode-tasks.template.json` as the source and
merge the relevant tasks into your repo's `.vscode/tasks.json`.

### Task 1 — Auto-start proxy on folder open

```json
{
  "label": "Start Ollama No-Think Proxy On Folder Open",
  "type": "shell",
  "command": "powershell",
  "args": ["-NoProfile", "-ExecutionPolicy", "Bypass", "-Command",
    "$portInUse = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 11435 -State Listen -ErrorAction SilentlyContinue; if (-not $portInUse) { Start-Process -WindowStyle Hidden -FilePath 'node' -ArgumentList @('tools/ollama-nothink-proxy.js') -WorkingDirectory $wd }"
  ],
  "runOptions": { "runOn": "folderOpen" }
}
```

### Task 2 — Manual start

```json
{
  "label": "Start Ollama No-Think Proxy",
  "type": "shell",
  "command": "node",
  "args": ["tools/ollama-nothink-proxy.js"]
}
```

### Task 3 — Log viewer

```json
{
  "label": "Show Ollama No-Think Proxy Log",
  "type": "shell",
  "command": "powershell",
  "args": ["-NoProfile", "-ExecutionPolicy", "Bypass", "-Command",
    "Get-Content 'logs/ollama-nothink-proxy.log' -Wait"
  ],
  "isBackground": true
}
```

---

## Manual Copilot Model Override

When using Copilot Chat in VS Code, you can use the model picker in the chat panel to
select the locally registered Ollama model if your Copilot extension version supports it.

For extensions that do not surface the model picker, the proxy endpoint setting is
sufficient — Copilot will forward all requests to the proxy which forwards to whatever
model Ollama has loaded.

---

## Verification Steps

1. Confirm proxy endpoint is reachable:

```bash
curl http://127.0.0.1:11435/api/tags
```

2. Send a test completion request through the proxy:

```bash
curl http://127.0.0.1:11435/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen3.5:27b-32k",
    "messages": [{"role": "user", "content": "Write a 1-line PHP hello function"}],
    "stream": false
  }'
```

3. Confirm no `<think>` tokens appear in the response body.

4. Open VS Code Copilot Chat and ask a simple code question. The response latency
   should be 5–10 s, not 30–60 s.

---

## Quick Test Prompts

Use these to validate the pipeline end-to-end without loading a full project:

| Test type | Prompt |
|-----------|--------|
| Simple    | `Write a 1-line PHP function that returns hello` |
| Micro-explain | `Explain in 1 sentence: function hello(){return "hello";}` |
| Refactor | `Refactor this PHP function with type hints: function hello(){return "hello";}` |

Expected: response arrives in under 10 s without any `<think>` block visible.

---

## Routing Summary

| Copilot request | Goes to | Reasoning |
|-----------------|---------|-----------|
| All completions | `127.0.0.1:11435` (proxy) | Always route through proxy |
| Never direct    | `127.0.0.1:11434` (Ollama) | Bypass proxy = slow responses |
