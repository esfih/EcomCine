# Playwright Interactions Reuse Guide

Last updated: 2026-03-29
Owner: Engineering workflow

## Purpose

Define a canonical way to reuse the generic clickable-interactions Playwright suite across different app stacks and codebases.

## Reuse Steps

1. Copy `tools/playwright/tests/fixtures/interactions.template.json`.
2. Rename it for your target flow (example: `interactions.checkout-core.json`).
3. Replace selectors/steps to represent the feature contract you are changing.
4. Run:
   - `./scripts/run-catalog-command.sh qa.playwright.test.interactions <scenario_file>`

## Step Model

Supported actions:

- `click`
- `assertVisible`
- `assertHidden`
- `assertHasClass`
- `assertNotHasClass`
- `assertUrlContains`
- `waitFor`

Portable selector options:

- `target`: string selector
- `target`: selector array (fallbacks)
- `within`: optional scope selector(s)
- `optional`: when true, missing or failed step is annotated and skipped

## Shadow DOM Support

For open shadow roots, define:

- `shadowHosts`: ordered host selector array from outer host to inner host
- `shadowTarget`: selector inside the innermost shadow root

Example:

```json
{
  "action": "click",
  "shadowHosts": ["app-shell", "feature-panel"],
  "shadowTarget": "button.save"
}
```

Notes:

- Open shadow roots are supported through Playwright locator chaining.
- Closed shadow roots are not directly traversable; add test hooks or host-exposed interactions.

## EcomCine Scenario Packs

- `tools/playwright/tests/fixtures/interactions.vendor-store.json`
- `tools/playwright/tests/fixtures/interactions.vendor-store-cta-tabs.json`
- `tools/playwright/tests/fixtures/interactions.template.json`

## Validation Loop

1. `./scripts/run-catalog-command.sh qa.playwright.install`
2. `./scripts/run-catalog-command.sh qa.playwright.test.interactions <scenario_file>`
3. If failing: `./scripts/run-catalog-command.sh qa.playwright.test.debug`
4. Attach report artifacts from `tools/playwright/playwright-report` and `tools/playwright/test-results`.
