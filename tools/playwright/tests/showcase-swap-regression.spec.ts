import { expect, test } from '@playwright/test';

async function readVendorEndpointSnapshot(page) {
	return page.evaluate(async () => {
		const restUrl = (window as any).tmVendorStoreRestUrl || ((window as any).vendorStoreData && (window as any).vendorStoreData.vendorStoreRestUrl) || '';
		const showcaseIds = Array.isArray((window as any).tmShowcaseIds) ? (window as any).tmShowcaseIds : [];
		if (!restUrl || showcaseIds.length < 2) {
			return [];
		}

		const targetIds = showcaseIds.slice(1, Math.min(showcaseIds.length, 4));
		const responses = await Promise.all(targetIds.map(async (vendorId) => {
			const url = new URL(restUrl, window.location.origin);
			url.searchParams.set('vendor_id', String(vendorId));
			const response = await fetch(url.toString(), { credentials: 'same-origin' });
			const json = await response.json();
			return {
				vendorId,
				httpStatus: response.status,
				success: !!json?.success,
				hasHtml: typeof json?.data?.html === 'string' && json.data.html.trim().length > 0,
				hasProfileBox: typeof json?.data?.html === 'string' && json.data.html.includes('profile-info-box'),
				hasMedia: Array.isArray(json?.data?.vendorMedia?.items) && json.data.vendorMedia.items.length > 0,
			};
			}));

		return responses;
	});
}

async function readShowcaseSnapshot(page) {
	return page.evaluate(() => {
		const overlay = document.querySelector('.tm-showcase-play-overlay');
		const rightNav = document.querySelector('.keyboard-nav-right');
		const collapsedName = document.querySelector('.collapsed-tab-name');
		const vendorName = document.querySelector('.vendor-name, .profile-name, .store_name, .profile-info-box h1, .profile-info-box h2');
		const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
			const style = window.getComputedStyle(video);
			return style.display !== 'none' && style.visibility !== 'hidden';
		}) as HTMLVideoElement | undefined;
		return {
			currentVendorId: (window as any).currentVendorId ?? null,
			tmPlayerMode: (window as any).tmPlayerMode ?? null,
			showcaseStarted: window.sessionStorage ? window.sessionStorage.getItem('tm_showcase_started') : null,
			overlayHidden: overlay ? overlay.classList.contains('is-hidden') : null,
			rightNavLoading: rightNav ? rightNav.classList.contains('is-loading') : null,
			rightNavDisabled: rightNav instanceof HTMLButtonElement ? rightNav.disabled : null,
			collapsedName: collapsedName ? collapsedName.textContent : null,
			vendorName: vendorName ? vendorName.textContent : null,
			activeVideoSrc: activeVideo ? activeVideo.currentSrc || activeVideo.src || null : null,
		};
	});
}

async function readActiveVideoState(page) {
	return page.evaluate(() => {
		const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
			const style = window.getComputedStyle(video);
			return style.display !== 'none' && style.visibility !== 'hidden';
		}) as HTMLVideoElement | undefined;
		return activeVideo ? {
			src: activeVideo.currentSrc || activeVideo.src || null,
			paused: activeVideo.paused,
			ended: activeVideo.ended,
			currentTime: Number(activeVideo.currentTime || 0),
		} : null;
	});
}

async function openShowcasePage(page, baseURL: string) {
	const homeUrl = new URL('/', baseURL || 'http://localhost:8180').toString();
	await page.goto(homeUrl, { waitUntil: 'commit', timeout: 30000 });
	await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

	const overlay = page.locator('.tm-showcase-play-overlay');
	const nextTalent = page.locator('.keyboard-nav-right').first();
	if ((await overlay.count()) > 0 || (await nextTalent.count()) > 0) {
		return;
	}

	const showcaseLink = page.locator('a[href*="showcase"], a[href*="talent-showcase"], .tm-showcase-link').first();
	if ((await showcaseLink.count()) > 0) {
		const href = await showcaseLink.getAttribute('href');
		if (href) {
			await page.goto(href, { waitUntil: 'commit', timeout: 30000 });
			await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
		}
	}
}

