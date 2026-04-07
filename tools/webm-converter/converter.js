/**
 * WebM Converter — standalone browser-side converter using @ffmpeg/ffmpeg v0.12.x (MT build)
 *
 * All logs go to:
 *   1. The in-page log panel  (press L to toggle)
 *   2. POST /api/log          (server writes to logs/session-*.log)
 *   3. console.*              (browser DevTools)
 */

// ─── Logging ──────────────────────────────────────────────────────────────────

const logLines = [];

async function log(level, message, data) {
  const ts  = new Date().toISOString().slice(11, 23); // HH:MM:SS.mmm
  const dataStr = data !== undefined ? " | " + JSON.stringify(data) : "";
  const line = `[${ts}][${level.toUpperCase()}] ${message}${dataStr}`;

  logLines.push(line);
  if (level === "error") console.error(line);
  else if (level === "warn") console.warn(line);
  else console.log(line);

  // Update in-page log panel
  const el = document.getElementById("log-output");
  if (el) {
    el.textContent = logLines.join("\n");
    el.scrollTop = el.scrollHeight;
  }

  // Async fire-and-forget: POST to server log endpoint
  try {
    await fetch("/api/log", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ level, message, data }),
    });
  } catch {
    // Server may not be running (opened as file://), ignore silently.
  }
}

const LOG = {
  info:  (msg, data) => log("info",  msg, data),
  warn:  (msg, data) => log("warn",  msg, data),
  error: (msg, data) => log("error", msg, data),
  debug: (msg, data) => log("debug", msg, data),
};

// ─── DOM refs ─────────────────────────────────────────────────────────────────

const $ = (id) => document.getElementById(id);
const elDropzone       = $("dropzone");
const elFileInput      = $("file-input");
const elFileInfo       = $("file-info");
const elFileName       = $("file-name");
const elFileSize       = $("file-size");
const elFileClear      = $("file-clear");
const elConvertBtn     = $("convert-btn");
const elActiveNotice   = $("active-notice");
const elProgressWrap   = $("progress-wrap");
const elProgressBar    = $("progress-bar");
const elProgressPct    = $("progress-pct");
const elProgressStage  = $("progress-stage");
const elProgressDetail = $("progress-detail");
const elDownloadWebm   = $("download-webm");
const elDownloadPoster = $("download-poster");
const elResultSizeWebm = $("result-size-webm");
const elResultPosterRow= $("result-poster-row");
const elResultSavings  = $("result-savings");
const elConvertAnother = $("convert-another");
const elErrorBox       = $("error-box");
const elFfmpegLoading  = $("ffmpeg-loading");
const elFfmpegBar      = $("ffmpeg-bar");
const elFfmpegPct      = $("ffmpeg-pct");
const elFfmpegStatus   = $("ffmpeg-status");
const elViewConvert    = $("view-convert");
const elViewResult     = $("view-result");
const elLogPanel       = $("log-panel");
const elLogOutput      = $("log-output");

function show(el) { if (el) el.hidden = false; }
function hide(el) { if (el) el.hidden = true; }

// ─── Log panel (press L) ──────────────────────────────────────────────────────

document.addEventListener("keydown", (e) => {
  if (e.key === "l" || e.key === "L") {
    elLogPanel.hidden = !elLogPanel.hidden;
  }
});
$("log-close-btn").addEventListener("click", () => { elLogPanel.hidden = true; });
$("log-clear-btn").addEventListener("click", () => {
  logLines.length = 0;
  elLogOutput.textContent = "";
});

// ─── Utilities ────────────────────────────────────────────────────────────────

function humanBytes(b) {
  if (b < 1024) return b + " B";
  if (b < 1024 * 1024) return (b / 1024).toFixed(1) + " KB";
  return (b / (1024 * 1024)).toFixed(1) + " MB";
}

function setProgress(pct, stage, detail) {
  pct = Math.max(0, Math.min(100, pct));
  elProgressBar.style.width = pct + "%";
  elProgressBar.setAttribute("aria-valuenow", pct);
  elProgressPct.textContent = pct + "%";
  if (stage !== undefined)  elProgressStage.textContent  = stage;
  if (detail !== undefined) elProgressDetail.textContent = detail;
}

function showError(msg) {
  elErrorBox.innerHTML = msg;
  elErrorBox.hidden    = false;
  LOG.error("UI error shown", msg);
}

function clearError() {
  elErrorBox.hidden    = true;
  elErrorBox.innerHTML = "";
}

