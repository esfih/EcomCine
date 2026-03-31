const http = require("http");
const net = require("net");
const fs = require("fs");
const path = require("path");
const { spawn, exec, execSync } = require("child_process");

const DEFAULT_TARGET_HOST = "127.0.0.1";
const TARGET_PORT = 11434;
const LISTEN_PORT = 11435;
let activeTargetHost = process.env.OLLAMA_UPSTREAM_HOST || DEFAULT_TARGET_HOST;

const LOG_DIR = path.join(__dirname, "..", "logs");
const LOG_FILE = path.join(LOG_DIR, "ollama-nothink-proxy.log");
const ANALYSIS_LOG = path.join(LOG_DIR, "ollama-context-analysis.log");

function ensureLogDir() {
  fs.mkdirSync(LOG_DIR, { recursive: true });
}

function log(...args) {
  const line =
    `[${new Date().toISOString()}] ` +
    args.map((x) => (typeof x === "string" ? x : JSON.stringify(x))).join(" ");
  console.log(...args);
  ensureLogDir();
  fs.appendFileSync(LOG_FILE, line + "\n", "utf8");
}

function isPortInUse(port) {
  return new Promise((resolve) => {
    const tester = net
      .createServer()
      .once("error", (err) => {
        if (err.code === "EADDRINUSE") resolve(true);
        else resolve(false);
      })
      .once("listening", () => {
        tester.close(() => resolve(false));
      })
      .listen(port, "127.0.0.1");
  });
}

function collectBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on("data", (chunk) => chunks.push(chunk));
    req.on("end", () => resolve(Buffer.concat(chunks)));
    req.on("error", reject);
  });
}

function shouldInject(pathname) {
  return (
    pathname === "/api/chat" ||
    pathname === "/api/generate" ||
    pathname === "/v1/chat/completions" ||
    pathname === "/v1/completions" ||
    pathname === "/v1/responses"
  );
}

const TOOL_WHITELIST = new Set([
  "read_file", "create_file", "create_directory", "list_dir",
  "file_search", "grep_search", "semantic_search",
  "replace_string_in_file", "multi_replace_string_in_file", "insert_edit_into_file",
  "get_errors",
  "run_in_terminal", "get_terminal_output", "await_terminal",
  "terminal_last_command", "terminal_selection", "kill_terminal",
  "run_task", "get_task_output", "create_and_run_task",
  "get_changed_files",
  "vscode_listCodeUsages", "vscode_renameSymbol", "run_vscode_command",
  "memory", "manage_todo_list",
  "runSubagent",
]);

function compressRequest(parsed) {
  const stats = { toolsRemoved: 0, bytesEstimate: 0 };
  if (!parsed) return stats;

  if (Array.isArray(parsed.tools) && parsed.tools.length > 0) {
    const before = parsed.tools.length;
    const beforeBytes = JSON.stringify(parsed.tools).length;
    parsed.tools = parsed.tools.filter(t => {
      const name = (t.function || t).name || "";
      return TOOL_WHITELIST.has(name);
    });
    stats.toolsRemoved = before - parsed.tools.length;
    stats.bytesEstimate += beforeBytes - JSON.stringify(parsed.tools).length;
  }

  const msgs = parsed.messages;
  if (Array.isArray(msgs)) {
    let lastCustomIdx = -1;
    msgs.forEach((m, i) => {
      if (m.role === "user" && typeof m.content === "string" &&
          m.content.includes('id="prompt:customizationsIndex"')) {
        lastCustomIdx = i;
      }
    });

    msgs.forEach((m, i) => {
      if (m.role !== "user" || typeof m.content !== "string") return;
      const before = m.content.length;

      m.content = m.content.replace(
        /<workspace_info>[\s\S]*?<\/workspace_info>/gi,
        "<workspace_info>[stripped by proxy]</workspace_info>"
      );

      m.content = m.content.replace(
        /<userMemory>[\s\S]*?<\/userMemory>/gi,
        "<userMemory>[stripped by proxy]</userMemory>"
      );

      m.content = m.content.replace(
        /<sessionMemory>[\s\S]*?<\/sessionMemory>/gi,
        "<sessionMemory>[stripped by proxy]</sessionMemory>"
      );

      m.content = m.content.replace(
        /<repoMemory>[\s\S]*?<\/repoMemory>/gi,
        "<repoMemory>[stripped by proxy]</repoMemory>"
      );

      if (i !== lastCustomIdx) {
        m.content = m.content.replace(
          /<attachment id="prompt:customizationsIndex">[\s\S]*?<\/attachment>/gi,
          ""
        );
      }

      stats.bytesEstimate += before - m.content.length;
    });
  }

  return stats;
}

