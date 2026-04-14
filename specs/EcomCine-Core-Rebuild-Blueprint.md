---
title: EcomCine Core Rebuild Blueprint
type: architecture-plan
status: planning
completion: 85
priority: critical
authority: primary
intent-scope: planning,spec-generation,implementation,refactor
phase: phase-1
last-reviewed: 2026-04-09
current-focus: close the planning and preparation phase with an approved Wave 1 deployment packet set
next-focus: begin controlled Wave 1 implementation and deployment
ide-context-token-estimate: 2600
token-estimate-method: approx-chars-div-4
session-notes: >
  This document is the working blueprint for the formal EcomCine Core rebuild inside
  the current product. It is intended to be a serious collaborative planning pad, not
  a retrospective note. It reframes the existing adapter and portability work into a
  stricter product architecture with EcomCine Core as the only canonical runtime.
known-issues:
   - Existing runtime adapters and default-WP paths exist, but templates and query paths still leak Dokan/Woo assumptions.
   - The product vocabulary still mixes legacy terms such as vendor/store with the intended listing/profile/showcase model.
   - Public UI parity has been recaptured faster than underlying data/query ownership.
do-not-repeat:
  - Do not continue treating direct third-party calls in templates as acceptable technical debt.
  - Do not attempt a big-bang rewrite in a separate codebase before the canonical domain model is frozen.
related-files:
   - ./technical-documentation.md
   - ./specs/WP-Default-Adapter-Refactoring-Plan.md
   - ./specs/EcomCine-Absolute-Portability.md
   - ./specs/Plugin-Dependency-Feature-Gating-Plan.md
   - ./specs/EcomCine-Core-Subsystem-Ownership-Matrix.md
   - ./specs/EcomCine-Core-Decision-Log.md
   - ./specs/EcomCine-Core-Wave-1-Cutover-Plan.md
   - ./specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md
   - ./specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md
   - ./specs/EcomCine-Core-Wave-1-Rollback-Checklist.md
   - ./specs/EcomCine-Core-Deployment-README.md
   - ./ecomcine/includes/core/runtime/class-runtime-adapters.php
   - ./ecomcine/includes/admin/class-admin-settings.php
---

# EcomCine Core Rebuild Blueprint

## Purpose

This document is the formal working blueprint for rebuilding EcomCine Core inside the current product.

It exists to prevent the team from continuing a reactive pattern of discovering missed Dokan, WooCommerce, or WooCommerce Bookings dependencies only after a bug appears. The goal is to define the product boundary, the target architecture, the migration program, and the cutover gates before more code is moved.

This is a planning artifact first. It should be edited as decisions sharpen.

---

## Executive Direction

**Recommended strategy:** rebuild EcomCine Core in place using a strangler pattern.

Do not create a separate greenfield product in another repository.
Do not continue accepting split ownership between EcomCine and third-party plugins.

Instead:

1. Freeze a canonical EcomCine-owned domain model.
2. Route all public runtime behavior through EcomCine-owned services and view models.
3. Push Dokan, WooCommerce, FluentCart, EDD, WooCommerce Bookings, and future commerce systems behind narrow adapter contracts.
4. Cut over subsystem by subsystem under feature flags and runtime gates.

The target is not “replace every commerce plugin.”
The target is “make EcomCine fully functional on naked WordPress, then let specialized commerce systems plug into the shell when present.”

---

## Product Definition

### What EcomCine Is

EcomCine is:

1. A WordPress-native cinematic experience shell.
2. A fullscreen media-first UI/UX system where video is the literal and subjective background layer.
3. A shell that renders listing information, CTA layers, tabs, overlays, panels, and transactional surfaces on top of that cinematic canvas.
4. A custom CPT, custom-fields, routing, template, and theme-orchestration system that can run on a fresh WordPress install with no marketplace plugin.
5. A direct checkout / watch-and-transact orchestration layer that can embed or bridge specialized commerce capabilities without becoming a commerce engine itself.

### User-Facing Product Outcomes

The product should be described externally as one platform with graduated capability outcomes, not as disconnected internal architectures.

#### 1. Cinematic Directory

Stack:

- fresh WordPress
- EcomCine only

Outcome:

- same cinematic listing, profile, showcase, overlays, tabs, and location/navigation system
- CTA is contact-driven rather than transaction-driven
- a listing may represent a human, company, venue, team, practice, agency, or other profile type

#### 2. Cinematic Store

Stack:

