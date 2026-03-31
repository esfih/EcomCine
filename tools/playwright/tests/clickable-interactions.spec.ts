import { expect, Locator, Page, test } from '@playwright/test';
import fs from 'fs';
import path from 'path';

type SelectorInput = string | string[];

type InteractionStep = {
  action: 'click' | 'assertVisible' | 'assertHidden' | 'assertHasClass' | 'assertNotHasClass' | 'assertUrlContains' | 'waitFor';
  target?: SelectorInput;
  within?: SelectorInput;
  shadowHosts?: string[];
  shadowTarget?: SelectorInput;
  className?: string;
  value?: string;
  timeoutMs?: number;
  optional?: boolean;
};

type InteractionPage = {
  name: string;
  url: string;
  waitFor?: SelectorInput;
  steps: InteractionStep[];
};

type InteractionSuite = {
  name: string;
  pages: InteractionPage[];
};

const DEFAULT_SCENARIO_PATH = path.join(process.cwd(), 'tests', 'fixtures', 'interactions.vendor-store.json');

function scenarioPath(): string {
  return process.env.ECOMCINE_INTERACTIONS_FILE || DEFAULT_SCENARIO_PATH;
}

function loadSuite(): InteractionSuite {
  const filePath = scenarioPath();
  if (!fs.existsSync(filePath)) {
    throw new Error(`Interaction scenario file not found: ${filePath}`);
  }

  const raw = fs.readFileSync(filePath, 'utf8');
  const parsed = JSON.parse(raw) as InteractionSuite;

  if (!parsed || !Array.isArray(parsed.pages) || parsed.pages.length === 0) {
    throw new Error(`Invalid interaction scenario: ${filePath}`);
  }

  return parsed;
}

type LocatorRoot = Page | Locator;

async function resolveLocatorInRoot(root: LocatorRoot, target: SelectorInput): Promise<Locator | null> {
  const selectors = Array.isArray(target) ? target : [target];

  for (const selector of selectors) {
    const locator = root.locator(selector).first();
    if ((await locator.count()) > 0) {
      return locator;
    }
  }

  return null;
}

async function resolveLocator(
  page: Page,
  target: SelectorInput,
  within?: SelectorInput,
  shadowHosts?: string[]
): Promise<Locator | null> {
  let root: LocatorRoot = page;

  if (within) {
    const scopedRoot = await resolveLocatorInRoot(page, within);
    if (!scopedRoot) {
      return null;
    }
    root = scopedRoot;
  }

  if (Array.isArray(shadowHosts) && shadowHosts.length > 0) {
    for (const host of shadowHosts) {
      const hostLocator = await resolveLocatorInRoot(root, host);
      if (!hostLocator) {
        return null;
      }
      // Playwright locator chaining pierces open shadow roots.
      root = hostLocator;
    }
  }

  return resolveLocatorInRoot(root, target);
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function executeStep(page: Page, step: InteractionStep): Promise<void> {
  const timeout = step.timeoutMs ?? 8000;
  try {
    if (step.action === 'assertUrlContains') {
      if (!step.value) {
        throw new Error('assertUrlContains requires value');
      }
      await expect(page).toHaveURL(new RegExp(escapeRegExp(step.value)), { timeout });
      return;
    }

    const requestedTarget = step.shadowTarget || step.target;
    if (!requestedTarget) {
      throw new Error(`${step.action} requires target or shadowTarget`);
    }

    const locator = await resolveLocator(page, requestedTarget, step.within, step.shadowHosts);
    if (!locator) {
      if (step.action === 'assertHidden') {
        return;
      }
      if (step.optional) {
        test.info().annotations.push({ type: 'optional-step-skipped', description: `Target not found for ${step.action}` });
        return;
      }
      throw new Error(`Target not found for step ${step.action}: ${JSON.stringify(requestedTarget)}`);
    }

    if (step.action === 'click') {
      await locator.click({ timeout });
      return;
    }

    if (step.action === 'assertVisible') {
      await expect(locator).toBeVisible({ timeout });
      return;
    }

    if (step.action === 'assertHidden') {
      await expect(locator).toBeHidden({ timeout });
      return;
    }

    if (step.action === 'waitFor') {
      await locator.waitFor({ state: 'visible', timeout });
      return;
    }

    if (step.action === 'assertHasClass') {
      if (!step.className) {
        throw new Error('assertHasClass requires className');
      }
      await expect(locator).toHaveClass(new RegExp(`\\b${escapeRegExp(step.className)}\\b`), { timeout });
      return;
    }

    if (step.action === 'assertNotHasClass') {
      if (!step.className) {
        throw new Error('assertNotHasClass requires className');
      }
      await expect(locator).not.toHaveClass(new RegExp(`\\b${escapeRegExp(step.className)}\\b`), { timeout });
      return;
    }
  } catch (err) {
    if (step.optional) {
      test.info().annotations.push({ type: 'optional-step-skipped', description: `Optional step failed: ${String(err)}` });
      return;
    }
    throw err;
  }
}

test.describe('Generic clickable interactions', () => {
  const suite = loadSuite();

  test('@interactions executes scenario-defined click flows', async ({ page, baseURL }) => {
    for (const scenario of suite.pages) {
      await test.step(scenario.name, async () => {
        const url = scenario.url || '/';
        const response = await page.goto(new URL(url, baseURL || 'http://localhost:8180').toString(), { waitUntil: 'domcontentloaded' });
        expect(response, `Failed to navigate to ${url}`).not.toBeNull();

        if (scenario.waitFor) {
          const waitLocator = await resolveLocator(page, scenario.waitFor);
          expect(waitLocator, `waitFor selector not found: ${JSON.stringify(scenario.waitFor)}`).not.toBeNull();
          await waitLocator?.waitFor({ state: 'visible', timeout: 10_000 });
        }

        for (const step of scenario.steps) {
          await executeStep(page, step);
        }
      });
    }
  });
});