function analyzeRequest(reqNum, pathname, parsed, bodyBytes) {
  try {
    if (!parsed) return;
    const lines = [];
    const ts = new Date().toISOString();
    const totalKB = Math.round(bodyBytes / 1024);
    lines.push(`\n${'='.repeat(80)}`);
    lines.push(`[${ts}] REQ #${reqNum} ${pathname}  model:${parsed.model || '?'}  total:${totalKB}KB`);
    lines.push(`${'='.repeat(80)}`);

    const skip = new Set(['messages', 'tools', 'model', 'stream', 'think',
      'reasoning_effort', 'reasoning', 'keep_alive', 'num_predict', 'max_tokens']);
    const extras = Object.keys(parsed).filter(k => !skip.has(k));
    if (extras.length) lines.push(`  options: ${extras.map(k => `${k}=${JSON.stringify(parsed[k])}`).join(', ')}`);

    if (Array.isArray(parsed.tools) && parsed.tools.length > 0) {
      const toolsKB = Math.round(JSON.stringify(parsed.tools).length / 1024);
      const names = parsed.tools.map(t => {
        const fn = t.function || t;
        return fn.name || '?';
      });
      lines.push(`  tools[${parsed.tools.length}] ${toolsKB}KB: ${names.join(', ')}`);
    }

    const msgs = parsed.messages || [];
    lines.push(`  messages: ${msgs.length}`);
    msgs.forEach((m, i) => {
      const role = m.role || '?';
      const content = m.content || '';
      const raw = typeof content === 'string' ? content : JSON.stringify(content);
      const kb = Math.round(raw.length / 1024);
      const markers = [];
      if (/<attachment /i.test(raw)) markers.push('attachment');
      if (/<workspace_info/i.test(raw)) markers.push('workspace_info');
      if (/<environment_info/i.test(raw)) markers.push('environment_info');
      if (/<context>/i.test(raw)) markers.push('context');
      if (/<userRequest>/i.test(raw)) markers.push('userRequest');
      if (/copilot-instructions/i.test(raw)) markers.push('copilot-instructions');
      if (/repeated-prompt/i.test(raw)) markers.push('repeated-prompt');
      if (/<reminderInstructions>/i.test(raw)) markers.push('reminderInstructions');
      if (/sessionMemory|repoMemory|userMemory/i.test(raw)) markers.push('memory');
      if (/tool_call|tool_result|function_call/i.test(raw)) markers.push('tool_io');
      const tag = role === 'system' ? 'S' : role === 'user' ? 'U' : role === 'assistant' ? 'A' : 'T';
      const markerStr = markers.length ? ` [${markers.join('|')}]` : '';
      const preview = kb >= 1 ? `\n      PREVIEW: ${raw.slice(0, 300).replace(/\n/g, '↵')}…` : '';
      lines.push(`    [${i}] ${tag} ${kb}KB${markerStr}${preview}`);
    });

    fs.mkdirSync(LOG_DIR, { recursive: true });
    fs.appendFileSync(ANALYSIS_LOG, lines.join('\n') + '\n', 'utf8');
  } catch (e) {
  }
}

function tryParseJson(buffer) {
  try {
    if (!buffer || buffer.length === 0) return {};
    return JSON.parse(buffer.toString("utf8"));
  } catch {
    return null;
  }
}

function isProxyHealthy(port) {
  return new Promise((resolve) => {
    const req = http.request(
      { hostname: "127.0.0.1", port, path: "/api/version", method: "GET", timeout: 2000 },
      (res) => {
        res.resume();
        resolve(res.statusCode < 500);
      }
    );
    req.on("error", () => resolve(false));
    req.on("timeout", () => { req.destroy(); resolve(false); });
    req.end();
  });
}

function readWindowsHostFromResolvConf() {
  try {
    if (process.platform === "win32") return null;
    const raw = fs.readFileSync("/etc/resolv.conf", "utf8");
    const m = raw.match(/^nameserver\s+([0-9.]+)\s*$/m);
    return m ? m[1] : null;
  } catch {
    return null;
  }
}

function readWindowsHostFromDefaultRoute() {
  try {
    if (process.platform === "win32") return null;
    const route = execSync("ip route 2>/dev/null", { encoding: "utf8" });
    const m = route.match(/^default\s+via\s+([0-9.]+)\s+/m);
    return m ? m[1] : null;
  } catch {
    return null;
  }
}

