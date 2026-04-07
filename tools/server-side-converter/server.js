const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { spawn, spawnSync } = require('child_process');
const { URL } = require('url');

const PORT = Number.parseInt(process.env.PORT || process.argv[2] || '9191', 10);
const ROOT = __dirname;
const LOGS_DIR = path.join(ROOT, 'logs');
const TMP_DIR = path.join(ROOT, 'tmp');
const BIN_DIR = path.join(ROOT, 'bin');
const PID_FILE = path.join(LOGS_DIR, 'server.pid');
const JOB_RETENTION_MS = 24 * 60 * 60 * 1000;
const VIDEO_THREADS = String(Math.min(4, Math.max(1, os.cpus().length || 1)));
const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.webm': 'video/webm',
  '.webp': 'image/webp',
  '.txt': 'text/plain; charset=utf-8',
};
const PRESETS = {
  hq: { label: 'HQ Archive', crf: '24' },
  balanced: { label: 'Balanced', crf: '32' },
  compressed: { label: 'Compressed', crf: '40' },
};
const HOST = process.env.HOST || '0.0.0.0';
const SETTINGS = {
  keyframeInterval: '48',
  posterOffset: '0.35',
  posterWidth: '1280',
  audioBitrate: '96k',
  audioVbrMode: 'constrained',
};

for (const dir of [LOGS_DIR, TMP_DIR]) {
  fs.mkdirSync(dir, { recursive: true });
}

const jobs = new Map();

function archVariants(arch) {
  const variants = new Set([arch]);

  if (arch === 'x64') {
    variants.add('x86_64');
  } else if (arch === 'x86_64') {
    variants.add('x64');
  } else if (arch === 'arm64') {
    variants.add('aarch64');
  } else if (arch === 'aarch64') {
    variants.add('arm64');
  }

  return Array.from(variants);
}

function detectLocalBinary(baseName) {
  const isWindows = process.platform === 'win32';
  const fileName = isWindows ? `${baseName}.exe` : baseName;
  const candidatePaths = [];

  for (const arch of archVariants(process.arch)) {
    candidatePaths.push(path.join(BIN_DIR, `${process.platform}-${arch}`, fileName));
  }

  candidatePaths.push(path.join(BIN_DIR, fileName));

  for (const candidate of candidatePaths) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  return '';
}

function resolveBinary(baseName, envKey) {
  const explicit = process.env[envKey] || '';
  if (explicit) {
    return explicit;
  }

  const bundled = detectLocalBinary(baseName);
  if (bundled) {
    return bundled;
  }

  return baseName;
}

const FFMPEG_BIN = resolveBinary('ffmpeg', 'FFMPEG_BIN');
const FFPROBE_BIN = resolveBinary('ffprobe', 'FFPROBE_BIN');

function commandExists(command, versionArgs) {
  const result = spawnSync(command, versionArgs, { stdio: 'ignore' });
  return result.status === 0;
}

const ffmpegAvailable = commandExists(FFMPEG_BIN, ['-version']);
const ffprobeAvailable = commandExists(FFPROBE_BIN, ['-version']);

function logLine(message) {
  const line = `[${new Date().toISOString()}] ${message}\n`;
  fs.appendFileSync(path.join(LOGS_DIR, 'server.log'), line);
  process.stdout.write(line);
}

function cleanupExpiredJobs() {
  const cutoff = Date.now() - JOB_RETENTION_MS;

  for (const [jobId, job] of jobs.entries()) {
    if (job.updatedAt < cutoff) {
      fs.rmSync(job.dir, { recursive: true, force: true });
      jobs.delete(jobId);
    }
  }
}

setInterval(cleanupExpiredJobs, 15 * 60 * 1000).unref();

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(payload));
}

function sendFile(res, filePath, downloadName) {
  fs.stat(filePath, (error, stat) => {
    if (error || !stat.isFile()) {
      sendJson(res, 404, { error: 'File not found.' });
      return;
    }

    const extension = path.extname(filePath).toLowerCase();
    res.writeHead(200, {
      'Content-Type': MIME[extension] || 'application/octet-stream',
      'Content-Length': stat.size,
      'Content-Disposition': `attachment; filename="${downloadName}"`,
      'Cache-Control': 'no-store',
    });

    fs.createReadStream(filePath).pipe(res);
  });
}

