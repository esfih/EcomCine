---
title: Foundation Migration Guide
type: foundation-guide
status: active
authority: primary
intent-scope: workspace-setup,maintenance,refactor
phase: extraction
last-reviewed: 2026-03-14
---

# Foundation Migration Guide

## Goal

Convert the current monorepo-style workspace into an app repository that consumes a shared `master-core` layer and a shared `wp-overlay` layer.

## Phase Order

1. Stage reusable assets under `foundation/` inside the current app repo.
2. Freeze ownership boundaries between reusable assets and app-local assets.
3. Create upstream repositories from the staged `foundation/core` and `foundation/wp` content.
4. Reconnect the app repo to those upstreams using Git subtree.
5. Replace any remaining duplicated root guidance with thin local adapters.

## Safe First Migration Slice

1. create templates and staging docs under `foundation/`
2. create sync wrapper scripts
3. keep all current root files authoritative
4. avoid moving product code or runtime files yet

## Current Local Upstream Setup

The staged shared layers now also exist as local sibling repositories:

- `https://github.com/esfih/master-core`
- `https://github.com/esfih/wp-overlay`

The app repo currently references them through these local remotes:

- `master-core-local`
- `wp-overlay-local`

## Current Boundary

The local sibling repositories have been initialized and populated, but they do not yet contain commits.

That means:

- local remote wiring is real
- dry-run subtree commands are now stable
- filesystem-level mirror sync is now active and verified in both sibling repos
- live subtree pull/push history exchange is not yet available

The next enabling condition is an explicit first commit in each sibling repository plus a committed foundation state in the app repository.

## Current Verified State

- `foundation/core` should sync with `https://github.com/esfih/master-core`
- `foundation/wp` should sync with `https://github.com/esfih/wp-overlay`

Local clones such as `C:/dev/master-core` and `C:/dev/wp-overlay` are optional convenience mirrors, not the canonical upstream defaults.
- the sibling repos currently act as local upstream working trees
- the app repo remains the source of truth until baseline commits are created upstream

## Extraction Rule

When a file is promoted upstream:

- first ensure the app repo has a local root adapter or local replacement path
- then move the reusable source into the upstream candidate area
- then update the root/local adapter to reference the imported foundation path

## Root Adapter Rule

The app repo should keep these root-level entrypoints even after subtree adoption:

- `README-FIRST.md`
- `WORKSPACE-SETUP.md`
- `DEVOPS-TECH-STACK.md`
- `.github/copilot-instructions.md`

These should become thin, app-local adapter files rather than large duplicated documents.