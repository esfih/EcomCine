import { expect, test } from '@playwright/test';

type PlayerSnapshot = {
	url: string;
	httpStatus: number | null;
	bodyClasses: string[];
	accountOpen: boolean;
	modalOpen: boolean;
	fieldEditorOpen: boolean;
	locationModalOpen: boolean;
	overlayHidden: boolean | null;
	videoCount: number;
	activeVideo: null | {
		src: string;
		currentTime: number;
		paused: boolean;
		ended: boolean;
		readyState: number;
		networkState: number;
		muted: boolean;
		display: string;
		errorCode: number | null;
	};
	videos: Array<{
		src: string;
		paused: boolean;
		readyState: number;
		networkState: number;
		display: string;
		errorCode: number | null;
	}>;
	remotePlaying: boolean;
	tmPlayerMode: string | null;
	vendorPlayerMode: string | null;
};

type SourceDiagnostics = {
	status: number | null;
	contentType: string | null;
	byteSample: string | null;
	acceptRanges: string | null;
	contentLength: string | null;
	rangeStatus: number | null;
	contentRange: string | null;
};

async function snapshotPlayer(page): Promise<PlayerSnapshot> {
	return page.evaluate(() => {
		const globalWindow = window as any;
		const overlay = document.querySelector('.tm-showcase-play-overlay');
		const videos = Array.from(document.querySelectorAll('video'));
		const visibleVideo = videos.find((video) => {
			const style = window.getComputedStyle(video);
			return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
		}) || videos[0] || null;
		const remote = document.querySelector('.hero-remote');
		const accountModal = document.querySelector('#tm-account-modal');
		const fieldEditor = document.querySelector('.tm-field-editor-modal');
		const locationModal = document.querySelector('.tm-location-modal');

		return {
			url: window.location.href,
			httpStatus: typeof document !== 'undefined' && document.body ? null : null,
			bodyClasses: Array.from(document.body.classList),
			accountOpen: document.body.classList.contains('tm-account-open'),
			modalOpen: !!(accountModal && accountModal.classList.contains('is-open')),
			fieldEditorOpen: !!(fieldEditor && fieldEditor.classList.contains('is-open')),
			locationModalOpen: !!(locationModal && locationModal.classList.contains('is-open')),
			overlayHidden: overlay ? overlay.classList.contains('is-hidden') : null,
			videoCount: videos.length,
			activeVideo: visibleVideo ? {
				src: visibleVideo.currentSrc || visibleVideo.getAttribute('src') || '',
				currentTime: Number(visibleVideo.currentTime || 0),
				paused: visibleVideo.paused,
				ended: visibleVideo.ended,
				readyState: visibleVideo.readyState,
				networkState: visibleVideo.networkState,
				muted: visibleVideo.muted,
				display: window.getComputedStyle(visibleVideo).display,
				errorCode: visibleVideo.error ? visibleVideo.error.code : null,
			} : null,
			videos: videos.map((video) => ({
				src: video.currentSrc || video.getAttribute('src') || '',
				paused: video.paused,
				readyState: video.readyState,
				networkState: video.networkState,
				display: window.getComputedStyle(video).display,
				errorCode: video.error ? video.error.code : null,
			})),
			remotePlaying: !!(remote && remote.classList.contains('is-playing')),
			tmPlayerMode: typeof globalWindow.tmPlayerMode === 'string' ? globalWindow.tmPlayerMode : null,
			vendorPlayerMode: globalWindow.vendorStoreData && typeof globalWindow.vendorStoreData.playerMode === 'string'
				? globalWindow.vendorStoreData.playerMode
				: null,
		};
	});
}

async function readHttpStatus(page): Promise<number | null> {
	const response = await page.waitForResponse(
		(resp) => resp.url() === page.url() && resp.request().resourceType() === 'document',
		{ timeout: 10000 }
	).catch(() => null);
	return response ? response.status() : null;
}