function readWindowsHostFromHostDockerInternal() {
  try {
    if (process.platform === "win32") return null;
    const hosts = execSync("getent hosts host.docker.internal 2>/dev/null", { encoding: "utf8" }).trim();
    if (!hosts) return null;
    const first = hosts.split(/\s+/)[0];
    return first || null;
  } catch {
    return null;
  }
}

function buildOllamaHostCandidates() {
  const candidates = [];
  const envHost = (process.env.OLLAMA_UPSTREAM_HOST || "").trim();
  if (envHost) candidates.push(envHost);
  candidates.push("127.0.0.1");
  const winHostResolv = readWindowsHostFromResolvConf();
  if (winHostResolv) candidates.push(winHostResolv);
  const winHostRoute = readWindowsHostFromDefaultRoute();
  if (winHostRoute) candidates.push(winHostRoute);
  const winHostDocker = readWindowsHostFromHostDockerInternal();
  if (winHostDocker) candidates.push(winHostDocker);
  return [...new Set(candidates.filter(Boolean))];
}

function probeOllamaHost(host, timeoutMs = 1800) {
  return new Promise((resolve) => {
    const req = http.request(
      {
        hostname: host,
        port: TARGET_PORT,
        path: "/api/version",
        method: "GET",
        timeout: timeoutMs,
      },
      (res) => {
        res.resume();
        resolve(res.statusCode < 500);
      }
    );
    req.on("error", () => resolve(false));
    req.on("timeout", () => {
      req.destroy();
      resolve(false);
    });
    req.end();
  });
}

async function resolveOllamaUpstreamHost() {
  const candidates = buildOllamaHostCandidates();
  for (const host of candidates) {
    if (await probeOllamaHost(host)) return host;
  }
  return (process.env.OLLAMA_UPSTREAM_HOST || DEFAULT_TARGET_HOST).trim() || DEFAULT_TARGET_HOST;
}

function killPortOccupant(port) {
  return new Promise((resolve) => {
    if (process.platform === "win32") {
      exec(`netstat -ano -p tcp`, (err, stdout) => {
        if (err || !stdout) { resolve(); return; }
        const portStr = `:${port}`;
        const line = stdout.split("\n").find(l => l.includes(portStr) && l.includes("LISTENING"));
        if (!line) { resolve(); return; }
        const parts = line.trim().split(/\s+/);
        const pid = parts[parts.length - 1];
        if (!pid || isNaN(Number(pid))) { resolve(); return; }
        log(`[WARN] ZOMBIE on port ${port} (PID ${pid}) -- force-killing...`);
        exec(`taskkill /F /PID ${pid}`, () => setTimeout(resolve, 800));
      });
      return;
    }

    exec(`lsof -ti tcp:${port} -sTCP:LISTEN`, (err, stdout) => {
      if (err || !stdout) { resolve(); return; }
      const pids = stdout
        .split("\n")
        .map((x) => x.trim())
        .filter(Boolean)
        .filter((x) => /^\d+$/.test(x));
      if (!pids.length) { resolve(); return; }
      log(`[WARN] ZOMBIE on port ${port} (PID ${pids.join(',')}) -- force-killing...`);
      exec(`kill -9 ${pids.join(' ')}`, () => setTimeout(resolve, 500));
    });
  });
}

