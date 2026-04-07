"use strict";

const http = require("http");
const { spawn } = require("child_process");
const fs = require("fs");
const path = require("path");
const { execSync } = require("child_process");

const OLLAMA_DEFAULT_HOST = "127.0.0.1";
const OLLAMA_PORT = 11434;
const PROXY_SCRIPT = path.join(__dirname, "ollama-nothink-proxy.js");
const POLL_MS = 3000;
const RESTART_DELAY_MS = 5000;

const LOG_DIR = path.join(__dirname, "..", "logs");
const LOG_FILE = path.join(LOG_DIR, "ollama-proxy-manager.log");
let activeOllamaHost = process.env.OLLAMA_UPSTREAM_HOST || OLLAMA_DEFAULT_HOST;

function isApprovedWslProjectCwd(cwd) {
  if (!cwd || typeof cwd !== "string") return false;
  if (cwd.startsWith("/home/") && cwd.includes("/dev/")) return true;
  if (cwd.startsWith("/root/dev/")) return true;
  return false;
}

function ensureLogDir() {
  fs.mkdirSync(LOG_DIR, { recursive: true });
}

function log(...args) {
  const line =
    `[${new Date().toISOString()}] ` +
    args.map((x) => (typeof x === "string" ? x : JSON.stringify(x))).join(" ");
  console.log(line);
  ensureLogDir();
  try {
    fs.appendFileSync(LOG_FILE, line + "\n", "utf8");
  } catch (_) {}
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

function isOllamaUp(host = activeOllamaHost, timeoutMs = 2500) {
  return new Promise((resolve) => {
    const req = http.request(
      {
        hostname: host,
        port: OLLAMA_PORT,
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

async function findReachableOllamaHost() {
  const candidates = buildOllamaHostCandidates();
  for (const host of candidates) {
    if (await isOllamaUp(host)) return host;
  }
  return null;
}

function isProxyHealthy(port, timeoutMs = 2000) {
  return new Promise((resolve) => {
    const req = http.request(
      { hostname: "127.0.0.1", port, path: "/api/version", method: "GET", timeout: timeoutMs },
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

let worker = null;
let workerRestarting = false;
let ollamaWasUp = false;
let shuttingDown = false;

function startWorker() {
  if (worker || workerRestarting || shuttingDown) return;

  log("Starting proxy worker...");
  worker = spawn(process.execPath, [PROXY_SCRIPT], {
    env: {
      ...process.env,
      PROXY_WORKER: "1",
      PROXY_FORCE_START: "1",
      OLLAMA_UPSTREAM_HOST: activeOllamaHost,
    },
    stdio: "inherit",
    detached: false,
  });

  worker.on("exit", (code, signal) => {
    worker = null;
    if (shuttingDown) return;
    log(`Proxy worker exited (code:${code} signal:${signal})`);

    if (code === 2) {
      workerRestarting = true;
      setTimeout(async () => {
        workerRestarting = false;
        if (shuttingDown) return;
        const proxyUp = await isProxyHealthy(11435);
        if (proxyUp) {
          log("Port 11435 held by a healthy proxy -- not restarting (will resume on next poll if it disappears)");
        } else if (await isOllamaUp()) {
          log("Port clear and Ollama still up -- restarting proxy worker");
          startWorker();
        }
      }, RESTART_DELAY_MS);
      return;
    }

    workerRestarting = true;
    setTimeout(async () => {
      workerRestarting = false;
      if (!shuttingDown && await isOllamaUp()) {
        log("Ollama still up -- restarting proxy worker");
        startWorker();
      } else {
        log("Ollama is down after worker exit -- will restart when Ollama comes back");
      }
    }, RESTART_DELAY_MS);
  });

  worker.on("error", (err) => {
    log(`[ERROR] Failed to spawn proxy worker: ${err.message}`);
    worker = null;
  });
}

function stopWorker(reason) {
  if (!worker) return;
  log(`Stopping proxy worker (reason: ${reason})...`);
  const w = worker;
  try {
    w.kill("SIGTERM");
  } catch (_) {}
  setTimeout(() => {
    if (w && !w.killed) {
      try {
        w.kill("SIGKILL");
      } catch (_) {}
    }
  }, 4000);
}

async function poll() {
  if (shuttingDown) return;

  const detectedHost = await findReachableOllamaHost();
  const ollamaUp = !!detectedHost;
  if (detectedHost && detectedHost !== activeOllamaHost) {
    activeOllamaHost = detectedHost;
    log(`Ollama upstream switched to http://${activeOllamaHost}:${OLLAMA_PORT}`);
  }

  if (ollamaUp && !ollamaWasUp) {
    log("Ollama came up");
  } else if (!ollamaUp && ollamaWasUp) {
    log("Ollama went down");
  }
  ollamaWasUp = ollamaUp;

  if (ollamaUp && !worker && !workerRestarting) {
    log("Ollama is up -- starting proxy worker");
    startWorker();
  } else if (!ollamaUp && worker) {
    log("Ollama is down -- stopping proxy worker");
    stopWorker("ollama-down");
  }
}

function shutdown(sig) {
  log(`Received ${sig} -- shutting down`);
  shuttingDown = true;
  stopWorker(sig);
  setTimeout(() => process.exit(0), 5000);
}

process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("SIGINT", () => shutdown("SIGINT"));

process.on("uncaughtException", (err) => {
  log(`[FATAL] uncaughtException: ${err.stack || err.message}`);
});

process.on("unhandledRejection", (reason) => {
  log(`[WARN] unhandledRejection: ${reason}`);
});

log("Ollama proxy manager started");
if (!isApprovedWslProjectCwd(process.cwd())) {
  log("[FATAL] Refusing to start outside approved WSL project roots.");
  log("Current cwd:", process.cwd());
  log("Expected: /home/<user>/dev/... (or /root/dev/... for CI/container contexts)");
  process.exit(12);
}
log(`Polling Ollama candidates every ${POLL_MS}ms (active: http://${activeOllamaHost}:${OLLAMA_PORT})`);
log(`Proxy script: ${PROXY_SCRIPT}`);

poll();
setInterval(poll, POLL_MS);
