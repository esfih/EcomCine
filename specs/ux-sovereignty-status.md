# EcomCine UX Sovereignty — Status (2026-03-29)

---

## Objective

**Full pixel-level UX ownership**: every visual and interactive element on the EcomCine store pages — icons, modals, tabs, overlays, panels, navigation — must be rendered and controlled exclusively by `tm-store-ui` (plugin) and `tm-theme` (Astra child theme). Zero runtime dependency on:

- FontAwesome (font files, CSS)
- Dokan's bundled CSS/JS beyond data layer
- WooCommerce frontend CSS
- Any third-party icon or UI library

The site should function completely if every third-party CSS is stripped, except for data/API plugins.

---

## Issue 1: Icons

### What Broke
FontAwesome icon font files were never deployed to the container path `/wp-content/plugins/tm-store-ui/assets/fonts/fontawesome/`. All `<i class="fas fa-*">` tags rendered as broken unicode boxes.

### Decision
Rather than fix the font path, migrated entirely to **inline SVG icons** — zero font/file dependency, no flicker, no CDN, works in any container.

### What Was Built

| Artifact | Status |
|---|---|
| `tm-store-ui/includes/class-icons.php` — 64 FA6 SVG paths, `TM_Icons::svg()` + `tm_svg_icon()` helpers, 21 FA5→FA6 aliases | ✅ Complete |
| `tmIcon()` JS function with 9 embedded paths in `vendor-store.js` | ✅ Complete |
| `.tm-icon` CSS rule in `vendor-store.css` | ✅ Complete |
| `dokan-fontawesome` always dequeued; `tm-fontawesome` CSS removed from `enqueue.php` | ✅ Complete |
| 139 `<i class="fas/far fa-*">` replacements across `tm-store-ui/templates/` + `theme/includes/` | ✅ Complete |

### Remaining Broken Icons ✅

None in active runtime paths.

### Completed Menu Icon Remediation (2026-03-29)

- ✅ Added runtime conversion for legacy nav menu icon HTML: `<i class="... fa-building ...">` is now replaced with `TM_Icons::svg('building')` during header menu render in `tm-store-ui/includes/hooks.php`.
- ✅ Keeps compatibility with existing database menu labels while removing FontAwesome runtime dependence for the Company icon.

### Completed Icon Remediation (2026-03-29)

- ✅ Replaced the 5 remaining `<i>` tags in `ecomcine/modules/tm-account-panel/tm-account-panel.php` with `tm_account_panel_svg_icon()` calls.
- ✅ Added guarded helper `tm_account_panel_svg_icon()` (uses `TM_Icons::svg()` when available, safe fallback otherwise).
- ✅ Updated `ecomcine/modules/tm-account-panel/assets/css/account-panel.css` so account panel SVG icons inherit intended icon sizing.

### Fix Plan for Remaining Icons
- Replace the 5 `<i>` tags in `ecomcine/modules/tm-account-panel/tm-account-panel.php` with `TM_Icons::svg()` calls (use `class_exists('TM_Icons')` guard since the module loads via `ecomcine` plugin which may load before `tm-store-ui`).
- For the nav menu building icon: update the menu item label directly in WP Admin → Appearance → Menus, or add a nav walker filter that converts `<i class="fas fa-building">` to SVG output.

---

## Issue 2: Click Blocking

### Symptom
Left side panel (`.profile-info-head` collapse), nav Sign-in/Sign-up links (`.tm-open-signin`, `.tm-open-signup`), and bottom category tabs (`.bottom-tab-item`) are all non-responsive. Only `.tm-account-tab` (right-side fixed tab) works.

### Investigation Log

| Hypothesis | Verdict | Notes |
|---|---|---|
| `.tm-account-backdrop` missing `pointer-events:none` when modal closed | Ruled out | Modal parent has `pointer-events:none`; backdrop is `position:absolute` inside it. Also `.tm-account-tab` z-index 1200 < modal z-index 2000 yet tab works — contradicts this theory. |
| `vendor-store.js` structural error (unbalanced braces) | Ruled out | Python brace-count check: 2443 opens = 2443 closes. |
| JS `stopPropagation` eating events | Ruled out | No `stopPropagation` on document-level handlers; all use `$(document).on("click", ...)` delegation. |
| FluentCart `fct-checkout-modal-container` (`position:fixed; inset:0; z-index:999999`) blocking | Partially ruled out | Has `opacity:0; visibility:hidden` when closed — should not intercept pointer events. |
| `tm-field-editor-modal` / `tm-location-modal` invisible overlay | Ruled out | Both use `display:none` when closed. |
| DCA `frontend.js` eating tab clicks | Ruled out | Only does category filter visibility toggling, no click trapping. |
| `vendor-store.js` `tmIcon()` string quote bug (line 241) | **Identified, not fixed** | Mixed single/double quotes in `$helpIcon` jQuery string in `openFieldEditorModal()`. Could silently abort that function but shouldn't break unrelated handlers. |
| `body.tm-showcase-page { overflow:hidden; height:100vh }` swallowing pointer events via scroll container | **Not yet confirmed** | Needs browser devtools inspection. |

### Root Cause: Determined and Fixed ✅

`tm-store-ui/assets/js/vendor-store.js` had a malformed string in `openFieldEditorModal()` (help icon HTML builder):

- Mixed quote boundaries broke JavaScript parsing.
- Parse abort prevented subsequent handler registration in `vendor-store.js`.
- This explains why `.profile-info-head`, `.tm-open-signin`, `.tm-open-signup`, and `.bottom-tab-item` were all dead while `.tm-account-tab` still worked (it is controlled by a different script: `account-panel.js`).

### Source Fix Applied

- ✅ Corrected the malformed string expression for `$helpIcon` in `tm-store-ui/assets/js/vendor-store.js`.

---

## Architecture Reference

### Active Plugin Paths (confirmed via `wp plugin list`)

| Plugin slug | Path in container | Status |
|---|---|---|
| `ecomcine` | `/wp-content/plugins/ecomcine/` | active |
| `tm-store-ui` | `/wp-content/plugins/tm-store-ui/` | active |
| `tm-media-player` | `/wp-content/plugins/tm-media-player/` | active |
| `tm-vendor-booking-modal` | `/wp-content/plugins/tm-vendor-booking-modal/` | active |
| `tm-account-panel` | `/wp-content/plugins/tm-account-panel/` | **inactive** (superseded by `ecomcine/modules/tm-account-panel`) |

### CSS Enqueue Order (frontend, homepage)
1. `tm-theme-css` (style.css)
2. `tm-store-ui-responsive` (responsive-config.css)
3. `tm-store-ui-css` (vendor-store.css)
4. `tm-player-css` (player.css)
5. `tm-account-panel-css` → from `ecomcine/modules/tm-account-panel/assets/css/account-panel.css`
6. FluentCart CSS (dequeued when not in `wp_fluentcart` mode)

### JS Load Order (frontend, homepage)
1. jQuery 3.7.1
2. Dokan iziModal, sweetalert2, helper
3. WooCommerce add-to-cart, woocommerce.js
4. `tm-media-player` player.js
5. `dokan-category-attributes` frontend.js
6. `tm-store-ui-js` **vendor-store.js**
7. `ecomcine/modules/tm-account-panel` **account-panel.js**
8. FluentCart app

---

## Work Remaining

- [x] Fix 5 FA `<i>` tags in `ecomcine/modules/tm-account-panel/tm-account-panel.php`
- [x] Fix Company/building icon in WP nav menu
- [x] Identify and fix click blocking root cause
- [x] Fix `tmIcon()` string quote bug in `vendor-store.js` line 241
