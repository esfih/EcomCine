---
title: Shared Layer Modus Operandi
type: foundation-guide
status: active
authority: primary
intent-scope: workspace-setup,planning,implementation,maintenance
phase: extraction
last-reviewed: 2026-03-16
---

# Shared Layer Modus Operandi

## Purpose

Define how app-local plugin repositories should work with the shared development layers.

## Repositories In The Model

### 1. Template Repository

Purpose:

- start a new app-local plugin repository with the correct root adapters, IDE AI guidance, and bootstrap scripts

Should contain:

- root adapter docs
- root Copilot/IDE instructions
- bootstrap and subtree sync scripts
- VS Code workspace helpers
- minimal local specs scaffolding

Should not become the long-term home of reusable framework logic.

### 2. `master-core`

Purpose:

- own reusable AI-context methodology, workflow rules, validation helpers, and generic development scripts

### 3. `wp-overlay`

Purpose:

- own reusable WordPress runtime, packaging, validation, and plugin-scaffold assets

### 4. App-Local Product Repository

Purpose:

- own plugin/product code, feature truth, and app-specific runtime decisions

## Operating Rule

When a reusable improvement is discovered inside an app repo:

1. prove it locally if needed
2. extract the reusable part upstream into `master-core` or `wp-overlay`
3. pull that upstream change back into app repos with subtree sync
4. keep only product-specific decisions local

## Subtree Rule

App repos should import:

- `foundation/core` from `master-core`
- `foundation/wp` from `wp-overlay`

Why subtree instead of template-copy-only:

- new repos can start quickly from a template
- shared layers still have a clean update path later
- each app repo remains a normal repository without submodule friction

## Root Adapter Rule

Keep these files local to each app repo even when their structure is templated:

- `README-FIRST.md`
- `WORKSPACE-SETUP.md`
- `DEVOPS-TECH-STACK.md`
- `.github/copilot-instructions.md`

These files should stay thin and route into imported shared layers plus local product truth.