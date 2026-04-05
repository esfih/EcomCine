# Demo Data Release Workflow

Last updated: 2026-04-05
Owner: Engineering workflow

## Objective

Define the canonical build and publish workflow for EcomCine demo-data packs so the remote importer can fetch a GitHub-hosted zip whose `media/` tree semantically matches the vendor payload.

## Authoritative Sources

- `demos/<pack-id>/vendor-data.json`
- `demos/<pack-id>/media-original/`
- `demos/<pack-id>/media/`
- `demos/manifest.json`

If `media-original/` no longer contains MP4 sources in the current checkout, the rebuild workflow may use the newest archival `media-backup-*` directory that still contains the original MP4s.

## Non-Canonical Working Directories

These may exist during rebuilds, but they are not release artifacts:

- `demos/<pack-id>/media-webm/`
- `demos/<pack-id>/media-webm-*`
- `demos/<pack-id>/media-backup-*`

The release zip must only package:

- `vendor-data.json`
- `media/`

## Required Command Path

Always use catalog commands:

1. `./scripts/run-catalog-command.sh demos.media.rebuild <pack-id>`
2. `./scripts/run-catalog-command.sh demos.release <version> --push`
3. `./scripts/run-catalog-command.sh data.vendors.import.demo <zip-url>` or import from WP Admin
4. `./scripts/run-catalog-command.sh qa.playwright.test.debug tests/demo-data-import-response.spec.ts`

## Canonical Media Build Rules

1. `media-original/` is the preferred source of truth for original video inputs and source non-video assets.
2. WebM conversion must preserve vendor-relative paths.
3. `media/` is the canonical release-ready tree assembled from source non-video assets plus rebuilt vendor WebMs.
4. `vendor-data.json` references must resolve against the final `media/` tree.
5. Use a fresh semver and release tag when publishing corrected media. Do not rely on overwriting an older release tag as the normal validation path.

Recovery rule:

- If `media-original/` has already been normalized to `.webm` outputs and no longer contains MP4 inputs, rebuild from the latest archival `media-backup-*` source tree instead of trying to reverse the release-ready `media/` tree.

## Root-Cause Guardrails

The failure mode fixed in April 2026 was path flattening during demo video conversion.

Wrong pattern:

- converting all vendor videos into one shared output directory by basename such as `video1.webm`
- staging release media from that flattened directory

Correct pattern:

- convert each source MP4 to a matching vendor-relative `.webm` path
- assemble `media/` from source non-video assets plus those vendor-relative WebMs
- release from that canonical `media/` tree

## Semantic Validation Oracle

Transport success is not sufficient. A release is only valid when all of the following are true:

1. The GitHub asset downloads from the new manifest URL.
2. The importer completes with `[demo-import] DONE` and expected vendor counts.
3. The admin AJAX import response returns HTTP 200 with `success: true`.
4. Distinct vendors resolve to distinct imported video payloads when the source pack contains distinct source videos.
5. The zip contains `vendor-data.json` plus `media/`, not `media-original/` or temp rebuild directories.

## Working Example

For `topdoctorchannel`:

```bash
./scripts/run-catalog-command.sh demos.media.rebuild topdoctorchannel
./scripts/run-catalog-command.sh demos.release 0.1.58 --push
./scripts/run-catalog-command.sh data.vendors.import.demo https://github.com/esfih/EcomCine/releases/download/v0.1.58-demo-data/ecomcine-demo-data-0.1.58.zip
./scripts/run-catalog-command.sh qa.playwright.test.debug tests/demo-data-import-response.spec.ts
```

## Output Expectations

- `demos.media.rebuild` leaves `demos/<pack-id>/media/` ready for packaging
- `demos.release --push` publishes a GitHub release and updates `demos/manifest.json`
- import validation updates vendors without importer fatals or malformed AJAX output

## Files Touched By The Canonical Workflow

- `scripts/rebuild-demo-media.sh`
- `scripts/convert-videos-fast.sh`
- `scripts/prepare-demo-media.sh`
- `scripts/build-demos-release.sh`
- `demos/manifest.json`
- `tools/playwright/tests/demo-data-import-response.spec.ts`