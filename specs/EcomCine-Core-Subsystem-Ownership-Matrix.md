---
title: EcomCine Core Subsystem Ownership Matrix
type: feature-architecture
status: planning
completion: 60
priority: critical
authority: primary
intent-scope: planning,spec-generation,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: support the start of deployment with a frozen Wave 1 boundary and closed dependency chain
next-focus: track implementation progress against the approved deployment packet set
ide-context-token-estimate: 2300
token-estimate-method: approx-chars-div-4
session-notes: >
  This document operationalizes the EcomCine Core rebuild blueprint by mapping real
  repository subsystems to target ownership, migration waves, and cutover gates.
  It is intended to guide sequencing, not to describe implementation details.
known-issues:
  - Several public-facing modules already contain EcomCine-first behavior, but the ownership boundary is still mixed.
  - Wave 1 is now decision-ready, but query and template cutovers still need parity and rollback artifacts before implementation.
  - CTA and adapter orchestration remain blocked on downstream decisions even though Wave 1 is now frozen.
do-not-repeat:
  - Do not score a subsystem as core-owned because its UI looks branded if its queries or URLs are still legacy-shaped.
  - Do not cut over a subsystem before its rollback path and parity oracle are named.
related-files:
  - ./specs/EcomCine-Core-Rebuild-Blueprint.md
  - ./specs/EcomCine-Core-Decision-Log.md
  - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
  - ./specs/EcomCine-Core-Deployment-README.md
  - ./ecomcine/includes/core/runtime/class-runtime-adapters.php
  - ./ecomcine/includes/functions.php
  - ./ecomcine/modules/tm-store-ui/
  - ./ecomcine/modules/tm-account-panel/
  - ./ecomcine/modules/tm-media-player/
  - ./ecomcine/modules/tm-vendor-booking-modal/
---

# EcomCine Core Subsystem Ownership Matrix

## Purpose

This document translates the rebuild blueprint into an operational migration map.

It answers four practical questions:

1. Which subsystem is actually responsible for a given outcome today?
2. Which subsystem should own it in the target architecture?
3. How risky is the cutover?
4. In what order should the team move it?

Use this document to decide sequence, staffing, parity coverage, and release gates.

---

## Scoring Model

### Ownership Status

- `Core-owned`: EcomCine is already the canonical runtime authority.
- `Mixed`: EcomCine owns part of the experience, but key queries, URLs, persistence, or templates still leak legacy behavior.
- `Legacy-shaped`: the user outcome still depends primarily on Dokan/Woo/Bookings-era structures.

### Risk Scales

- `Low`: limited blast radius, easy rollback, or already isolated behind a wrapper.
- `Medium`: noticeable blast radius, but bounded by one module family or one public flow.
- `High`: public runtime, shared data model, or a cutover that can break multiple product modes at once.

### Wave Intent

- `Wave 0`: governance and architectural freeze
- `Wave 1`: URL, listing object, and query sovereignty
- `Wave 2`: public payloads, templates, and account ownership flows
- `Wave 3`: CTA and adapter orchestration
- `Wave 4`: canonical persistence and activity records
- `Wave 5`: decommissioning and removal

---

## Matrix

