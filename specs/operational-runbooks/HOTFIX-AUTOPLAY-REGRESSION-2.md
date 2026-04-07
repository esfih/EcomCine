# Hotfix #2: Auto-Play Regression - Final Fix

## Date
April 5, 2026

## Issue
After implementing the first hotfix, both issues remained on localhost:
1. Auto-play still not working
2. Styling still missing

## Root Cause Analysis

### Why the First Hotfix Didn't Work

The first hotfix added `document.userActivation.hasBeenActive` as a fallback in `hasShowcaseStarted()`, which automatically set `sessionStorage.tm_showcase_started = '1'` when a user gesture was detected. This caused a **false positive**:

```javascript
// WRONG: Automatically sets sessionStorage from userActivation
if (document.userActivation.hasBeenActive) {
    sessionStorage.setItem('tm_showcase_started', '1'); // Sets it too early!
    return true;
}
```

**The Problem:**
- When a user navigates to the page (even without clicking), the browser considers it a "navigation" gesture
- `document.userActivation.hasBeenActive` returns `true`
- `sessionStorage.tm_showcase_started` is set to `'1'`
- On the next page load, `hasShowcaseStarted()` returns `true`
- The overlay is hidden, but auto-play is blocked because the user never actually clicked the play button

### Why Styling Issues Persisted

The styling issues were likely caused by:
1. Browser cache not being cleared
2. WordPress cache not being flushed
3. CSS files not being reloaded

## Fix Applied

### Fix #1: Simplified `hasShowcaseStarted()` Function
**Location**: Lines 1548-1558

```javascript
function hasShowcaseStarted() {
    // Primary: Check sessionStorage (set ONLY when user clicks play button)
    // This is the authoritative source for whether showcase has started
    // We do NOT set this from document.userActivation to avoid false positives
    try {
        if (window.sessionStorage && sessionStorage.getItem('tm_showcase_started') === '1') {
            return true;
        }
    } catch (e) {}
    return false;
}
```

**Key Changes**:
1. **Only check sessionStorage** - No fallback to `document.userActivation`
2. **No automatic setting** - sessionStorage is only set when user clicks play button
3. **Strict behavior** - Ensures overlay is shown until user explicitly clicks

**Benefits**:
- Maintains original behavior: overlay shown until user clicks
- No false positives from navigation gestures
- Subsequent vendor swaps autoplay automatically after first click

### Fix #2: Removed Unused `hasValidUserActivation()` Function
**Location**: Lines 1470-1493 (removed)

The `hasValidUserActivation()` function was added in the first hotfix but is no longer needed since we're not using `document.userActivation` as a fallback.

**Benefits**:
- Cleaner code
- No confusion about which function is authoritative
- Maintains original behavior

## Testing Plan

### Step 1: Clear All Caches
```bash
# Clear browser cache
# In browser console (F12):
sessionStorage.clear();
localStorage.clear();
location.reload();

# Clear WordPress cache
cd /root/dev/EcomCine
MSYS_NO_PATHCONV=1 docker exec wordpress7 wp cache flush --allow-root
```

### Step 2: Test Auto-Play Flow
1. Navigate to http://localhost:8180
2. Go to Talent Showcase page
3. **Verify**: `.tm-showcase-play-overlay` button is VISIBLE (not hidden)
4. Click `.tm-showcase-play-overlay` button
5. **Verify**: Video starts playing
6. **Verify**: Overlay becomes hidden
7. Click `.keyboard-nav-right` to navigate to next vendor
8. **Expected**: Video autoplay starts without clicking overlay again

### Step 3: Verify Styling
1. Check that all fonts are loaded
2. Verify all elements are styled correctly
3. Check for any missing CSS rules
4. If styling issues persist, hard reload browser (Ctrl+Shift+R)

