import { expect, test } from '@playwright/test';

test('demo data import ajax returns a response body', async ({ page, baseURL }) => {
  const root = baseURL || 'http://localhost:8180';

  await page.goto(new URL('/wp-login.php', root).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(process.env.ECOMCINE_ADMIN_USER || 'admin');
  await page.locator('#user_pass').fill(process.env.ECOMCINE_ADMIN_PASS || 'admin');
  await page.locator('#wp-submit').click();
  await page.waitForLoadState('networkidle');

  await page.goto(new URL('/wp-admin/admin.php?page=ecomcine-demo-data', root).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('.ecomcine-import-remote-btn').waitFor({ state: 'visible', timeout: 10000 });

  const responsePromise = page.waitForResponse(
    (response) => response.url().includes('/wp-admin/admin-ajax.php') && response.request().method() === 'POST',
    { timeout: 300000 }
  );

  await page.locator('.ecomcine-import-remote-btn').click();

  const response = await responsePromise;
  const body = await response.text();
  console.log(JSON.stringify({
    status: response.status(),
    body,
  }));

  expect(response.status()).toBe(200);
  expect(body.length).toBeGreaterThan(0);
});
