import { expect, test } from '@playwright/test';

type PlayerSnapshot = {
	url: string;
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
			tmPlayerMode: typeof window.tmPlayerMode === 'string' ? window.tmPlayerMode : null,
			vendorPlayerMode: window.vendorStoreData && typeof window.vendorStoreData.playerMode === 'string'
				? window.vendorStoreData.playerMode
				: null,
		};
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
	test('@interactions showcase/profile play overlay starts playback', async ({ page, baseURL }) => {
		const targets = [
			{ name: 'showcase', path: '/' },
			{ name: 'profile', path: '/person/agnieszka-kami-ska/' },
		];

		for (const target of targets) {
			await test.step(target.name, async () => {
				await page.goto(new URL(target.path, baseURL || 'http://localhost:8180').toString(), { waitUntil: 'domcontentloaded' });
				await page.waitForLoadState('networkidle').catch(() => {});
				await page.locator('.tm-showcase-play-overlay').waitFor({ state: 'visible', timeout: 10000 });

				const before = await snapshotPlayer(page);
				console.log(`[player-regression][${target.name}] before click`, JSON.stringify(before, null, 2));

				await page.locator('.tm-showcase-play-overlay').click();
				await page.waitForTimeout(1200);

				const after = await snapshotPlayer(page);
				console.log(`[player-regression][${target.name}] after click`, JSON.stringify(after, null, 2));
				console.log(`[player-regression][${target.name}] source diagnostics`, JSON.stringify(await fetchSourceDiagnostics(page, after.activeVideo?.src || ''), null, 2));

				expect(after.accountOpen, 'account modal gate should be closed').toBeFalsy();
				expect(after.modalOpen, 'account modal should not be open').toBeFalsy();
				expect(after.fieldEditorOpen, 'field editor gate should be closed').toBeFalsy();
				expect(after.locationModalOpen, 'location modal gate should be closed').toBeFalsy();
				expect(after.activeVideo, 'expected a visible video element').not.toBeNull();
				expect(after.remotePlaying, 'hero remote should reflect playing state').toBeTruthy();
				expect(after.overlayHidden, 'play overlay should hide after click').toBeTruthy();
				expect(after.activeVideo?.paused, 'visible video should be playing after click').toBeFalsy();
			});
		}
	});
});