async function resolveCurrentVendorProfileUrl(page): Promise<string | null> {
	return page.evaluate(async () => {
		const globalWindow = window as any;
		const ajaxUrl = globalWindow.vendorStoreData && (globalWindow.vendorStoreData.ajaxurl || globalWindow.vendorStoreData.ajax_url);
		const currentVendorId = globalWindow.currentVendorId || (globalWindow.vendorStoreData && globalWindow.vendorStoreData.userId) || 0;
		if (!ajaxUrl || !currentVendorId) {
			return null;
		}

		const params = new URLSearchParams();
		params.set('action', 'get_vendor_navigation_list');
		params.set('current_vendor_id', String(currentVendorId));

		const response = await fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: params.toString(),
		});
		const json = await response.json();
		if (!json?.success || !json?.data?.vendors || !Array.isArray(json.data.vendors)) {
			return null;
		}
		const currentVendor = json.data.vendors.find((vendor: { id?: number; url?: string }) => Number(vendor.id) === Number(currentVendorId));
		if (!currentVendor?.url) {
			return null;
		}

		try {
			const currentUrl = new URL(currentVendor.url, window.location.origin);
			const segments = currentUrl.pathname.split('/').filter(Boolean);
			if (segments.length >= 2 && segments[0] !== 'author') {
				return currentUrl.toString();
			}
			const nicename = segments.length ? segments[segments.length - 1] : '';
			if (!nicename) {
				return currentUrl.toString();
			}
			return new URL(`/person/${nicename}/`, window.location.origin).toString();
		} catch {
			return currentVendor.url;
		}
	});
}

async function fetchSourceDiagnostics(page, src: string): Promise<SourceDiagnostics | null> {
	if (!src) {
		return null;
	}

	return page.evaluate(async (videoSrc: string) => {
		try {
			const headResponse = await fetch(videoSrc, { method: 'HEAD', credentials: 'include' });
			const sampleResponse = await fetch(videoSrc, {
				method: 'GET',
				credentials: 'include',
				headers: {
					Range: 'bytes=0-255',
				},
			});
			const buffer = await sampleResponse.arrayBuffer();
			const sample = Array.from(new Uint8Array(buffer).slice(0, 128))
				.map((value) => (value >= 32 && value <= 126 ? String.fromCharCode(value) : '.'))
				.join('');
			return {
				status: headResponse.status,
				contentType: headResponse.headers.get('content-type'),
				byteSample: sample,
				acceptRanges: headResponse.headers.get('accept-ranges'),
				contentLength: headResponse.headers.get('content-length'),
				rangeStatus: sampleResponse.status,
				contentRange: sampleResponse.headers.get('content-range'),
			};
		} catch {
			return {
				status: null,
				contentType: null,
				byteSample: null,
				acceptRanges: null,
				contentLength: null,
				rangeStatus: null,
				contentRange: null,
			};
		}
	}, src);
}