async function start() {
  const inUse = await isPortInUse(LISTEN_PORT);

  if (inUse) {
    const healthy = await isProxyHealthy(LISTEN_PORT);
    if (process.env.PROXY_FORCE_START === "1") {
      log(`Taking ownership of port ${LISTEN_PORT} -- evicting existing occupant (force-start mode)...`);
      await killPortOccupant(LISTEN_PORT);
    } else if (healthy) {
      log(`[WARN] Proxy already running on port ${LISTEN_PORT}`);
      process.exit(2);
    } else {
      log(`[WARN] ZOMBIE detected on port ${LISTEN_PORT} -- killing and taking over...`);
      await killPortOccupant(LISTEN_PORT);
    }
  }

  activeTargetHost = await resolveOllamaUpstreamHost();
  log(`UPSTREAM → http://${activeTargetHost}:${TARGET_PORT}`);

  let proxyMode = "nothink";
  let requestCounter = 0;
  let predictCap = 4096;
  let captureRemaining = 0;
  const CAPTURE_DIR = path.join(LOG_DIR, "captures");

  const server = http.createServer(async (req, res) => {
    try {
      const pathname = req.url || "/";

      if (pathname === "/proxy/status" && req.method === "GET") {
        res.writeHead(200, { "content-type": "application/json" });
        res.end(JSON.stringify({
          mode: proxyMode,
          predictCap,
          captureRemaining,
          requests: requestCounter,
          port: LISTEN_PORT,
          upstreamHost: activeTargetHost,
          upstreamPort: TARGET_PORT
        }));
        return;
      }

      if (pathname === "/proxy/capture" && req.method === "POST") {
        const capBody = await collectBody(req);
        const capParsed = tryParseJson(capBody);
        if (capParsed && typeof capParsed.count === "number" && capParsed.count >= 0) {
          captureRemaining = capParsed.count;
          if (captureRemaining > 0) {
            fs.mkdirSync(CAPTURE_DIR, { recursive: true });
          }
          log(`CAPTURE → ${captureRemaining === 0 ? "disabled" : `next ${captureRemaining} requests`}`);
          res.writeHead(200, { "content-type": "application/json" });
          res.end(JSON.stringify({ captureRemaining, captureDir: CAPTURE_DIR, ok: true }));
        } else {
          res.writeHead(400, { "content-type": "application/json" });
          res.end(JSON.stringify({ error: "count must be a non-negative integer" }));
        }
        return;
      }

      if (pathname === "/proxy/mode" && req.method === "POST") {
        const modeBody = await collectBody(req);
        const modeParsed = tryParseJson(modeBody);
        if (modeParsed && (modeParsed.mode === "nothink" || modeParsed.mode === "passthrough")) {
          proxyMode = modeParsed.mode;
          log(`MODE → ${proxyMode}`);
          res.writeHead(200, { "content-type": "application/json" });
          res.end(JSON.stringify({ mode: proxyMode, ok: true }));
        } else {
          res.writeHead(400, { "content-type": "application/json" });
          res.end(JSON.stringify({ error: "mode must be 'nothink' or 'passthrough'" }));
        }
        return;
      }

      if (pathname === "/proxy/predict-cap" && req.method === "POST") {
        const capBody = await collectBody(req);
        const capParsed = tryParseJson(capBody);
        if (capParsed && typeof capParsed.cap === "number" && capParsed.cap >= 0) {
          predictCap = capParsed.cap;
          log(`PREDICT_CAP → ${predictCap === 0 ? "disabled" : predictCap}`);
          res.writeHead(200, { "content-type": "application/json" });
          res.end(JSON.stringify({ predictCap, ok: true }));
        } else {
          res.writeHead(400, { "content-type": "application/json" });
          res.end(JSON.stringify({ error: "cap must be a non-negative integer (0 = disabled)" }));
        }
        return;
      }

      requestCounter++;
      const t0 = Date.now();
      const bodyBuffer = await collectBody(req);
      let outgoingBody = bodyBuffer;

      if (captureRemaining > 0 && shouldInject(pathname)) {
        captureRemaining--;
        const captureFile = path.join(CAPTURE_DIR, `req-${Date.now()}-${requestCounter}.json`);
        try {
          fs.mkdirSync(CAPTURE_DIR, { recursive: true });
          fs.writeFileSync(captureFile, bodyBuffer.toString("utf8"), "utf8");
          log(`CAPTURED → ${captureFile} (${Math.round(bodyBuffer.length / 1024)}KB) remaining:${captureRemaining}`);
        } catch (e) {
          log(`CAPTURE_ERR ${e.message}`);
        }
      }

      const parsed = tryParseJson(bodyBuffer);

      if (parsed && shouldInject(pathname)) {
        analyzeRequest(requestCounter, pathname, parsed, bodyBuffer.length);
      }

      const msgCount = parsed && parsed.messages ? parsed.messages.length : "?";
      const bodyKB = Math.round(bodyBuffer.length / 1024);
      const msgSizes = parsed && parsed.messages
        ? parsed.messages.map(m => {
            const content = m.content || "";
            const sz = typeof content === "string" ? content.length : JSON.stringify(content).length;
            return `${m.role[0].toUpperCase()}:${Math.round(sz/1024)}KB`;
          }).join(" ")
        : "";
      const topLevelKeys = parsed ? Object.keys(parsed).map(k => {
        const v = parsed[k];
        if (k === "messages") return null;
        const sz = typeof v === "string" ? v.length : JSON.stringify(v).length;
        return sz > 512 ? `${k}:${Math.round(sz/1024)}KB` : null;
      }).filter(Boolean).join(" ") : "";
      log(
        "REQ",
        pathname,
        parsed && parsed.model,
        parsed && parsed.think,
        parsed && parsed.stream,
        `msgs:${msgCount}`,
        `body:${bodyKB}KB`,
        `[${msgSizes}]`,
        topLevelKeys || ""
      );

      if (parsed && shouldInject(pathname)) {
        const compStats = compressRequest(parsed);
        if (compStats.toolsRemoved > 0 || compStats.bytesEstimate > 0) {
          log(
            "COMPRESS",
            `tools_removed:${compStats.toolsRemoved}`,
            `tools_remaining:${parsed.tools ? parsed.tools.length : 0}`,
            `~bytes_saved:${compStats.bytesEstimate}`,
            `~KB_saved:${Math.round(compStats.bytesEstimate / 1024)}`
          );
        }

        if (pathname === "/api/chat" || pathname === "/api/generate") {
          parsed.keep_alive = -1;
        }

        if (proxyMode === "nothink") {
          if (pathname === "/api/chat" || pathname === "/api/generate") {
            parsed.think = false;
          } else {
            parsed.think = false;
            parsed.reasoning_effort = "none";
            parsed.reasoning = {
              ...(parsed.reasoning || {}),
              effort: "none"
            };
          }

          const msgs = parsed.messages;
          if (Array.isArray(msgs)) {
            const sysMsg = msgs.find(m => m.role === "system");
            if (sysMsg && typeof sysMsg.content === "string") {
              if (!sysMsg.content.includes("/no_think")) {
                sysMsg.content = "/no_think\n" + sysMsg.content;
              }
            } else if (!sysMsg) {
              msgs.unshift({ role: "system", content: "/no_think" });
            }
          }
        }

        if (predictCap > 0) {
          if (pathname === "/api/chat" || pathname === "/api/generate") {
            if (parsed.num_predict == null) {
              parsed.num_predict = predictCap;
            }
          } else {
            if (parsed.max_tokens == null) {
              parsed.max_tokens = predictCap;
            }
          }
        }

        log(
          "FWD",
          pathname,
          parsed && parsed.model,
          parsed && parsed.think,
          parsed && parsed.stream,
          parsed && parsed.reasoning_effort,
          parsed && parsed.reasoning && parsed.reasoning.effort,
          parsed.num_predict != null ? `num_predict:${parsed.num_predict}` : (parsed.max_tokens != null ? `max_tokens:${parsed.max_tokens}` : "")
        );

        outgoingBody = Buffer.from(JSON.stringify(parsed), "utf8");
      }

      const options = {
        hostname: activeTargetHost,
        port: TARGET_PORT,
        path: pathname,
        method: req.method,
        headers: {
          ...req.headers,
          host: `${activeTargetHost}:${TARGET_PORT}`,
          "content-length": Buffer.byteLength(outgoingBody)
        }
      };

      const isStreaming = parsed && parsed.stream === true;

      const proxyReq = http.request(options, (proxyRes) => {
        if (isStreaming) {
          res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
          proxyRes.on("data", (chunk) => { try { res.write(chunk); } catch (_) {} });
          proxyRes.on("end", () => {
            const elapsed = Date.now() - t0;
            log("RESP", pathname, proxyRes.statusCode, `${elapsed}ms`, `mode:${proxyMode}`, "stream:true");
            try { res.end(); } catch (_) {}
          });
          proxyRes.on("error", (err) => {
            log("ERR", "STREAM_FROM_OLLAMA", err.message);
            try { res.end(); } catch (_) {}
          });
        } else {
          const responseChunks = [];
          proxyRes.on("data", (chunk) => responseChunks.push(chunk));
          proxyRes.on("end", () => {
            const responseBody = Buffer.concat(responseChunks);
            const elapsed = Date.now() - t0;
            log("RESP", pathname, proxyRes.statusCode, `${elapsed}ms`, `mode:${proxyMode}`);
            res.writeHead(proxyRes.statusCode || 500, proxyRes.headers);
            res.end(responseBody);
          });
          proxyRes.on("error", (err) => {
            log("ERR", "STREAM_FROM_OLLAMA", err.message);
            try { res.end(); } catch (_) {}
          });
        }
      });

      res.on("error", (err) => {
        log("ERR", "STREAM_TO_CLIENT", err.message);
      });

      proxyReq.on("error", (err) => {
        log("ERR", pathname, err.message);
        res.writeHead(502, { "content-type": "application/json" });
        res.end(JSON.stringify({ error: `Proxy request failed: ${err.message}` }));
      });

      if (outgoingBody.length > 0) {
        proxyReq.write(outgoingBody);
      }

      proxyReq.end();
    } catch (err) {
      log("ERR", "SERVER", err.message);
      res.writeHead(500, { "content-type": "application/json" });
      res.end(JSON.stringify({ error: err.message }));
    }
  });

  server.on("error", (err) => {
    if (err.code === "EADDRINUSE") {
      log(`[WARN] Port ${LISTEN_PORT} already in use -- another proxy instance is running. Exiting.`);
      process.exit(2);
    } else {
      log("ERR", "SERVER_BIND", err.message);
      process.exit(1);
    }
  });

  server.listen(LISTEN_PORT, "127.0.0.1", () => {
    log(`Proxy running on http://127.0.0.1:${LISTEN_PORT}`);
    log(`Forwarding to http://${activeTargetHost}:${TARGET_PORT}`);
    log(`Log file: ${LOG_FILE}`);
  });
}

