# Next Project Bootstrap Blueprint

Last updated: 2026-03-27
Owner: Productization and DevOps
Scope: Repeatable bootstrap of a new WordPress plugin project with a paired private control-plane plugin.

## Purpose

This blueprint defines the fastest reliable way to start the next project using the exact stack patterns validated in EcomCine v0.1.0.

Primary goal:
- Start from a reproducible baseline where app plugin, control-plane plugin, licensing data shape, and release flow are already aligned.

Primary non-goal:
- Cloning running containers or manually carrying over mutable runtime state.

## Core Decision

Use repository + scripts + seed data as the system of record.

Do not use full Docker container cloning as the baseline strategy.

Rationale:
- Container snapshots hide drift and are hard to diff.
- Runtime mutations become invisible and non-repeatable.
- Environment portability decreases across machines and Docker versions.

## Golden Baseline Contents

Every new project should start from a baseline repository that already includes:

1. Runtime and orchestration
- docker-compose and .env example with project-scoped naming and ports.
- scripts/bootstrap-foundation.sh
- scripts/check-local-dev-infra.sh
- scripts/check-local-wp.sh
- scripts/wp.sh

2. Dual plugin architecture
- Public app plugin folder.
- Private control-plane plugin folder.
- Clear contract between app plugin and control plane for activation and entitlement resolution.

3. Billing and licensing parity seeding
- Source-authoritative runbook for FluentCart parity.
- Idempotent seed/clone script for product, variation, licensing, customer/order/license graph.
- Option/meta synchronization pass for FluentCart settings and payment meta rows.
- Committed reusable SQL fixture: `db/fluentcart-control-plane-seed.sql`.
- One-action importer: `./scripts/licensing/import-fluentcart-control-plane-seed.sh`.

4. Release pipeline
- ZIP artifact build command.
- ZIP structure validator command.
- GitHub release asset publish workflow.

5. Documentation and context
- README-FIRST with runtime rules and setup steps.
- Migration/setup guide with explicit handoff checklist.
- Execution-plan phase log for major milestones and validations.
- Canonical AI command contract file: `specs/IDE-AI-Command-Catalog.md`.
- Canonical remediation governance file: `specs/AI-Root-Cause-Remediation-Policy.md`.

## Bootstrap Inputs

Parameterize these values for each new project at creation time:

- project_name
- repository_name
- compose_project_name
- app_plugin_slug
- cp_plugin_slug
- local_wp_port
- local_pma_port
- local_base_url
- live_base_url (for search-replace)
- billing_seed_source (if cloning from prior product baseline)

## Bootstrap Sequence (Canonical)

Execute in order:

1. Create repository from the baseline template
- Create new GitHub repository from the approved starter baseline.
- Clone into WSL Linux path only.

2. Apply project identity
- Replace plugin slugs, namespace prefixes, and environment names.
- Update .env and docker-compose ports and project name.

3. Start runtime and baseline checks
- Run bootstrap foundation script.
- Install repository hooks.
- Start docker compose stack.
- Run local infra check.

4. Initialize WordPress state
- Import scrubbed seed.sql.
- Run URL search-replace from live URL to local URL.
- Reset local admin password.
- Set pretty permalinks.

5. Install and activate dependencies
- Run setup-deps script.
- Confirm required premium plugin folders or ZIPs exist locally.

6. Activate app and control-plane plugins
- Ensure only intended plugin stack is active.
- Confirm no duplicate legacy plugin symbols are loaded.

7. Apply billing/licensing parity seed
- Run FluentCart parity script.
- Validate product/variation mapping and activation limits.
- Validate customer/order/license graph shape.
- Validate option/meta keys required by licensing and payment settings.

8. Run validation stack
- Runtime checks (infra + WP health).
- Contract checks (activation and entitlement resolve).
- Security checks for changed entry points.
- Release ZIP build and path validation.

9. Create first release cut
- Commit baseline initialization state.
- Tag v0.1.0 for the new project.
- Publish GitHub release with validated ZIP assets.

## Required Validation Gates

Do not move forward unless all pass.

Gate A: Environment integrity
- Runtime path uses Linux filesystem.
- Docker bind mounts resolve to expected project paths.

Gate B: Application health
- WordPress admin loads.
- Key plugin screens load without fatal errors.

Gate C: Licensing and billing parity
- Expected FluentCart table counts and key relationships exist.
- Licensing settings include WP-plugin flags and activation limits.
- Required FluentCart options and fct_meta keys are present.

Gate D: Contract behavior
- Control-plane activation endpoint succeeds with test key.
- Entitlement resolution returns expected allowance payload without SQL/HTML contamination.

Gate E: Distribution readiness
- ZIP artifacts contain exactly one top-level plugin folder.
- Bootstrap file paths are correct.
- Release assets are attached to GitHub release.

Gate F: Remediation governance
- Remediation type is declared (`source-fix` or `mitigation`) for each fix.
- Mitigation changes include required metadata fields.
- Commit and push hooks enforce remediation policy trailers.

## Data Pack Strategy

Keep seed data in named packs so projects can bootstrap quickly and consistently.

Recommended packs:
- pack-core-config: base WordPress + Woo + Dokan settings.
- pack-licensing-catalog: control-plane plan mapping and catalog metadata.
- pack-billing-parity: FluentCart product, pricing, license, order, and option/meta parity set.

Rules:
- Always scrub PII from committed SQL and fixtures.
- Keep live exports out of git.
- Version each pack and log provenance.

## What To Reuse Directly From EcomCine v0.1.0

- Dual-plugin split pattern (public app + private control plane).
- Licensing control-plane resolver hardening against table-column mismatches.
- WMOS parity seeding approach for FluentCart data and options/meta layer.
- Canonical release flow using validated ZIP release assets.

## Anti-Patterns (Do Not Use)

- Full docker container snapshot as source of truth.
- Manual database patching without script/runbook updates.
- Anonymous assumptions about FluentCart option/meta defaults.
- Publishing source archives instead of validated plugin ZIP assets.

## Day-0 Deliverables For A New Project

Before feature development begins, ensure these exist:

1. README-FIRST adjusted for the new project identity.
2. Migration/setup guide with exact bootstrap commands.
3. App plugin and control-plane plugin folders with bootstrap files.
4. Seed scripts and runbook for billing/licensing parity.
5. Validation checklist document with gate pass records.
6. Release script and ZIP-path validator integrated.

## Day-1 Execution Checklist

Use this checklist on first machine setup:

1. Clone repository to WSL Linux path.
2. Run bootstrap foundation script.
3. Start containers and import scrubbed seed.
4. Run dependency setup script.
5. Run billing/licensing parity script.
6. Verify activation + entitlement contract endpoints.
7. Build and validate plugin ZIP artifacts.
8. Publish initial tagged release with ZIP assets.

## Ownership And Maintenance

Maintain this blueprint as a living baseline contract.

Update when any of the following changes:
- control-plane API contract
- FluentCart schema assumptions
- release validation tooling
- runtime shell/path policy

If a bootstrap gap is discovered in a future project:
- fix it in scripts/runbooks first
- update this blueprint immediately
- then continue feature work