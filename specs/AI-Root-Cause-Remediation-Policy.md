# AI Root-Cause Remediation Policy

Last updated: 2026-03-27
Owner: Engineering Governance
Scope: Mandatory decision and validation rules for IDE AI and developer changes.

## Purpose

Prevent symptom-only fixes from entering the codebase.

This policy applies to:

- terminal actions
- code changes
- configuration changes
- release and hotfix workflows

## Core Rule

Every fix must pass the Root-Cause Decision Gate before implementation.

Allowed remediation types:

1. `source-fix`
- fixes the authoritative source of the issue (logic, schema, config, contract, runtime mapping)

2. `mitigation`
- temporary containment only when source-fix cannot be completed immediately

`mitigation` is allowed only with explicit justification and removal plan.

## Root-Cause Decision Gate (Mandatory)

Before changing files, record:

1. Problem statement
2. Verified root cause
3. Chosen remediation type (`source-fix` or `mitigation`)
4. Validation oracle (how semantic success will be proven)

If remediation type is `mitigation`, you must also record all required mitigation fields (below).

## Required Mitigation Fields

All mitigation changes must include:

1. `Root-Cause:`
- the real source issue not yet fixed

2. `Mitigation-Reason:`
- why source-fix is blocked now

3. `Removal-Trigger:`
- exact condition that requires mitigation removal

4. `Follow-Up-Issue:`
- tracking reference for the source-fix

Without all four fields, mitigation changes are not allowed.

## Terminal Action Rules

1. Use canonical command contracts from `specs/IDE-AI-Command-Catalog.md`.
2. If a command does not exist in the catalog, stop and request a new contract entry.
3. Do not improvise ad-hoc terminal commands for task execution.
4. Exit code is canonical pass/fail signal.
5. Warning text is diagnostic context, not pass/fail authority.

## Code Action Rules

1. Prefer source-fix over fallback or masking behavior.
2. Temporary fallbacks must be explicitly marked as mitigation.
3. Any mitigation must include a cleanup path and validation evidence.
4. Do not mark feature complete on transport success alone; require semantic validation.

## Validation Requirements

For every fix:

1. Validate transport success (command/request executed)
2. Validate semantic success (state/contract behavior matches oracle)
3. Confirm no new regressions in impacted area

If semantic validation is missing, completion is not allowed.

## Commit Policy

Each commit message must include:

- `Remediation-Type: source-fix` or `Remediation-Type: mitigation`

If remediation type is `mitigation`, commit message must also include:

- `Root-Cause:`
- `Mitigation-Reason:`
- `Removal-Trigger:`
- `Follow-Up-Issue:`

Repository hooks should enforce this policy.

## Review Checklist

Reviewers must confirm:

1. Is this a source-fix or a mitigation?
2. If mitigation, are all mitigation fields present and valid?
3. Does validation prove semantic success, not only transport success?
4. Is there a removal path for any temporary mitigation?

## Anti-Patterns

Do not:

- hide an error without proving root cause
- ship cosmetic patches as final fixes
- treat warning suppression as source remediation
- bypass command contracts with ad-hoc terminal execution
