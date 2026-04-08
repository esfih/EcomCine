import { expect, test } from '@playwright/test';

async function snapshotAutoSwapState(page) {
	return page.evaluate(() => {
		const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
			const style = window.getComputedStyle(video);
			return style.display !== 'none' && style.visibility !== 'hidden';
		}) as HTMLVideoElement | undefined;
		const overlay = document.querySelector('.tm-showcase-play-overlay');
		return {
			vendorId: (window as any).currentVendorId ?? null,
			overlayHidden: overlay ? overlay.classList.contains('is-hidden') : null,
			showcaseStarted: window.sessionStorage ? window.sessionStorage.getItem('tm_showcase_started') : null,
			stateIsPlaying: (window as any).tmHeroState && typeof (window as any).tmHeroState.isPlaying === 'boolean'
				? (window as any).tmHeroState.isPlaying
				: null,
			video: activeVideo ? {
				src: activeVideo.currentSrc || activeVideo.src || null,
				paused: activeVideo.paused,
				ended: activeVideo.ended,
				currentTime: Number(activeVideo.currentTime || 0),
				readyState: activeVideo.readyState,
			} : null,
		};
	});
}

test.describe('Showcase auto-swap autoplay', () => {
	test('@interactions autoplay survives timer-driven vendor swap', async ({ page, baseURL }) => {
		const url = new URL('/?minduration=1', baseURL || 'http://localhost:8180').toString();
		await page.goto(url, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});
		await page.reload({ waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		const overlay = page.locator('.tm-showcase-play-overlay').first();
		await overlay.waitFor({ state: 'visible', timeout: 15000 });
		await overlay.click();
		await expect(overlay).toHaveClass(/is-hidden/, { timeout: 5000 });

		const beforeSwap = await snapshotAutoSwapState(page);
		expect(beforeSwap.showcaseStarted).toBe('1');
		expect(beforeSwap.video).not.toBeNull();
		expect(beforeSwap.video?.paused).toBeFalsy();

		let previousState = beforeSwap;
		for (let step = 0; step < 4; step += 1) {
			await page.waitForFunction(
				(previousVendorId) => (window as any).currentVendorId && (window as any).currentVendorId !== previousVendorId,
				previousState.vendorId,
				{ timeout: 15000 }
			);

			await page.waitForFunction(() => {
				const activeVideo = Array.from(document.querySelectorAll('video')).find((video) => {
					const style = window.getComputedStyle(video);
					return style.display !== 'none' && style.visibility !== 'hidden';
				}) as HTMLVideoElement | undefined;
				return !!activeVideo && !activeVideo.paused && !activeVideo.ended && activeVideo.currentTime > 0;
			}, undefined, { timeout: 15000 });

			const afterSwap = await snapshotAutoSwapState(page);
			expect(afterSwap.vendorId).not.toBe(previousState.vendorId);
			expect(afterSwap.showcaseStarted).toBe('1');
			expect(afterSwap.overlayHidden).toBeTruthy();
			expect(afterSwap.video).not.toBeNull();
			expect(afterSwap.video?.src).not.toBe(previousState.video?.src || null);
			expect(afterSwap.video?.paused).toBeFalsy();
			previousState = afterSwap;
		}
	});
	});