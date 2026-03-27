---
title: No-Think Proxy
type: architecture
status: active
authority: primary
intent-scope: workspace-setup,implementation,debugging,maintenance
phase: setup
last-reviewed: 2026-03-21
ide-context-token-estimate: 1480
token-estimate-method: approx-chars-div-4
related-files:
  - ./OLLAMA-LOCAL-AI-README.md
  - ./LOCAL-LLM-SETUP.md
  - ./IDE-COPILOT-INTEGRATION.md
  - ../../../../tools/ollama-nothink-proxy.js
  - ../scripts/benchmark-proxy.sh
---

# No-Think Proxy

## Purpose

The no-think proxy is the critical performance layer between VS Code Copilot and Ollama.

It intercepts every request and injects reasoning-disable directives so the model skips
its internal chain-of-thought. This is the single largest performance improvement — not
hardware, not quantization — disabling reasoning is what cuts response time from 30–60 s
down to 5–10 s.

The proxy also solves the availability problem: because Copilot is configured once to
point at the proxy port (11435), killing the proxy means Copilot loses the Ollama route
entirely. The proxy should always stay running. Use the **mode toggle** instead of
stopping the proxy when you want to switch behavior.

---

## Architecture

```
VS Code Copilot
      │
      │  HTTP requests to 127.0.0.1:11435
      ▼
┌──────────────────────────────────────┐
│  No-Think Proxy                      │
│  (tools/ollama-nothink-proxy.js)     │
│                                      │
│  mode: "nothink"   → inject think:false   (fast)  │
│  mode: "passthrough" → forward as-is      (slow)  │
│                                      │
│  Control endpoints:                  │
│    GET  /proxy/status                │
│    POST /proxy/mode                  │
│                                      │
│  Logs ms per request                 │
└──────────────────────────────────────┘
      │
      │  HTTP to 127.0.0.1:11434
      ▼
Ollama Server
      │
      ▼
Qwen Model (local GPU)
```

---

## Proxy Modes

| Mode | What it does | Typical response time |
|------|--------------|-----------------------|
| `nothink` | Injects `think: false` / `reasoning_effort: none` | 5–10 s |
| `passthrough` | Forwards request unchanged (reasoning active) | 30–60 s |

Default on startup: `nothink`.

### Switching modes without restarting

```bash
# Switch to passthrough (thinking enabled — slow)
curl -X POST http://127.0.0.1:11435/proxy/mode \
  -H "Content-Type: application/json" \
  -d '{"mode":"passthrough"}'

# Switch back to no-think (fast)
curl -X POST http://127.0.0.1:11435/proxy/mode \
  -H "Content-Type: application/json" \
  -d '{"mode":"nothink"}'

# Check current mode and request count
curl http://127.0.0.1:11435/proxy/status
```

Or use the VS Code tasks:

- **Proxy: Enable Passthrough Mode (slow, for benchmarking)**
- **Proxy: Enable No-Think Mode (fast)**
- **Proxy: Status**

---

## Request Timing

Every inference request logs its wall-clock duration. Example log line:

```
[2026-03-21T14:32:01.452Z] RESP /v1/chat/completions 200 7234ms mode:nothink
[2026-03-21T14:33:11.201Z] RESP /v1/chat/completions 200 48901ms mode:passthrough
```

Use **Proxy: Show Benchmark (last RESP lines)** VS Code task to extract timings.

---

## In-Situ Copilot Chat Benchmark Protocol

This is the recommended procedure to compare no-think vs. passthrough directly from
the Copilot Chat interface without any CLI curl. The proxy stays running throughout.

1. Make sure the proxy is running (`curl http://127.0.0.1:11435/proxy/status`).
2. Run task **Proxy: Enable No-Think Mode (fast)** — confirm `"mode":"nothink"`.
3. In Copilot Chat, ask your benchmark prompt (e.g. `Refactor this PHP function with type hints: function hello(){return "hello";}`).
4. Wait for the full response.
5. Run task **Proxy: Show Benchmark (last RESP lines)** — note the `ms` value on the last `RESP` line.
6. Run task **Proxy: Enable Passthrough Mode (slow, for benchmarking)**.
7. Ask the **exact same prompt** in Copilot Chat again.
8. Wait for the full response.
9. Run **Proxy: Show Benchmark (last RESP lines)** again — note the `ms` value.
10. Compare the two `RESP` lines. The mode label (`mode:nothink` vs `mode:passthrough`) is included in each log line.
11. Run task **Proxy: Enable No-Think Mode (fast)** to restore normal operation.

---

## Injection Logic

### Ollama native API (`/api/chat`, `/api/generate`)

```json
{ "think": false }
```

### OpenAI-compatible API (`/v1/chat/completions`)

```json
{
  "reasoning_effort": "none",
  "reasoning": { "effort": "none" }
}
```

### What is always preserved

| Field | Behavior |
|-------|----------|
| `stream` | Always preserved — Copilot requires streaming |
| `model` | Never overridden |
| `messages` | Never modified |

---

## Singleton Behavior

The proxy checks port 11435 before binding. If the port is already in use, the new
process exits cleanly instead of raising an error. This allows the VS Code `On Folder
Open` task to be safely re-triggered without duplicating the proxy.

---

## Log File

The proxy writes all traffic to:

```
logs/ollama-nothink-proxy.log
```

Use the VS Code task **Show Ollama No-Think Proxy Log** to tail the log in real time.

### Log signal guide

