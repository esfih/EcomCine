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
	test('keeps the handheld showcase menu collapsed behind a toggle and avoids horizontal overflow', async ({ page, baseURL }) => {
		await page.addInitScript(() => {
			window.localStorage.setItem('tm_profile_collapsed', 'false');
		});
		await page.setViewportSize({ width: 412, height: 915 });
		await gotoShowcase(page, baseURL);
		await page.waitForFunction(() => document.body.classList.contains('tm-mobile-portrait'), undefined, { timeout: 15000 });

		const initialState = await page.evaluate(() => {
			const root = document.documentElement;
			const body = document.body;
			const panel = document.querySelector('.profile-info-head');
			const toggle = document.querySelector('.tm-header-toggle');
			const header = document.querySelector('.tm-cinematic-header');
			const maxScrollWidth = Math.max(
				root ? root.scrollWidth : 0,
				body ? body.scrollWidth : 0,
				root ? root.clientWidth : 0,
				body ? body.clientWidth : 0,
			);

			return {
				hasToggle: Boolean(toggle),
				panelCollapsed: panel ? panel.classList.contains('is-collapsed') : false,
				menuOpen: header ? header.classList.contains('is-menu-open') : false,
				maxScrollWidth,
				viewportWidth: window.innerWidth,
				viewportHeight: window.innerHeight,
			};
		});

		expect(initialState.hasToggle).toBeTruthy();
		expect(initialState.panelCollapsed).toBeTruthy();
		expect(initialState.menuOpen).toBeFalsy();
		expect(initialState.maxScrollWidth).toBeLessThanOrEqual(initialState.viewportWidth + 1);

		const compactTab = page.locator('.profile-bottom-drawer.is-compact-tabs .bottom-tab-item').first();
		await expect(compactTab).toBeVisible({ timeout: 10000 });
		const compactTabsRail = page.locator('.profile-bottom-drawer.is-compact-tabs .profile-bottom-tabs').first();
		const compactTabsRailBox = await compactTabsRail.boundingBox();
		expect(compactTabsRailBox).not.toBeNull();
		expect((compactTabsRailBox?.y || 0) + (compactTabsRailBox?.height || 0)).toBeLessThanOrEqual(initialState.viewportHeight);
		expect((compactTabsRailBox?.y || 0) + (compactTabsRailBox?.height || 0)).toBeGreaterThanOrEqual(initialState.viewportHeight - 2);

		const globalControls = page.locator('.hero-global-controls').first();
		await expect(globalControls).toBeVisible({ timeout: 10000 });
		const globalControlsBox = await globalControls.boundingBox();
		expect(globalControlsBox).not.toBeNull();
		expect((globalControlsBox?.y || 0) + (globalControlsBox?.height || 0)).toBeLessThanOrEqual((compactTabsRailBox?.y || 0) - 4);
		const compactTabState = await compactTab.evaluate((element) => {
			const rest = element.querySelector('.bottom-tab-rest');
			const word = element.querySelector('.bottom-tab-word');
			const icon = element.querySelector('.bottom-tab-icon svg, .bottom-tab-icon');
			return {
				hasWord: Boolean(word && word.textContent && word.textContent.trim().length > 0),
				restHidden: Boolean(rest) && window.getComputedStyle(rest).display === 'none',
				hasIcon: Boolean(icon),
			};
		});

		expect(compactTabState.hasWord).toBeTruthy();
		expect(compactTabState.restHidden).toBeTruthy();
		expect(compactTabState.hasIcon).toBeTruthy();

		await compactTab.click();
		const openPanel = page.locator('.attribute-slide-section.slide-up').first();
		await expect(openPanel).toBeVisible({ timeout: 10000 });
		const openPanelBox = await openPanel.boundingBox();
		expect(openPanelBox).not.toBeNull();
		expect(openPanelBox?.width || 0).toBeLessThanOrEqual(initialState.viewportWidth);

		const portraitFocusState = await page.evaluate(() => {
			const header = document.querySelector('.tm-cinematic-header') as HTMLElement | null;
			const controls = document.querySelector('.hero-global-controls') as HTMLElement | null;
			const profile = document.querySelector('.profile-info-head') as HTMLElement | null;
			return {
				headerHidden: header ? window.getComputedStyle(header).visibility === 'hidden' || Number(window.getComputedStyle(header).opacity) === 0 : true,
				controlsHidden: controls ? window.getComputedStyle(controls).visibility === 'hidden' || Number(window.getComputedStyle(controls).opacity) === 0 : true,
				profileHidden: profile ? window.getComputedStyle(profile).visibility === 'hidden' || Number(window.getComputedStyle(profile).opacity) === 0 : true,
			};
		});

		expect(portraitFocusState.headerHidden).toBeTruthy();
		expect(portraitFocusState.controlsHidden).toBeTruthy();
		expect(portraitFocusState.profileHidden).toBeTruthy();

		await openPanel.evaluate((element) => {
			element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		});
		await expect(openPanel).toBeHidden({ timeout: 10000 });
		await expect(compactTabsRail).toBeVisible({ timeout: 10000 });

		const toggle = page.locator('.tm-header-toggle').first();
		await expect(toggle).toBeVisible({ timeout: 10000 });
		await toggle.click();
		await expect(page.locator('.tm-cinematic-header').first()).toHaveClass(/is-menu-open/, { timeout: 10000 });
		await expect(page.locator('.tm-header-nav').first()).toBeVisible({ timeout: 10000 });
	});

	test('keeps the landscape talent panel above the tabs and below the play overlay stack', async ({ page, baseURL }) => {
		await page.setViewportSize({ width: 915, height: 412 });
		await gotoShowcase(page, baseURL);
		await page.waitForFunction(() => document.body.classList.contains('tm-mobile-landscape'), undefined, { timeout: 15000 });

		const profileHead = page.locator('.profile-info-head').first();
		await expect(profileHead).toBeVisible({ timeout: 10000 });
		if (await profileHead.evaluate((element) => element.classList.contains('is-collapsed'))) {
			await profileHead.click({ force: true });
			await expect(profileHead).not.toHaveClass(/is-collapsed/, { timeout: 10000 });
		}
		await page.evaluate(() => {
			document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		});
		await expect(profileHead).toHaveClass(/is-collapsed/, { timeout: 10000 });
		await profileHead.click({ force: true });
		await expect(profileHead).not.toHaveClass(/is-collapsed/, { timeout: 10000 });

		const compactTabsRail = page.locator('.profile-bottom-drawer.is-compact-tabs .profile-bottom-tabs').first();
		await expect(compactTabsRail).toBeHidden({ timeout: 10000 });

		const ctaButtons = page.locator('.vendor-cta-buttons').first();
		await expect(ctaButtons).toBeVisible({ timeout: 10000 });
		const resumeSlot = page.locator('.tm-showcase-resume-slot').first();
		const controls = page.locator('.hero-global-controls').first();
		const header = page.locator('.tm-cinematic-header').first();

		const boxes = await page.evaluate(() => {
			const overlay = document.querySelector('.tm-showcase-play-overlay') as HTMLElement | null;
			const profile = document.querySelector('.profile-info-head') as HTMLElement | null;
			const rail = document.querySelector('.profile-bottom-drawer.is-compact-tabs .profile-bottom-tabs') as HTMLElement | null;
			const cta = document.querySelector('.vendor-cta-buttons') as HTMLElement | null;
			const qr = document.querySelector('.vendor-cta-qr') as HTMLElement | null;
			const avatar = document.querySelector('.profile-info-head .profile-img') as HTMLElement | null;
			const channels = document.querySelector('.contact-channel-row') as HTMLElement | null;
			const resume = document.querySelector('.tm-showcase-resume-slot') as HTMLElement | null;
			const controls = document.querySelector('.hero-global-controls') as HTMLElement | null;
			const header = document.querySelector('.tm-cinematic-header') as HTMLElement | null;

			const rect = (element: HTMLElement | null) => {
				if (!element) return null;
				const box = element.getBoundingClientRect();
				return { x: box.x, y: box.y, width: box.width, height: box.height };
			};

			const zIndex = (element: HTMLElement | null) => {
				if (!element) return -1;
				const raw = window.getComputedStyle(element).zIndex;
				const numeric = Number(raw);
				return Number.isFinite(numeric) ? numeric : -1;
			};

			const content = profile ? profile.querySelector('.profile-info-content') as HTMLElement | null : null;

			return {
				overlayZ: zIndex(overlay),
				profileZ: zIndex(profile),
				profileBox: rect(profile),
				railBox: rect(rail),
				ctaBox: rect(cta),
				qrBox: rect(qr),
				avatarBox: rect(avatar),
				channelBox: rect(channels),
				contentOverflowY: content ? window.getComputedStyle(content).overflowY : '',
				viewportHeight: window.innerHeight,
				resumeHidden: resume ? window.getComputedStyle(resume).visibility === 'hidden' || Number(window.getComputedStyle(resume).opacity) === 0 : true,
				controlsHidden: controls ? window.getComputedStyle(controls).visibility === 'hidden' || Number(window.getComputedStyle(controls).opacity) === 0 : true,
				headerHidden: header ? window.getComputedStyle(header).visibility === 'hidden' || Number(window.getComputedStyle(header).opacity) === 0 : true,
				railHidden: rail ? window.getComputedStyle(rail).visibility === 'hidden' || Number(window.getComputedStyle(rail).opacity) === 0 || window.getComputedStyle(rail).display === 'none' : true,
			};
		});

		expect(boxes.overlayZ).toBeLessThan(boxes.profileZ);
		expect(boxes.profileBox).not.toBeNull();
		expect(boxes.profileBox?.y || 0).toBeLessThanOrEqual(1);
		expect((boxes.profileBox?.y || 0) + (boxes.profileBox?.height || 0)).toBeGreaterThanOrEqual((boxes.viewportHeight || 0) - 2);
		expect(boxes.ctaBox).not.toBeNull();
		expect(boxes.qrBox).not.toBeNull();
		expect(boxes.avatarBox).not.toBeNull();
		expect(boxes.resumeHidden).toBeTruthy();
		expect(boxes.controlsHidden).toBeTruthy();
		expect(boxes.headerHidden).toBeTruthy();
		expect(boxes.railHidden).toBeTruthy();
		expect((boxes.ctaBox?.y || 0) + (boxes.ctaBox?.height || 0)).toBeLessThanOrEqual((boxes.profileBox?.y || 0) + (boxes.profileBox?.height || 0) - 2);
		expect(boxes.qrBox?.y || 0).toBeLessThanOrEqual((boxes.profileBox?.y || 0) + 88);
		expect(boxes.qrBox?.x || 0).toBeGreaterThanOrEqual((boxes.avatarBox?.x || 0) + (boxes.avatarBox?.width || 0) - 2);
		const groupCenter = ((boxes.avatarBox?.x || 0) + ((boxes.qrBox?.x || 0) + (boxes.qrBox?.width || 0))) / 2;
		const profileCenter = (boxes.profileBox?.x || 0) + ((boxes.profileBox?.width || 0) / 2);
		expect(Math.abs(groupCenter - profileCenter)).toBeLessThanOrEqual(24);
		if (boxes.channelBox) {
			expect((boxes.channelBox.y || 0) + (boxes.channelBox.height || 0)).toBeLessThanOrEqual((boxes.profileBox?.y || 0) + (boxes.profileBox?.height || 0) - 2);
		}
		expect(boxes.contentOverflowY).not.toMatch(/auto|scroll/);

		await profileHead.click({ force: true });
		await expect(profileHead).toHaveClass(/is-collapsed/, { timeout: 10000 });
		await expect(compactTabsRail).toBeVisible({ timeout: 10000 });
		await expect(header).toBeVisible({ timeout: 10000 });
		await expect(controls).toBeVisible({ timeout: 10000 });
		await expect(resumeSlot).toBeVisible({ timeout: 10000 });

		const relaxedLandscapeState = await page.evaluate(() => {
			const overlay = document.querySelector('.tm-showcase-play-overlay') as HTMLElement | null;
			const remote = document.querySelector('.hero-remote') as HTMLElement | null;
			const rail = document.querySelector('.profile-bottom-drawer.is-compact-tabs .profile-bottom-tabs') as HTMLElement | null;
			const headerElement = document.querySelector('.tm-cinematic-header') as HTMLElement | null;
			const controlsElement = document.querySelector('.hero-global-controls') as HTMLElement | null;

			const rect = (element: HTMLElement | null) => {
				if (!element) return null;
				const box = element.getBoundingClientRect();
				return { x: box.x, y: box.y, width: box.width, height: box.height, bottom: box.bottom };
			};

			return {
				overlayBox: rect(overlay),
				remoteBox: rect(remote),
				railBox: rect(rail),
				headerBox: rect(headerElement),
				controlsBox: rect(controlsElement),
			};
		});

		expect(relaxedLandscapeState.overlayBox).not.toBeNull();
		expect(relaxedLandscapeState.remoteBox).not.toBeNull();
		expect(relaxedLandscapeState.railBox).not.toBeNull();
		expect(relaxedLandscapeState.headerBox).not.toBeNull();
		expect(relaxedLandscapeState.controlsBox).not.toBeNull();
		const overlayCenterY = (relaxedLandscapeState.overlayBox?.y || 0) + ((relaxedLandscapeState.overlayBox?.height || 0) / 2);
		const stageCenterY = ((relaxedLandscapeState.headerBox?.bottom || 0) + (relaxedLandscapeState.railBox?.y || 0)) / 2;
		expect(overlayCenterY).toBeLessThanOrEqual(stageCenterY + 12);
		expect(overlayCenterY).toBeGreaterThanOrEqual((relaxedLandscapeState.headerBox?.bottom || 0) + 48);
		expect(relaxedLandscapeState.remoteBox?.bottom || 0).toBeLessThanOrEqual((relaxedLandscapeState.railBox?.y || 0) - 8);
		expect(relaxedLandscapeState.controlsBox?.bottom || 0).toBeLessThanOrEqual((relaxedLandscapeState.railBox?.y || 0) - 8);

		await page.locator('.profile-info-box').first().dispatchEvent('touchstart');
		await page.waitForFunction(() => document.body.classList.contains('tm-mobile-landscape-play-focus'), undefined, { timeout: 10000 });
		const landscapePlaybackFocus = await page.evaluate(() => {
			const headerElement = document.querySelector('.tm-cinematic-header') as HTMLElement | null;
			const railElement = document.querySelector('.profile-bottom-drawer') as HTMLElement | null;
			const remoteElement = document.querySelector('.hero-remote') as HTMLElement | null;
			const controlsElement = document.querySelector('.hero-global-controls') as HTMLElement | null;
			const rect = (element: HTMLElement | null) => {
				if (!element) return null;
				const box = element.getBoundingClientRect();
				return { x: box.x, y: box.y, width: box.width, height: box.height, bottom: box.bottom };
			};
			return {
				headerHidden: headerElement ? window.getComputedStyle(headerElement).visibility === 'hidden' || Number(window.getComputedStyle(headerElement).opacity) === 0 : true,
				railHidden: railElement ? window.getComputedStyle(railElement).visibility === 'hidden' || Number(window.getComputedStyle(railElement).opacity) === 0 : true,
				remoteVisible: remoteElement ? window.getComputedStyle(remoteElement).visibility !== 'hidden' && Number(window.getComputedStyle(remoteElement).opacity) > 0 : false,
				controlsVisible: controlsElement ? window.getComputedStyle(controlsElement).visibility !== 'hidden' && Number(window.getComputedStyle(controlsElement).opacity) > 0 : false,
				remoteBox: rect(remoteElement),
				controlsBox: rect(controlsElement),
				viewportHeight: window.innerHeight,
			};
		});
		expect(landscapePlaybackFocus.headerHidden).toBeTruthy();
		expect(landscapePlaybackFocus.railHidden).toBeTruthy();
		expect(landscapePlaybackFocus.remoteVisible).toBeTruthy();
		expect(landscapePlaybackFocus.controlsVisible).toBeTruthy();
		expect(landscapePlaybackFocus.remoteBox?.bottom || 0).toBeLessThanOrEqual((landscapePlaybackFocus.viewportHeight || 0) - 10);
		expect(landscapePlaybackFocus.controlsBox?.bottom || 0).toBeLessThanOrEqual((landscapePlaybackFocus.viewportHeight || 0) - 10);
		await page.evaluate(() => {
			document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		});
		await page.waitForFunction(() => !document.body.classList.contains('tm-mobile-landscape-play-focus'), undefined, { timeout: 10000 });

		const landscapeTab = page.locator('.profile-bottom-drawer.is-compact-tabs .bottom-tab-item').first();
		await landscapeTab.click();
		const landscapeOpenPanel = page.locator('.attribute-slide-section.slide-up').first();
		await expect(landscapeOpenPanel).toBeVisible({ timeout: 10000 });
		const landscapeDrawerFocusState = await page.evaluate(() => {
			const headerElement = document.querySelector('.tm-cinematic-header') as HTMLElement | null;
			const controlsElement = document.querySelector('.hero-global-controls') as HTMLElement | null;
			const profileElement = document.querySelector('.profile-info-head') as HTMLElement | null;
			return {
				headerHidden: headerElement ? window.getComputedStyle(headerElement).visibility === 'hidden' || Number(window.getComputedStyle(headerElement).opacity) === 0 : true,
				controlsHidden: controlsElement ? window.getComputedStyle(controlsElement).visibility === 'hidden' || Number(window.getComputedStyle(controlsElement).opacity) === 0 : true,
				profileHidden: profileElement ? window.getComputedStyle(profileElement).visibility === 'hidden' || Number(window.getComputedStyle(profileElement).opacity) === 0 : true,
			};
		});
		expect(landscapeDrawerFocusState.headerHidden).toBeTruthy();
		expect(landscapeDrawerFocusState.controlsHidden).toBeTruthy();
		expect(landscapeDrawerFocusState.profileHidden).toBeTruthy();
		await landscapeOpenPanel.evaluate((element) => {
			element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		});
		await expect(landscapeOpenPanel).toBeHidden({ timeout: 10000 });
	});

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