// ─── State ────────────────────────────────────────────────────────────────────

let selectedFile    = null;
let ffmpegInstance  = null;
let ffmpegLoaded    = false;
let isConverting    = false;

// ─── File selection ───────────────────────────────────────────────────────────

elDropzone.addEventListener("click", () => elFileInput.click());
elDropzone.addEventListener("keydown", (e) => {
  if (e.key === "Enter" || e.key === " ") elFileInput.click();
});
elDropzone.addEventListener("dragover",  (e) => { e.preventDefault(); elDropzone.classList.add("drag-over"); });
elDropzone.addEventListener("dragleave", () => elDropzone.classList.remove("drag-over"));
elDropzone.addEventListener("drop", (e) => {
  e.preventDefault();
  elDropzone.classList.remove("drag-over");
  const file = e.dataTransfer && e.dataTransfer.files[0];
  if (file) applyFile(file);
});
elFileInput.addEventListener("change", () => {
  if (elFileInput.files && elFileInput.files[0]) applyFile(elFileInput.files[0]);
});
elFileClear.addEventListener("click", resetUI);

function applyFile(file) {
  clearError();
  LOG.info("File selected", { name: file.name, size: file.size, type: file.type });
  if (!file.type.startsWith("video/")) {
    showError("Please select a video file.");
    LOG.warn("Rejected: not a video", { type: file.type });
    return;
  }
  selectedFile = file;
  elFileName.textContent = file.name;
  elFileSize.textContent = humanBytes(file.size);
  show(elFileInfo);
  hide(elDropzone);
  elConvertBtn.disabled = false;
  LOG.info("File accepted, ready to convert");
}

// ─── Quality map ─────────────────────────────────────────────────────────────

const QUALITY_MAP = { hq: 18, balanced: 28, compressed: 40 };

function getSelectedCrf() {
  const radios = document.querySelectorAll("input[name='quality']");
  for (const r of radios) {
    if (r.checked) return QUALITY_MAP[r.value] ?? 28;
  }
  return 28;
}

// ─── FFmpeg init ─────────────────────────────────────────────────────────────

function setFfmpegProgress(pct) {
  pct = Math.round(pct);
  elFfmpegBar.style.width = pct + "%";
  elFfmpegBar.setAttribute("aria-valuenow", pct);
  elFfmpegPct.textContent = pct + "%";
}

function setFfmpegStatus(text) {
  if (elFfmpegStatus) elFfmpegStatus.textContent = text;
  LOG.info("[FFmpeg init] " + text);
}

async function initFfmpeg() {
  if (ffmpegLoaded && ffmpegInstance) {
    LOG.info("FFmpeg already loaded, reusing instance");
    return ffmpegInstance;
  }

  LOG.info("initFfmpeg(): start");
  LOG.info("crossOriginIsolated = " + self.crossOriginIsolated);

  if (!self.crossOriginIsolated) {
    LOG.error("Page is NOT cross-origin isolated — SharedArrayBuffer unavailable");
    throw new Error(
      "Page is not cross-origin isolated. " +
      "Hard-reload with Ctrl+Shift+R, or ensure the server is running " +
      "(node server.js) rather than opening index.html directly."
    );
  }

  show(elFfmpegLoading);
  setFfmpegProgress(0);
  setFfmpegStatus("Importing FFmpeg ESM module…");

  // Simulate smooth progress bar to 85% while wasm loads
  let simPct = 0;
  const simIv = setInterval(() => {
    simPct = Math.min(simPct + Math.random() * 2 + 0.3, 85);
    setFfmpegProgress(simPct);
  }, 200);

  try {
    LOG.info("Dynamic import: ./ffmpeg/index.js");
    const mod = await import("./ffmpeg/index.js");
    LOG.info("ESM import complete", { exports: Object.keys(mod) });

    const FFmpeg = mod.FFmpeg;
    if (!FFmpeg) throw new Error("FFmpeg class not found in ESM export");

    LOG.info("new FFmpeg()");
    const ff = new FFmpeg();

    // Wire up log events BEFORE .load() so we capture init messages
    ff.on("log", (e) => {
      LOG.debug("[ffmpeg-core log] " + (e.message || JSON.stringify(e)));
    });

    setFfmpegStatus("Loading WebAssembly core…");
    // Use absolute paths so worker.js doesn't double-resolve them
    // (worker is at /ffmpeg/worker.js — relative ./ffmpeg/... would become /ffmpeg/ffmpeg/...)
    const coreURL   = "/ffmpeg/ffmpeg-core.js";
    const wasmURL   = "/ffmpeg/ffmpeg-core.wasm";
    const workerURL = "/ffmpeg/ffmpeg-core.worker.js";

    LOG.info("ff.load() starting", { coreURL, wasmURL, workerURL });

    await ff.load({ coreURL, wasmURL, workerURL });

    clearInterval(simIv);
    setFfmpegProgress(100);
    setFfmpegStatus("FFmpeg ready");
    LOG.info("ff.load() complete ✓");

    ffmpegInstance = ff;
    ffmpegLoaded   = true;

    setTimeout(() => hide(elFfmpegLoading), 600);
    return ff;
  } catch (e) {
    clearInterval(simIv);
    hide(elFfmpegLoading);
    LOG.error("initFfmpeg() failed", { message: e.message, stack: e.stack });
    throw e;
  }
}

