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

### CPT Migration (Default-WP Cutover)

`id`: `migrate.tap.cpt`
- goal: Migrate TAP Dokan/WC data to tm_invitation + tm_order + tm_booking CPTs
- command: `./scripts/wp.sh php scripts/migrate-tap-cpt.php`
- args: optional `-- dry-run` to preview without writing
- success: exit `0`, summary line ending with `[tap-migrate] DONE`
- failure: non-zero or any `[FAIL]`; inspect per-record error lines
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `migrate.tvbm.cpt`
- goal: Migrate TVBM WC booking products to tm_offer CPT
- command: `./scripts/wp.sh php scripts/migrate-tvbm-cpt.php`
- args: optional `-- dry-run` to preview without writing
- success: exit `0`, summary line ending with `[tvbm-migrate] DONE`
- failure: non-zero or any `[FAIL]`; inspect per-record error lines
- failure_class: `data`
- remediation_type: `source-fix`

`id`: `migrate.tho.cpt`
- goal: Migrate THO Dokan vendor profile user-meta to tm_vendor CPT
- command: `./scripts/wp.sh php scripts/migrate-tho-cpt.php`
- args: optional `-- dry-run` to preview without writing
- success: exit `0`, summary line ending with `[tho-migrate] DONE`
- failure: non-zero or any `[FAIL]`; inspect per-record error lines
- failure_class: `data`
- remediation_type: `source-fix`

### Adapter Parity and Toggle Validation

> **Note — `wp config set` string constants:** Do NOT use `--raw` for string values.
> Use `./scripts/wp.sh wp config set <name> '<value>'` (no `--raw`) so WP-CLI wraps the value in single quotes.
> `--raw` is only for numeric or boolean PHP expressions.


`id`: `parity.check.tap`
- goal: Run TAP parity suite (should report 16/16 PASS)
- command: `./scripts/wp.sh wp eval '(new TAP_Parity_Check())->run();'`
- args: none
- success: exit `0`; no `[FAIL]` lines in output
- failure: non-zero or any `[FAIL]`; diagnose adapter contract drift
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `parity.check.tvbm`
- goal: Run TVBM parity suite (should report 14/14 PASS)
- command: `./scripts/wp.sh wp eval '(new TVBM_Parity_Check())->run();'`
- args: none
- success: exit `0`; no `[FAIL]` lines in output
- failure: non-zero or any `[FAIL]`; diagnose adapter contract drift
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `parity.check.tho`
- goal: Run THO parity suite (should report 20/20 PASS)
- command: `./scripts/wp.sh wp eval 'require ABSPATH."wp-content/themes/astra-child/includes/parity/class-parity-check.php"; THO_Parity_Check::run();'`
- args: none
- success: exit `0`; output contains `20/20 checks passed`
- failure: non-zero or any `✗`; diagnose adapter contract drift
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `parity.check.all`
- goal: Run all three parity suites (TAP + TVBM + THO) in one pass
- command: `./scripts/wp.sh php scripts/run-parity-checks.php`
- args: none
- success: exit `0`, output ends with `ALL PARITY CHECKS PASS`
- failure: non-zero or any `[FAIL]`; inspect per-check output
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `adapter.toggle.validate`
- goal: Validate all adapter registry toggle selection logic (auto-detect and constant-override paths)
- command: `./scripts/wp.sh php scripts/validate-adapter-toggles.php`
- args: none
- success: exit `0`, all registries report expected adapter class
- failure: non-zero or any `[FAIL]`; verify auto-detect function names and constant spelling
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

`id`: `updates.package.clean`
- goal: Build clean self-hosted updater deployment bundle without Windows ADS/Zone artifacts
- command: `./scripts/package-updates-ecomcine-clean.sh`
- args: none
- success: exit `0`; bundle created at `deploy/updates-ecomcine-clean` and optional `deploy/updates-ecomcine-clean.zip`
- failure: non-zero; stop and fix missing source files under `updates.domain.com/`
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `git.stage.paths`
- goal: Stage an explicit set of files safely without staging unrelated changes
- command: `git add -- <path1> [path2 ...]`
- args: one or more repository-relative paths required
- success: exit `0`
- failure: non-zero; stop and verify path correctness/repository state
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `git.commit.create`
- goal: Create a commit from currently staged changes
- command: `git commit -m <commit_message>`
- args: single commit message string required
- success: exit `0`
- failure: non-zero; stop and resolve hook/policy/identity issues
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `github.release.create`
- goal: Publish a GitHub release and optionally upload one or more assets
- command: `gh release create <tag> [assets...] --title <title> --notes <notes>`
- args: `tag`, `title`, `notes` required; optional asset paths
- success: exit `0`
- failure: non-zero; stop and resolve auth/tag/repository issues
- failure_class: `auth`
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