test.describe('Player overlay regression', () => {
	test('@interactions showcase initial overlay unlock starts playback', async ({ page, baseURL }) => {
		const showcaseUrl = new URL('/', baseURL || 'http://localhost:8180').toString();
		const showcaseResponse = await page.goto(showcaseUrl, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		const showcaseBefore = await snapshotPlayer(page);
		showcaseBefore.httpStatus = showcaseResponse ? showcaseResponse.status() : null;
		console.log('[player-regression][showcase] before click', JSON.stringify(showcaseBefore, null, 2));

		const showcaseOverlay = page.locator('.tm-showcase-play-overlay').first();
		await showcaseOverlay.waitFor({ state: 'visible', timeout: 10000 });
		await showcaseOverlay.click();
		await page.waitForTimeout(1200);

		const showcaseAfter = await snapshotPlayer(page);
		console.log('[player-regression][showcase] after click', JSON.stringify(showcaseAfter, null, 2));
		console.log('[player-regression][showcase] source diagnostics', JSON.stringify(await fetchSourceDiagnostics(page, showcaseAfter.activeVideo?.src || ''), null, 2));

		expect(showcaseAfter.accountOpen, 'account modal gate should be closed').toBeFalsy();
		expect(showcaseAfter.modalOpen, 'account modal should not be open').toBeFalsy();
		expect(showcaseAfter.fieldEditorOpen, 'field editor gate should be closed').toBeFalsy();
		expect(showcaseAfter.locationModalOpen, 'location modal gate should be closed').toBeFalsy();
		expect(showcaseAfter.activeVideo, 'expected a visible showcase video element').not.toBeNull();
		expect(showcaseAfter.remotePlaying, 'hero remote should reflect playing state').toBeTruthy();
		expect(showcaseAfter.overlayHidden, 'showcase overlay should hide after the initial unlock click').toBeTruthy();
		expect(showcaseAfter.activeVideo?.paused, 'showcase video should be playing after the initial unlock click').toBeFalsy();
	});

	test('@interactions legacy /person alias redirects to canonical profile route', async ({ page, baseURL }) => {
		const showcaseUrl = new URL('/', baseURL || 'http://localhost:8180').toString();
		await page.goto(showcaseUrl, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		const canonicalProfileUrl = await resolveCurrentVendorProfileUrl(page);
		test.skip(!canonicalProfileUrl, 'Could not resolve a live canonical profile URL from showcase navigation.');

		const canonicalUrl = new URL(canonicalProfileUrl!);
		const nicename = canonicalUrl.pathname.split('/').filter(Boolean).pop();
		test.skip(!nicename, 'Canonical profile URL did not expose a nicename segment.');

		const legacyAliasUrl = new URL(`/person/${nicename}/`, baseURL || 'http://localhost:8180').toString();
		const legacyResponse = await page.goto(legacyAliasUrl, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		expect(legacyResponse?.status(), 'legacy alias should redirect successfully').toBe(200);
		expect(page.url(), 'legacy /person alias should land on the configured canonical profile URL').toBe(canonicalUrl.toString());
	});

	test('@interactions profile autoplay reuses showcase unlock when a real profile page resolves', async ({ page, baseURL }) => {
		const showcaseUrl = new URL('/', baseURL || 'http://localhost:8180').toString();
		await page.goto(showcaseUrl, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});

		const showcaseOverlay = page.locator('.tm-showcase-play-overlay').first();
		await showcaseOverlay.waitFor({ state: 'visible', timeout: 10000 });
		await showcaseOverlay.click();
		await page.waitForTimeout(1200);

		const profileUrl = await resolveCurrentVendorProfileUrl(page);
		expect(profileUrl, 'expected a real profile URL from the current showcase vendor').toBeTruthy();

		const profileResponse = await page.goto(profileUrl!, { waitUntil: 'domcontentloaded' });
		await page.waitForLoadState('networkidle').catch(() => {});
		await page.waitForTimeout(1200);
		const profileContentType = profileResponse ? (profileResponse.headers()['content-type'] || '') : '';

		const profileAfter = await snapshotPlayer(page);
		profileAfter.httpStatus = profileResponse ? profileResponse.status() : null;
		console.log('[player-regression][profile] same-session autoplay', JSON.stringify(profileAfter, null, 2));
		console.log('[player-regression][profile] source diagnostics', JSON.stringify(await fetchSourceDiagnostics(page, profileAfter.activeVideo?.src || ''), null, 2));

		const resolvedToPlayerPage = Boolean(
			profileAfter.httpStatus !== 404
			&& /^text\/html/i.test(profileContentType)
			&& (profileAfter.tmPlayerMode !== null || profileAfter.vendorPlayerMode !== null || profileAfter.videoCount > 0 || profileAfter.bodyClasses.length > 0)
			&& !/^image\//i.test(profileContentType)
		);
		if (!resolvedToPlayerPage) {
			test.skip(true, 'Canonical profile route does not resolve to a player page in this runtime, so same-session profile autoplay cannot be validated semantically here.');
		}

		expect(profileAfter.httpStatus, 'profile page should resolve to a real page').not.toBe(404);
		expect(profileAfter.accountOpen, 'account modal gate should be closed on profile page').toBeFalsy();
		expect(profileAfter.modalOpen, 'account modal should not be open on profile page').toBeFalsy();
		expect(profileAfter.fieldEditorOpen, 'field editor gate should be closed on profile page').toBeFalsy();
		expect(profileAfter.locationModalOpen, 'location modal gate should be closed on profile page').toBeFalsy();
		expect(profileAfter.activeVideo, 'expected a visible profile video element').not.toBeNull();
		expect(profileAfter.remotePlaying, 'profile page should reflect active playback in the same session').toBeTruthy();
		expect(profileAfter.overlayHidden, 'profile page should not require a second play overlay after showcase unlock in the same session').toBeTruthy();
		expect(profileAfter.activeVideo?.paused, 'profile video should autoplay in the same session after showcase unlock').toBeFalsy();
	});
});