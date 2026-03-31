---
name: playwright-wp-debug
description: "Use when: validating frontend fixes, reproducing runtime JS/UI regressions, collecting WordPress/PHP evidence, or when the user asks IDE AI to self-test instead of relying on manual console sharing."
---

# Playwright + WordPress Debug Workflow

## Goal

Run deterministic, repo-local self-tests and collect actionable diagnostics before asking the user for browser console output.

## Required Command Path

Always execute through catalog contracts:

- `./scripts/run-catalog-command.sh qa.playwright.install`
- `./scripts/run-catalog-command.sh qa.playwright.test.smoke`
- `./scripts/run-catalog-command.sh qa.playwright.test.interactions [scenario_file]`
- `./scripts/run-catalog-command.sh qa.playwright.test.debug`
- `./scripts/run-catalog-command.sh debug.snapshot.collect [lines]`
- `./scripts/run-catalog-command.sh wp.debug.log.tail [lines]`
- `./scripts/run-catalog-command.sh wp.debug.php.info`

Do not run ad-hoc package-manager commands directly.

## Standard Triage Sequence

1. Validate environment baseline.
2. Ensure Playwright harness is installed.
3. Run smoke tests to detect hard JS/runtime failures.
4. Run scenario-driven clickable interaction tests for the feature being changed.
5. If failure persists, run debug mode with trace and screenshots.
6. Capture debug snapshot and WP logs.
7. Implement source-level fix and rerun smoke + interaction tests.

## Evidence to Include in Reports

- Failing Playwright assertion and URL
- Trace/report artifact path (`tools/playwright/playwright-report`)
- Relevant WordPress debug lines
- Relevant Docker WordPress log lines
- Root cause classification (`source-fix` vs `mitigation`)

## Notes

- Base URL defaults to `http://localhost:8180`; override with `ECOMCINE_BASE_URL` when needed.
- Prefer source fixes in plugin/theme/runtime wiring over cosmetic fallbacks.
- Reuse scenarios across codebases via `tools/playwright/tests/fixtures/interactions.template.json` and the canonical guide `specs/operational-runbooks/playwright-interactions-reuse-guide.md`.
- Shadow DOM (open roots): use `shadowHosts` + `shadowTarget` in interaction steps.
- Shadow DOM (closed roots): require application test hooks or host-level actions.
