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

test('category edit screen manages custom fields via list, edit, and create tabs', async ({ page, baseURL }) => {
  await loginAsAdmin(page, baseURL);

  await page.goto(
    new URL('/wp-admin/admin.php?page=ecomcine-settings&tab=categories&view=edit&edit_cat=1', baseURL || 'http://localhost:8180').toString(),
    { waitUntil: 'domcontentloaded' }
  );

  await expect(page.getByRole('heading', { name: /Edit Category:/i })).toBeVisible();
  await expect(page.getByRole('heading', { name: /Custom Fields:/i })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Custom Fields' })).toHaveClass(/nav-tab-active/);
  await expect(page.getByRole('link', { name: 'Create New Field' })).toBeVisible();
  await expect(page.locator('code', { hasText: 'actingexperience' })).toBeVisible();

  await page.getByRole('link', { name: /^Edit$/ }).last().click();
  await expect(page.getByRole('link', { name: 'Edit Custom Field' })).toHaveClass(/nav-tab-active/);
  await expect(page.getByRole('link', { name: 'Back to Custom Fields' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Edit Field' })).toBeVisible();
  await expect(page.locator('#field_key')).toHaveValue('actingexperience');
  await expect(page.getByRole('button', { name: 'Select From Media Library' }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Remove Image' }).first()).toBeVisible();

  await page.getByRole('link', { name: 'Create New Field' }).click();
  await expect(page.getByRole('link', { name: 'Create New Field' })).toHaveClass(/nav-tab-active/);
  await expect(page.getByRole('link', { name: 'Back to Custom Fields' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Create New Field' })).toBeVisible();
  await expect(page.locator('#field_key')).toHaveValue('');
  await expect(page.getByRole('button', { name: 'Select From Media Library' }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Remove Image' }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Add Field' })).toBeVisible();
});