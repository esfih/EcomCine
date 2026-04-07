---
title: Model Optimization
type: reference
status: active
authority: primary
intent-scope: workspace-setup,implementation,maintenance
phase: setup
last-reviewed: 2026-03-21
ide-context-token-estimate: 960
token-estimate-method: approx-chars-div-4
related-files:
  - ./OLLAMA-LOCAL-AI-README.md
  - ./LOCAL-LLM-SETUP.md
  - ../templates/Modelfile.template
---

# Model Optimization

## Purpose

This document defines the recommended quantization levels, context window sizes, and
runtime flags for running Qwen models efficiently on a single consumer GPU.

---

## Execution Philosophy

This layer deliberately avoids:

- multi-GPU model splitting
- CPU offloading (unless explicitly required)
- concurrent inference threads
- model sharding

Rationale: single-GPU execution is more stable, has lower overhead, and produces
better per-request latency than distributed strategies for IDE code-assistance workloads.

---

## Quantization

### Recommended quantization by model size

| Model size | Quantization | Rationale |
|------------|-------------|-----------|
| 27B        | `Q4_K_M`    | Best quality/VRAM balance for a 24 GB card |
| 9B         | `Q8_0`      | Fits comfortably with headroom for KV cache |

### Quantization trade-offs

| Level   | VRAM use | Quality | Speed |
|---------|----------|---------|-------|
| Q4_K_M  | Low      | Good    | Fast  |
| Q5_K_M  | Medium   | Better  | Medium|
| Q8_0    | High     | Best    | Slower |
| FP16    | Very high| Native  | Slowest |

For IDE code tasks, `Q4_K_M` on 27B and `Q8_0` on 9B are the sweet spots.

---

## Context Window Sizes

Default Ollama context windows are often smaller than ideal for file-level code tasks.
Use custom Modelfiles to override `num_ctx`.

### Recommended window sizes by model

| Model          | Recommended `num_ctx` | Notes |
|----------------|-----------------------|-------|
| 27B Q4_K_M     | 32768–56320           | Larger windows increase VRAM usage |
| 9B  Q8_0       | 56320–98304           | More headroom available |

### Rule

Start with the smaller end of the range and increase only if the model is truncating
context on real tasks.

---

## Creating Custom Context-Window Models

Use a Modelfile to bake the context size into a named model variant:

```
FROM qwen3.5:27b-q4_K_M
PARAMETER num_ctx 32768
```

Create and register:

```bash
ollama create qwen3.5:27b-32k -f Modelfile-qwen35-27b-32k
```

Verify:

```bash
ollama list
```

See `../templates/Modelfile.template` for the full parameterized template.

---

## Flash Attention and KV Cache

### Environment variables

```bash
# Set permanently via the repo helper script (requires admin PowerShell):
powershell -ExecutionPolicy Bypass -File scripts/set-ollama-env.ps1

# Verify they are applied:
powershell -ExecutionPolicy Bypass -File scripts/set-ollama-env.ps1 -Verify
```

The script sets all five vars at the Windows Machine scope so they survive reboots
and apply to the Ollama background service. After running it, kill and restart the
Ollama process.

### What each variable does

| Variable | Value | Effect |
|----------|-------|--------|
| `OLLAMA_FLASH_ATTENTION` | `1` | Flash attention kernels — reduces memory bandwidth on long contexts |
| `OLLAMA_KV_CACHE_TYPE` | `q8_0` | Quantizes KV cache — frees VRAM headroom for larger context windows |
| `OLLAMA_KEEP_ALIVE` | `-1` | Never auto-unload model from VRAM (prevents 20–30 s cold-load penalty) |
| `OLLAMA_MAX_LOADED_MODELS` | `1` | Only one model in VRAM at a time |
| `OLLAMA_NUM_PARALLEL` | `1` | Single-request-at-a-time — full GPU bandwidth per request |

### When to use both FLASH_ATTENTION + KV_CACHE

Always enable for 27B models on 24 GB VRAM when using context windows ≥ 32 K.
With both active, the freed VRAM headroom is enough to safely use `qwen3.5:27b-56k`
instead of `qwen3.5:27b-32k` — 75% more context at similar VRAM usage.

---

## Model Selection Decision Tree

```
Is VRAM ≥ 20 GB?
├── Yes → use qwen3.5:27b-q4_K_M with num_ctx 32768
│         enable FLASH_ATTENTION + KV_CACHE q8_0
└── No, 12–16 GB → use qwen3.5:9b-q8_0 with num_ctx 56320
                   enable FLASH_ATTENTION
```

---

## Naming Convention

Custom model variants should follow this pattern:

```
<base-name>:<size>-<context-shorthand>
```

Examples:

```
qwen3.5:27b-32k
qwen3.5:27b-56k
qwen3.5:9b-96k
```

This makes the active model identifiable in logs without needing to inspect the Modelfile.
