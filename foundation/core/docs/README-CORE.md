---
title: Master Core Staging
type: foundation-guide
status: active
authority: primary
intent-scope: workspace-setup,planning,implementation,maintenance
phase: extraction
last-reviewed: 2026-03-14
---

# Master Core Staging

## Purpose

This folder defines the reusable, stack-agnostic development foundation that should later live in the separate `master-core` repository.

It is the upstream candidate for:

- AI context routing methodology
- canonical workflow and lifecycle guidance
- reusable feature package templates
- sync and handover automation
- generic file-safety and validation helpers

## Must Belong Here

- generic guidance that applies across multiple app repositories
- templates that are product-neutral
- shared scripts that do not assume WordPress runtime structure
- schema or metadata files that describe reusable conventions

## Must Not Belong Here

- WordPress runtime files
- Docker services specific to WordPress
- plugin packaging logic
- app-specific feature inventory, branding, or product logic

## Local Migration Rule

During staging inside this repository:

- add new reusable assets here first
- do not move current root guidance until equivalent adapters exist
- keep root files stable while this folder proves its shape