### IDE / Editor Tooling

`id`: `host.tool.install`
- goal: Install approved host CLI tools safely without freezing VS Code
- command: `./scripts/install-host-tool.sh <tool>`
- args: `tool` required; allowed values `ripgrep|jq|yq|php-cli|nodejs`
- success: exit `0`; tool binary resolves via `command -v`
- failure: exit `10` means blocked in IDE terminal (run from external WSL terminal); any other non-zero means resolve apt/sudo/runtime issue
- failure_class: `infra`
- remediation_type: `source-fix`

Guardrail:
- Do not run interactive package-manager commands directly in IDE AI terminal flow.
- Use this contract to avoid renderer hangs from high-volume interactive apt output.

`id`: `php.path.detect`
- goal: Locate the PHP CLI executable in the current WSL environment
- command: `which php || echo "PHP not found"`
- args: none
- success: exit `0`, path printed (e.g. `/usr/bin/php`); copy value into `.vscode/settings.json` → `php.validate.executablePath`
- failure: non-zero or "PHP not found"; install via `./scripts/run-catalog-command.sh host.tool.install php-cli` from external WSL terminal, then re-run
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `node.path.detect`
- goal: Locate the Node.js executable in the current WSL environment
- command: `command -v node || command -v nodejs || echo "Node not found"`
- args: none
- success: exit `0`, path printed (e.g. `/usr/bin/node` or `/usr/bin/nodejs`)
- failure: non-zero or "Node not found"; install Node runtime then re-run
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.path.detect`
- goal: Locate the Ollama executable in the current WSL environment
- command: `command -v ollama || echo "Ollama not found"`
- args: none
- success: exit `0`; path printed when available
- failure: non-zero or "Ollama not found"; install/enable Ollama runtime then re-run
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.service.start`
- goal: Start local Ollama server on `127.0.0.1:11434` in detached mode
- command: `mkdir -p logs && nohup ollama serve >> logs/ollama-server.log 2>&1 &`
- args: none
- success: exit `0`; `ollama.proxy.status` reports Ollama endpoint as reachable
- failure: non-zero; resolve Ollama runtime/path/startup issues
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.service.stop`
- goal: Stop local Ollama server processes
- command: `pkill -f 'ollama serve' || true; pkill -f '/ollama' || true`
- args: none
- success: exit `0`; Ollama endpoint is no longer reachable
- failure: non-zero; inspect process permissions and runtime state
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.service.log.tail`
- goal: Inspect recent Ollama server log lines
- command: `mkdir -p logs && touch logs/ollama-server.log && tail -n 80 logs/ollama-server.log`
- args: none
- success: exit `0`; prints recent Ollama log lines
- failure: non-zero; resolve filesystem permissions/path issues
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.windows.probe`
- goal: Probe WSL connectivity to Windows-hosted Ollama on default port `11434`
- command: `HOST="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"; echo "WSL->Windows host: ${HOST:-unknown}"; echo '--- probe localhost ---'; curl -sS -m 3 http://127.0.0.1:11434/api/version || echo 'down'; echo; echo '--- probe windows-host-ip ---'; if [[ -n "${HOST}" ]]; then curl -sS -m 3 "http://${HOST}:11434/api/version" || echo 'down'; else echo 'down'; fi`
- args: none
- success: exit `0`; prints probe results for localhost and resolved Windows host IP
- failure: non-zero; resolve shell/curl/runtime issues
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.windows.probe.full`
- goal: Probe multiple WSL-to-Windows routes for Ollama endpoint discovery
- command: `set -e; C1="127.0.0.1"; C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"; C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"; C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"; echo "Candidates: $C1 ${C2:-} ${C3:-} ${C4:-}"; for H in "$C1" "$C2" "$C3" "$C4"; do [[ -z "$H" ]] && continue; echo "--- probe $H ---"; curl -sS -m 3 "http://$H:11434/api/version" || echo down; echo; done`
- args: none
- success: exit `0`; prints connectivity result for each candidate host
- failure: non-zero; resolve shell/curl/runtime issues
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.windows.query.tags`
- goal: Query model tags from Windows-hosted Ollama using best reachable WSL route
- command: `set -e; C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"; C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"; C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"; for H in 127.0.0.1 "$C2" "$C3" "$C4"; do [[ -z "$H" ]] && continue; if curl -fsS -m 3 "http://$H:11434/api/version" >/dev/null; then echo "Using host: $H"; curl -fsS -m 8 "http://$H:11434/api/tags"; exit 0; fi; done; echo "No reachable Windows Ollama host from WSL" >&2; exit 7`
- args: none
- success: exit `0`; returns JSON tag list from reachable host
- failure: exit `7` when no reachable host; otherwise non-zero for transport/runtime issues
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.windows.query.generate`
- goal: Run a minimal non-stream inference query against Windows-hosted Ollama from WSL
- command: `set -e; MODEL="${1:-qwen3.5:9b-slim-256k}"; PROMPT="${2:-Reply with OK}"; C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"; C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"; C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"; for H in 127.0.0.1 "$C2" "$C3" "$C4"; do [[ -z "$H" ]] && continue; if curl -fsS -m 3 "http://$H:11434/api/version" >/dev/null; then echo "Using host: $H"; curl -fsS -m 30 "http://$H:11434/api/generate" -H 'Content-Type: application/json' -d "{\"model\":\"${MODEL}\",\"prompt\":\"${PROMPT}\",\"stream\":false,\"options\":{\"num_predict\":32}}"; exit 0; fi; done; echo "No reachable Windows Ollama host from WSL" >&2; exit 7`
- args: optional `model` and `prompt`
- success: exit `0`; returns JSON with `response`
- failure: exit `7` when no reachable host; otherwise non-zero for transport/runtime/model issues
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.proxy.manager.start`
- goal: Start recovered local Ollama proxy manager in detached mode under WSL2
- command: `mkdir -p logs && nohup node tools/ollama-proxy-manager.js >> logs/ollama-proxy-manager.log 2>&1 &`
- args: none
- success: exit `0`; process for `tools/ollama-proxy-manager.js` is present
- failure: non-zero; resolve Node runtime/path/script errors
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.proxy.manager.stop`
- goal: Stop local Ollama proxy manager and proxy workers
- command: `pkill -f 'tools/ollama-proxy-manager.js' || true; pkill -f 'tools/ollama-nothink-proxy.js' || true`
- args: none
- success: exit `0`; manager and worker are no longer running
- failure: non-zero; inspect process permissions and command availability
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.proxy.status`
- goal: Verify Ollama and proxy endpoints are reachable
- command: `C2="$(awk '/nameserver/{print $2; exit}' /etc/resolv.conf 2>/dev/null || true)"; C3="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"; C4="$(getent hosts host.docker.internal 2>/dev/null | awk '{print $1; exit}')"; echo '--- Ollama localhost ---'; curl -sf http://127.0.0.1:11434/api/version || echo 'down'; echo; for H in "$C2" "$C3" "$C4"; do [[ -z "$H" ]] && continue; echo "--- Ollama $H ---"; curl -sf "http://$H:11434/api/version" || echo 'down'; echo; done; echo '--- Proxy ---'; curl -sf http://127.0.0.1:11435/proxy/status || echo 'down'`
- args: none
- success: exit `0`; returns JSON status for both endpoints (or explicit `down` markers)
- failure: non-zero; inspect service health, ports, and logs
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.proxy.mode.nothink`
- goal: Set proxy to no-think mode for low-latency responses
- command: `curl -sf -X POST http://127.0.0.1:11435/proxy/mode -H 'Content-Type: application/json' -d '{"mode":"nothink"}'`
- args: none
- success: exit `0`; response includes `"mode":"nothink"`
- failure: non-zero; proxy not running or endpoint unavailable
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.proxy.mode.passthrough`
- goal: Set proxy to passthrough mode for benchmark comparison
- command: `curl -sf -X POST http://127.0.0.1:11435/proxy/mode -H 'Content-Type: application/json' -d '{"mode":"passthrough"}'`
- args: none
- success: exit `0`; response includes `"mode":"passthrough"`
- failure: non-zero; proxy not running or endpoint unavailable
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `ollama.proxy.log.manager.tail`
- goal: Inspect recent proxy-manager log lines for startup/runtime diagnostics
- command: `mkdir -p logs && touch logs/ollama-proxy-manager.log && tail -n 80 logs/ollama-proxy-manager.log`
- args: none
- success: exit `0`; prints recent manager log lines
- failure: non-zero; resolve filesystem permissions/path issues
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.proxy.log.proxy.tail`
- goal: Inspect recent no-think proxy worker log lines for request/runtime diagnostics
- command: `mkdir -p logs && touch logs/ollama-nothink-proxy.log && tail -n 80 logs/ollama-nothink-proxy.log`
- args: none
- success: exit `0`; prints recent proxy log lines
- failure: non-zero; resolve filesystem permissions/path issues
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `ollama.proxy.smoke`
- goal: Perform a minimal end-to-end request through proxy OpenAI-compatible endpoint
- command: `curl -sf http://127.0.0.1:11435/v1/chat/completions -H 'Content-Type: application/json' -d '{"model":"qwen3.5:9b","messages":[{"role":"user","content":"Reply with OK"}],"stream":false}'`
- args: optional model override as first arg
- success: exit `0`; JSON response contains `choices`
- failure: non-zero; proxy/ollama/model endpoint unavailable or request failed
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `qa.playwright.install`
- goal: Install/refresh the repo-local Playwright harness used by IDE AI self-tests
- command: `./scripts/playwright-selftest.sh install`
- args: optional passthrough flags
- success: exit `0`; `tools/playwright/node_modules` exists and Playwright Chromium browser is installed
- failure: non-zero; resolve missing Node/npm runtime or npm install errors before proceeding
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `qa.playwright.test.smoke`
- goal: Run deterministic smoke checks against local WordPress runtime and fail fast on hard JS/runtime regressions
- command: `./scripts/playwright-selftest.sh smoke`
- args: optional Playwright test flags
- success: exit `0`; smoke test suite passes
- failure: non-zero; inspect test output and HTML report under `tools/playwright/playwright-report`
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `qa.playwright.test.interactions`
- goal: Run generic scenario-driven clickable interaction tests for active feature flows (menu toggles, modal open/close, account actions, and similar UI contracts)
- command: `./scripts/playwright-selftest.sh interactions [scenario_file] [playwright flags...]`
- args: optional first arg `scenario_file`; if omitted defaults to `tools/playwright/tests/fixtures/interactions.vendor-store.json`
- scenario schema: supports fallback selectors (`target` array), optional steps (`optional`), scoped lookup (`within`), and open shadow DOM selectors (`shadowHosts` + `shadowTarget`)
- reuse guidance: `specs/operational-runbooks/playwright-interactions-reuse-guide.md`
- success: exit `0`; all configured interaction steps pass
- failure: non-zero; inspect failed step and update source behavior or scenario selectors/contract
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `qa.playwright.test.debug`
- goal: Run Playwright in trace-rich debug mode for reproducible failure diagnostics
- command: `./scripts/playwright-selftest.sh debug`
- args: optional Playwright test flags
- success: exit `0`; debug suite passes
- failure: non-zero; analyze retained trace/screenshots/videos and remediate source-level cause
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `qa.playwright.test.headed`
- goal: Run Playwright headed when visual interaction timing needs inspection
- command: `./scripts/playwright-selftest.sh headed`
- args: optional Playwright test flags
- success: exit `0`
- failure: non-zero; inspect headed run output and follow debug mode workflow
- failure_class: `contract`
- remediation_type: `source-fix`

