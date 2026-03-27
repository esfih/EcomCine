---
title: DevOps Tech Stack
type: root-guidance
status: active
authority: primary
intent-scope: workspace-setup,implementation,maintenance
phase: setup
last-reviewed: 2026-03-14
---

# DevOps Tech Stack

## Purpose

This document defines the canonical local workstation and runtime stack for the current app repository.

## Split Responsibility Rule

- document cross-app baseline assumptions here or via imported `foundation/core`
- document stack-specific runtime assumptions through the relevant overlay
- document only app-specific deviations at the app repo root

## Baseline Categories

- primary authoring shell
- secondary shell
- runtime/container baseline
- validation execution baseline
- IDE prompt parity rule
- per-worktree isolation model

## App-Local Deviation Section

Each app repository should explicitly list:

- extra required tools
- excluded tools
- runtime exceptions
- validation exceptions