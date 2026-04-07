import fs from 'fs';
import path from 'path';

import { expect, test } from '@playwright/test';

type IterationResult = {
  loadMetadataMs: number;
  loadDataMs: number;
  canPlayMs: number;
  playToPlayingMs: number;
  seekResults: Array<{
    target: number;
    seekMs: number;
    actualTime: number;
    readyState: number;
  }>;
};

type VariantSummary = {
  name: string;
  filePath: string;
  sizeBytes: number;
  iterations: IterationResult[];
  medians: {
    loadMetadataMs: number;
    loadDataMs: number;
    canPlayMs: number;
    playToPlayingMs: number;
    seekMsByTarget: Record<string, number>;
  };
};

type BenchmarkVariant = {
  name: string;
  filePath: string;
};

const ITERATIONS = 5;
const SEEK_TARGETS = [1.0, 7.2, 9.2];
const CPU_THROTTLE_RATE = 4;
const DEFAULT_VARIANTS: BenchmarkVariant[] = [
  {
    name: 'original',
    filePath: path.resolve(__dirname, '../../../wp-content/uploads/2026/04-webm/Agnes-Doctor-Video.webm'),
  },
  {
    name: 'optimized',
    filePath: path.resolve(__dirname, '../../../wp-content/uploads/2026/04-webm-optimized/Agnes-Doctor-Video.webm'),
  },
];

function getBenchmarkVariants(): BenchmarkVariant[] {
  const rawVariants = process.env.WEBM_BENCHMARK_VARIANTS;

  if (!rawVariants) {
    return DEFAULT_VARIANTS;
  }

  const parsed = JSON.parse(rawVariants) as BenchmarkVariant[];

  if (!Array.isArray(parsed) || parsed.length === 0) {
    throw new Error('WEBM_BENCHMARK_VARIANTS must be a non-empty JSON array.');
  }

  return parsed.map((variant) => ({
    name: variant.name,
    filePath: path.resolve(variant.filePath),
  }));
}

function median(values: number[]): number {
  const sorted = [...values].sort((left, right) => left - right);
  const midpoint = Math.floor(sorted.length / 2);

  if (sorted.length % 2 === 0) {
    return Number(((sorted[midpoint - 1] + sorted[midpoint]) / 2).toFixed(2));
  }

  return Number(sorted[midpoint].toFixed(2));
}

async function collectVariantSummary(
  page: Parameters<typeof test>[0]['page'],
  variant: { name: string; filePath: string },
): Promise<VariantSummary> {
  const buffer = fs.readFileSync(variant.filePath);
  const bytes = [...buffer];

  const iterations = await page.evaluate(
    async ({ binary, seekTargets, iterationCount }) => {
      const waitForEvent = (target: EventTarget, eventName: string, timeoutMs = 15000): Promise<number> =>
        new Promise((resolve, reject) => {
          const startedAt = performance.now();
          const timeoutId = window.setTimeout(() => {
            reject(new Error(`Timed out waiting for ${eventName}`));
          }, timeoutMs);

          const onEvent = () => {
            window.clearTimeout(timeoutId);
            resolve(performance.now() - startedAt);
          };

          target.addEventListener(eventName, onEvent, { once: true });
        });

      const cleanupVideo = (video: HTMLVideoElement) => {
        video.pause();
        video.removeAttribute('src');
        video.load();
      };

      const results: IterationResult[] = [];
      const container = document.createElement('div');
      document.body.innerHTML = '';
      document.body.appendChild(container);

      for (let iterationIndex = 0; iterationIndex < iterationCount; iterationIndex += 1) {
        const blob = new Blob([new Uint8Array(binary)], { type: 'video/webm' });
        const blobUrl = URL.createObjectURL(blob);
        const video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        container.innerHTML = '';
        container.appendChild(video);

        video.src = blobUrl;
        const metadataPromise = waitForEvent(video, 'loadedmetadata');
        const loadedDataPromise = waitForEvent(video, 'loadeddata');
        const canPlayPromise = waitForEvent(video, 'canplay');
        video.load();

        const loadMetadataMs = await metadataPromise;
        const loadDataMs = await loadedDataPromise;
        const canPlayMs = await canPlayPromise;

        const playPromise = waitForEvent(video, 'playing');
        const playCall = video.play();
        if (playCall && typeof playCall.then === 'function') {
          await playCall;
        }
        const playToPlayingMs = await playPromise;

        const seekResults: IterationResult['seekResults'] = [];

        for (const requestedTarget of seekTargets) {
          const boundedTarget = Math.min(Math.max(requestedTarget, 0.1), Math.max(video.duration - 0.25, 0.1));
          const seekedPromise = waitForEvent(video, 'seeked');
          video.currentTime = boundedTarget;
          const seekMs = await seekedPromise;

          if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
            await waitForEvent(video, 'canplay');
          }

          seekResults.push({
            target: Number(boundedTarget.toFixed(2)),
            seekMs: Number(seekMs.toFixed(2)),
            actualTime: Number(video.currentTime.toFixed(3)),
            readyState: video.readyState,
          });
        }

        results.push({
          loadMetadataMs: Number(loadMetadataMs.toFixed(2)),
          loadDataMs: Number(loadDataMs.toFixed(2)),
          canPlayMs: Number(canPlayMs.toFixed(2)),
          playToPlayingMs: Number(playToPlayingMs.toFixed(2)),
          seekResults,
        });

        cleanupVideo(video);
        URL.revokeObjectURL(blobUrl);
      }

      return results;
    },
    {
      binary: bytes,
      seekTargets: SEEK_TARGETS,
      iterationCount: ITERATIONS,
    },
  );

  const medians = {
    loadMetadataMs: median(iterations.map((entry) => entry.loadMetadataMs)),
    loadDataMs: median(iterations.map((entry) => entry.loadDataMs)),
    canPlayMs: median(iterations.map((entry) => entry.canPlayMs)),
    playToPlayingMs: median(iterations.map((entry) => entry.playToPlayingMs)),
    seekMsByTarget: Object.fromEntries(
      SEEK_TARGETS.map((target) => {
        const key = target.toFixed(1);
        const samples = iterations.map((entry) => {
          const seek = entry.seekResults.find((result) => result.target.toFixed(1) === key);
          return seek ? seek.seekMs : Number.NaN;
        }).filter((value) => Number.isFinite(value));

        return [key, median(samples)];
      }),
    ),
  };

  return {
    name: variant.name,
    filePath: variant.filePath,
    sizeBytes: buffer.length,
    iterations,
    medians,
  };
}

test('WebM benchmark: compare original and optimized player timings', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'CPU throttling benchmark only applies to Chromium.');

  const variants = getBenchmarkVariants();

  const client = await page.context().newCDPSession(page);
  await client.send('Emulation.setCPUThrottlingRate', { rate: CPU_THROTTLE_RATE });

  const summaries: VariantSummary[] = [];

  for (const variant of variants) {
    summaries.push(await collectVariantSummary(page, variant));
  }

  console.log('[webm-benchmark] summary', JSON.stringify({
    cpuThrottleRate: CPU_THROTTLE_RATE,
    seekTargets: SEEK_TARGETS,
    summaries,
  }, null, 2));

  for (const summary of summaries) {
    expect(summary.iterations).toHaveLength(ITERATIONS);
    expect(summary.medians.loadDataMs).toBeGreaterThan(0);
    expect(summary.medians.playToPlayingMs).toBeGreaterThan(0);
  }
});