| Subsystem | Current Runtime Gravity | Canonical Owner Target | Primary Repo Surface | Ownership Status | Legacy Dependency | Adapter Readiness | Cutover Difficulty | Regression Risk | Target Wave | Decision Dependencies |
|---|---|---|---|---|---|---|---|---|---|---|
| Listing URL and route resolution | Mixed; EcomCine route helpers exist, but page-resolution rules and alias expectations still carry legacy shape | EcomCine Core URL service and rewrite policy | `ecomcine/includes/functions.php`, `ecomcine/includes/core/runtime/`, `tm-account-panel` page context | Mixed | Medium | High | Medium | High | Wave 1 | Closed: `CD-01` |
| Canonical Listing object and ownership relation | Hybrid user/meta plus `tm_vendor` behavior; ownership rules are now frozen conceptually but not yet implemented | Listing + ListingOwnership as explicit core domain objects | `tm-store-ui` vendor profile flows, `tm-account-panel`, importer/bootstrap logic | Mixed | High | Medium | High | High | Wave 1 | Closed: `CD-02`, `CD-06` |
| Listing query, search, and filter engine | Still partly Dokan/Woo-shaped in outcome design and lookup assumptions | Core-owned listing query service with parity oracle | `tm-store-ui` shortcodes, maps, listing/profile loaders, runtime adapters | Legacy-shaped | High | Medium | High | High | Wave 1 | Closed: `CD-01`, `CD-02`, `CD-06` |
| Listing profile payload, completeness, and publish state | EcomCine-heavy, but still linked to older vendor/profile assumptions | ListingProfile service and publish-state policy | `tm-store-ui/includes/vendor-profile/`, completeness/admin helpers | Mixed | Medium | Medium | High | High | Wave 2 | `CD-02`, `CD-06` |
| Categories, attributes, and directory registry | Shared between EcomCine and compatibility-shaped flows | Core-owned listing category and attribute registry | `tm-store-ui`, `dokan-category-attributes/` | Mixed | High | Medium | High | Medium | Wave 2 | `CD-02`, `CD-06` |
| Media assets, showcase, and player state | Mostly EcomCine-owned already | Experience shell and media services | `tm-media-player/`, listing media helpers | Core-owned | Low | High | Medium | Medium | Wave 2 | None |
| Public view models and templates | Branded by EcomCine but still contain legacy calls in places | Adapter-free EcomCine view-model layer | `tm-store-ui/templates/`, `tm-store-ui/includes/template-helpers.php` | Mixed | Medium | Medium | High | High | Wave 2 | `CD-01`, `CD-02` |
| Account workspace and onboarding | Default-WP adapters exist, but listing claim/edit flow is not fully frozen | Core-owned account shell with Listing ownership workflow | `tm-account-panel/` default-wp and compatibility adapters | Mixed | Medium | High | Medium | Medium | Wave 2 | `CD-02`, `CD-06` |
| Invitations, share links, and public landing links | Mostly EcomCine-owned and already central to public routing | Core Invitation/Share service | `ecomcine/includes/functions.php`, account/share helpers | Core-owned | Low | High | Low | Medium | Wave 2 | `CD-01` |
| CTA orchestration and offer attachment model | Mixed; cinematic shell exists, but offer attachment is still adapter-shaped in places | Core CTA model plus ListingOfferRelation | `tm-store-ui`, `tm-vendor-booking-modal`, commerce adapters | Mixed | Medium | Medium | Medium | High | Wave 3 | Closed: `CD-05` |
| Booking, checkout launch, and handoff behavior | Largely compatibility-backed, though contracts already exist | Narrow adapter contracts graded by embed depth | `tm-vendor-booking-modal/`, `ecomcine/includes/core/adapters/` | Mixed | High | High | Medium | High | Wave 3 | Closed: `CD-03`, `CD-04`, `CD-05` |
| Orders, bookings, and account activity persistence | Early default-WP surfaces exist, but not yet the canonical program center | Core persistence policy with adapter-backed activity records | `tm-account-panel` default-wp CPTs, booking/order adapters | Mixed | Medium | Medium | High | Medium | Wave 4 | Closed: `CD-02`, `CD-03`, `CD-04` |
| Runtime mode selection and capability detection | Already EcomCine-owned conceptually | Core runtime gatekeeper | `ecomcine/includes/core/class-plugin-capability.php`, runtime adapters, admin settings | Core-owned | Low | High | Low | Low | Wave 0 | `CD-07` |
| Legacy compatibility adapters and decommission path | Necessary today, but still too broad | Explicit adapters only, then removal | compatibility adapters across modules, `class-commerce-adapter-woodokan.php` | Mixed | High | Medium | Medium | Medium | Wave 5 | All prior decisions closed |

---

## Wave 1 Boundary Freeze

The following Wave 1 boundary decisions are now fixed for planning and sequencing.

### Route Contract

- the canonical public base for fresh installs is `profile`
- the route is owned conceptually as a Listing profile route
- legacy bases such as `person`, `talent`, and prior terminology-derived singular bases remain redirect aliases only

### Storage Contract

- Phase 1 uses the existing `tm_vendor` CPT as the single canonical Listing storage object
- Phase 1 does not introduce a second Listing CPT
- user meta remains compatibility projection or lookup support, not a separate source of truth