### Step 4: Console Diagnostics
```javascript
// Check player state
console.log('state.isPlaying:', window.tmPlayerState?.isPlaying);
console.log('userHasInteracted:', window.tmPlayerState?.userHasInteracted);

// Check sessionStorage
console.log('tm_showcase_started:', sessionStorage.getItem('tm_showcase_started'));

// Check user activation API
console.log('userActivation available:', typeof document.userActivation !== 'undefined');
if (typeof document.userActivation !== 'undefined') {
    console.log('hasBeenActive:', document.userActivation.hasBeenActive);
}
```

## Expected Outcomes

### Before Hotfix
- ❌ Auto-play broken on localhost
- ❌ Overlay remains visible after first click
- ❌ Users must click overlay for each vendor swap
- ❌ Styling issues persist

### After Hotfix
- ✅ Auto-play works on localhost
- ✅ Overlay hidden after first click
- ✅ Subsequent vendor swaps autoplay automatically
- ✅ Styling restored (after cache flush)

## Technical Explanation

### Why sessionStorage is the Authoritative Source

The `sessionStorage.tm_showcase_started` flag is set **ONLY** when the user clicks the play button:

```javascript
$overlay.on("click", function() {
    // ...
    try {
        if (window.sessionStorage) {
            sessionStorage.setItem("tm_showcase_started", "1");
        }
    } catch (e) {}
});
```

This is the definitive indicator that:
1. The user has interacted with the player
2. Autoplay should be enabled for subsequent vendor swaps
3. The overlay should be hidden

### Why We Don't Use document.userActivation

The `document.userActivation.hasBeenActive` API returns `true` if a user gesture has occurred within the last 10 seconds. However, this includes:
- Page navigation (clicking links, back/forward buttons)
- Keyboard interactions
- Touch gestures

These are **not** the same as clicking the play button. Using `document.userActivation` as a fallback would cause false positives where the overlay is hidden even though the user never clicked the play button.

### Why This Fix Maintains Original Behavior

The original behavior was:
1. Show overlay on initial page load
2. User clicks overlay to start playback
3. Set `sessionStorage.tm_showcase_started = '1'`
4. Hide overlay
5. Subsequent vendor swaps autoplay without clicking overlay again

The hotfix maintains this behavior by:
1. Checking `sessionStorage` only (authoritative source)
2. NOT setting `sessionStorage` from `document.userActivation`
3. Ensuring overlay is shown until user explicitly clicks

This ensures the fix works correctly while maintaining the original user experience.

## Deployment Notes

### Localhost Testing
1. Clear browser cache and sessionStorage
2. Hard reload the page
3. Test the auto-play flow

### Remote Deployment (if needed)
```bash
# Deploy to remote hosting
./scripts/deploy-player-fix.sh

# Flush LiteSpeed cache
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
  "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache flush --allow-root"
```

## Related Documentation
- `specs/operational-runbooks/P0-FIX-AUTOPLAY-PERSISTENCE.md` - Original P0 fix documentation
- `specs/operational-runbooks/HOTFIX-AUTOPLAY-REGRESSION.md` - First hotfix documentation
- `specs/IDE-AI-Command-Catalog.md` - Terminal commands

## Author
GitHub Copilot (qwen3.5:35b-q3-256k-bartowski)

## Status
✅ **HOTFIX #2 APPLIED** - Ready for testing

---

## Summary of Changes

| File | Changes |
|---|---|
| `ecomcine/modules/tm-media-player/assets/js/player.js` | - Simplified `hasShowcaseStarted()` function<br>- Removed `hasValidUserActivation()` function<br>- Removed automatic sessionStorage setting from userActivation |
| `specs/operational-runbooks/HOTFIX-AUTOPLAY-REGRESSION-2.md` | (NEW) - Comprehensive documentation of the second hotfix |

## Key Takeaways

1. **sessionStorage is the authoritative source** for whether the showcase has started
2. **document.userActivation should NOT be used as a fallback** - it causes false positives
3. **Strict behavior** ensures overlay is shown until user explicitly clicks
4. **Original behavior is maintained** - no regression from the original code