function sanitizeFileName(fileName) {
  return decodeURIComponent(String(fileName || 'upload.mp4'))
    .replace(/[\\/]/g, '_')
    .replace(/[^a-zA-Z0-9._-]/g, '_');
}

function baseNameFromFile(fileName) {
  const ext = path.extname(fileName);
  return path.basename(fileName, ext).replace(/[^a-zA-Z0-9._-]/g, '_') || 'converted-video';
}

function parseDurationSeconds(text) {
  const match = /time=(\d+):(\d+):(\d+(?:\.\d+)?)/.exec(text);
  if (!match) {
    return null;
  }

  return (Number(match[1]) * 3600) + (Number(match[2]) * 60) + Number(match[3]);
}

function buildJobDownload(job, key) {
  const file = job.outputs[key];
  if (!file || !file.exists) {
    return null;
  }

  return {
    url: `/api/jobs/${job.id}/download/${key}`,
    fileName: file.fileName,
    sizeBytes: file.sizeBytes,
  };
}

function publicJob(job) {
  return {
    id: job.id,
    status: job.status,
    stage: job.stage,
    detail: job.detail,
    progressPercent: job.progressPercent,
    error: job.error,
    sourceSizeBytes: job.sourceSizeBytes,
    downloads: {
      webm: buildJobDownload(job, 'webm'),
      poster: buildJobDownload(job, 'poster'),
    },
  };
}

function updateJob(job, patch) {
  Object.assign(job, patch, { updatedAt: Date.now() });
}

function runProcess(command, args, onData) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { stdio: ['ignore', 'ignore', 'pipe'] });
    let stderr = '';

    child.stderr.on('data', (chunk) => {
      const text = chunk.toString();
      stderr += text;
      if (onData) {
        onData(text);
      }
    });

    child.on('error', reject);
    child.on('close', (code) => {
      if (code === 0) {
        resolve(stderr);
        return;
      }

      reject(new Error(stderr.trim() || `${command} exited with code ${code}`));
    });
  });
}

async function probeDuration(inputPath) {
  if (!ffprobeAvailable) {
    return null;
  }

  return new Promise((resolve) => {
    const child = spawn(FFPROBE_BIN, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', inputPath], { stdio: ['ignore', 'pipe', 'ignore'] });
    let stdout = '';

    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
    });

    child.on('close', (code) => {
      if (code !== 0) {
        resolve(null);
        return;
      }

      const value = Number.parseFloat(stdout.trim());
      resolve(Number.isFinite(value) ? value : null);
    });
  });
}

