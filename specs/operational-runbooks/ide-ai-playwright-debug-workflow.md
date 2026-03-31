# IDE AI Playwright + Debug Workflow

Last updated: 2026-03-29
Owner: Engineering workflow

## Objective

Ensure IDE AI self-tests and debugs changes directly using reproducible local tooling, instead of waiting for user-provided browser console screenshots or HTML dumps.

## Required Command Path

Always use catalog commands:

1. `./scripts/run-catalog-command.sh infra.check`
2. `./scripts/run-catalog-command.sh wp.health.check`
3. `./scripts/run-catalog-command.sh qa.playwright.install`
4. `./scripts/run-catalog-command.sh qa.playwright.test.smoke`
5. Run feature-click regression flow: `./scripts/run-catalog-command.sh qa.playwright.test.interactions`
6. If needed, run another project/feature scenario: `./scripts/run-catalog-command.sh qa.playwright.test.interactions tools/playwright/tests/fixtures/interactions.template.json`
7. If failures persist: `./scripts/run-catalog-command.sh qa.playwright.test.debug`
8. Collect evidence: `./scripts/run-catalog-command.sh debug.snapshot.collect 200`
9. Inspect WP logs directly: `./scripts/run-catalog-command.sh wp.debug.log.tail 200`
10. Inspect PHP/WP runtime info: `./scripts/run-catalog-command.sh wp.debug.php.info`

## Tooling Layout

- Playwright harness: `tools/playwright/`
- Playwright runner script: `scripts/playwright-selftest.sh`
- Generic interactions scenarios: `tools/playwright/tests/fixtures/`
- Debug bundle script: `scripts/collect-debug-snapshot.sh`
- AI skill guidance: `.github/skills/playwright-wp-debug/SKILL.md`
- Cross-project scenario authoring guide: `specs/operational-runbooks/playwright-interactions-reuse-guide.md`

## Cross-Project Reuse (Canonical)

Use this pattern for any app/codebase:

1. Copy `tools/playwright/tests/fixtures/interactions.template.json`
2. Adapt selectors/steps for the active feature contract
3. Run `./scripts/run-catalog-command.sh qa.playwright.test.interactions <scenario_file>`

Shadow DOM support:

- For open shadow roots, use step fields `shadowHosts` + `shadowTarget`.
- For closed shadow roots, require app-level test hooks or host-exposed interactions.

## Debug Artifacts

- Playwright report: `tools/playwright/playwright-report/`
- Playwright test artifacts: `tools/playwright/test-results/`
- Debug snapshots: `logs/debug-snapshots/snapshot-<timestamp>.md`

## AI Operating Guidance

- Prefer source-fix remediation over mitigations.
- Use deterministic command IDs only.
- Treat non-zero exit code as failure signal.
- Include artifact paths and root-cause evidence in final status updates.
- Ask user for manual browser console data only if local tooling cannot reproduce the issue.
