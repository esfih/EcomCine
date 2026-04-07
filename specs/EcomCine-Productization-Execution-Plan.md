# EcomCine Productization Execution Plan

Last updated: 2026-03-27
Owner: EcomCine Productization Track
Scope: Transform CastingAgency-specific custom stack into productized EcomCine architecture.

## Phase Status Board

- [x] Phase 0: Product contract and baseline freeze
- [x] Phase 1: Controlled consolidation on current stack (single app plugin) - Completed
- [x] Phase 2: Abstraction layer (theme/plugin agnostic core + adapters) - Completed
- [x] Phase 3: WP-Admin configuration and management layer - Completed
- [~] Phase 4: Licensing, billing integration hardening, and release pipeline - In progress

## North-Star Product Shape

- Public distributable plugin: `ecomcine` (single app plugin)
- Private billing/licensing plugin: `ecomcine-control-plane` (installed only on ecomcine.com billing site)
- Compatibility model:
  - Canonical stack: bare WordPress + `ecomcine-base` + EcomCine-owned CPT/meta/taxonomy flows
  - Legacy compatibility stack: Dokan Pro + WooCommerce + WooCommerce Bookings when parity is required
  - Theme strategy: one canonical minimal theme shell with optional third-party compatibility layers

## Phase 0 - Product Contract And Baseline Freeze (Completed)

Status: Completed on 2026-03-27

### 0.1 Contract Decisions Frozen

- Single public app plugin target confirmed (`ecomcine`).
- Separate private control-plane plugin target confirmed (`ecomcine-control-plane`).
- Migration order confirmed: parity-first consolidation, then abstraction, then admin layer.

### 0.2 Runtime And Behavior Baseline Captured

Environment baseline:
- WSL2 Ubuntu runtime active.
- Repository path on Linux filesystem (`/root/dev/EcomCine`).
- Docker bind mounts resolved to Linux paths.
- WordPress local endpoint `http://localhost:8180` returns HTTP 200.

Health checks executed:
- `./scripts/check-local-dev-infra.sh` -> PASS
- `./scripts/check-local-wp.sh` -> PASS

Active plugin baseline snapshot:
- dokan-lite `4.2.8`
- dokan-category-attributes `1.0.0`
- dokan-pro `4.2.3`
- tm-account-panel `1.0.0`
- tm-media-player `1.0.0`
- tm-vendor-booking-modal `1.0.0`
- woocommerce `10.5.3`
- woocommerce-bookings `2.2.9`

Active theme baseline snapshot:
- ecomcine-base `1.0.0`

Known non-blocking note:
- WP-CLI command output includes PHP deprecation warning:
  `Creation of dynamic property ftp::$features is deprecated`.
- This warning does not block baseline health checks and can be handled in a later hardening pass.

### 0.3 Phase Gate For Next Work

Before Phase 1 starts, this baseline is the parity reference:
- Store/showcase behavior must remain functionally equivalent.
- Booking modal flow must remain functionally equivalent.
- Account panel and onboarding flow must remain functionally equivalent.
- No regression in local health checks.

---

## Phase 1 - Controlled Consolidation On Current Stack (Completed)

Status: Completed on 2026-03-27

Goal:
- Consolidate current 3 TM plugins into one `ecomcine` plugin while preserving behavior on the legacy marketplace compatibility stack.

High-level outcomes:
- Business logic moved out of entangled locations into modular plugin internals.
- Child theme reduced toward compatibility/presentation role.
- One app-plugin artifact ready for iterative distribution testing.

### Phase 1 Milestone Log

