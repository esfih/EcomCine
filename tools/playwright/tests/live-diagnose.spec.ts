/**
 * Targeted live-site diagnostic spec.
 * Run: ECOMCINE_BASE_URL=https://app.topdoctorchannel.us npx playwright test tests/live-diagnose.spec.ts --reporter=list
 */

import { expect, test } from '@playwright/test';

const PAGES_TO_CHECK = [
  {
    name: 'Home',
    path: '/',
    waitFor: 'body',
    checkTab: false,
    checkPlayer: false,
  },
  {
    name: 'Talents listing',
    path: '/talents/',
    waitFor: 'body',
    checkTab: false,
    checkPlayer: false,
  },
  {
    name: 'Talent profile (Agnieszka)',
    path: '/person/agnieszka/',
    waitFor: 'body',
    checkTab: true,
    checkPlayer: true,
  },
  {
    name: 'Talent profile (Aiko)',
    path: '/person/aiko/',
    waitFor: 'body',
    checkTab: true,
    checkPlayer: true,
  },
  {
    name: 'Showcase',
    path: '/',
    showcaseSlug: true,
    waitFor: 'body',
    checkTab: false,
    checkPlayer: true,
  },
];

test.describe('Live site diagnosis', () => {
  for (const pg of PAGES_TO_CHECK) {
    test(`[${pg.name}] console errors + player state + tab response`, async ({ page, baseURL }) => {
      const base = baseURL || 'https://app.topdoctorchannel.us';
      const consoleAll: string[] = [];
      const consoleErrors: string[] = [];
      const pageErrors: string[] = [];
      const requests: string[] = [];
      const failedRequests: string[] = [];

      page.on('console', (msg) => {
        const text = msg.text();
        consoleAll.push(`[${msg.type()}] ${text}`);
        if (msg.type() === 'error') consoleErrors.push(text);
      });
      page.on('pageerror', (err) => pageErrors.push(String(err)));
      page.on('requestfailed', (req) => failedRequests.push(`${req.failure()?.errorText} — ${req.url()}`));

      // Resolve the test URL.
      let url = base.replace(/\/$/, '') + pg.path;
      if ((pg as any).showcaseSlug) {
        // Try to find the showcase page slug from the sitemap; fall back to /home/.
        url = base.replace(/\/$/, '') + '/';
        // navigate first to get the showcase link if visible
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        const showcaseLink = page.locator('a[href*="showcase"], a[href*="talent-showcase"], .tm-showcase-link').first();
        if (await showcaseLink.count() > 0) {
          url = await showcaseLink.getAttribute('href') || url;
        }
      }

      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      expect(response, `Expected a response for ${url}`).not.toBeNull();
      console.log(`\n[${pg.name}] HTTP status: ${response?.status()} — ${url}`);

      // Wait up to 10s for network idle (catch slow JS).
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {
        console.log(`[${pg.name}] WARNING: network never went idle — possible looping AJAX`);
      });

      // Collect player mode from JS globals.
      const jsState = await page.evaluate(() => ({
        tmPlayerMode: (window as any).tmPlayerMode ?? '(not set)',
        tmShowcaseIds: (window as any).tmShowcaseIds ?? '(not set)',
        vendorStoreData: (window as any).vendorStoreData
          ? {
              playerMode: (window as any).vendorStoreData.playerMode ?? '(not set)',
              userId: (window as any).vendorStoreData.userId ?? '(not set)',
              ajaxurl: !!(window as any).vendorStoreData.ajaxurl,
            }
          : '(not defined)',
        playerJsLoaded: typeof (window as any).tmPlayerInit === 'function' || typeof (window as any).tmPlayerMode !== 'undefined',
        jQueryLoaded: typeof (window as any).jQuery !== 'undefined',
        jQueryVersion: typeof (window as any).jQuery !== 'undefined' ? (window as any).jQuery.fn.jquery : 'n/a',
      }));
      console.log(`[${pg.name}] JS state:`, JSON.stringify(jsState, null, 2));

      // Collect all console output.
      console.log(`[${pg.name}] Console (${consoleAll.length} lines):`);
      for (const line of consoleAll.slice(0, 60)) console.log('  ' + line);
      if (consoleAll.length > 60) console.log(`  ... (${consoleAll.length - 60} more)`);

      if (failedRequests.length > 0) {
        console.log(`[${pg.name}] Failed requests (${failedRequests.length}):`);
        for (const r of failedRequests) console.log('  ' + r);
      }

      // Check page errors.
      if (pageErrors.length > 0) {
        console.log(`[${pg.name}] PAGE ERRORS: ${pageErrors.join(' | ')}`);
      }

      // Check for tab trigger on profile pages.
      if (pg.checkTab) {
        const tabSelectors = [
          '.tm-tab-nav li:nth-child(2)',
          '.tm-tabs-nav a:nth-child(2)',
          '[data-tab]:nth-child(2)',
          '.tab-title:nth-child(2)',
          '.wc-tabs li:nth-child(2)',
          '.tm-category-tab',
          '[role="tab"]:nth-child(2)',
          '.tm-profile-tab:nth-child(2)',
        ];
        let tabClicked = false;
        for (const sel of tabSelectors) {
          const el = page.locator(sel).first();
          if (await el.count() > 0) {
            const label = await el.textContent().catch(() => '?');
            console.log(`[${pg.name}] Clicking tab: "${label?.trim()}" (${sel})`);
            await el.click({ timeout: 5000 }).catch((e) => console.log(`  click failed: ${e.message}`));
            await page.waitForTimeout(800);
            const errorsAfterClick = consoleErrors.slice();
            console.log(`[${pg.name}] Console errors after tab click: ${errorsAfterClick.length}`);
            tabClicked = true;
            break;
          }
        }
        if (!tabClicked) {
          console.log(`[${pg.name}] WARNING: No tab element found — check selectors`);
        }
      }

      // Check for play button / showcase player container.
      if (pg.checkPlayer) {
        const playerSelectors = [
          '.tm-player-play-btn',
          '.tm-play-button',
          'button.play-btn',
          '.tm-showcase-play',
          '[class*="play-btn"]',
          '[class*="play_btn"]',
          '.tm-media-player button',
          '#tm-player-wrap',
          '.tm-player-container',
          '[data-player]',
        ];
        let found = false;
        for (const sel of playerSelectors) {
          const el = page.locator(sel).first();
          if (await el.count() > 0) {
            const visible = await el.isVisible().catch(() => false);
            console.log(`[${pg.name}] Player element "${sel}": exists=true, visible=${visible}`);
            found = true;
            break;
          }
        }
        if (!found) {
          console.log(`[${pg.name}] WARNING: No player element found with common selectors`);
          // Dump relevant DOM for investigation.
          const dom = await page.locator('[class*="tm-"]').evaluateAll((els) =>
            els.slice(0, 20).map((el) => `${el.tagName}.${el.className.split(' ').slice(0, 3).join('.')}`)
          );
          console.log(`[${pg.name}] TM elements found:`, dom);
        }
      }

      // Final summary line: errors.
      console.log(`[${pg.name}] SUMMARY — pageErrors:${pageErrors.length} consoleErrors:${consoleErrors.length} failedRequests:${failedRequests.length}`);
      if (consoleErrors.length > 0) {
        console.log(`[${pg.name}] CONSOLE ERRORS: ${consoleErrors.join(' | ')}`);
      }
    });
  }
});