process.on("uncaughtException", (err) => {
  try {
    const line = `[${new Date().toISOString()}] FATAL uncaughtException: ${err.stack || err.message}\n`;
    fs.appendFileSync(LOG_FILE, line, "utf8");
    console.error("FATAL uncaughtException:", err);
  } catch (_) {}
});

process.on("unhandledRejection", (reason) => {
  try {
    const line = `[${new Date().toISOString()}] WARN unhandledRejection: ${reason}\n`;
    fs.appendFileSync(LOG_FILE, line, "utf8");
    console.error("WARN unhandledRejection:", reason);
  } catch (_) {}
});

function watchdog() {
  isPortInUse(LISTEN_PORT).then(async inUse => {
    if (inUse) {
      const healthy = await isProxyHealthy(LISTEN_PORT);
      if (healthy) {
        log(`[WARN] Proxy already running on port ${LISTEN_PORT} -- watchdog exiting (no restart needed)`);
        process.exit(2);
      } else {
        log(`[WARN] ZOMBIE detected on port ${LISTEN_PORT} -- killing before spawning worker...`);
        await killPortOccupant(LISTEN_PORT);
      }
    }

    log(`Watchdog started -- worker will be restarted on unexpected exit`);

    let restartCount = 0;
    let lastStartTime = 0;
    let currentWorker = null;

    function spawnWorker() {
      lastStartTime = Date.now();
      if (restartCount > 0) {
        log(`WATCHDOG: restarting worker (attempt ${restartCount})`);
      }

      const worker = spawn(process.execPath, [__filename], {
        env: { ...process.env, PROXY_WORKER: "1" },
        stdio: "inherit",
        detached: false
      });
      currentWorker = worker;

      worker.on("exit", (code, signal) => {
        currentWorker = null;
        const uptime = Math.round((Date.now() - lastStartTime) / 1000);

        if (code === 2) {
          log(`[WARN] WATCHDOG: worker detected existing proxy on port ${LISTEN_PORT} -- stopping watchdog`);
          process.exit(2);
          return;
        }

        if (code === 0) {
          log(`WATCHDOG: worker exited cleanly (uptime:${uptime}s) -- stopping watchdog`);
          process.exit(0);
          return;
        }

        log(`[WARN] WATCHDOG: worker exited unexpectedly (code:${code} signal:${signal} uptime:${uptime}s)`);
        if (uptime > 60) restartCount = 0;
        restartCount++;
        const delay = Math.min(2000 * Math.pow(2, restartCount - 1), 30000);
        log(`   -> Restarting in ${Math.round(delay / 1000)}s...`);
        setTimeout(spawnWorker, delay);
      });

      worker.on("error", (err) => {
        currentWorker = null;
        log(`[WARN] WATCHDOG: failed to spawn worker -- ${err.message}`);
        restartCount++;
        setTimeout(spawnWorker, 5000);
      });
    }

    function shutdown(sig) {
      log(`WATCHDOG: ${sig} received -- shutting down`);
      if (currentWorker) {
        try { currentWorker.kill(sig); } catch (_) {}
      }
      setTimeout(() => process.exit(0), 3000);
    }
    process.on("SIGTERM", () => shutdown("SIGTERM"));
    process.on("SIGINT", () => shutdown("SIGINT"));

    spawnWorker();
  });
}

const IS_WORKER = process.env.PROXY_WORKER === "1";
if (IS_WORKER) {
  start();
} else {
  watchdog();
}
