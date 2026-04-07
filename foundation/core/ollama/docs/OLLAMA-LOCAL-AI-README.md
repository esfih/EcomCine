---
title: Ollama Local AI Staging
type: foundation-guide
status: active
authority: primary
intent-scope: workspace-setup,implementation,maintenance
phase: extraction
last-reviewed: 2026-03-21
ide-context-token-estimate: 820
token-estimate-method: approx-chars-div-4
related-files:
  - ./LOCAL-LLM-SETUP.md
  - ./NO-THINK-PROXY.md
  - ./MODEL-OPTIMIZATION.md
  - ./IDE-COPILOT-INTEGRATION.md
  - ../templates/Modelfile.template
  - ../templates/vscode-tasks.template.json
  - ../scripts/check-ollama-prereqs.sh
  - ../scripts/benchmark-proxy.sh
---

# Ollama Local AI Staging

## Purpose

This folder defines the reusable local-AI infrastructure layer for VS Code + GitHub Copilot + Ollama
running optimized Qwen models on a local GPU.

It is the upstream candidate for:

- local LLM setup and optimization guidance
- no-think proxy architecture and operation
- model quantization and context window configuration
- VS Code Copilot integration patterns for Ollama endpoints
- benchmarking and debugging utilities

## Must Belong Here

- generic guidance applicable to any app repository using Ollama-backed Copilot
- reusable proxy patterns and model configuration templates
- VS Code task scaffolding for proxy auto-start
- Modelfile templates for custom context window models
- benchmark and prereq-check scripts that are stack-agnostic

## Must Not Belong Here

- app-specific feature specs or product logic
- credentials, API keys, or tokens
- WordPress-specific runtime or plugin logic
- generated build artifacts or log files

## Architecture at a Glance

```
VS Code Copilot
      ↓
Ollama No-Think Proxy  (port 11435)
      ↓
Ollama Server          (port 11434)
      ↓
Qwen Model             (local GPU)
```

The proxy intercepts all requests and injects `"think": false` (Ollama native API) and
`"reasoning_effort": "none"` (OpenAI-compatible API) to suppress internal reasoning chains.
This produces 3–5× faster responses with no quality loss for code-assistance tasks.

## Key Performance Principle

The largest performance gain is not hardware — it is disabling reasoning at the API layer.

Typical response times:

| Mode           | Response time |
|----------------|---------------|
| Direct Ollama  | 30–60 s       |
| With proxy     | 5–10 s        |

## Document Index

| File | Purpose |
|------|---------|
| [LOCAL-LLM-SETUP.md](LOCAL-LLM-SETUP.md) | End-to-end environment setup steps |
| [NO-THINK-PROXY.md](NO-THINK-PROXY.md) | Proxy architecture, operation, and debugging |
| [MODEL-OPTIMIZATION.md](MODEL-OPTIMIZATION.md) | Quantization, context windows, flash attention |
| [IDE-COPILOT-INTEGRATION.md](IDE-COPILOT-INTEGRATION.md) | VS Code Copilot ↔ Ollama wiring |

## Local Migration Rule

During staging inside this repository:

- add new reusable Ollama/AI assets here first
- do not move the app-local `tools/ollama-nothink-proxy.js` until this layer is stable
- keep `.vscode/tasks.json` wiring in the app repo; use `templates/vscode-tasks.template.json`
  as the shareable seed