- 2026-03-27 (M1): Created unified `ecomcine` plugin bootstrap at `ecomcine/ecomcine.php`.
- 2026-03-27 (M1): Vendored existing TM plugins as internal modules under `ecomcine/modules/`.
- 2026-03-27 (M1): Updated `docker-compose.yml` to mount `ecomcine` plugin in WordPress runtime.
- 2026-03-27 (M1): Updated `scripts/setup-deps.sh` to activate `ecomcine` and deactivate legacy TM plugins.
- 2026-03-27 (M1): Updated `scripts/check-local-wp.sh` to prefer `ecomcine` check with legacy fallback.
- 2026-03-27 (M1): Added legacy-active guards in `ecomcine/ecomcine.php` to prevent duplicate symbol fatals during transition.
- 2026-03-27 (M1): Completed runtime cutover with only `ecomcine` active for TM feature set; legacy TM plugins deactivated.
- 2026-03-27 (M1): Validation PASS: `./scripts/setup-deps.sh` idempotent, `./scripts/check-local-wp.sh` PASS, HTTP 200 on localhost:8180.
- 2026-03-27 (M2): Extracted shared vendor utility functions from theme into `ecomcine/includes/compat/vendor-utilities.php`.
- 2026-03-27 (M2): Added fallback guards in `theme/functions.php` so plugin-owned utilities load first without redeclare fatals.
- 2026-03-27 (M3a): Re-implemented extraction incrementally: moved `calculate_age_from_birth_date` and `age_matches_range` into `ecomcine/includes/compat/vendor-utilities.php` and guarded theme fallbacks.
- 2026-03-27 (M3a): Validation PASS: `./scripts/check-local-wp.sh` PASS, runtime function checks PASS.
- 2026-03-27 (M3b): Continued incremental extraction: moved `get_vendor_id_from_store_user` and `render_editable_attribute` into `ecomcine/includes/compat/vendor-utilities.php` and guarded theme fallbacks.
- 2026-03-27 (M3b): Validation PASS: `./scripts/check-local-wp.sh` PASS, runtime function checks PASS.
- 2026-03-27 (M3c): Completed incremental extraction: moved `mp_get_vendor_avatar_url` and `mp_print_vendor_avatar_badge` into `ecomcine/includes/compat/vendor-utilities.php` and guarded theme fallbacks.
- 2026-03-27 (M3c): Validation PASS: full health checks PASS and all M3 function presence checks PASS.
- 2026-03-27 (M4): Final Phase 1 extraction pass initiated for remaining theme-owned business hooks into plugin-owned compat modules.
- 2026-03-27 (M4a): Extracted booking modal helper functions `tm_modal_hide_woo_terms` and `tm_modal_privacy_text_filter` into `ecomcine/includes/compat/vendor-utilities.php` and converted theme definitions to guarded fallbacks.
- 2026-03-27 (M4a): Validation PASS: health checks PASS and runtime function checks PASS.
- 2026-03-27 (M4b): Extracted grouped asset/filter callbacks into `ecomcine/includes/compat/vendor-utilities.php`: `tm_remove_dokan_mapbox_on_store_page`, `tm_strip_mapbox_resource_hints`, `tm_remove_google_assets`, `tm_strip_google_resource_hints`, `tm_remove_woocommerce_assets_on_store_page`, and `tm_remove_editor_assets_on_store_listing`; converted theme definitions to guarded fallbacks.
- 2026-03-27 (M4b): Validation PASS: `./scripts/check-local-wp.sh` PASS, diagnostics PASS on touched files, and runtime function checks PASS for all M4b callbacks.
- 2026-03-27 (Phase 1 Exit): Consolidation phase closed in one go after parity validations and guarded fallback conversion of extracted utility functions.

## Phase 2 - Abstraction Layer (Completed)

Status: Completed on 2026-03-27

Goal:
- Introduce core domain and adapter architecture so EcomCine can run beyond current stack.

High-level outcomes:
- Core modules independent of Astra/Dokan/Woo assumptions.
- Adapter contracts for theme and commerce integrations.
- Bare WordPress baseline mode available with graceful degradation.

### Phase 2 Milestone Log

- 2026-03-27 (Phase 2 Start): Began abstraction layer implementation in one go.
- 2026-03-27 (Phase 2 Build): Added core adapter contracts and runtime resolver under `ecomcine/includes/core/` with legacy compatibility (`dokan-astra`, `woo-dokan`) and baseline (`wp-baseline`) adapters.
- 2026-03-27 (Phase 2 Build): Wired new abstraction layer into `ecomcine/ecomcine.php` and exposed `ecomcine_get_runtime_adapter_snapshot()` for diagnostics and follow-on integration work.
- 2026-03-27 (Phase 2 Exit): Core adapter contract baseline completed and validated on the legacy compatibility runtime.

## Phase 3 - WP-Admin Management Layer (Completed)

Status: Completed on 2026-03-27