async function startConversion(job) {
  try {
    updateJob(job, { status: 'processing', stage: 'Probing source…', detail: 'Reading source duration and preparing ffmpeg.', progressPercent: 28 });
    const durationSeconds = await probeDuration(job.inputPath);
    job.durationSeconds = durationSeconds;

    updateJob(job, { stage: 'Encoding WebM…', detail: `${job.preset.label} preset with 48-frame GOP and constrained Opus audio.`, progressPercent: 32 });

    const ffmpegArgs = [
      '-y',
      '-nostdin',
      '-i', job.inputPath,
      '-c:v', 'libvpx-vp9',
      '-b:v', '0',
      '-crf', job.preset.crf,
      '-deadline', 'good',
      '-cpu-used', '3',
      '-row-mt', '1',
      '-tile-columns', '1',
      '-frame-parallel', '1',
      '-lag-in-frames', '16',
      '-g', SETTINGS.keyframeInterval,
      '-keyint_min', SETTINGS.keyframeInterval,
      '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2,format=yuv420p',
      '-pix_fmt', 'yuv420p',
      '-map_metadata', '-1',
      '-map_chapters', '-1',
      '-c:a', 'libopus',
      '-b:a', SETTINGS.audioBitrate,
      '-vbr', SETTINGS.audioVbrMode,
      '-compression_level', '10',
      '-threads', VIDEO_THREADS,
      job.outputPath,
    ];

    await runProcess(FFMPEG_BIN, ffmpegArgs, (stderrChunk) => {
      if (!durationSeconds) {
        return;
      }

      const currentSeconds = parseDurationSeconds(stderrChunk);
      if (currentSeconds === null) {
        return;
      }

      const encodePercent = Math.max(32, Math.min(92, Math.round((currentSeconds / durationSeconds) * 60) + 32));
      updateJob(job, {
        progressPercent: encodePercent,
        stage: 'Encoding WebM…',
        detail: `ffmpeg progress: ${Math.round((currentSeconds / durationSeconds) * 100)}% of source duration processed.`,
      });
    });

    const webmStat = fs.statSync(job.outputPath);
    job.outputs.webm = {
      exists: true,
      path: job.outputPath,
      fileName: `${job.outputBaseName}.webm`,
      sizeBytes: webmStat.size,
    };

    updateJob(job, { stage: 'Extracting poster…', detail: 'Generating WebP poster frame.', progressPercent: 94 });

    const posterArgs = [
      '-y',
      '-nostdin',
      '-ss', SETTINGS.posterOffset,
      '-i', job.inputPath,
      '-frames:v', '1',
      '-vf', `scale='min(${SETTINGS.posterWidth},iw)':-2`,
      '-c:v', 'libwebp',
      '-quality', '80',
      '-compression_level', '6',
      '-preset', 'photo',
      '-an',
      '-map_metadata', '-1',
      job.posterPath,
    ];

    try {
      await runProcess(FFMPEG_BIN, posterArgs);
      const posterStat = fs.statSync(job.posterPath);
      job.outputs.poster = {
        exists: true,
        path: job.posterPath,
        fileName: `${job.outputBaseName}.poster.webp`,
        sizeBytes: posterStat.size,
      };
    } catch (posterError) {
      logLine(`Poster generation failed for ${job.id}: ${posterError.message}`);
    }

    updateJob(job, { status: 'complete', stage: 'Complete', detail: 'WebM and poster are ready.', progressPercent: 100 });
    logLine(`Completed job ${job.id} (${job.sourceFileName})`);
  } catch (error) {
    updateJob(job, {
      status: 'failed',
      stage: 'Failed',
      detail: 'The native ffmpeg process did not finish successfully.',
      error: error.message,
    });
    logLine(`Job ${job.id} failed: ${error.message}`);
  }
}

function createJob(fileName) {
  const id = crypto.randomUUID();
  const sourceFileName = sanitizeFileName(fileName);
  const outputBaseName = baseNameFromFile(sourceFileName);
  const extension = path.extname(sourceFileName) || '.mp4';
  const dir = path.join(TMP_DIR, id);
  fs.mkdirSync(dir, { recursive: true });

  return {
    id,
    dir,
    sourceFileName,
    sourceSizeBytes: 0,
    outputBaseName,
    inputPath: path.join(dir, `input${extension}`),
    outputPath: path.join(dir, `${outputBaseName}.webm`),
    posterPath: path.join(dir, `${outputBaseName}.poster.webp`),
    status: 'uploading',
    stage: 'Uploading source…',
    detail: 'Receiving file data from browser.',
    progressPercent: 0,
    error: '',
    createdAt: Date.now(),
    updatedAt: Date.now(),
    outputs: {
      webm: null,
      poster: null,
    },
    preset: PRESETS.balanced,
    durationSeconds: null,
  };
}

