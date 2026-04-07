import { expect, test } from '@playwright/test';

test.describe('EcomCine smoke checks', () => {
  test('@smoke homepage renders without hard JS failures', async ({ page, baseURL }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      pageErrors.push(String(err));
    });

    const response = await page.goto(baseURL || '/', { waitUntil: 'domcontentloaded' });
    expect(response, 'Expected an HTTP response when loading homepage').not.toBeNull();
    expect(response?.ok(), `Homepage request failed with status ${response?.status()}`).toBeTruthy();

    await page.waitForLoadState('networkidle');

    const hasSyntaxError = [...consoleErrors, ...pageErrors].some((line) =>
      /syntaxerror|unexpected token|missing \)|unterminated/i.test(line)
    );

    expect(hasSyntaxError, `Detected probable JS syntax error.\nConsole: ${consoleErrors.join('\n')}\nPageErrors: ${pageErrors.join('\n')}`).toBeFalsy();

    await expect(page.locator('body')).toBeVisible();
  });
});