// ─── Convert ─────────────────────────────────────────────────────────────────

elConvertBtn.addEventListener("click", doConvert);

function doConvert() {
  if (isConverting || !selectedFile) return;
  LOG.info("Convert button clicked", { file: selectedFile.name, crf: getSelectedCrf() });
  clearError();
  startConvert(selectedFile, getSelectedCrf());
}

function startConvert(file, crf) {
  isConverting = true;
  elConvertBtn.disabled = true;
  show(elActiveNotice);
  show(elProgressWrap);
  clearError();
  setProgress(0, "Loading FFmpeg…", "");
  window.addEventListener("beforeunload", warnBeforeUnload);
  LOG.info("startConvert()", { fileName: file.name, fileSize: file.size, crf });

  initFfmpeg()
    .then((ff) => {
      setProgress(2, "Reading file…", "");
      LOG.info("Reading file into ArrayBuffer…");

      const reader = new FileReader();
      reader.onload = (e) => {
        LOG.info("FileReader done", { bytes: e.target.result.byteLength });
        runEncode(ff, file, new Uint8Array(e.target.result), crf);
      };
      reader.onerror = () => {
        LOG.error("FileReader error");
        finishConvert();
        showError("Failed to read the file.");
      };
      reader.readAsArrayBuffer(file);
    })
    .catch((err) => {
      LOG.error("FFmpeg init failed in startConvert", { message: err.message });
      finishConvert();
      showError("Could not load FFmpeg: " + err.message);
    });
}