Goal:
- Replace hardcoded behavior with user-manageable configuration in wp-admin.

High-level outcomes:
- Admin-managed fields, taxonomies, layout presets, style tokens, and feature toggles.
- Data model and migration path from hardcoded values.
- Presets for legacy compatibility mode and baseline WordPress mode.

### Phase 3 Milestone Log

- 2026-03-27 (Phase 3 Start): Started wp-admin management layer implementation in one go.
- 2026-03-27 (Phase 3 Build): Added plugin-owned admin settings module at `ecomcine/includes/admin/class-admin-settings.php` with runtime mode preset, module feature toggles, and style token settings.
- 2026-03-27 (Phase 3 Build): Wired settings module into `ecomcine/ecomcine.php` and applied feature toggles to unified module loading decisions.
- 2026-03-27 (Phase 3 Build): Extended admin settings with layout preset and managed labels, and applied frontend runtime hooks for body classes and style-token CSS variables.
- 2026-03-27 (Phase 3 Build): Updated abstraction resolver to honor `runtime_mode` so wp-admin can force baseline adapters.
- 2026-03-27 (Phase 3 Validation): PASS on diagnostics + health checks; verified the legacy compatibility preset resolves to `dokan-astra`/`woo-dokan` and forced `baseline_wp` resolves to `wp-baseline`/`wp-baseline` (with settings restored).

## Phase 4 - Licensing/Billing Hardening And Distribution (In Progress)

Goal:
- Finalize dual-plugin distribution model for paid productization.

High-level outcomes:
- Control-plane integration aligned with foundation licensing templates.
- Entitlement and activation lifecycle stable.
- Repeatable release packaging pipeline and manifests.
- Upgrade/rollback paths documented and tested.

### Phase 4 Milestone Log

- 2026-03-27 (Phase 4 Start): Began licensing/billing hardening and distribution implementation in one go.
- 2026-03-27 (Phase 4 Build): Added `ecomcine/includes/licensing/class-licensing.php` with control-plane settings, status resolver filter contract (`ecomcine_license_status`), soft/strict enforcement mode, and admin licensing screen.
- 2026-03-27 (Phase 4 Build): Wired licensing module into plugin bootstrap and exposed `ecomcine_get_license_status_snapshot()` for diagnostics.
- 2026-03-27 (Phase 4 Build): Added release packaging script `scripts/build-ecomcine-release.sh` to generate versioned zip artifacts and SHA-256 manifests under `dist/`.
- 2026-03-27 (Phase 4 Build): Added WMOS FluentCart parity catalog module `ecomcine/includes/licensing/class-offer-catalog.php` with canonical freemium/solo/maestro/agency product+variation mapping and activation limits.
- 2026-03-27 (Phase 4 Build): Extended licensing status/admin screen to surface resolved offer and max site activation limits from parity catalog.
- 2026-03-27 (Phase 4 Build): Added billing seed clone scripts `scripts/licensing/extract-wmos-billing-seed.php` and `scripts/licensing/import-billing-seed.php` plus runbook `specs/Phase4-WMOS-FluentCart-Parity.md` for product/order/license migration continuity.
- 2026-03-27 (Phase 4 Validation): PASS on diagnostics + health checks; licensing snapshot returns active/local-default/soft baseline.
- 2026-03-27 (Phase 4 Validation): PASS on distribution packaging; generated `dist/ecomcine-0.1.0.tar.gz` and matching manifest with SHA-256 checksum (tar fallback used because `zip` binary is unavailable in host runtime).
- 2026-04-03 (Architecture): Standalone plugin consolidation complete. Deleted root-level `tm-store-ui/`, `tm-media-player/`, `tm-account-panel/`, `tm-vendor-booking-modal/` folders. All module code now lives solely in `ecomcine/modules/`. `ecomcine_load_legacy_module()` and `ecomcine_is_plugin_slug_active()` replaced with `ecomcine_load_module()` + feature flags. No dual sources of truth. Docker-compose volume mounts and README-FIRST inventory updated to reflect single canonical location.

## Working Rule

Update this file at every phase gate and after each major milestone so work can resume safely after interruptions.

Execution mode update (2026-03-27): run phases end-to-end in one go unless explicitly paused or redirected.