function handleCreateJob(req, res, requestUrl) {
  if (!ffmpegAvailable) {
    sendJson(res, 503, { error: 'ffmpeg is not available to this standalone converter.' });
    return;
  }

  const presetKey = requestUrl.searchParams.get('preset') || 'balanced';
  const preset = PRESETS[presetKey] || PRESETS.balanced;
  const rawFileName = req.headers['x-file-name'] || 'upload.mp4';
  const job = createJob(rawFileName);
  job.preset = preset;
  jobs.set(job.id, job);

  const writeStream = fs.createWriteStream(job.inputPath);
  let uploadBytes = 0;

  req.on('data', (chunk) => {
    uploadBytes += chunk.length;
  });

  req.pipe(writeStream);

  req.on('aborted', () => {
    writeStream.destroy();
    updateJob(job, { status: 'failed', stage: 'Failed', error: 'Upload aborted.' });
  });

  writeStream.on('error', (error) => {
    updateJob(job, { status: 'failed', stage: 'Failed', error: error.message });
    sendJson(res, 500, { error: 'Could not write uploaded file.' });
  });

  writeStream.on('finish', () => {
    job.sourceSizeBytes = uploadBytes;
    updateJob(job, { status: 'queued', stage: 'Queued…', detail: `Upload complete. Starting ${preset.label} conversion.`, progressPercent: 25 });
    sendJson(res, 202, { jobId: job.id });
    startConversion(job);
  });
}

function serveStaticFile(res, requestPath) {
  let filePath = path.join(ROOT, requestPath === '/' ? 'index.html' : requestPath);
  if (!filePath.startsWith(ROOT)) {
    sendJson(res, 403, { error: 'Forbidden' });
    return;
  }

  fs.stat(filePath, (error, stat) => {
    if (error || !stat.isFile()) {
      sendJson(res, 404, { error: 'Not found' });
      return;
    }

    const extension = path.extname(filePath).toLowerCase();
    res.writeHead(200, {
      'Content-Type': MIME[extension] || 'application/octet-stream',
      'Cache-Control': extension === '.html' ? 'no-store' : 'no-cache',
    });
    fs.createReadStream(filePath).pipe(res);
  });
}

const server = http.createServer((req, res) => {
  const requestUrl = new URL(req.url, `http://127.0.0.1:${PORT}`);
  const pathname = requestUrl.pathname;

  if (req.method === 'GET' && pathname === '/api/config') {
    sendJson(res, 200, {
      ffmpegAvailable,
      ffprobeAvailable,
      ffmpegPath: FFMPEG_BIN,
      ffprobePath: FFPROBE_BIN,
      bundledBinaryDir: path.join(BIN_DIR, `${process.platform}-${process.arch}`),
      runtimeMode: ['127.0.0.1', 'localhost'].includes(req.headers.host ? req.headers.host.split(':')[0] : '') ? 'local' : 'hosted',
      presets: PRESETS,
      settings: SETTINGS,
    });
    return;
  }

  if (req.method === 'POST' && pathname === '/api/jobs') {
    handleCreateJob(req, res, requestUrl);
    return;
  }

  if (req.method === 'GET' && pathname.startsWith('/api/jobs/')) {
    const parts = pathname.split('/').filter(Boolean);
    const job = jobs.get(parts[2]);
    if (!job) {
      sendJson(res, 404, { error: 'Job not found.' });
      return;
    }

    if (parts.length === 3) {
      sendJson(res, 200, publicJob(job));
      return;
    }

    if (parts.length === 5 && parts[3] === 'download') {
      const kind = parts[4];
      const file = job.outputs[kind];
      if (!file || !file.exists) {
        sendJson(res, 404, { error: 'Requested output does not exist.' });
        return;
      }
      sendFile(res, file.path, file.fileName);
      return;
    }
  }

  serveStaticFile(res, pathname);
});

server.listen(PORT, HOST, () => {
  fs.writeFileSync(PID_FILE, String(process.pid));
  logLine(`Standalone server-side converter listening on http://${HOST}:${PORT}`);
  logLine(`ffmpeg available: ${ffmpegAvailable} (${FFMPEG_BIN})`);
  logLine(`ffprobe available: ${ffprobeAvailable} (${FFPROBE_BIN})`);
});

server.on('close', () => {
  fs.rmSync(PID_FILE, { force: true });
});

process.on('SIGINT', () => {
  server.close(() => process.exit(0));
});

process.on('SIGTERM', () => {
  server.close(() => process.exit(0));
});