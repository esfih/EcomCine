# Foundation

This folder receives imported shared layers via `scripts/bootstrap-foundation.sh`.

- `foundation/core` — reusable devops, AI-context, validation (from `master-core`)
- `foundation/wp` — Docker runtime, WP-CLI patterns, packaging (from `wp-overlay`)

**Do not edit files here directly.** Improve them upstream and re-pull.

Run `./scripts/bootstrap-foundation.sh` to populate this folder.
