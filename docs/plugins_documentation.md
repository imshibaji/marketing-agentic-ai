# Marketing AI Agent - Plugins Documentation

This document describes the design, architecture, features, and configuration of the custom extension plugins developed for the **Marketing AI Agent Suite**: **Alert Box** and **Notification Widget**.

---

## 1. Plugin Engine Architecture

The application implements a hybrid extension engine supporting both server-side PHP hooks and client-side JavaScript hooks:

- **PHP Engine**: scans the `plugins/` directory, checks if folders are listed in the `active_plugins` setting inside the database, loads the plugin's entry PHP file (e.g. `app.php`), and serves CSS/JS assets via `/plugin-asset.php`.
- **JS Hook Registry**: exposes a global `window.AppHooks` object on the frontend. Plugins can attach event hooks using `addAction(hook, callback)` and modify data using `addFilter(hook, callback)`.

---

## 2. Alert Box Plugin

### Description
The **Alert Box** plugin intercepts all standard, blocking browser `window.alert()` dialogs and redirects them to a premium glassmorphic modal box. This ensures a consistent, visually pleasing design that does not block the browser's thread, paired with context-aware auditory feedback.

### Features
- **Global Interception**: Overrides `window.alert` so that any JavaScript alert call in the application is routed through the custom modal.
- **Glassmorphic Design**: An overlay with heavy backdrop-blur (`blur(14px) saturate(180%)`) and a sleek dark card featuring type-based neon glowing rings (emerald for success, rose/red for errors, blue for info).
- **Asynchronous Queue**: Handles multiple alert calls sequentially. If an alert is triggered while another is open, it queue-stores them and shows them one after the other.
- **Web Audio API Synthesizer**: Uses client-side audio synthesis to play pleasant chimes. It configures the following audio frequency and gain curves:
  - **Success Theme**: Plays a pleasant double A-major chime. Frequencies of 659.25Hz (E5) and 880.00Hz (A5) are triggered sequentially (E5 -> A5 at 100ms offset). The gain rises to `0.15` over `0.05s` and exponentially decays to `0.0001` over `0.4s` using a `sine` oscillator.
  - **Error/Blocked Theme**: Plays a low warning descending tone. Frequencies sweep from 174.61Hz (F3) to 130.81Hz (C3) over `0.25s`. The gain rises to `0.2` and exponentially decays to `0.0001` over `0.4s` using a `triangle` oscillator.
  - **Info Theme**: Plays a soft A-natural chime. Frequency of 440.00Hz (A4) is held, and the gain rises to `0.15` and decays to `0.0001` over `0.35s` using a `sine` oscillator.
- **Accessibility**: Includes keyboard listeners supporting modal dismissal with **Enter** or **Escape** keys, and automatically focuses the dismiss button upon opening.

### File Structure
- `plugins/alert-box/plugin.json`: Metadata, specifying the script entries (`app.js`, `app.css`).
- `plugins/alert-box/app.php`: Backend plugin bootloader (purely client-side plugin, no server-side hooks required).
- `plugins/alert-box/app.js`: Intercepts `window.alert`, manages the queue, constructs DOM nodes, and synthesizes chimes.
- `plugins/alert-box/app.css`: Styles for the overlay, card, icons, and transitions.

---

## 3. Notification Widget Plugin

### Description
The **Notification Widget** plugin introduces a live alert center on the dashboard. It polls system notifications periodically and alerts the user of important system updates, high-value leads, or direct username mentions (`@username`) via sliding visual toasts.

### Server-Side Hook Registration (`plugins/notification-widget/app.php`)
1. **Whitelisted Configuration Variables**:
   Whitelists specific admin settings keys via the filter hook:
   - `notification_widget_max_display`: Maximum number of notifications to show in the scrollable view (default: `5`).
   - `notification_widget_refresh_rate`: Time in seconds between background notification pulls (default: `15`).
   - `notification_widget_sound_enabled`: Toggles client-side chime sounds (default: `1`).
