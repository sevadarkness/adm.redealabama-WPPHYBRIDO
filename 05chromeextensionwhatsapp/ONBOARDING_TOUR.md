# Onboarding Tour - WhatsHybrid Lite

## Overview

The onboarding tour provides a guided walkthrough for new users, introducing them to all the features of the WhatsHybrid Lite extension. The tour is split into two parts:

1. **Popup Tour** (6 steps) - Introduces the extension settings and configuration
2. **WhatsApp Web Tour** (9 steps) - Guides users through the chatbot panel features

## Architecture

### Files Structure

```
05chromeextensionwhatsapp/
├── popup/
│   ├── onboarding.js       # Popup tour logic
│   ├── onboarding.css      # Popup tour styles
│   └── popup.html          # Modified to include tour
├── content/
│   ├── onboarding.js       # WhatsApp tour logic (standalone)
│   ├── onboarding.css      # WhatsApp tour styles
│   └── content.js          # Modified to include inline tour
└── assets/
    └── tour-config.json    # Tour configuration
```

## How It Works

### Popup Tour

1. **Auto-start**: Automatically starts when user opens the extension popup for the first time
2. **Persistence**: Uses `chrome.storage.local` to track completion status (`onboarding_popup_completed`)
3. **Help Button**: "?" button in bottom-right corner allows users to restart the tour

**Tour Steps:**
1. Welcome message (center)
2. Conexão card - API configuration
3. Backend card - Server setup
4. Chatbot card - AI persona and business context
5. Modo Copiloto card - Auto-response mode
6. Memória Híbrida card - Memory synchronization

### WhatsApp Web Tour

1. **Trigger**: Starts automatically when user opens the chatbot panel for the first time
2. **Shadow DOM**: Works inside the shadow root of the floating panel
3. **Observer Pattern**: Uses MutationObserver to detect when panel is opened
4. **Help Button**: "?" button inside panel allows tour restart

**Tour Steps:**
1. FAB button - How to open the panel
2. Chatbot tab - AI suggestions
3. Action modes - Different AI modes
4. Generate button - Creating suggestions and feedback
5. Memory button - Leão memory system
6. Campaigns tab - Mass messaging
7. Scheduling - Campaign scheduling
8. Contacts tab - Contact extraction
9. Training tab - AI knowledge base

## Features

### Visual Feedback
- **Highlight**: Purple glow around active element
- **Overlay**: Dark backdrop to focus attention
- **Tooltip**: Positioned contextually (top/bottom/left/right/center)
- **Progress Dots**: Visual progress indicator
- **Animations**: Smooth fade-in transitions

### Navigation
- **Next/Previous**: Navigate between steps
- **Skip**: Close tour with confirmation
- **Complete**: Mark tour as finished
- **Restart**: Review tour anytime via "?" button

### Persistence
- `onboarding_popup_completed` - Tracks popup tour completion
- `onboarding_whatsapp_completed` - Tracks WhatsApp tour completion

## User Flow

```
1. User installs extension
   ↓
2. Opens popup → Popup tour starts (6 steps)
   ↓
3. Completes popup tour
   ↓
4. Goes to WhatsApp Web
   ↓
5. Clicks 🤖 button → Panel opens
   ↓
6. WhatsApp tour starts (9 steps)
   ↓
7. Completes WhatsApp tour
   ↓
8. Can click "?" anytime to restart tours
```

## Configuration

Edit `assets/tour-config.json` to modify tour settings:

```json
{
  "version": "1.0.0",
  "popup_steps": 6,
  "whatsapp_steps": 9,
  "total_steps": 15,
  "features": {
    "auto_start": true,        // Auto-start on first open
    "show_help_button": true,  // Show "?" button
    "allow_skip": true,        // Allow skipping tour
    "show_progress": true      // Show progress dots
  }
}
```

## Customization

### Adding New Steps

1. Add step to `steps` array in `onboarding.js`:
```javascript
{
  step: 7,
  target: '.new-element',
  title: '🎯 New Feature',
  content: 'Description of the new feature.',
  position: 'bottom',
  highlight: true
}
```

2. Update `tour-config.json` with new step count

### Styling

Tour styles are included inline in both:
- `popup/onboarding.css` - For popup
- `content/content.js` - For WhatsApp panel (inline styles)

Modify CSS variables:
```css
--accent: #8b5cf6;      /* Primary purple */
--accent2: #3b82f6;     /* Secondary blue */
--ok: #22c55e;          /* Success green */
```

## Testing

### Manual Testing Checklist

**Popup Tour:**
- [ ] Opens automatically on first popup open
- [ ] All 6 steps display correctly
- [ ] Navigation (Next/Previous) works
- [ ] Elements are highlighted correctly
- [ ] Skip button shows confirmation
- [ ] Complete button finishes tour
- [ ] Completion modal appears
- [ ] "?" button restarts tour
- [ ] Tour doesn't show again after completion

**WhatsApp Web Tour:**
- [ ] Opens when panel is opened first time
- [ ] All 9 steps display correctly
- [ ] Tabs switch automatically
- [ ] Elements inside shadow DOM are highlighted
- [ ] Navigation works correctly
- [ ] Completion modal shows
- [ ] "?" button in panel works
- [ ] Tour doesn't show again after completion

### Reset Tour for Testing

Open browser console and run:
```javascript
chrome.storage.local.remove(['onboarding_popup_completed', 'onboarding_whatsapp_completed']);
```

## Troubleshooting

**Tour doesn't start:**
- Check browser console for errors
- Verify chrome.storage permissions in manifest.json
- Clear storage and reload extension

**Elements not highlighted:**
- Verify CSS selectors match actual DOM
- Check z-index conflicts
- Ensure shadow DOM access for WhatsApp tour

**Tooltip positioning wrong:**
- Adjust position calculation in `positionTooltip()`
- Check for viewport overflow
- Verify parent element positioning

## Future Enhancements

- [ ] Add voice narration option
- [ ] Interactive elements (user must click to proceed)
- [ ] Video tutorials embedded in steps
- [ ] Analytics tracking for tour completion
- [ ] Multi-language support
- [ ] Contextual help tooltips throughout app
