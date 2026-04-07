import path from 'path';

import { expect, test } from '@playwright/test';

const TEST_VIDEO = path.resolve(__dirname, '../../../wp-content/uploads/2026/04/Agnes-Doctor-Video.mp4');

test('standalone server-side converter completes a real conversion', async ({ page }) => {
  test.setTimeout(180000);

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('h1')).toContainText('Optimize Videos for the Web', { timeout: 15000 });
  await expect(page.locator('#hero-subtitle')).toContainText('Convert your videos to our WebM optimized format');
  await expect(page.locator('#error-box')).toBeHidden();

  await page.setInputFiles('#file-input', TEST_VIDEO);
  await expect(page.locator('#file-name')).toContainText('Agnes-Doctor-Video.mp4');

  await page.locator('#convert-btn').click();
  await expect(page.locator('#progress-wrap')).toBeVisible();
  await expect(page.locator('#view-result')).toBeVisible({ timeout: 180000 });

  const webmHref = await page.locator('#download-webm').getAttribute('href');
  expect(webmHref).toContain('/api/jobs/');
  await expect(page.locator('#result-savings')).toContainText('WebM');

  const response = await page.request.get(new URL(webmHref || '', page.url()).toString());
  expect(response.ok()).toBe(true);
  expect(response.headers()['content-type']).toContain('video/webm');
});