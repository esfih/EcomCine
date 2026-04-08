import { expect, Page, test } from '@playwright/test';

const BASE_PATH = '/showcase/';

type ViewportCase = {
	name: string;
	viewport: { width: number; height: number };
	expectedColumns: number;
	maxWidthRatio: number;
};

const viewportCases: ViewportCase[] = [
	{
		name: 'phone portrait',
		viewport: { width: 393, height: 852 },
		expectedColumns: 1,
		maxWidthRatio: 0.93,
	},
	{
		name: 'tablet portrait',
		viewport: { width: 820, height: 1180 },
		expectedColumns: 1,
		maxWidthRatio: 0.93,
	},
	{
		name: 'tablet landscape',
		viewport: { width: 1180, height: 820 },
		expectedColumns: 2,
		maxWidthRatio: 0.93,
	},
	{
		name: 'laptop hidpi',
		viewport: { width: 1440, height: 900 },
		expectedColumns: 2,
		maxWidthRatio: 0.79,
	},
	{
		name: 'desktop regular',
		viewport: { width: 1920, height: 1080 },
		expectedColumns: 2,
		maxWidthRatio: 0.71,
	},
	{
		name: 'smart tv',
		viewport: { width: 3840, height: 2160 },
		expectedColumns: 2,
		maxWidthRatio: 0.59,
	},
];

async function gotoShowcase(page: Page, baseURL?: string): Promise<void> {
	await page.goto(new URL(BASE_PATH, baseURL || 'http://localhost:8180').toString(), {
		waitUntil: 'domcontentloaded',
	});
	await page.waitForLoadState('networkidle').catch(() => {});
	await page.locator('.profile-frame').first().waitFor({ state: 'visible', timeout: 15000 });
	await page.locator('.tm-account-tab:not(.tm-account-tab--admin)').first().waitFor({ state: 'visible', timeout: 15000 });
}

async function maybeStartPlayback(page: Page): Promise<void> {
	const overlay = page.locator('.tm-showcase-play-overlay').first();
	if (await overlay.count()) {
		await overlay.click({ timeout: 10000 }).catch(() => {});
	}
	await page.locator('.hero-next').first().click({ timeout: 10000 });
	await expect(page.locator('.tm-showcase-resume-control.is-visible').first()).toBeVisible({ timeout: 10000 });
}

async function openAccountModal(page: Page): Promise<void> {
	const trigger = page.locator('.tm-account-tab:not(.tm-account-tab--admin)').first();
	await trigger.focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('.tm-account-modal.is-open').first()).toBeVisible({ timeout: 10000 });
	await expect(page.locator('.tm-account-modal.is-open .tm-account-dialog').first()).toBeVisible({ timeout: 10000 });
}

function gridColumnCount(templateColumns: string): number {
	return templateColumns.split(' ').filter(Boolean).length;
}

test.describe('UI port parity', () => {
	test('keeps the resume control above navigation and level label in its own pill', async ({ page, baseURL }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await gotoShowcase(page, baseURL);
		await maybeStartPlayback(page);

		const resume = page.locator('.tm-showcase-resume-control').first();
		const keyboardNav = page.locator('.keyboard-nav-container').first();
		const resumeOwnership = await resume.evaluate((element) => ({
			inSlot: Boolean(element.closest('.tm-showcase-resume-slot')),
			inKeyboardNav: Boolean(element.closest('.keyboard-nav-container')),
		}));

		expect(resumeOwnership.inSlot).toBeTruthy();
		expect(resumeOwnership.inKeyboardNav).toBeFalsy();

		const resumeBox = await resume.boundingBox();
		const keyboardBox = await keyboardNav.boundingBox();
		expect(resumeBox).not.toBeNull();
		expect(keyboardBox).not.toBeNull();
		expect((resumeBox?.y || 0) + (resumeBox?.height || 0)).toBeLessThanOrEqual((keyboardBox?.y || 0) - 1);

		const categoriesGroup = page.locator('.store-categories-display-group').first();
		const categoriesPill = categoriesGroup.locator('.store-categories-display').first();
		const levelPill = categoriesGroup.locator('.tm-combo-pill__level-pill').first();
		await expect(categoriesPill).toBeVisible({ timeout: 10000 });
		await expect(levelPill).toBeVisible({ timeout: 10000 });

		const levelOwnership = await levelPill.evaluate((element) => ({
			insideCategoriesPill: Boolean(element.closest('.store-categories-display')),
		}));
		expect(levelOwnership.insideCategoriesPill).toBeFalsy();

		const categoriesBox = await categoriesPill.boundingBox();
		const levelBox = await levelPill.boundingBox();
		expect(categoriesBox).not.toBeNull();
		expect(levelBox).not.toBeNull();
		expect(levelBox?.y || 0).toBeGreaterThanOrEqual((categoriesBox?.y || 0) + (categoriesBox?.height || 0));
	});

	for (const viewportCase of viewportCases) {
		test(`sizes the account modal for ${viewportCase.name}`, async ({ page, baseURL }) => {
			await page.setViewportSize(viewportCase.viewport);
			await gotoShowcase(page, baseURL);
			await openAccountModal(page);

			const dialog = page.locator('.tm-account-modal.is-open .tm-account-dialog').first();
			const dialogBox = await dialog.boundingBox();
			expect(dialogBox).not.toBeNull();
			expect(dialogBox?.width || 0).toBeLessThanOrEqual(viewportCase.viewport.width * viewportCase.maxWidthRatio);

			const loginGridColumns = await page.locator('.tm-account-login form.tm-account-login-grid').first().evaluate((element) => {
				return window.getComputedStyle(element).gridTemplateColumns;
			});
			expect(gridColumnCount(loginGridColumns)).toBe(viewportCase.expectedColumns);

			await page.locator('#tm-account-modal [data-tab="register"]').click({ timeout: 10000 });
			const registerGridColumns = await page.locator('.tm-account-register form.tm-account-register-grid').first().evaluate((element) => {
				return window.getComputedStyle(element).gridTemplateColumns;
			});
			expect(gridColumnCount(registerGridColumns)).toBe(viewportCase.expectedColumns);
		});
	}
});