2. **Dynamic DB Simulation Endpoint**:
   Registers a custom routing hook under the key `api_route_notification-widget_simulate`. Clients can make a POST request to:
   `/api/plugin-route.php?plugin=notification-widget&action=simulate` (with optional `&type=mention`) to generate mock events.
3. **Automated Notification Generator**:
   Hooks into the backend's core activity logger via `activity_logged`. When key operational actions occur, a notification entry is automatically written to the database:
   - `CREATE_CAMPAIGN` -> *New Campaign Setup*
   - `DELETE_CAMPAIGN` -> *Campaign Removed*
   - `RUN_CAMPAIGN_GENERATOR` -> *Campaign Copy Generated*
   - `RUN_SDR_FINDER` -> *Prospects Qualified*
   - `RUN_STANDALONE_SCRAPER` -> *Scraper Completed*
   - `SEND_OUTREACH` -> *Outreach Dispatched*
   - `RESET_USAGE` -> *Usage Restored*
   - `RESET_PLAN_EXPIRY` -> *Subscription Renewed*
   - `LOAD_DEMO` -> *Demo Workspace Loaded*

### Client-Side Features (`plugins/notification-widget/app.js`)
- **Dashboard Stats Panel**: Displays metrics for *Total Alerts*, *Unread*, and *My Mentions* with automated pulsing animations for unread updates.
- **Interactive Filtering & Search**: Users can filter alerts by Category (All, Unread, Mentions, High Priority) and perform real-time key searches.
- **P2P Mentions**: Highlights `@username` inside notification text with custom highlighted badges.
- **Toast Notifications**: Slides toast banners into the bottom-right corner of the screen when new alerts arrive, complete with sliding animations, automatic 6-second dismissals, and chime sounds.
- **Action Integrations**: Provides "Mark All Read" options and developer tools to simulate system notifications and mentions.

### File Structure
- `plugins/notification-widget/plugin.json`: Metadata and plugin script entries.
- `plugins/notification-widget/app.php`: Backend routes, hook registrations, settings, and mock simulators.
- `plugins/notification-widget/app.js`: Handles polling, rendering the hub UI, managing read states (persisted via `localStorage`), highlighting mentions, and showing sliding toasts.

---

## 4. Key Developer Context: DOM Query Safeguards

### The Challenge
To prevent page script crashes when looking up DOM elements that only exist on specific tabs, the main application framework script (`public/app.js`) implements a safeguard wrapper around standard lookup methods:
```javascript
const originalGet = document.getElementById;
document.getElementById = function(id) {
    const el = originalGet.call(document, id);
    if (el) return el;
    return createMockElement(); // Returns a dummy helper object instead of null
};
```

### The Bug & The Resolution
Because `document.getElementById` returns a truthy mock object instead of `null` when an element is absent, checks like `if (!toastContainer)` or `if (!overlay)` inside the plugins evaluated to `false`, causing the plugins to skip creating the real DOM elements (meaning they would play sounds but remain invisible on the screen).

To solve this, both plugins implement a **real element resolver** using native prototype binding to bypass mock element generation:
- **Bypassing in Alert Box**:
  ```javascript
  function getRealElement(id) {
      const el = document.getElementById(id);
      return (el && el instanceof HTMLElement) ? el : null;
  }
  ```
- **Bypassing in Notification Widget**:
  ```javascript
  function getRealElement(id) {
      const el = Document.prototype.getElementById.call(document, id);
      return (el && el instanceof HTMLElement) ? el : null;
  }
  ```
Always use `getRealElement()` in frontend scripts whenever checking for element existence.

---

## 5. Activation & Cache-Busting

- **Activation**: Can be toggled on/off through the **System Operations** panel under Settings, which updates the `active_plugins` JSON setting in the `settings` database table.
- **Cache-Busting**: The application templates ([head.php](file:///Users/shibaji/.gemini/antigravity/scratch/marketing-ai-agent/app/Views/layout/partials/head.php) and [scripts.php](file:///Users/shibaji/.gemini/antigravity/scratch/marketing-ai-agent/app/Views/layout/partials/scripts.php)) append the version parameter (e.g. `&v=1.0.1` from `plugin.json`) to serve fresh files and bypass outdated browser caches during updates.