- WordPress
- EcomCine
- one commerce adapter, such as WooCommerce first and later FluentCart / EDD / others

Outcome:

- cinematic directory experience remains visually the same
- CTA becomes product-linked and launches EcomCine’s direct checkout shell
- the key relationship is between a listing and one or more commerce-backed product offers

#### 2.1. Cinematic Store + Bookings

Outcome:

- same as Cinematic Store
- a listing may expose one or more booking CTAs in addition to product CTAs
- products and bookings are not mutually exclusive on a listing

#### 3. Cinematic Marketplace

Outcome:

- same cinematic store shell
- multi-owner / multi-seller / marketplace operations are enabled behind adapter-backed capabilities
- marketplace complexity must remain outside the core cinematic model whenever possible

#### 4. Cinematic Marketplace + Bookings

Outcome:

- same as Cinematic Marketplace
- listings may expose product CTAs, booking CTAs, or both
- this is the most complex configuration, but it should still be a configuration of the same core platform rather than a separate architecture

### What EcomCine Is Not

EcomCine is not:

1. A full ecommerce engine.
2. A replacement for order management, payment gateways, accounting, tax logic, or advanced booking engines.
3. A Dokan skin.
4. A WooCommerce child customization.
5. A system whose canonical data model depends on any single commerce plugin.

### Product Boundary Rule

**EcomCine must own every layer required to deliver the cinematic experience out of the box on naked WordPress.**

Third-party systems may supply advanced commerce capabilities, but they must not own EcomCine’s core routing, visual shell, identity model, profile model, overlay data, or public interaction structure.

### Canonical Public Entity Decision

**The canonical public entity should be the Listing, not the WP User.**

Reason:

1. A user-centric public model forces one-user-one-profile too early.
2. A listing-centric model supports people, companies, venues, teams, agencies, and future profile types cleanly.
3. One WP User can own or manage multiple Listings.
4. Multiple WP Users can collaborate on one Listing.
5. Product and booking CTAs attach naturally to Listings, not to accounts.

Design implication:

- WP User remains the canonical authentication and permissions actor.
- Listing becomes the canonical public directory/store/marketplace object.

### Human-First Without User-Locked Architecture

EcomCine can remain human-first at the product level without making the WP User the core entity.

Recommended rule:

- default listing type = `person`
- additional listing types may include `company`, `venue`, `practice`, `brand`, `team`, or other future classes

This preserves today’s human-first experience while keeping the platform extensible.

---

## Core Thesis

The product should be re-framed around four layers.

### 1. EcomCine Core Domain

Owns:

- listing identity and listing type
- user-to-listing ownership and management relations
- listing profile data
- categories and attributes
- media assets and showcase logic
- profile routing
- completeness / publish state
- invitations / shares / public URLs
- canonical data persistence

### 2. EcomCine Experience Shell

Owns:

- fullscreen cinematic layout
- player state machine
- overlays, drawers, tabs, modals, CTA orchestration
- public listing rendering
- account shell rendering
- embedded checkout shell and transaction UI handoff surfaces

### 3. Commerce / Transaction Adapter Layer

Owns only adapter implementations for:

- offer discovery
- availability lookup
- booking intent capture
- checkout launch
- payment session launch
- order summary retrieval
- account activity synchronization

### 4. Minimal Theme Shell

Owns only:

- document structure
- `wp_head()` / `wp_footer()`
- basic theme support
- minimal fallback styling

Business logic, route handling, view-model assembly, and subsystem orchestration belong to the plugin stack, not the theme.

---

## Canonical Vocabulary

One source of architectural confusion is legacy vocabulary. EcomCine should adopt canonical terms and treat Dokan/Woo naming as compatibility translation only.

| Legacy Term | Canonical EcomCine Term | Notes |
|---|---|---|
| vendor | listing owner or listing manager | Actor, not the public object |
| seller | listing owner or listing manager | Compatibility role alias only |
| store | listing | Public-facing object |
| profile | listing profile | Public surface of a listing |
| store URL | listing URL | Should be generated by EcomCine only |
| booking product | offer | External systems may still back it |
| vendor dashboard | account workspace | EcomCine-owned shell |
| store category | listing category | EcomCine registry owned |
| talent / doctor / company page | listing type specific rendering | Human-first is default, not a hard architectural constraint |

**Rule:** templates, services, and specs should speak canonical EcomCine vocabulary unless discussing a compatibility adapter explicitly.

---

## Product Modes

