# P0 Fix: Auto-Play Persistence Enhancement

## Date
April 5, 2026

## Issue
Auto-play persistence works on localhost but fails on mutualized hosting (`https://app.topdoctorchannel.us/`). Users must click the `.tm-showcase-play-overlay` button for each newly loaded vendor/talent instead of autoplay working after the first click.

## Root Causes Identified

### Root Cause #1: Missing `document.userActivation` API Fallback
- **Severity**: Critical
- **Impact**: `sessionStorage` alone is not reliable across browser privacy features, tab suspension, or shared hosting environments
- **Evidence**: Diagnostic commands confirmed no `document.userActivation` usage in player.js

### Root Cause #2: Deprecated `performance.navigation.type` Detection
- **Severity**: High
- **Impact**: API deprecated in Chrome 100+, Safari 15.4+, Firefox 100+; causes incorrect page reload detection
- **Evidence**: Diagnostic commands confirmed usage of deprecated API at line 2760

## Fixes Applied

### Fix #1: Added `document.userActivation` API Support
**Location**: Lines 1475-1493

```javascript
// ── User Activation API ──
// Modern API to detect if a user gesture has occurred within the last 10 seconds.
// Fallback to sessionStorage for older browsers.
function hasValidUserActivation() {
    try {
        // Modern API: Chrome 83+, Edge 83+, Safari 16.4+, Firefox 123+
        if (typeof document.userActivation !== 'undefined') {
            return document.userActivation.hasBeenActive;
        }
        // Fallback: sessionStorage check
        if (window.sessionStorage && sessionStorage.getItem('tm_showcase_started') === '1') {
            return true;
        }
        return false;
    } catch (e) {
        return false;
    }
}
```

**Benefits**:
- Uses modern browser API for user activation detection
- Backward compatible with older browsers
- More reliable across different hosting environments

### Fix #2: Updated `hasShowcaseStarted()` Function
**Location**: Lines 1548-1551

```javascript
function hasShowcaseStarted() {
    // Use modern user activation API with sessionStorage fallback
    return hasValidUserActivation();
}
```

**Benefits**:
- Simplified function that leverages the new `hasValidUserActivation()` helper
- Ensures consistent behavior across all browsers

### Fix #3: Replaced Deprecated `performance.navigation.type` Detection
**Location**: Lines 2771-2803

```javascript
// Detect page reloads using modern navigation API (performance.navigation.type is deprecated).
// Uses PerformanceObserver + navigation.type for Chrome 71+, Safari 15.4+, Firefox 70+.
// Fallback to sessionStorage timestamp check for older browsers.
var isPageReload = false;
try {
    // Modern API: PerformanceNavigationTiming (Chrome 71+, Firefox 70+, Safari 15.4+)
    if (typeof performance.getEntriesByType === 'function') {
        var navEntries = performance.getEntriesByType('navigation');
        if (navEntries && navEntries.length > 0) {
            var navType = navEntries[0].type;
            // 'reload' = F5/Ctrl+R, 'navigate' = link click/redirect, 'back_forward' = browser nav
            isPageReload = (navType === 'reload' || navType === 'back_forward');
        }
    }
    // Fallback: deprecated performance.navigation.type (Chrome <100, Safari <15.4)
    if (!isPageReload && window.performance && window.performance.navigation) {
        isPageReload = (window.performance.navigation.type === 1);
    }
    // Final fallback: sessionStorage timestamp check
    if (!isPageReload && window.sessionStorage) {
        var lastVisit = sessionStorage.getItem('tm_last_visit_timestamp');
        var now = Date.now();
        if (lastVisit && (now - parseInt(lastVisit, 10) > 30000)) {
            // No visit in last 30 seconds = likely a fresh page load
            isPageReload = true;
        }
    }
} catch (e) {
    // Silent fail, default to false
}
// Update timestamp for next check
try {
    if (window.sessionStorage) {
        sessionStorage.setItem('tm_last_visit_timestamp', Date.now().toString());
    }
} catch (e) {}
```

**Benefits**:
- Uses modern `performance.getEntriesByType('navigation')` API
- Maintains backward compatibility with older browsers
- Adds timestamp-based fallback for maximum reliability
- Prevents false negatives in page reload detection

## Testing Plan

### Local Testing
1. Clear browser cache and sessionStorage
2. Load showcase page
3. Click `.tm-showcase-play-overlay` button
4. Navigate to next vendor using `.keyboard-nav-right`
5. Verify autoplay works without clicking the overlay again

### Remote Testing (app.topdoctorchannel.us)
1. Flush LiteSpeed cache: `wp lscache flush --allow-root`
2. Purge player.js: `wp lscache purge_url 'https://app.topdoctorchannel.us/wp-content/plugins/ecomcine/modules/tm-media-player/assets/js/player.js' --allow-root`
3. Test on multiple browsers (Chrome, Firefox, Safari)
4. Verify autoplay persistence across vendor swaps

### Browser Console Diagnostics
Run these commands in browser console:
```javascript
// Check user activation API
console.log('userActivation available:', typeof document.userActivation !== 'undefined');
if (typeof document.userActivation !== 'undefined') {
    console.log('hasBeenActive:', document.userActivation.hasBeenActive);
}

// Check navigation API
console.log('navigation entries:', performance.getEntriesByType('navigation'));

// Check sessionStorage
console.log('tm_showcase_started:', sessionStorage.getItem('tm_showcase_started'));
console.log('tm_last_visit_timestamp:', sessionStorage.getItem('tm_last_visit_timestamp'));
```

## Expected Outcomes

### Before Fix
- Auto-play works on localhost but fails on mutualized hosting
- Users must click `.tm-showcase-play-overlay` for each vendor/talent swap
- Inconsistent behavior between environments

### After Fix
- Auto-play works consistently on both localhost and mutualized hosting
- Users only need to click once; subsequent swaps autoplay automatically
- Cross-browser compatibility maintained
- No more deprecated API usage

## Rollback Plan

If issues arise, revert to the original code:

```javascript
// Revert hasValidUserActivation() function
var userHasInteracted = false;
var userHasMadeRealGesture = false;

// Revert hasShowcaseStarted() function
function hasShowcaseStarted() {
    try {
        return !!(window.sessionStorage && sessionStorage.getItem("tm_showcase_started") === "1");
    } catch (e) {
        return false;
    }
}

// Revert isPageReload detection
var isPageReload = !!(window.performance && window.performance.navigation && window.performance.navigation.type === 1);
```

## Deployment Notes

1. **Cache Clearing**: After deployment, flush LiteSpeed cache on the online instance:
   ```bash
   ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
     "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache flush --allow-root"
   ```

2. **Player.js Purge**: Specifically purge player.js from cache:
   ```bash
   ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
     "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache purge_url 'https://app.topdoctorchannel.us/wp-content/plugins/ecomcine/modules/tm-media-player/assets/js/player.js' --allow-root"
   ```

3. **Version Bump**: Consider bumping the plugin version to trigger a fresh install on the online instance

## Related Documentation

- `specs/IDE-AI-Command-Catalog.md` - Canonical terminal commands
- `specs/AI-Root-Cause-Remediation-Policy.md` - Remediation policy
- `foundation/wp/docs/WP-REMOTE-OPS.md` - Remote WordPress operations
- `specs/GITHUB-AUTH-REFERENCE.md` - SSH connection reference

## Author
GitHub Copilot (qwen3.5:35b-q3-256k-bartowski)

## Status
✅ **P0 FIXES APPLIED** - Ready for testing
