/**
 * Standalone WebM Converter — Dev Server
 *
 * Serves the converter UI with COOP/COEP headers (required for SharedArrayBuffer
 * multi-threaded wasm), and receives log posts from the browser, writing them
 * to logs/session-<date>.log
 *
 * Usage:  node server.js [port]   (default port: 9000)
 */

const http  = require("http");
const fs    = require("fs");
const path  = require("path");

const PORT = parseInt(process.argv[2] || "9000", 10);
const ROOT = __dirname;
const LOGS_DIR = path.join(ROOT, "logs");

if (!fs.existsSync(LOGS_DIR)) fs.mkdirSync(LOGS_DIR, { recursive: true });

const MIME = {
  ".html":  "text/html; charset=utf-8",
  ".js":    "application/javascript; charset=utf-8",
  ".mjs":   "application/javascript; charset=utf-8",
  ".css":   "text/css; charset=utf-8",
  ".wasm":  "application/wasm",
  ".json":  "application/json",
  ".ico":   "image/x-icon",
};

// One log file per server session (not per day), named by startup timestamp.
const SESSION_START = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
const LOG_FILE = path.join(LOGS_DIR, `session-${SESSION_START}.log`);

function writeLog(line) {
  const ts  = new Date().toISOString();
  const out = `[${ts}] ${line}\n`;
  process.stdout.write(out);
  fs.appendFileSync(LOG_FILE, out);
}

writeLog(`Server starting on http://localhost:${PORT}`);
writeLog(`Log file: ${LOG_FILE}`);

const server = http.createServer((req, res) => {
  // CORS + cross-origin isolation headers on every response
  res.setHeader("Cross-Origin-Opener-Policy",   "same-origin");
  res.setHeader("Cross-Origin-Embedder-Policy",  "require-corp");
  res.setHeader("Cross-Origin-Resource-Policy",  "same-origin");
  res.setHeader("Access-Control-Allow-Origin",   "http://localhost:" + PORT);

  // ---- API: POST /api/log ----
  if (req.method === "POST" && req.url === "/api/log") {
    let body = "";
    req.on("data", chunk => { body += chunk; });
    req.on("end", () => {
      try {
        const { level, message, data } = JSON.parse(body);
        const dataStr = data !== undefined ? " | " + JSON.stringify(data) : "";
        writeLog(`[BROWSER][${(level || "info").toUpperCase()}] ${message}${dataStr}`);
        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ ok: true }));
      } catch (e) {
        res.writeHead(400);
        res.end(JSON.stringify({ error: "invalid json" }));
      }
    });
    return;
  }

  // ---- API: GET /api/logs ----
  if (req.method === "GET" && req.url === "/api/logs") {
    try {
      const content = fs.readFileSync(LOG_FILE, "utf8");
      res.writeHead(200, { "Content-Type": "text/plain; charset=utf-8" });
      res.end(content);
    } catch {
      res.writeHead(200, { "Content-Type": "text/plain" });
      res.end("(no log yet)");
    }
    return;
  }

  // ---- Static file serving ----
  let urlPath = req.url.split("?")[0];
  if (urlPath === "/") urlPath = "/index.html";
  const filePath = path.join(ROOT, urlPath);

  // Security: prevent path traversal outside ROOT
  if (!filePath.startsWith(ROOT + path.sep) && filePath !== ROOT) {
    res.writeHead(403); res.end("Forbidden"); return;
  }

  fs.stat(filePath, (err, stat) => {
    if (err || !stat.isFile()) {
      res.writeHead(404, { "Content-Type": "text/plain" });
      res.end("Not found: " + urlPath);
      return;
    }

    const ext  = path.extname(filePath).toLowerCase();
    const mime = MIME[ext] || "application/octet-stream";

    // WASM files get no-cache during dev
    if (ext === ".wasm" || ext === ".js") {
      res.setHeader("Cache-Control", "no-cache");
    }

    res.writeHead(200, { "Content-Type": mime });
    fs.createReadStream(filePath).pipe(res);
  });
});

server.listen(PORT, "127.0.0.1", () => {
  writeLog(`Listening → http://127.0.0.1:${PORT}`);
  console.log(`\nOpen: http://localhost:${PORT}\nLogs: ${LOG_FILE}\n`);
});