The existing runtime modes are useful, but the product should conceptually be managed in three higher-level states.

### Mode A — Core Only

WordPress + EcomCine only.

Required outcomes:

- listings work
- categories and attributes work
- media/showcase works
- overlays and account shell work
- invitations / shares / routing work
- CTA defaults to contact / inquiry / lead intent where no commerce adapter exists
- no Dokan / WooCommerce / Bookings assumptions exist in public runtime

### Mode B — Core + Commerce Adapter

EcomCine Core remains canonical.
Commerce systems are additive.

Examples:

- EcomCine + WooCommerce adapter
- EcomCine + FluentCart adapter
- EcomCine + EDD adapter
- EcomCine + booking adapter

Required outcomes:

- cinematic experience remains visually and structurally EcomCine-owned
- transaction flows happen inside the EcomCine shell whenever feasible
- graceful branded handoff exists when full embed is not practical

### Mode C — Legacy Compatibility

Temporary migration mode.

Used when:

- old Dokan / Woo data still powers key flows
- parity is not yet achieved on a subsystem
- the adapter layer still requires legacy plugin-owned queries or templates

**Rule:** legacy compatibility is a transitional state, not a product identity.

### Commercial Packaging Model

Internally, EcomCine should be built as one platform with capability packs.

Recommended commercial structure:

1. `Core / Directory Pack`
2. `Commerce Adapter Pack`
3. `Booking Adapter Pack`
4. `Marketplace Pack`

This is cleaner than treating Directory, Store, and Marketplace as separate products at the architecture layer.

Externally, the user can still buy/install outcomes such as:

- Cinematic Directory
- Cinematic Store
- Cinematic Store + Bookings
- Cinematic Marketplace
- Cinematic Marketplace + Bookings

But internally they should all resolve to the same core platform plus capability combinations.

---

## Architectural Principles

1. **Core-first, adapter-second.**
   EcomCine-owned contracts define behavior. Third-party systems conform to them.

2. **Naked WordPress is the baseline.**
   If a subsystem cannot function on fresh WordPress, it is not part of the canonical core yet.

3. **Presentation must not call third-party APIs directly.**
   Templates render EcomCine view models only.

4. **Query ownership matters more than CSS ownership.**
   If a query path, route, or persistence path is still third-party shaped, the subsystem is still structurally dependent.

5. **Commerce adapters must stay narrow.**
   EcomCine should not attempt to absorb full plugin-specific domain logic.

6. **Feature parity is semantic, not literal.**
   The goal is the same user outcome inside the cinematic shell, not byte-for-byte mimicry of legacy UIs.

7. **Compatibility branches must terminate.**
   Every compatibility path should either become an adapter or be deleted.

---

## Canonical Core Ownership Map

### EcomCine Must Fully Own

- listing identity and listing type
- listing URLs and rewrite rules
- listing completeness and publish state
- user-to-listing ownership and management relations
- categories, custom attributes, and taxonomy-like registries
- biography, avatar, banner, gallery, video metadata
- map/location metadata shape
- account shell and onboarding shell
- showcase playlist logic and player behavior
- overlay rendering and cinematic state transitions
- invitations, shares, public landing links, QR endpoints
- public-facing templates and view models

### EcomCine May Integrate But Should Not Reimplement Deeply

- payments
- advanced order management
- advanced booking management
- subscriptions
- tax calculations
- refunds
- transactional email engines
- inventory systems

### Third-Party Systems Should Only Supply

- transaction execution
- payment orchestration
- order persistence when adapter-backed
- booking calendars/availability when adapter-backed
- specialized admin workflows outside EcomCine’s cinematic domain

---

## Canonical Entity Model

This section defines the target conceptual model. Exact implementation can evolve, but the ownership should not.

### UserAccount

Represents the authenticated WordPress user actor.

Owns:

- authentication
- capabilities/roles
- operator identity in admin/account surfaces

### Listing

Represents the canonical public entity in EcomCine.

Owns:

- listing type
- slug
- public/private state
- publish state
- completeness state
- canonical URL target

### ListingOwnership

Represents the relation between users and listings.

Owns:

- primary owner
- managers/editors
- collaboration permissions
- future multi-owner support rules

### ListingProfile

Represents the public payload rendered by the cinematic UI for a listing.

Owns:

- title / display name
- biography
- contact display settings
- avatar/banner
- social links
- geolocation
- visual badges / labels / levels
- structured overlay fields

### ListingCategory

