---
title: Repository Context Router
type: root-guidance
status: active
authority: primary
intent-scope: all
phase: setup
current-focus: route AI sessions to the minimum safe guidance set for the active mode
last-reviewed: 2026-03-14
---

# README FIRST

## Purpose

This is the first file an AI model should read before doing meaningful work in this repository.

Its purpose is to:

- route the model to only the files needed for the current task
- reduce unnecessary context loading
- identify which documents are authoritative
- separate reusable process guidance from app-local product truth

## Product Snapshot Placeholder

Replace this section in each app repository with the local product identity.

Suggested fields:

- product type
- target user
- runtime assumptions
- deployment constraints

## Core Rule

Do not load all guidance files by default.

First determine the current mode.

Then load only the minimum required files.

## Foundation Routing

When the repository consumes shared foundation layers:

- root-level files remain the first entrypoints for IDEs and humans
- root-level files may point to imported guidance under `foundation/core` and `foundation/wp`
- app-local specs remain authoritative for product behavior

## Required App-Local Truth

The following stay local to each app repository:

- feature inventory
- feature packages
- product architecture specifics
- release manifests
- runtime credentials and test-environment notes