---
title: Workspace Setup Guide
type: root-guidance
status: active
authority: primary
intent-scope: workspace-setup
phase: setup
last-reviewed: 2026-03-14
---

# Workspace Setup

## Purpose

This file defines the standard setup and validation flow for a new repository workspace or worktree.

Use this template as the root app-local setup entrypoint.

## Split Responsibility Rule

- keep generic setup discipline in the app-local file or imported `foundation/core`
- route stack-specific setup to the relevant overlay, such as `foundation/wp`
- keep product-specific runtime mapping in the app-local repository

## Minimum Setup Output

1. confirm workspace identity and branch/worktree role
2. confirm required host tools
3. confirm runtime wiring for the chosen stack
4. confirm app-local feature inventory readiness
5. confirm testing readiness for the active feature set

## Suggested Local Sections

1. Workspace identity and git state
2. Shared foundation status
3. Stack overlay setup
4. Product runtime mapping
5. Feature inventory reconciliation
6. Testing readiness