Represents curated directory classification owned by EcomCine.

Owns:

- slug
- name
- icon
- ordering
- grouping metadata

### ListingAttribute

Represents category-aware or global structured profile attributes.

Owns:

- field key
- field type
- visibility rules
- category applicability
- display group / tab mapping

### ContactIntent

Represents the Core Only CTA path when no commerce adapter is installed.

Owns:

- inquiry purpose
- target listing
- normalized contact payload
- destination workflow or adapter handoff

### MediaAsset

Represents playable or displayable media in the showcase/profile shell.

Owns:

- asset type
- ordering
- source URL / attachment binding
- duration metadata
- thumbnail/poster metadata
- featured/priority state

### Offer

Represents a bookable or purchasable intent surface exposed by EcomCine.

Owns:

- offer type
- adapter reference
- display metadata
- CTA behavior contract

Adapters may map offers to Woo products, FluentCart items, EDD products, or booking resources.

### ListingOfferRelation

Represents the relationship between a listing and one or more CTA-capable offers.

Owns:

- attached product offers
- attached booking offers
- CTA ordering / priority
- conditional display rules

Rule:

- products and bookings are not mutually exclusive on a listing
- the cinematic shell should support multiple CTA types on the same listing without changing the listing model

### BookingIntent / CheckoutIntent

Represents user intent captured inside the EcomCine shell before handoff or embedded checkout.

Owns:

- selected offer
- selected slots/options
- normalized user input
- adapter handoff payload

### Invitation / Share

Represents ownership-transfer or public sharing flows.

Owns:

- token
- expiry
- audience/purpose
- target entity

---

## Adapter Strategy

Adapters should be intentionally narrow and graded by how deeply they can stay inside the cinematic shell.

### Required Adapter Contracts

Each commerce/transaction adapter should implement only the smallest useful surface.

Candidate contracts:

- `OfferDiscoveryAdapter`
- `AvailabilityAdapter`
- `BookingFormAdapter`
- `CheckoutLaunchAdapter`
- `CheckoutPolicyAdapter`
- `OrderSummaryAdapter`
- `AccountActivityAdapter`
- `ProfileSyncAdapter`

### Adapter Grades

#### Grade A — Fully Embedded

The transaction can stay inside EcomCine modals/panels with strong parity.

Examples:

- embedded booking form
- embedded checkout session
- inline success state

#### Grade B — Partially Embedded

EcomCine owns the shell and intent capture, but an intermediate external form/view is required.

#### Grade C — Branded Handoff

EcomCine captures intent and hands the user to an external flow with clear return hooks.

**Rule:** Grade C is acceptable if the adapter is explicit. It is better than pretending every commerce system can be fully embedded.

---

## Strategic Decision: In-Place Core Rebuild vs Full Greenfield Rewrite

### Recommendation

Use an in-place core rebuild.

### Why

1. The codebase already contains real adapter scaffolding and WP-native replacements.
2. A separate greenfield rewrite would still need to run alongside the current system for months.
3. Migration safety, parity testing, and live cutover are easier when the product remains in one repo.
4. The real blocker is not lack of code. It is lack of singular ownership.

### What “rebuild” means here

It means rebuilding the canonical center of gravity, not throwing everything away.

Specifically:

- move ownership of runtime behavior into EcomCine Core
- reduce legacy plugins to compatibility providers
- replace third-party-shaped query and routing paths
- progressively remove direct dependency leaks from templates and controllers

---

## Structural Problem Statement

The codebase has already recaptured much of the look, feel, branding, and behavior.

The remaining fragility comes from these deeper causes:

1. Query ownership is still partly Dokan-shaped.
2. Some routing and page-context detection is still Dokan/Woo shaped.
3. Public templates still carry direct third-party calls.
4. Profile, listing, and commerce boundaries are not yet strict enough.
5. The project has partial default-WP architecture, but it is not yet the sole authority.

This creates a misleading state where EcomCine appears visually sovereign while still being structurally dependent.

---

## Program Workstreams

### Workstream 1 — Canonical Domain Freeze

Goal:

- define the EcomCine-owned entities, contracts, and naming

Outputs:

- canonical entity map
- canonical field map
- ownership matrix by subsystem
- terminology guide

### Workstream 2 — Routing and Identity Sovereignty

Goal:

- make EcomCine the sole owner of listing/profile URLs and page resolution

Outputs:

- canonical rewrite rules
- listing URL service
- legacy alias redirect rules
- listing slug conflict policy