test.describe('Showcase vendor swap regression', () => {
	test('@interactions keyboard shortcuts keep working at laptop viewport widths', async ({ page, baseURL }) => {
		await page.setViewportSize({ width: 1280, height: 720 });
		const url = new URL('/?minduration=20', baseURL || 'http://localhost:8180').toString();
		await page.goto(url, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		const overlay = page.locator('.tm-showcase-play-overlay').first();
		await overlay.waitFor({ state: 'visible', timeout: 15000 });
		await overlay.click();
		await expect(overlay).toHaveClass(/is-hidden/, { timeout: 5000 });

		await page.waitForFunction(() => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			return !!activeVideo && !activeVideo.paused && activeVideo.currentTime > 0.75;
		}, undefined, { timeout: 15000 });

		await page.keyboard.press('Space');
		await page.waitForFunction(() => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			return !!activeVideo && activeVideo.paused;
		}, undefined, { timeout: 5000 });

		await page.keyboard.press('Space');
		await page.waitForFunction(() => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			return !!activeVideo && !activeVideo.paused && activeVideo.currentTime > 0.1;
		}, undefined, { timeout: 5000 });

		await page.waitForTimeout(700);
		const beforeArrowDown = await readActiveVideoState(page);
		expect(beforeArrowDown).not.toBeNull();
		expect(beforeArrowDown?.paused).toBeFalsy();

		await page.keyboard.press('ArrowDown');
		await page.waitForFunction((previous) => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			if (!activeVideo) return false;
			const currentSrc = activeVideo.currentSrc || activeVideo.src || null;
			return currentSrc !== previous.src || Number(activeVideo.currentTime || 0) < Math.max(previous.currentTime - 0.5, 0.35);
		}, beforeArrowDown, { timeout: 5000 });

		await page.waitForTimeout(700);
		const beforeArrowUp = await readActiveVideoState(page);
		expect(beforeArrowUp).not.toBeNull();

		await page.keyboard.press('ArrowUp');
		await page.waitForFunction((previous) => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			if (!activeVideo) return false;
			const currentSrc = activeVideo.currentSrc || activeVideo.src || null;
			return currentSrc !== previous.src || Number(activeVideo.currentTime || 0) < Math.max(previous.currentTime - 0.5, 0.35);
		}, beforeArrowUp, { timeout: 5000 });

		await page.waitForTimeout(700);
		const beforeClickDown = await readActiveVideoState(page);
		expect(beforeClickDown).not.toBeNull();

		await page.locator('.keyboard-nav-down').first().click();
		await page.waitForFunction((previous) => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			if (!activeVideo) return false;
			const currentSrc = activeVideo.currentSrc || activeVideo.src || null;
			return currentSrc !== previous.src || Number(activeVideo.currentTime || 0) < Math.max(previous.currentTime - 0.5, 0.35);
		}, beforeClickDown, { timeout: 5000 });

		await page.waitForTimeout(700);
		const beforeClickUp = await readActiveVideoState(page);
		expect(beforeClickUp).not.toBeNull();

		await page.locator('.keyboard-nav-up').first().click();
		await page.waitForFunction((previous) => {
			const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
				const style = window.getComputedStyle(video);
				return style.display !== 'none' && style.visibility !== 'hidden';
			}) as HTMLVideoElement | undefined;
			if (!activeVideo) return false;
			const currentSrc = activeVideo.currentSrc || activeVideo.src || null;
			return currentSrc !== previous.src || Number(activeVideo.currentTime || 0) < Math.max(previous.currentTime - 0.5, 0.35);
		}, beforeClickUp, { timeout: 5000 });
	});

	test('@interactions first play survives next talent swap', async ({ page, baseURL }) => {
		await openShowcasePage(page, baseURL || 'http://localhost:8180');

		const endpointSnapshots = await readVendorEndpointSnapshot(page);
		expect(endpointSnapshots.length).toBeGreaterThan(0);
		for (const snapshot of endpointSnapshots) {
			expect(snapshot.httpStatus).toBe(200);
			expect(snapshot.success).toBeTruthy();
			expect(snapshot.hasHtml).toBeTruthy();
			expect(snapshot.hasProfileBox).toBeTruthy();
			expect(snapshot.hasMedia).toBeTruthy();
		}

		const overlay = page.locator('.tm-showcase-play-overlay');
		const nextTalent = page.locator('.keyboard-nav-right').first();
		await expect(nextTalent).toBeVisible();

		const overlayCount = await overlay.count();
		const overlayVisible = overlayCount > 0 ? await overlay.isVisible().catch(() => false) : false;

		if (overlayVisible) {
			await overlay.click();
			await expect(overlay).toHaveClass(/is-hidden/, { timeout: 5000 });
		}

		const beforeSwap = await readShowcaseSnapshot(page);
		if (overlayVisible) {
			expect(beforeSwap.showcaseStarted).toBe('1');
			expect(beforeSwap.overlayHidden).toBeTruthy();
		}

		let previousVendorId = beforeSwap.currentVendorId;
		let previousVendorName = beforeSwap.vendorName;
		let previousVideoSrc = beforeSwap.activeVideoSrc;
		for (let step = 0; step < 5; step += 1) {
			await nextTalent.click();
			await page.waitForFunction(
				(vendorId) => (window as any).currentVendorId && (window as any).currentVendorId !== vendorId,
				previousVendorId,
				{ timeout: 15000 }
			);
			await page.waitForFunction(
				() => {
					const nextButton = document.querySelector('.keyboard-nav-right');
					return !nextButton || !nextButton.classList.contains('is-loading');
				},
				undefined,
				{ timeout: 15000 }
			);

			const afterSwap = await readShowcaseSnapshot(page);
			expect(afterSwap.tmPlayerMode).toBe('showcase');
			if (overlayVisible) {
				expect(afterSwap.showcaseStarted).toBe('1');
			}
			expect(afterSwap.currentVendorId).not.toBe(previousVendorId);
			if (overlayVisible) {
				expect(afterSwap.overlayHidden).toBeTruthy();
			}
			expect(afterSwap.rightNavLoading).toBeFalsy();
			expect(afterSwap.vendorName).not.toBe(previousVendorName);
			expect(afterSwap.activeVideoSrc).not.toBe(previousVideoSrc);
			previousVendorId = afterSwap.currentVendorId;
			previousVendorName = afterSwap.vendorName;
			previousVideoSrc = afterSwap.activeVideoSrc;
		}
	});
});