`id`: `qa.playwright.report`
- goal: Open Playwright HTML report for latest run
- command: `./scripts/playwright-selftest.sh report`
- args: none
- success: exit `0`
- failure: non-zero; ensure tests have been executed and report artifacts exist
- failure_class: `tooling`
- remediation_type: `source-fix`

`id`: `wp.debug.log.tail`
- goal: Read recent WordPress debug log lines from local runtime
- command: `./scripts/wp.sh log [lines]`
- args: optional integer line count (default 80)
- success: exit `0`; log output is printed
- failure: non-zero; verify runtime health and WP debug log path/config
- failure_class: `config`
- remediation_type: `source-fix`

`id`: `wp.debug.php.info`
- goal: Surface PHP/WP-CLI runtime diagnostics for container-level debugging
- command: `./scripts/wp.sh wp --info`
- args: none
- success: exit `0`; info output includes PHP binary/version and WP-CLI context
- failure: non-zero; resolve container/bootstrap/runtime mismatch
- failure_class: `infra`
- remediation_type: `source-fix`

`id`: `debug.snapshot.collect`
- goal: Capture a single markdown evidence bundle (infra checks, WP logs, Docker logs, PHP info, git status)
- command: `./scripts/collect-debug-snapshot.sh [lines]`
- args: optional integer line count (default 200)
- success: exit `0`; prints path under `logs/debug-snapshots/`
- failure: non-zero; resolve script/runtime permissions or dependency issues
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
