import { expect, test } from '@playwright/test';

test('profile bottom drawer tabs map to rendered sections', async ({ page, baseURL }) => {
  await page.goto(new URL('/', baseURL || 'http://localhost:8180').toString(), {
    waitUntil: 'domcontentloaded',
  });

  const tabs = page.locator('.bottom-tab-item[data-target]');
  await expect(tabs.first()).toBeVisible();

  const targets = await tabs.evaluateAll((nodes) =>
    nodes
      .map((node) => node.getAttribute('data-target') || '')
      .filter((value) => value.length > 0)
  );

  expect(targets.length).toBeGreaterThanOrEqual(2);

  for (const target of targets) {
    const tab = page.locator(`.bottom-tab-item[data-target="${target}"]`).first();
    const panel = page.locator(`#${target}`).first();

    await expect(panel, `Missing panel for ${target}`).toHaveCount(1);
    await tab.click();
    await expect(tab).toHaveClass(/active-panel/);
    await expect(panel).toHaveClass(/slide-up/);
  }

  await expect(page.locator('.bottom-tab-item[data-target="physical-section"]')).toHaveCount(0);
  await expect(page.locator('.bottom-tab-item[data-target="cameraman-section"]')).toHaveCount(0);
});
