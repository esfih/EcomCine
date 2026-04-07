# Auto-Play Persistence Testing Guide

## Overview
This guide provides step-by-step instructions for testing the P0 fix for auto-play persistence across localhost and online hosting environments.

## Prerequisites
- Access to localhost WordPress instance (http://localhost:8180)
- Access to online instance (https://app.topdoctorchannel.us)
- SSH access to remote hosting (via `~/.ssh/ecomcine_n0c`)
- Modern browser (Chrome 83+, Firefox 70+, Safari 15.4+)

---

## Test Scenario 1: Localhost Baseline

### Step 1: Clear Browser Cache
```bash
# In browser console (F12):
sessionStorage.clear();
localStorage.clear();
```

### Step 2: Navigate to Showcase Page
1. Open http://localhost:8180 in browser
2. Navigate to Talent Showcase page
3. Verify `.tm-showcase-play-overlay` button is visible

### Step 3: First Play Interaction
1. Click the `.tm-showcase-play-overlay` button
2. Verify video starts playing
3. Verify overlay becomes hidden (`.is-hidden` class added)
4. Check browser console for: `[TM PLAYER v2.0.2] _playerMode: showcase`

### Step 4: Vendor Swap Test
1. Click the `.keyboard-nav-right` button (next talent)
2. Wait for new vendor to load
3. **Expected**: Video autoplay starts without clicking the overlay again
4. **Verify**: Overlay remains hidden

### Step 5: Console Diagnostics
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

// Check player state
console.log('state.isPlaying:', window.tmPlayerState?.isPlaying);
console.log('userHasInteracted:', window.tmPlayerState?.userHasInteracted);
```

---

## Test Scenario 2: Online Instance (Mutualized Hosting)

### Step 1: Flush LiteSpeed Cache
```bash
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
  "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache flush --allow-root"
```

### Step 2: Purge player.js from Cache
```bash
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
  "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache purge_url 'https://app.topdoctorchannel.us/wp-content/plugins/ecomcine/modules/tm-media-player/assets/js/player.js' --allow-root"
```

### Step 3: Clear Browser Cache
1. Open https://app.topdoctorchannel.us in browser
2. Open DevTools (F12)
3. Right-click the Refresh button → "Empty Cache and Hard Reload"

### Step 4: Navigate to Showcase Page
1. Go to Talent Showcase page
2. Verify `.tm-showcase-play-overlay` button is visible
3. Verify player.js is loaded (check Network tab for player.js)

### Step 5: First Play Interaction
1. Click the `.tm-showcase-play-overlay` button
2. Verify video starts playing
3. Verify overlay becomes hidden
4. Check browser console for: `[TM PLAYER v2.0.2] _playerMode: showcase`

### Step 6: Vendor Swap Test
1. Click the `.keyboard-nav-right` button (next talent)
2. Wait for new vendor to load
3. **Expected**: Video autoplay starts without clicking the overlay again
4. **Verify**: Overlay remains hidden

### Step 7: Console Diagnostics
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

// Check player state
console.log('state.isPlaying:', window.tmPlayerState?.isPlaying);
console.log('userHasInteracted:', window.tmPlayerState?.userHasInteracted);
```

---

## Test Scenario 3: Cross-Browser Compatibility

### Chrome/Edge
1. Test on Chrome 120+ and Edge 120+
2. Verify `document.userActivation` API is available
3. Verify autoplay persistence works

### Firefox
1. Test on Firefox 120+
2. Verify `document.userActivation` API is available
3. Verify autoplay persistence works

### Safari
1. Test on Safari 16.4+
2. Verify `document.userActivation` API is available
3. Verify autoplay persistence works

---

## Expected Results

### Success Criteria
✅ User clicks `.tm-showcase-play-overlay` once
✅ Subsequent vendor swaps autoplay without clicking overlay again
✅ `document.userActivation.hasBeenActive` returns `true` after first click
✅ `sessionStorage.tm_showcase_started` is set to `"1"`
✅ Navigation API correctly detects page reloads
✅ No console errors related to autoplay or player state

### Failure Indicators
❌ User must click `.tm-showcase-play-overlay` for each vendor swap
❌ `document.userActivation` API not available or returns `false`
❌ `sessionStorage` not set or cleared unexpectedly
❌ Console errors: "Failed to play", "Autoplay blocked", etc.
❌ Overlay reappears on vendor swap

---

## Troubleshooting

### Issue: Autoplay still requires click on each vendor swap

#### Diagnostic Steps
1. Check browser console for autoplay rejection errors
2. Verify `document.userActivation.hasBeenActive` returns `true`
3. Check `sessionStorage.tm_showcase_started` is set to `"1"`
4. Verify player.js is loaded (check Network tab)
5. Check LiteSpeed cache is flushed on remote hosting

#### Solutions
1. **Clear browser cache and sessionStorage**
   ```javascript
   sessionStorage.clear();
   localStorage.clear();
   location.reload();
   ```

2. **Flush LiteSpeed cache on remote hosting**
   ```bash
   ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
     "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache flush --allow-root"
   ```

3. **Verify player.js is loaded**
   - Open DevTools → Network tab
   - Filter for `player.js`
   - Verify status is `200` (not `304` from cache)

4. **Check browser autoplay policy**
   - Chrome: `chrome://settings/content/autoplay`
   - Firefox: `about:config` → `media.autoplay.default`
   - Safari: Settings → Websites → Auto-Play

### Issue: `document.userActivation` API not available

#### Diagnostic Steps
1. Check browser version
2. Verify API availability in console:
   ```javascript
   console.log('userActivation available:', typeof document.userActivation !== 'undefined');
   ```

#### Solutions
1. **Update browser** to latest version
2. **Fallback to sessionStorage** is built into the code
3. **Test on multiple browsers** to verify compatibility

### Issue: Page reload detection fails

#### Diagnostic Steps
1. Check navigation API in console:
   ```javascript
   console.log('navigation entries:', performance.getEntriesByType('navigation'));
   ```

2. Verify `isPageReload` logic in player.js

#### Solutions
1. **Clear browser cache** to ensure fresh page load
2. **Test with hard reload** (Ctrl+Shift+R or Cmd+Shift+R)
3. **Verify timestamp fallback** works correctly

---

## Performance Metrics

### Load Time
- Player.js load time should be < 500ms
- First play interaction should be < 2 seconds

### Autoplay Latency
- Time from vendor swap to autoplay start should be < 1 second
- No visible overlay reappearing during swap

### Memory Usage
- sessionStorage should use < 1KB
- No memory leaks after multiple vendor swaps

---

## Rollback Procedure

If issues arise after deployment:

### Step 1: Restore Backup
```bash
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
  "cp ${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js.backup.* ${PLUGIN_PATH}/modules/tm-media-player/assets/js/player.js"
```

### Step 2: Flush Cache
```bash
ssh -i ~/.ssh/ecomcine_n0c -p 5022 efttsqrtff@209.16.158.249 \
  "wp --path=/home/efttsqrtff/app.topdoctorchannel.us lscache flush --allow-root"
```

### Step 3: Verify Rollback
- Test on localhost and online instance
- Verify original behavior is restored

---

## Reporting Results

### Success Report Template
```
Test Date: YYYY-MM-DD
Browser: Chrome/Firefox/Safari (version)
Environment: Localhost / Online (app.topdoctorchannel.us)

Results:
✅ First play interaction: PASS
✅ Vendor swap autoplay: PASS
✅ document.userActivation API: Available/Not Available
✅ sessionStorage.tm_showcase_started: Set/Not Set
✅ Navigation API: Working/Not Working
✅ Console errors: None/Specific errors listed

Notes:
[Any additional observations or issues]
```

### Failure Report Template
```
Test Date: YYYY-MM-DD
Browser: Chrome/Firefox/Safari (version)
Environment: Localhost / Online (app.topdoctorchannel.us)

Results:
❌ First play interaction: FAIL (reason)
❌ Vendor swap autoplay: FAIL (reason)
❌ document.userActivation API: Not Available/Returns false
❌ sessionStorage.tm_showcase_started: Not Set/Cleared unexpectedly
❌ Navigation API: Not Working/Incorrect detection
❌ Console errors: [List errors]

Root Cause Analysis:
[Detailed analysis of why the test failed]

Recommended Fix:
[Suggested solution to resolve the issue]
```

---

## Related Documentation
- `specs/operational-runbooks/P0-FIX-AUTOPLAY-PERSISTENCE.md` - Fix documentation
- `specs/IDE-AI-Command-Catalog.md` - Terminal commands
- `foundation/wp/docs/WP-REMOTE-OPS.md` - Remote WordPress operations
- `specs/GITHUB-AUTH-REFERENCE.md` - SSH connection reference

---

## Author
GitHub Copilot (qwen3.5:35b-q3-256k-bartowski)

## Last Updated
April 5, 2026
