/**
 * Playwright test: WebM Converter (standalone, http://localhost:9000)
 *
 * What it does:
 *  1. Opens the converter page
 *  2. Collects ALL browser console messages and JS errors
 *  3. Verifies crossOriginIsolated = true
 *  4. Uploads a real MP4 video
 *  5. Clicks Convert and waits up to 5 minutes for completion or failure
 *  6. Dumps every collected log line so server-side logs get full detail
 *
 * Run:  cd tools/playwright && npx playwright test tests/webm-converter.spec.ts --timeout=360000
 */

import { test, expect, Page } from "@playwright/test";
import path from "path";
import fs from "fs";

const CONVERTER_URL = "http://localhost:9000";
const TEST_VIDEO    = path.resolve(__dirname, "../../../demos/topdoctorchannel/media-backup-20260405-191724/eva/video1.mp4");
const TIMEOUT_MS    = 5 * 60 * 1000; // 5 minutes for encode

// ─── helpers ─────────────────────────────────────────────────────────────────

async function sendLog(page: Page, level: string, message: string, data?: unknown) {
  try {
    await page.request.post(`${CONVERTER_URL}/api/log`, {
      data: { level, message, data },
    });
  } catch { /* server may be gone */ }
}

// ─── test ─────────────────────────────────────────────────────────────────────

test("WebM Converter: full encode flow", async ({ page }) => {
  const consoleLogs: string[] = [];
  const jsErrors:    string[] = [];

  // Capture ALL console messages
  page.on("console", (msg) => {
    const line = `[console.${msg.type()}] ${msg.text()}`;
    consoleLogs.push(line);
    console.log("[BROWSER]", line);
  });

  // Capture unhandled JS errors
  page.on("pageerror", (err) => {
    const line = `[pageerror] ${err.message}\n${err.stack || ""}`;
    jsErrors.push(line);
    console.error("[BROWSER ERROR]", line);
  });

  // Capture network failures
  page.on("requestfailed", (req) => {
    console.error(`[NETWORK FAIL] ${req.method()} ${req.url()} — ${req.failure()?.errorText}`);
  });

  // ── 1. Open the page ──────────────────────────────────────────────────────

  await sendLog(page, "info", "Playwright: navigating to converter", { url: CONVERTER_URL });
  await page.goto(CONVERTER_URL, { waitUntil: "networkidle", timeout: 30_000 });
  await sendLog(page, "info", "Playwright: page loaded");

  const title = await page.title();
  await sendLog(page, "info", "Page title", { title });
  expect(title).toBe("WebM Converter");

  // ── 2. Verify crossOriginIsolated ─────────────────────────────────────────

  const coi = await page.evaluate(() => (self as any).crossOriginIsolated);
  await sendLog(page, "info", "crossOriginIsolated", { value: coi });
  console.log("crossOriginIsolated =", coi);
  expect(coi, "crossOriginIsolated must be true for MT wasm").toBe(true);

  // ── 3. Wait for boot log line to appear (converter.js loaded) ─────────────

  await page.waitForTimeout(500);

  // ── 4. Upload file ────────────────────────────────────────────────────────

  await sendLog(page, "info", "Uploading test video", { path: TEST_VIDEO });
  expect(fs.existsSync(TEST_VIDEO), "Test video must exist").toBe(true);

  const [fileChooser] = await Promise.all([
    page.waitForEvent("filechooser"),
    page.click("#dropzone"),
  ]);
  await fileChooser.setFiles(TEST_VIDEO);
  await sendLog(page, "info", "File set via file chooser");

  // Wait for file info to appear
  await expect(page.locator("#file-info")).toBeVisible({ timeout: 5_000 });
  const fileName = await page.locator("#file-name").textContent();
  const fileSize = await page.locator("#file-size").textContent();
  await sendLog(page, "info", "File accepted by UI", { fileName, fileSize });

  // ── 5. Click Convert ──────────────────────────────────────────────────────

  await sendLog(page, "info", "Clicking Convert button");
  await page.click("#convert-btn");

  // ── 6. Wait for FFmpeg loading banner to appear ───────────────────────────

  await expect(page.locator("#ffmpeg-loading")).toBeVisible({ timeout: 10_000 });
  await sendLog(page, "info", "FFmpeg loading banner visible");

  // ── 7. Poll for result OR error, up to TIMEOUT_MS ────────────────────────

  await sendLog(page, "info", "Waiting for result or error (up to 5 min)");

  const deadline = Date.now() + TIMEOUT_MS;
  let outcome: "success" | "error" | "timeout" = "timeout";

  while (Date.now() < deadline) {
    // Check for result view
    const resultVisible = await page.locator("#view-result").isVisible();
    if (resultVisible) {
      outcome = "success";
      break;
    }

    // Check for error box
    const errorVisible = await page.locator("#error-box").isVisible();
    if (errorVisible) {
      const errorText = await page.locator("#error-box").innerText();
      await sendLog(page, "error", "Error box appeared", { errorText });
      outcome = "error";
      break;
    }

    // Log progress every 10s
    const stage = await page.locator("#progress-stage").textContent();
    const pct   = await page.locator("#progress-pct").textContent();
    const ffStatus = await page.locator("#ffmpeg-status").textContent();
    await sendLog(page, "info", "Still running…", { stage, pct, ffStatus });

    await page.waitForTimeout(10_000);
  }

  // ── 8. Dump all console logs to server log ────────────────────────────────

  await sendLog(page, "info", "Playwright: final outcome", { outcome });
  await sendLog(page, "info", "All browser console logs", { lines: consoleLogs });
  if (jsErrors.length) {
    await sendLog(page, "error", "Browser JS errors", { errors: jsErrors });
  }

  // ── 9. Screenshot ─────────────────────────────────────────────────────────

  await page.screenshot({
    path: path.join(__dirname, "../test-results/webm-converter-final.png"),
    fullPage: true,
  });
  await sendLog(page, "info", "Screenshot saved to test-results/webm-converter-final.png");

  // ── 10. Assertions ────────────────────────────────────────────────────────

  if (outcome === "timeout") {
    // Grab final UI state
    const stage    = await page.locator("#progress-stage").textContent();
    const pct      = await page.locator("#progress-pct").textContent();
    const ffStatus = await page.locator("#ffmpeg-status").textContent();
    await sendLog(page, "error", "TIMED OUT", { stage, pct, ffStatus });
    expect(outcome, `Timed out at stage="${stage}" pct="${pct}" ffStatus="${ffStatus}"`).toBe("success");
  } else if (outcome === "error") {
    const errorText = await page.locator("#error-box").innerText();
    expect(outcome, `Conversion failed: ${errorText}`).toBe("success");
  }

  // Success
  const savings = await page.locator("#result-savings").textContent();
  await sendLog(page, "info", "Conversion succeeded", { savings });
  expect(page.locator("#download-webm")).toBeVisible();
});