### Workstream 3 — Query and Listing Sovereignty

Goal:

- replace Dokan-shaped listing/filter/search logic with EcomCine-owned query services

Outputs:

- listing query service
- category/attribute filtering service
- showcase source selection service
- parity oracle for legacy-vs-core listing results

### Workstream 4 — View-Model and Template Sovereignty

Goal:

- remove third-party calls from templates and public controllers

Outputs:

- profile page view model
- listing card view model
- account shell view model
- adapter-free public templates

### Workstream 5 — Commerce Surface Narrowing

Goal:

- reduce commerce integrations to clear, small adapter contracts

Outputs:

- offer contract
- checkout launch contract
- order summary contract
- booking capability matrix

### Workstream 6 — Data Migration and Canonical Persistence

Goal:

- make EcomCine-owned storage the canonical source of truth

Outputs:

- migration scripts
- dual-read / staged-write policy
- final cutover plan away from legacy keys/tables

### Workstream 7 — Runtime Simplification and Decommissioning

Goal:

- retire compatibility branches once core paths are proven

Outputs:

- legacy dependency kill list
- removal sequence
- post-cutover validation gates

---

## Initial Subsystem Priority Order

### Priority 1 — Routing + Query Layer

This is the first major cut.

Reason:

- if listing URLs, listing lookup, listing filters, and search are still legacy-owned, every other subsystem remains fragile

### Priority 2 — Public View Models and Templates

Reason:

- public UI should render EcomCine-owned data only

### Priority 3 — Account / Onboarding Core

Reason:

- it defines how users claim, own, manage, and edit listings

### Priority 4 — Offer / Booking / Checkout Adapters

Reason:

- transaction features matter, but they should plug into a stable core shell rather than define the core

### Priority 5 — Admin / Reporting / Legacy Cleanup

Reason:

- these are important, but they should follow the domain freeze rather than drive it

---

## Deployment Philosophy

### Do Not Attempt Big-Bang Cutover

Each subsystem should move through these stages:

1. contract frozen
2. EcomCine-owned implementation built
3. parity test added
4. runtime gated behind feature flag
5. shadow comparison in legacy mode
6. controlled cutover
7. legacy branch removal

### Required Release Gates

A subsystem cannot be declared core-owned until:

1. EcomCine-owned canonical data source exists.
2. Public rendering no longer calls legacy APIs directly.
3. A parity oracle exists for the subsystem.
4. Rollback path is documented.
5. Failure mode in absence of the third-party plugin is known and acceptable.

---

## Architectural Rules to Enforce in Code Review

1. No direct Dokan/Woo/Bookings/EDD/FluentCart calls in public templates.
2. No direct third-party URL generation in public UI.
3. No third-party page-context checks in public controllers without an EcomCine wrapper.
4. No third-party query hooks as the canonical listing source.
5. No new legacy-shaped meta keys introduced into core-owned flows.
6. Every new integration must declare its adapter contract and its degradation grade.
7. Every core-owned feature must specify whether it works in Core Only mode.

---

## Definitions of Done

### “Core-Owned Profile System” Done Means

- listing URL is generated by EcomCine
- routing is resolved by EcomCine
- listing payload is loaded by EcomCine services
- templates render EcomCine view models
- listing works on fresh WordPress without Dokan

### “Core-Owned Listing System” Done Means

- listing/search/filter query path is EcomCine-owned
- category and attribute joins are EcomCine-owned
- showcase source selection does not depend on Dokan queries
- results can be compared against compatibility mode for parity

### “Core-Owned CTA Model” Done Means

- every listing can expose at least one Core Only CTA without a commerce plugin
- listings can attach product and booking CTAs independently
- CTA rendering is controlled by EcomCine, not plugin-native templates
- unsupported adapter capabilities degrade intentionally to contact / inquiry or branded handoff

### “Commerce Adapter Ready” Done Means

- offers can be surfaced by EcomCine without exposing plugin-native UI directly
- checkout/booking behavior is mediated through an adapter contract
- unsupported capabilities degrade intentionally, not accidentally

### “Legacy-Free Runtime” Done Means

- Core Only mode works with no Dokan/Woo/Bookings installed
- public runtime has no hard dependency on legacy plugin APIs
- remaining plugin integrations are optional adapters only

---

## Risks and Realism

### Feasible

The vision is feasible because the product does not need to replace full commerce engines.
It needs to own the cinematic shell and define strict integration seams.

### Expensive Areas