| Pattern | Meaning |
|---------|---------|
| `REQ /v1/chat/completions ...` | Request received from Copilot |
| `FWD ... none none` | Reasoning disabled, forwarding to Ollama |
| `RESP ... 200 7234ms mode:nothink` | Fast response — injection working |
| `RESP ... 200 48901ms mode:passthrough` | Slow response — passthrough mode active |
| `MODE → passthrough` | Mode was switched via control endpoint |
| `reasoning present` in response body | Injection failed — see troubleshooting |

---

## Troubleshooting

### Proxy not running

```bash
curl http://127.0.0.1:11435/proxy/status
```

If this fails, start manually:

```bash
node tools/ollama-nothink-proxy.js
```

### Slow responses despite nothink mode

Run `Proxy: Status` and confirm `"mode":"nothink"`. If mode is correct but still slow,
check the log for `FWD` lines. No `FWD` line means the injection block was skipped —
confirm the API path is in `shouldInject()` in the proxy source.

### Port already in use by another process

```bash
# Windows
netstat -ano | findstr 11435
taskkill /PID <pid> /F
```

### Copilot falling back to cloud models

This happens when the proxy is down and Copilot cannot reach `127.0.0.1:11435`.
Restart the proxy. Do not reconfigure Copilot's endpoint. The proxy should stay running
at all times; use mode toggle instead of stopping it.

---

## Performance Baseline

| Mode | Expected response time (27B Q4_K_M) |
|------|--------------------------------------|
| `nothink` (proxy) | 5–10 s |
| `passthrough` (proxy, thinking active) | 30–60 s |
| Direct Ollama port 11434 (no proxy) | 30–60 s |


# No-Think Proxy

## Purpose

The no-think proxy is the critical performance layer between VS Code Copilot and Ollama.

It intercepts every request and injects reasoning-disable directives so the model skips
its internal chain-of-thought. This is the single largest performance improvement — not
hardware, not quantization — disabling reasoning is what cuts response time from 30–60 s
down to 5–10 s.

---

## Architecture

```
VS Code Copilot
      │
      │  HTTP requests to 127.0.0.1:11435
      ▼
┌─────────────────────────────┐
│  No-Think Proxy             │
│  (tools/ollama-nothink-proxy.js) │
│                             │
│  • intercepts all requests  │
│  • injects think: false     │
│  • preserves stream: true   │
│  • logs all traffic         │
│  • singleton (port check)   │
└─────────────────────────────┘
      │
      │  HTTP to 127.0.0.1:11434
      ▼
Ollama Server
      │
      ▼
Qwen Model (local GPU)
```

---

## Injection Logic

### Ollama native API (`/api/chat`, `/api/generate`)

```json
{
  "think": false
}
```

### OpenAI-compatible API (`/v1/chat/completions`)

```json
{
  "reasoning_effort": "none",
  "reasoning": {
    "effort": "none"
  }
}
```

### What is preserved

| Field | Behavior |
|-------|----------|
| `stream` | Always preserved — Copilot requires streaming |
| `model` | Never overridden |
| `messages` | Never modified |

---

## Singleton Behavior

The proxy checks port 11435 before binding. If the port is already in use, the new
process exits cleanly instead of raising an error. This allows the VS Code `On Folder
Open` task to be safely re-triggered without duplicating the proxy.

---

## Log File

The proxy writes all traffic to:

```
logs/ollama-nothink-proxy.log
```

Use the VS Code task **Show Ollama No-Think Proxy Log** to tail the log in real time.

### Log signal guide

| Pattern | Meaning |
|---------|---------|
| `REQ /v1/chat/completions ...` | Request received from Copilot |
| `FWD ... none none` | Reasoning disabled, forwarding to Ollama |
| `RESP 200` | Ollama responded successfully |
| `reasoning present` in response | Injection failed — see troubleshooting |
| No `FWD` line | Request not forwarded — proxy error |

---

## VS Code Auto-Start

The proxy is started automatically on VS Code folder open via a PowerShell task that
checks whether port 11435 is already occupied before launching:

```powershell
$portInUse = Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 11435
                                  -State Listen -ErrorAction SilentlyContinue
if (-not $portInUse) {
  Start-Process -WindowStyle Hidden -FilePath 'node' `
    -ArgumentList @('tools/ollama-nothink-proxy.js') `
    -WorkingDirectory $wd
}
```

The template for this task is in `../templates/vscode-tasks.template.json`.

---

## Troubleshooting

### Proxy not running

```bash
curl http://127.0.0.1:11435/api/tags
```

If this fails, start manually:

```bash
node tools/ollama-nothink-proxy.js
```

### Slow responses despite proxy

Check the log for `reasoning present`. If thinking tokens appear in the response body,
the model variant or API path may require a different injection key. See
[MODEL-OPTIMIZATION.md](MODEL-OPTIMIZATION.md) for model-specific notes.

### Port already in use by another process

```bash
# Windows
netstat -ano | findstr 11435
taskkill /PID <pid> /F

# Unix
lsof -i :11435 | awk 'NR>1 {print $2}' | xargs kill
```

### Copilot falling back to cloud models

This happens when the proxy is down and Copilot cannot reach `127.0.0.1:11435`.
Restart the proxy. Cloud fallback will stop once the local endpoint is healthy again.

---

## Performance Baseline

| Mode | Expected response time |
|------|------------------------|
| Direct Ollama (27B Q4_K_M) | 30–60 s |
| Through proxy (27B Q4_K_M) | 5–10 s  |
| Through proxy (9B Q8_0)    | 2–5 s   |
