# Testing Guide - Onboarding Tour

## Quick Start Testing

### 1. Load the Extension

1. Open Chrome/Edge browser
2. Go to `chrome://extensions/` (or `edge://extensions/`)
3. Enable "Developer mode" (toggle in top-right)
4. Click "Load unpacked"
5. Select the `05chromeextensionwhatsapp` directory
6. Extension should now be loaded

### 2. Reset Tour State (if needed)

Open browser console (F12) and run:
```javascript
chrome.storage.local.remove(['onboarding_popup_completed', 'onboarding_whatsapp_completed'], () => {
  console.log('Tour state reset');
});
```

### 3. Test Popup Tour

**Expected Behavior:**
1. Click the extension icon in toolbar
2. Tour should start automatically with a purple overlay
3. First step shows welcome message (centered)
4. Click "Próximo →" to advance through 6 steps
5. Each card should be highlighted with purple glow
6. Progress dots show current position
7. Last step shows "Concluir ✓" button
8. Completion modal appears with success message
9. Click "?" button in bottom-right to restart tour

**Tour Steps to Verify:**
- Step 1: Welcome (center position)
- Step 2: Conexão card (bottom position)
- Step 3: Backend card (bottom position)
- Step 4: Chatbot card (bottom position)
- Step 5: Modo Copiloto card (bottom position) - has highlight flag
- Step 6: Memória Híbrida card (top position)

**Things to Check:**
- [ ] Overlay dims background
- [ ] Elements are highlighted correctly
- [ ] Tooltip arrows point to correct elements
- [ ] "← Anterior" button works (from step 2+)
- [ ] "✕" close button asks for confirmation
- [ ] Progress dots show active/completed states
- [ ] Completion modal auto-closes after 5 seconds
- [ ] Tour doesn't restart on next popup open

### 4. Test WhatsApp Web Tour

**Expected Behavior:**
1. Go to https://web.whatsapp.com
2. Wait for WhatsApp to load
3. Look for the floating 🤖 button (bottom-right)
4. Click the 🤖 button to open panel
5. Tour should start automatically
6. First step highlights the FAB button (left position)
7. Click "Próximo →" to advance through 9 steps
8. Tour automatically switches between tabs as needed
9. Completion modal shows with 2 buttons
10. "?" button in panel allows tour restart

**Tour Steps to Verify:**
- Step 1: FAB button (left position)
- Step 2: Chatbot tab (right position)
- Step 3: Action modes dropdown (right position)
- Step 4: Generate button (right position) - has highlight flag
- Step 5: Memory button (right position)
- Step 6: Campaigns tab (right position)
- Step 7: Scheduling input (right position)
- Step 8: Contacts tab (right position)
- Step 9: Training tab (right position)

**Things to Check:**
- [ ] Tour waits for panel to open
- [ ] Overlay works inside shadow DOM
- [ ] Elements inside panel are highlighted
- [ ] Tabs switch automatically
- [ ] Tooltip positioning is correct
- [ ] Navigation works smoothly
- [ ] "Ver tour novamente" button in completion modal works
- [ ] "?" button in panel is visible and works
- [ ] Tour doesn't restart on next panel open

## Edge Cases to Test

### Popup Tour
- [ ] Close extension popup during tour - should not save progress
- [ ] Skip tour - should not show again
- [ ] Restart tour - should work multiple times
- [ ] Resize browser window - tooltip should stay positioned
- [ ] Open multiple popup windows - each should handle tour independently

### WhatsApp Web Tour
- [ ] Close panel during tour - tour should pause
- [ ] Reopen panel after closing during tour - should restart from beginning
- [ ] Switch to different WhatsApp chat - tour should continue
- [ ] Navigate away from WhatsApp - tour should stop
- [ ] Return to WhatsApp - tour should not auto-restart (unless first time)

## Browser Console Debugging

### Check if tour is completed:
```javascript
chrome.storage.local.get(['onboarding_popup_completed', 'onboarding_whatsapp_completed'], (result) => {
  console.log('Popup tour completed:', result.onboarding_popup_completed);
  console.log('WhatsApp tour completed:', result.onboarding_whatsapp_completed);
});
```

### Force complete tours:
```javascript
chrome.storage.local.set({
  onboarding_popup_completed: true,
  onboarding_whatsapp_completed: true
}, () => {
  console.log('Tours marked as completed');
});
```

### Force restart tours:
```javascript
chrome.storage.local.set({
  onboarding_popup_completed: false,
  onboarding_whatsapp_completed: false
}, () => {
  console.log('Tours will restart on next open');
});
```

### Check for JavaScript errors:
```javascript
// Open DevTools (F12) and look for any red errors in Console
// Common issues:
// - "Cannot read property of undefined" - element not found
// - "chrome.storage is not defined" - permission issue
// - "classList is null" - DOM element missing
```

## Performance Testing

### Popup Tour
- [ ] Tour starts within 500ms of popup open
- [ ] Transitions are smooth (no jank)
- [ ] Memory usage is reasonable (<5MB)
- [ ] No console errors or warnings

### WhatsApp Web Tour
- [ ] Tour starts within 500ms of panel open
- [ ] Doesn't impact WhatsApp Web performance
- [ ] Shadow DOM isolation works correctly
- [ ] No memory leaks after tour completion

## Accessibility Testing

### Keyboard Navigation
- [ ] Tab key navigates through tour buttons
- [ ] Enter key activates buttons
- [ ] Escape key closes tour (future enhancement)

### Screen Reader
- [ ] Tour tooltip has proper ARIA labels
- [ ] Highlighted elements are announced
- [ ] Progress indicators are accessible

## Cross-Browser Testing

Test in multiple browsers:
- [ ] Chrome (latest)
- [ ] Chrome (version -1)
- [ ] Edge (Chromium-based)
- [ ] Brave
- [ ] Opera

## Mobile/Responsive (if applicable)
- [ ] Tour works on smaller viewports
- [ ] Tooltips don't overflow
- [ ] Touch interactions work
- [ ] Help button is accessible

## Known Issues / Limitations

1. **Shadow DOM**: WhatsApp tour requires shadow DOM support (all modern browsers)
2. **Positioning**: Tooltip position may need adjustment based on viewport size
3. **Tab Switching**: Auto tab switching may have slight delay (200ms)
4. **First Time Only**: Tour only shows on first open (by design)

## Reporting Issues

If you find any issues, please report with:
1. Browser name and version
2. Steps to reproduce
3. Expected vs actual behavior
4. Screenshots/video if possible
5. Console errors (if any)

## Success Criteria

✅ **The onboarding tour is working correctly if:**
- Popup tour shows on first popup open
- All 6 popup steps display with correct highlighting
- WhatsApp tour shows on first panel open
- All 9 WhatsApp steps display with correct highlighting
- Navigation works smoothly
- Tours don't show again after completion
- Help buttons allow tour restart
- No console errors
- Tours persist correctly across sessions