The heaviest effort is likely in:

- listing query extraction
- template de-Dokanization
- route and listing state unification
- order/booking adapter normalization

### Main Failure Modes

1. Trying to abstract too much commerce detail.
2. Preserving legacy naming and query paths for too long.
3. Allowing templates to keep bypassing the core service layer.
4. Attempting cutover before parity oracles exist.

---

## Proposed First Milestones

### Milestone 1 — Core Architecture Freeze

Deliverables:

- final canonical entity map
- final adapter contract list
- final listing ownership model
- subsystem ownership matrix
- terminology freeze

### Milestone 2 — Routing and Listing Blueprint

Deliverables:

- exact target design for profile routing
- exact target design for listing search/filter query engine
- cutover and parity strategy for those two systems

### Milestone 3 — Public View-Model Refactor Plan

Deliverables:

- target shape for profile/listing/showcase view models
- inventory of templates still calling legacy APIs directly
- conversion plan by module

---

## Brainstorming Pad

Use this section for live refinement. Replace placeholders with decisions.

### Open Decisions

| ID | Question | Current Lean | Decision | Notes |
|---|---|---|---|---|
| CD-01 | Canonical public URL base: `person`, `talent`, `doctor`, or product-defined label? | Product-defined but system-owned | Closed: canonical base is `profile`; legacy bases redirect | Listing route stays system-owned and alias-safe |
| CD-02 | Should `tm_vendor` evolve into the canonical Listing CPT, or should listing state stay primarily meta-driven with CPT augmentation? | Lean toward canonical Listing CPT with service aggregation | Closed: Phase 1 uses `tm_vendor` as the single canonical Listing storage object under a Listing service boundary | No second Listing CPT in Wave 1 |
| CD-03 | Which commerce adapters are Tier 1 for the first real standalone program? | WooCommerce first | Closed: WooCommerce is Tier 1 commerce; WooCommerce Bookings is Tier 1 booking path; FluentCart and EDD move to Tier 2 | First deployment path stays narrow |
| CD-04 | Should EcomCine embed checkout whenever possible, or standardize on branded handoff first? | Branded handoff acceptable for early adapters | Closed: branded handoff is the default posture; embedded flows are explicit upgrades | Adapter grades must be declared |
| CD-05 | What is the minimum viable Core Only transaction substitute, if any? | Inquiry / lead / intent capture only | Closed: every Listing supports EcomCine-owned ContactIntent CTA in Core Only mode | No fake checkout in Core Only |
| CD-06 | Which listing types are first-class in V1? | `person` first, with `company` and `venue` prepared conceptually | Closed: `person`, `company`, and `venue` are first-class in V1 | `practice`, `brand`, `team`, and `agency` remain future types |
| CD-07 | Should licensing be structured as capability packs internally and marketed as named outcomes externally? | Yes | Closed: internal capability packs, external outcome-based product language | Marketplace remains an additive pack/preset |

### Working Assumptions

1. EcomCine Core must be useful on naked WordPress.
2. Legacy compatibility remains temporary.
3. Public templates should become adapter-free.
4. Commerce adapters should stay narrow and capability-graded.
5. Listing is the canonical public entity; WP User is the canonical auth actor.
6. Contact / inquiry CTA is the baseline fallback when no commerce adapter exists.

### Questions for the First Deployment Pass

1. Which exact code surfaces implement the first Wave 1 vertical slice with the smallest safe blast radius?
2. Which parity checks will be automated first versus observed manually?
3. Which route, Listing, and query gates will be introduced first in code?
4. Which public directory slice will be the first authoritative cutover target?

---

## Immediate Next Step

Use this document as the top-level planning anchor.

Active companion artifacts:

1. `specs/EcomCine-Core-Subsystem-Ownership-Matrix.md`
2. `specs/EcomCine-Core-Decision-Log.md`
3. `specs/EcomCine-Core-Wave-1-Cutover-Plan.md`
4. `specs/EcomCine-Core-Wave-1-Feature-Flag-Plan.md`
5. `specs/EcomCine-Core-Wave-1-Parity-Oracle-Checklist.md`
6. `specs/EcomCine-Core-Wave-1-Rollback-Checklist.md`
7. `specs/EcomCine-Core-Deployment-README.md`

Immediate deployment focus:

1. start Wave 1 execution from the approved deployment README
2. implement the first vertical slice under explicit route, Listing, and query gates
3. preserve parity and rollback behavior throughout the first cutover