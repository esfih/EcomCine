# IDE AI Command Catalog (Canonical Contracts)

Last updated: 2026-03-27
Owner: DevOps + AI workflow
Scope: Approved terminal commands for IDE AI operations in this repository.

## Policy

The IDE AI must execute terminal operations through this catalog.

Hard rules:

1. Use only catalog command IDs and their defined arguments.
2. Do not improvise ad-hoc shell commands for task execution.
3. If no catalog entry exists for the needed job, stop and ask the user to approve creating a new catalog entry.
4. Exit code is the pass/fail authority; warning text alone is never sufficient to mark failure.
5. Run WP-CLI only through `./scripts/wp.sh`.

## Command Contracts

Each command contract defines:

- `id`: canonical command ID
- `goal`: intended outcome
- `command`: executable command
- `args`: allowed argument shape
- `success`: expected success signal
- `failure`: expected failure signal and action
- `failure_class`: one of `infra|config|data|contract|tooling|auth|unknown`
- `remediation_type`: `source-fix` (default) or `mitigation` with policy fields

`remediation_type` must follow `specs/AI-Root-Cause-Remediation-Policy.md`.

### Runtime and Health

`id`: `git.hooks.install`
- goal: Enable repository-local commit and push policy hooks
- command: `./scripts/install-git-hooks.sh`
- args: none
- success: exit `0`
- failure: non-zero; stop and resolve permission/git-config issues
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `infra.check`
- goal: Validate runtime baseline and bind mount integrity
- command: `./scripts/check-local-dev-infra.sh`
- args: none
- success: exit `0`
- failure: non-zero; stop and resolve baseline mismatch
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `wp.health.check`
- goal: Validate WordPress service baseline health
- command: `./scripts/check-local-wp.sh`
- args: none
- success: exit `0`
- failure: non-zero; classify as runtime/config/data issue
- failure_class: `config`
- remediation_type: `source-fix`

`id`: `stack.up`
- goal: Start local Docker stack
- command: `docker compose up -d`
- args: none
- success: exit `0`
- failure: non-zero; inspect docker status and compose logs
- failure_class: `infra`
- remediation_type: `source-fix`

### WordPress / WP-CLI

`id`: `wp.plugins.list`
- goal: List current plugin state
- command: `./scripts/wp.sh wp plugin list`
- args: optional WP-CLI flags
- success: exit `0`
- failure: non-zero; stop and diagnose container/wp-cli path
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `wp.themes.list`
- goal: List current theme state
- command: `./scripts/wp.sh wp theme list`
- args: optional WP-CLI flags
- success: exit `0`
- failure: non-zero; stop and diagnose container/wp-cli path
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `wp.option.get`
- goal: Read a WordPress option
- command: `./scripts/wp.sh wp option get <option_name>`
- args: `option_name` required
- success: exit `0`
- failure: non-zero; confirm option name and WP bootstrap state
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `wp.eval`
- goal: Execute controlled PHP snippet with WordPress context
- command: `./scripts/wp.sh wp eval '<php_code>'`
- args: php snippet required
- success: exit `0`
- failure: non-zero; fix PHP snippet or environment
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `wp.eval.file`
- goal: Execute controlled PHP script file with WordPress context
- command: `./scripts/wp.sh php <local_php_file>`
- args: file path required
- success: exit `0`
- failure: non-zero; fix script or environment
- failure_class: `tooling`
- remediation_type: `source-fix`

### Database and Seed Contracts

`id`: `db.seed.import.core`
- goal: Import scrubbed base project SQL seed
- command: `./scripts/wp.sh wp db import db/seed.sql`
- args: none
- success: exit `0`
- failure: non-zero; stop and resolve file/runtime/db mismatch
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `db.seed.import.fluentcart_cp`
- goal: Import reusable FluentCart/control-plane baseline in one action
- command: `./scripts/licensing/import-fluentcart-control-plane-seed.sh`
- args: optional seed path, default `db/fluentcart-control-plane-seed.sql`
- success: exit `0`
- failure: non-zero; stop and validate DB credentials/table prefix/fixture integrity
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `db.seed.export.fluentcart_cp`
- goal: Regenerate reusable FluentCart/control-plane baseline SQL from validated local state
- command: `./scripts/licensing/export-fluentcart-control-plane-seed.sh`
- args: optional output path
- success: exit `0`
- failure: non-zero; stop and validate local fixture state and DB access
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `db.query`
- goal: Run controlled SQL query against current DB
- command: `./scripts/wp.sh db '<sql_query>'`
- args: SQL string required
- success: exit `0`
- failure: non-zero; stop and check SQL syntax/permissions/schema
- failure_class: `data`
- remediation_type: `source-fix`

### Licensing / Billing Workflows

`id`: `licensing.seed.clone_wmos`
- goal: Rebuild local FluentCart fixtures from WMOS baseline clone script
- command: `./scripts/wp.sh php scripts/licensing/seed-fluentcart-from-wmos-clone.php`
- args: none
- success: exit `0` with expected count output
- failure: non-zero; stop and inspect missing tables/mapping assumptions
- failure_class: `contract`
- remediation_type: `source-fix`

### Release and Git

`id`: `release.build.ecomcine`
- goal: Build public plugin artifact and manifest
- command: `./scripts/build-ecomcine-release.sh`
- args: none
- success: exit `0`
- failure: non-zero; stop and resolve build script/runtime issue
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `git.status`
- goal: Inspect repository working tree safely
- command: `git status --short`
- args: none
- success: exit `0`
- failure: non-zero; stop and inspect git repository health
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `git.push.master`
- goal: Push current local master to origin
- command: `git push origin master`
- args: none
- success: exit `0`
- failure: non-zero; stop and request user direction for auth/branch policy issues
- failure_class: `auth`
- remediation_type: `source-fix`

`id`: `git.commitmsg.validate`
- goal: Validate a commit message file against remediation policy
- command: `./scripts/validate-remediation-commit-msg.sh <commit_msg_file>`
- args: commit message file path required
- success: exit `0`
- failure: non-zero; fix remediation trailers or mitigation metadata
- failure_class: `tooling`
- remediation_type: `source-fix`

## Missing Command Procedure

When a needed task has no command contract:

1. Stop execution.
2. Report: missing command contract for the requested task.
3. Propose a new catalog entry with:
- id
- goal
- exact command
- allowed arguments
- success/failure criteria
4. Wait for user approval before running anything outside catalog.
5. Any approved mitigation path must comply with `specs/AI-Root-Cause-Remediation-Policy.md`.

## Maintenance

Any time a recurring workflow appears, add it here before repeating it.

Each entry must remain:

- deterministic
- least-privilege
- repository-local when possible
- aligned with runtime baseline in `README-FIRST.md`