function runEncode(ff, file, inputBytes, crf) {
  const baseName  = file.name.replace(/\.[^.]+$/, "");
  const inName    = "input.mp4";
  const outWebm   = "output.webm";
  const outPoster = "poster.jpg";

  LOG.info("runEncode() start", { baseName, inName, outWebm, crf, inputBytes: inputBytes.length });

  // Capture all ffmpeg logs
  const ffLogs = [];
  const logHandler = (e) => {
    const msg = e && e.message ? e.message : JSON.stringify(e);
    ffLogs.push(msg);
    LOG.debug("[ffmpeg] " + msg);
  };
  ff.on("log", logHandler);

  // Progress updates — throttle to one log per 5% change
  let lastLoggedPct = -1;
  const progressHandler = (e) => {
    if (e && typeof e.progress === "number" && e.progress >= 0) {
      const pct = Math.min(Math.round(e.progress * 100), 99);
      setProgress(pct, "Encoding VP9…", "");
      if (pct - lastLoggedPct >= 5) {
        LOG.info(`Encode progress: ${pct}%`);
        lastLoggedPct = pct;
      }
    }
  };
  ff.on("progress", progressHandler);

  const ffArgs = [
    "-i",             inName,
    "-vf",            "scale=1280:720:force_original_aspect_ratio=decrease,scale=trunc(iw/2)*2:trunc(ih/2)*2",
    "-c:v",           "libvpx-vp9",
    "-b:v",           "0",
    "-crf",           String(crf),
    "-deadline",      "good",
    "-cpu-used",      "4",
    "-row-mt",        "1",
    "-auto-alt-ref",  "1",
    "-lag-in-frames", "16",
    "-tile-columns",  "2",
    "-pix_fmt",       "yuv420p",
    "-g",             "120",
    "-keyint_min",    "120",
    "-ac",            "2",
    "-ar",            "44100",
    "-c:a",           "libopus",
    "-b:a",           "96k",
    "-y",             outWebm,
  ];

  setProgress(5, "Writing input to wasm memory…", "");
  LOG.info("ff.writeFile() start");

  Promise.resolve()
    .then(() => ff.writeFile(inName, inputBytes))
    .then(() => {
      LOG.info("ff.writeFile() done");
      setProgress(8, "Encoding VP9 + Opus…", "This may take a few minutes.");
      LOG.info("ff.exec() start", { args: ffArgs });
      return ff.exec(ffArgs);
    })
    .then((ret) => {
      LOG.info("ff.exec() returned", { exitCode: ret });
      if (ret !== 0) {
        const snippet = ffLogs.slice(-10).join("\n");
        LOG.error("ffmpeg exited non-zero", { exitCode: ret, lastLines: snippet });
        throw new Error("FFmpeg exited with code " + ret + (snippet ? "\n\n" + snippet : ""));
      }
      setProgress(95, "Reading WebM output…", "");
      LOG.info("ff.readFile(output.webm) start");
      return ff.readFile(outWebm);
    })
    .then((webmData) => {
      ff.off("progress", progressHandler);
      ff.off("log", logHandler);
      LOG.info("WebM read complete", { bytes: webmData.length });

      setProgress(97, "Extracting poster frame…", "");

      const webmBlob = new Blob([webmData.buffer], { type: "video/webm" });
      const webmUrl  = URL.createObjectURL(webmBlob);
      elDownloadWebm.href     = webmUrl;
      elDownloadWebm.download = baseName + ".webm";
      elResultSizeWebm.textContent = humanBytes(webmBlob.size);

      const savings = Math.round((1 - webmBlob.size / file.size) * 100);
      elResultSavings.textContent = savings > 0
        ? "⬇ " + savings + "% smaller (" + humanBytes(file.size) + " → " + humanBytes(webmBlob.size) + ")"
        : "Output size: " + humanBytes(webmBlob.size);

      LOG.info("Savings", { originalBytes: file.size, webmBytes: webmBlob.size, savingsPct: savings });

      LOG.info("ff.exec() poster start");
      return ff.exec(["-ss", "0.5", "-i", inName, "-vframes", "1", "-q:v", "2", "-y", outPoster])
        .then(() => ff.readFile(outPoster).catch(() => null))
        .catch(() => null);
    })
    .then((posterData) => {
      if (posterData) {
        LOG.info("Poster extracted", { bytes: posterData.length });
        const posterBlob = new Blob([posterData.buffer], { type: "image/jpeg" });
        elDownloadPoster.href     = URL.createObjectURL(posterBlob);
        elDownloadPoster.download = baseName + "-poster.jpg";
        show(elResultPosterRow);
      } else {
        LOG.warn("Poster extraction failed or no data — skipped");
        hide(elResultPosterRow);
      }

      setProgress(100, "Done!", "");
      LOG.info("Conversion complete ✓ — switching to result view");

      hide(elViewConvert);
      show(elViewResult);
      finishConvert();
    })
    .catch((err) => {
      ff.off("progress", progressHandler);
      ff.off("log", logHandler);
      const msg = err && err.message ? err.message : String(err);
      LOG.error("Conversion failed", { message: msg });
      finishConvert();
      showError("<strong>Conversion failed:</strong> " + msg.replace(/\n/g, "<br>"));
    })
    .finally(() => {
      const noop = () => {};
      ff.deleteFile(inName).catch(noop);
      ff.deleteFile(outWebm).catch(noop);
      ff.deleteFile(outPoster).catch(noop);
    });
}

// ─── Finish / reset ───────────────────────────────────────────────────────────

function finishConvert() {
  isConverting = false;
  hide(elActiveNotice);
  elConvertBtn.disabled = !!selectedFile ? false : true;
  window.removeEventListener("beforeunload", warnBeforeUnload);
  LOG.info("finishConvert()");
}

function warnBeforeUnload(e) {
  e.preventDefault();
  e.returnValue = "Conversion in progress — sure you want to leave?";
}

function resetUI() {
  LOG.info("resetUI() — returning to convert view");
  selectedFile = null;
  elFileInput.value = "";
  hide(elFileInfo);
  show(elDropzone);
  elConvertBtn.disabled = true;
  hide(elProgressWrap);
  hide(elViewResult);
  show(elViewConvert);
  clearError();
  setProgress(0, "Encoding…", "");
}

elConvertAnother.addEventListener("click", resetUI);

// ─── Boot log ─────────────────────────────────────────────────────────────────

LOG.info("converter.js loaded", {
  crossOriginIsolated: self.crossOriginIsolated,
  userAgent: navigator.userAgent,
});