### Type Contract

- V1 first-class Listing types are `person`, `company`, and `venue`
- `person` is the default onboarding and language baseline
- all three types share one core Listing model and one cinematic shell, with restrained type-specific extensions only where necessary

### Planning Consequence

- Wave 1 may now freeze ownership and cutover sequencing without reopening the naming contract or object-boundary debate
- Wave 2 payload and onboarding planning must assume these three types are real, not hypothetical

## Migration Map by Wave

## Wave 0 - Freeze the Rules Before Moving Runtime Ownership

Objectives:

- freeze vocabulary
- freeze decision order
- freeze cutover criteria
- confirm which product outcomes must remain live during migration

Required artifacts:

- blueprint
- ownership matrix
- decision log
- feature-flag naming policy for shadow mode and cutover mode

Exit gate:

- Wave 1 planning is now unlocked because `CD-01`, `CD-02`, and `CD-06` are closed
- implementation work still requires parity-oracle definition and rollback plans per subsystem

## Wave 1 - Listing Sovereignty

Subsystems:

- Listing URL and route resolution
- Canonical Listing object and ownership relation
- Listing query, search, and filter engine

Why first:

- if these stay legacy-shaped, every later subsystem remains structurally dependent even if the UI looks independent

Required release gates:

- canonical URL policy closed
- canonical Listing object direction closed
- V1 Listing type scope closed
- parity oracle defined for listing lookup and search/filter outcomes
- rollback rules documented for public route resolution

## Wave 2 - Public Runtime Payloads

Subsystems:

- Listing profile payload, completeness, and publish state
- Categories, attributes, and directory registry
- Public view models and templates
- Account workspace and onboarding
- Invitations and share flows
- Media/showcase alignment where needed

Why second:

- once Listing sovereignty exists, the public shell can stop leaking legacy assumptions and account flows can become ownership-aware instead of vendor-shaped

Required release gates:

- templates render only EcomCine view models for cutover paths
- listing completeness and publish rules are defined against the Listing model
- account claim/edit flows attach to Listing ownership, not implied vendor state

## Wave 3 - CTA and Adapter Narrowing

Subsystems:

- CTA orchestration and offer attachment model
- Booking, checkout launch, and handoff behavior

Why third:

- transaction behavior should plug into a stable listing shell rather than define listing identity

Required release gates:

- Core Only CTA baseline is closed
- adapter grading policy is closed
- unsupported adapter capabilities degrade intentionally

## Wave 4 - Canonical Persistence and Activity Records

Subsystems:

- Orders, bookings, and account activity persistence

Why fourth:

- persistence should follow a stable ownership model and adapter policy rather than drive them prematurely

Required release gates:

- dual-read and staged-write policy defined
- activity surfaces declare what is core-owned versus adapter-owned
- account workspace reporting path does not assume Woo/Dokan tables directly

## Wave 5 - Decommission Compatibility Ownership

Subsystems:

- Legacy compatibility adapters and decommission path

Why last:

- removal should happen only after the replacement is semantically validated, not after transport-level success alone

Required release gates:

- public runtime works in Core Only mode for the targeted subsystem set
- rollback is no longer needed for retired branches
- deletion list is explicit and reviewed

---

## Recommended First Fully Core-Owned Vertical Slice

The first fully core-owned slice should be:

1. Listing URL generation and route resolution
2. Listing object direction
3. Listing query for one public directory surface
4. One adapter-free public listing card and listing page view model

Reason:

- this is the smallest slice that proves EcomCine owns identity, lookup, and rendering together rather than only branding the output

Do not start with checkout.
Checkout is commercially important, but architecturally it should prove attachment to a stable listing shell, not define the shell.

---

## Cutover Gate Checklist Per Subsystem

Every subsystem must clear all of the following before being called core-owned:

1. Canonical owner is named.
2. Decision dependencies are closed.
3. Parity oracle is named.
4. Rollback path is named.
5. Failure mode without legacy plugins is acceptable.
6. Public template path does not bypass the new owner.
7. Data source of truth is explicit.

If any one of these is missing, the subsystem remains mixed, even if it is visually working.