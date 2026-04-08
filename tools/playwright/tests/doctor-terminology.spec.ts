import { expect, test, Page } from '@playwright/test';

async function loginAsAdmin(page: Page, baseURL?: string): Promise<void> {
  const alreadyLoggedIn = await page.locator('#wpadminbar').count();
  if (alreadyLoggedIn > 0) {
    return;
  }

  await page.goto(new URL('/wp-login.php', baseURL || 'http://localhost:8180').toString(), {
    waitUntil: 'domcontentloaded',
  });

  await page.locator('#user_login').fill(process.env.ECOMCINE_ADMIN_USER || 'admin');
  await page.locator('#user_pass').fill(process.env.ECOMCINE_ADMIN_PASS || 'admin');
  await page.locator('#wp-submit').click();
  await page.waitForLoadState('networkidle');
}

test.describe('Doctor terminology routing', () => {
  test('admin setting persists Doctor label', async ({ page, baseURL }) => {
    await loginAsAdmin(page, baseURL);

    await page.goto(new URL('/wp-admin/admin.php?page=ecomcine-settings&tab=settings', baseURL || 'http://localhost:8180').toString(), {
      waitUntil: 'domcontentloaded',
    });

    await expect(page.locator('#ecomcine-talent-label')).toHaveValue('Doctor');
  });

  test('Doctors listing and Doctor profile routes work', async ({ page, baseURL }) => {
    const listingResponse = await page.goto(new URL('/doctor/', baseURL || 'http://localhost:8180').toString(), {
      waitUntil: 'domcontentloaded',
    });

    expect(listingResponse, 'Expected HTTP response for /doctor/').not.toBeNull();
    expect(listingResponse?.ok(), `Listing page returned ${listingResponse?.status()}`).toBeTruthy();

    await expect(page.locator('#ecomcine-person-listing-filter-form')).toBeVisible();
    await expect(page.locator('#tm-pager-bar')).toBeVisible();
    await expect(page.getByRole('link', { name: /Doctors/i }).first()).toBeVisible();

    const profileLink = page.locator("a[aria-label^='Open profile:']").first();
    await expect(profileLink).toBeVisible();
    await profileLink.click();
    await expect(page).toHaveURL(/\/doctor\/[^/]+\/?$/);
  });

  test('Doctor terms page resolves', async ({ page, baseURL }) => {
    const response = await page.goto(new URL('/doctor-terms/', baseURL || 'http://localhost:8180').toString(), {
      waitUntil: 'domcontentloaded',
    });

    expect(response, 'Expected HTTP response for /doctor-terms/').not.toBeNull();
    expect(response?.ok(), `Doctor terms page returned ${response?.status()}`).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
  });

  test('Doctor categories and locations pages resolve', async ({ page, baseURL }) => {
    const categoriesResponse = await page.goto(new URL('/doctor-categories/', baseURL || 'http://localhost:8180').toString(), {
      waitUntil: 'domcontentloaded',
    });

    expect(categoriesResponse, 'Expected HTTP response for /doctor-categories/').not.toBeNull();
    expect(categoriesResponse?.ok(), `Doctor categories page returned ${categoriesResponse?.status()}`).toBeTruthy();
    await expect(page).toHaveURL(/\/doctor-categories\/?$/);

    const locationsResponse = await page.goto(new URL('/doctor-locations/', baseURL || 'http://localhost:8180').toString(), {
      waitUntil: 'domcontentloaded',
    });

    expect(locationsResponse, 'Expected HTTP response for /doctor-locations/').not.toBeNull();
    expect(locationsResponse?.ok(), `Doctor locations page returned ${locationsResponse?.status()}`).toBeTruthy();
    await expect(page).toHaveURL(/\/doctor-locations\/?$/);
  });
});