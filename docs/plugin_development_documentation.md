# Marketing AI Agent - Plugin Development Documentation

Welcome to the **Plugin Development Guide** for the Marketing AI Agent Suite. This document details how to extend and customize the application by creating plugins. The system features a unified pluggable architecture with hook execution patterns on both the **backend (PHP)** and the **client frontend (JavaScript)**.

---

## 1. Architecture Overview

Plugins reside in the `plugins/` directory at the project root. The core system discovers and manages plugins dynamically through `PluginManager` and `HookManager`.

```
plugins/
├── slack-notifier/               # Example Plugin 1
│   ├── plugin.json               # Required: Plugin metadata
│   ├── app.php                   # Required: PHP backend hooks entry point
│   └── app.js                    # Optional: Frontend JS injection entry point
│
└── notification-widget/          # Example Plugin 2
    ├── plugin.json
    ├── app.php
    └── app.js
```

### Lifecycle Flow

```
Application Boot
    │
    ├─ DatabaseService::__construct()
    │       └─ PluginManager::initialize($db)
    │               └─ Loads all enabled plugins' app.php files
    │                       └─ Each app.php registers HookManager actions/filters
    │
    ├─ CodeIgniter 4 Handles HTTP Request
    │       └─ Controllers call HookManager::doAction() / applyFilters() at key points
    │
    └─ dashboard.php View is rendered
            ├─ window.AppHooks object is defined (JS hook registry)
            ├─ Active plugins' app.js files are injected via <script> tags
            └─ app.js fires: window.AppHooks.doAction('dom_ready')
```

### Backend vs Frontend Scope

| Capability | Backend (PHP) | Frontend (JavaScript) |
| :--- | :--- | :--- |
| Hook System | `MarketingAgent\Plugin\HookManager` | `window.AppHooks` |
| DB Access | Via `PluginManager::getDatabaseService()` | API calls via `fetch()` |
| Settings | `HookManager::addFilter('admin_settings_allowed_keys', ...)` | Inject inputs into `#app-panel` |
| Custom Endpoints | `HookManager::addAction('api_route_{plugin}_{action}', ...)` | `fetch('api/plugin-route.php?plugin=...&action=...')` |
| Event Hooks | `lead_qualified`, `leads_pipeline_complete`, etc. | `dom_ready`, `tab_switched`, `app_init` |

---

## 2. Directory & Metadata Structure

Each plugin **must** be contained in its own folder under `plugins/`. Entry files are declared in `plugin.json`.

### `plugin.json` — Required Metadata File

```json
{
  "name": "My Custom Plugin",
  "version": "1.0.0",
  "description": "Explains what the plugin does and how it extends the system.",
  "author": "Your Name",
  "entry_php": "app.php",
  "entry_js": "app.js",
  "entry_css": ""
}
```

| Field | Required | Description |
| :--- | :--- | :--- |
| `name` | Yes | Display name shown in the Plugins panel |
| `version` | Yes | Semantic version string |
| `description` | Yes | Human-readable description |
| `author` | No | Plugin author name |
| `entry_php` | No | PHP file loaded on bootstrap (default: `{plugin-id}.php`) |
| `entry_js` | No | JS file injected when plugin is active (default: `{plugin-id}.js`) |
| `entry_css` | No | CSS file injected in `<head>` when plugin is active |

## 3. Backend Hook System (PHP)

The backend provides a WordPress-style event system managed by `MarketingAgent\Plugin\HookManager`.

### 3.1 Complete Action Hook Reference

Actions execute your callback at system events. They do **not** return values. There are **4 action hooks** available:

| # | Hook Name | Arguments | Triggered In | When It Fires |
| :- | :--- | :--- | :--- | :--- |
| 1 | `db_initialize_schema` | `DatabaseService $db` | `DatabaseService::__construct()` | Every time the application boots and initializes the database |
| 2 | `lead_qualified` | `int $leadId, int $campaignId, array $lead` | `LeadAgent::generateAndQualifyLeads()` | After each individual lead is scored and saved by the AI SDR |
| 3 | `leads_pipeline_complete` | `int $campaignId, array $qualifiedLeads` | `LeadAgent::generateAndQualifyLeads()` | After the entire lead generation run for a campaign finishes |
| 4 | `api_route_{plugin}_{action}` | `array $requestData` | `PluginRouteController::dispatch()` | When an HTTP request hits `/api/plugin-route.php?plugin=X&action=Y` |

> **Dynamic Hook Name Pattern** for `api_route_*`: Replace `{plugin}` with your plugin folder name and `{action}` with any action string. Example: `api_route_notification-widget_simulate`.

#### `$lead` array shape (passed to `lead_qualified`)

```php
[
    'id'             => int,     // Database row ID of the saved lead
    'company_name'   => string,
    'contact_name'   => string,
    'email'          => string,
    'whatsapp'       => string,
    'industry'       => string,
    'description'    => string,
    'score'          => string,  // 'HIGH' | 'MEDIUM' | 'LOW'
    'reasoning'      => string,  // LLM qualification rationale
    'email_draft'    => string,
    'whatsapp_draft' => string,
    'sms_draft'      => string,
    'status'         => string   // Always 'GENERATED' at this stage
]
```

#### Registering an Action Hook

```php
<?php
use MarketingAgent\Plugin\HookManager;

// Syntax: addAction(string $hook, callable $callback, int $priority = 10)
HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    error_log("Lead #{$leadId} qualified in campaign #{$campaignId}");
}, 10);
```

#### Hook 1: `db_initialize_schema` — Custom DB Migrations

```php
<?php
use MarketingAgent\Plugin\HookManager;

HookManager::addAction('db_initialize_schema', function($db): void {
    $pdo = $db->getPdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS my_plugin_data (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id    INTEGER NOT NULL,
        extra_note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
});
```

#### Hook 2: `lead_qualified` — React to Each Qualified Lead

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) return;

    $settings = $db->getSettings();
    $webhookUrl = $settings['my_plugin_webhook_url'] ?? '';
    $company    = $lead['company_name'] ?? 'Unknown';
    $score      = $lead['score'] ?? 'MEDIUM';

    $db->logAgentAction($campaignId, 'My Plugin', 'WEBHOOK_SENT',
        "Alert dispatched for '{$company}' (Score: {$score}) → {$webhookUrl}"
    );
});
```

#### Hook 3: `leads_pipeline_complete` — Act on the Full Batch

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

HookManager::addAction('leads_pipeline_complete', function(int $campaignId, array $qualifiedLeads): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) return;

    $highCount = count(array_filter($qualifiedLeads, fn($l) => $l['score'] === 'HIGH'));
    $total     = count($qualifiedLeads);

    $db->logAgentAction($campaignId, 'My Plugin', 'SUMMARY',
        "Pipeline done. {$highCount}/{$total} HIGH-score leads found."
    );
});
```

#### Hook 4: `api_route_{plugin}_{action}` — Custom API Endpoint

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// Called via: GET/POST /api/plugin-route.php?plugin=my-plugin&action=get-data
HookManager::addAction('api_route_my-plugin_get-data', function(array $request): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB unavailable']);
        exit;
    }

    $filter = $request['filter'] ?? 'all';

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filter' => $filter, 'items' => []]);
    exit; // Always exit from API route handlers
});
```

---

### 3.2 Complete Filter Hook Reference

Filters intercept data values and **must return** the (possibly modified) value. There are **5 filter hooks** available:

| # | Filter Name | Arguments | Must Return | Triggered In | When It Fires |
| :- | :--- | :--- | :--- | :--- | :--- |
| 1 | `admin_settings_allowed_keys` | `array $allowedKeys` | `array` | `SettingController::create()` | When admin saves settings — your key must be here or it will be discarded |
| 2 | `admin_get_settings` | `array $settings` | `array` | `SettingController::index()` | When admin fetches settings — use for defaults and masking |
| 3 | `public_settings` | `array $publicSettings, array $fullSettings` | `array` | `SettingController::index()` | When a non-admin user loads settings (login page, etc.) |
| 4 | `llm_provider_instance` | `null $current, string $provider, array $settings` | `LlmProviderInterface\|null` | `LlmFactory::create()` | Before a campaign run — return your LLM class or `null` to pass through |
| 5 | `db_backup_tables` | `array $tables` | `array` | `SystemController::backup()` | During a JSON database export — add your tables here to include them |

#### Registering a Filter Hook

```php
<?php
use MarketingAgent\Plugin\HookManager;

// Syntax: addFilter(string $hook, callable $callback, int $priority = 10)
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'my_plugin_api_key';
    $keys[] = 'my_plugin_webhook_url';
    return $keys;   // ALWAYS return the value
});
```

#### Filter 1: `admin_settings_allowed_keys` — Whitelist Your Settings Keys

```php
<?php
use MarketingAgent\Plugin\HookManager;

HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'my_plugin_api_key';
    $keys[] = 'my_plugin_webhook_url';
    $keys[] = 'my_plugin_enabled';
    return $keys;
});
```

#### Filter 2: `admin_get_settings` — Set Defaults and Mask Secrets

```php
<?php
use MarketingAgent\Plugin\HookManager;

HookManager::addFilter('admin_get_settings', function(array $settings): array {
    // Set defaults if not yet configured
    $settings['my_plugin_max_items'] ??= '10';
    $settings['my_plugin_enabled']   ??= '1';

    // Mask sensitive value so raw key is never sent to browser
    if (!empty($settings['my_plugin_api_key']) && strlen($settings['my_plugin_api_key']) > 8) {
        $key = $settings['my_plugin_api_key'];
        $settings['my_plugin_api_key_masked'] = substr($key, 0, 4) . '....' . substr($key, -4);
    }

    return $settings;
});
```

#### Filter 3: `public_settings` — Expose Safe Values to Non-Admins

```php
<?php
use MarketingAgent\Plugin\HookManager;

// $fullSettings = all admin settings (read-only reference, do not return this!)
// $publicSettings = only keys safe for public exposure
HookManager::addFilter('public_settings', function(array $publicSettings, array $fullSettings): array {
    $publicSettings['my_plugin_banner_text'] = $fullSettings['my_plugin_banner_text'] ?? '';
    return $publicSettings;
});
```

#### Filter 4: `llm_provider_instance` — Register a Custom LLM Adapter

```php
<?php
use MarketingAgent\Plugin\HookManager;

// Your class must implement MarketingAgent\Service\Llm\LlmProviderInterface
HookManager::addFilter('llm_provider_instance', function($current, string $provider, array $settings) {
    if ($provider === 'my-custom-llm') {
        return new MyCustomLlmProvider($settings['my_custom_llm_api_key'] ?? '');
    }
    return $current; // null = fall through to built-in provider list
});
```

#### Filter 5: `db_backup_tables` — Include Plugin Tables in Backups

```php
<?php
use MarketingAgent\Plugin\HookManager;

HookManager::addFilter('db_backup_tables', function(array $tables): array {
    $tables[] = 'my_plugin_data';
    return $tables;
});
```

---

## 4. Plugin API Routing — Custom Backend Endpoints

Plugins can expose their own authenticated REST endpoints **without creating standalone PHP files**. Requests are dispatched through the `PluginRouteController`.

### Endpoint Format

```
GET/POST  /api/plugin-route.php?plugin={plugin-id}&action={action-name}
```

- The `{plugin-id}` must match your plugin's folder name (e.g. `notification-widget`).
- The `{action-name}` is any custom string identifier.
- The request is authenticated via the same `auth` filter as all other API routes.

### Registering a Plugin API Route

In your `app.php` file, register the hook `api_route_{plugin-id}_{action-name}`:

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// Hook name format: api_route_{plugin-id}_{action}
// Called via: GET/POST /api/plugin-route.php?plugin=my-plugin&action=get-data
HookManager::addAction('api_route_my-plugin_get-data', function(array $requestData): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    // Access query/post params from $requestData
    $filter = $requestData['filter'] ?? 'all';

    // ... perform your logic ...

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'filter'  => $filter,
        'items'   => ['Item A', 'Item B', 'Item C']
    ]);
    exit; // Always exit from plugin route handlers
});
```

### Calling the Endpoint from Frontend JavaScript

```javascript
// Authenticated GET request
const res = await fetch('api/plugin-route.php?plugin=my-plugin&action=get-data&filter=active');
const data = await res.json();
console.log(data.items); // ['Item A', 'Item B', 'Item C']

// POST request with JSON body
const postRes = await fetch('api/plugin-route.php?plugin=my-plugin&action=save-config', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ key: 'value', enabled: true })
});
const postData = await postRes.json();
```

---

## 5. Frontend Hook System (JavaScript)

When a plugin's `app.js` is loaded, it can register callbacks using the globally available `window.AppHooks` object.

### JS Action Hooks

| Hook Name | Description | Arguments |
| :--- | :--- | :--- |
| `app_init` | Fires immediately when `app.js` begins executing (before DOM is ready). | None |
| `dom_ready` | Fires when `DOMContentLoaded` completes — safe to manipulate DOM here. | None |
| `tab_switched` | Fires when the user clicks a navigation tab. | `string tabId` |

### Registering JS Action Hooks

```javascript
// Always wrap in an existence check for safety
if (window.AppHooks) {

    // Fires when DOM is fully parsed — safest place to inject UI
    window.AppHooks.addAction('dom_ready', function() {
        console.log('DOM ready — injecting plugin UI...');
    });

    // Fires when the user switches tabs; tabId matches the tab button's id
    window.AppHooks.addAction('tab_switched', function(tabId) {
        if (tabId === 'tab-btn-leads') {
            console.log('User navigated to the Leads tab.');
        }
    });
}
```

### JS Filter Hooks

```javascript
if (window.AppHooks) {
    // Filters apply transformations to a value
    window.AppHooks.addFilter('my_custom_filter', function(value, extraArg) {
        return value + ' (modified by plugin)';
    });
}
```

### Injecting Settings Fields

Use `dom_ready` to add inputs into the **App Settings** panel. The input's `name` attribute must match a whitelisted key registered via `admin_settings_allowed_keys`.

```javascript
if (window.AppHooks) {
    window.AppHooks.addAction('dom_ready', function() {
        const appPanel = document.getElementById('app-panel');
        if (!appPanel) return;

        // Guard against double-injection on re-renders
        if (document.getElementById('my_plugin_api_key')) return;

        const section = document.createElement('div');
        section.style.cssText = 'margin-top:20px; padding-top:16px; border-top:1px dashed var(--border-color);';
        section.innerHTML = `
            <h4 style="margin:0 0 12px; font-size:13px; color:var(--accent-primary);">
                <i class="fas fa-puzzle-piece"></i> My Plugin Settings
            </h4>
            <div class="form-group">
                <label for="my_plugin_api_key">My Plugin API Key</label>
                <input type="password" id="my_plugin_api_key" name="my_plugin_api_key"
                       placeholder="Enter API key..." style="width:100%;">
                <small style="color:var(--text-muted); font-size:11px;">
                    Required to activate the integration.
                </small>
            </div>
        `;
        appPanel.appendChild(section);
    });
}
```

### Injecting Dashboard Widgets

You can dynamically inject a card widget into the dashboard tab area (`#tab-admin-dashboard`):

```javascript
if (window.AppHooks) {
    window.AppHooks.addAction('dom_ready', async function() {
        const dashboardTab = document.getElementById('tab-admin-dashboard');
        if (!dashboardTab) return;

        // Prevent double injection
        if (document.getElementById('my-plugin-widget')) return;

        const widget = document.createElement('div');
        widget.id = 'my-plugin-widget';
        widget.style.cssText = `
            margin-bottom: 24px;
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
        `;
        widget.innerHTML = `
            <h3 style="margin:0 0 16px; font-size:16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                <i class="fas fa-star" style="color:var(--accent-primary);"></i> My Plugin Widget
            </h3>
            <div id="my-plugin-content">Loading...</div>
        `;

        // Insert after the stats grid
        const statsGrid = document.getElementById('admin-only-stats') || document.getElementById('user-only-stats');
        if (statsGrid) {
            statsGrid.parentNode.insertBefore(widget, statsGrid.nextSibling);
        } else {
            dashboardTab.appendChild(widget);
        }

        // Fetch data from your plugin API endpoint
        try {
            const res = await fetch('api/plugin-route.php?plugin=my-plugin&action=get-data');
            const data = await res.json();
            if (data.success) {
                document.getElementById('my-plugin-content').textContent = JSON.stringify(data.items);
            }
        } catch (e) {
            document.getElementById('my-plugin-content').textContent = 'Failed to load data.';
        }
    });
}
```

---

## 6. Web Audio Notifications (Advanced)

Plugins can produce browser sound alerts using the **Web Audio API** without any external libraries:

```javascript
function playChime() {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;

    const ctx = new AudioContext();

    // First tone (C5 → G5)
    const osc1 = ctx.createOscillator();
    const gain = ctx.createGain();

    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(523.25, ctx.currentTime);
    osc1.frequency.exponentialRampToValueAtTime(783.99, ctx.currentTime + 0.15);

    gain.gain.setValueAtTime(0.08, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);

    osc1.connect(gain);
    gain.connect(ctx.destination);

    osc1.start();
    osc1.stop(ctx.currentTime + 0.4);
}

// Call when a new event arrives
playChime();
```

---

## 7. LocalStorage State Persistence

For lightweight client-side state (like read/unread notification tracking), use `localStorage` keyed by username:

```javascript
// Save state
function saveState(username, key, data) {
    localStorage.setItem(`plugin_${key}_${username}`, JSON.stringify(data));
}

// Load state
function loadState(username, key, defaultValue = null) {
    const saved = localStorage.getItem(`plugin_${key}_${username}`);
    if (!saved) return defaultValue;
    try {
        return JSON.parse(saved);
    } catch (e) {
        return defaultValue;
    }
}

// Usage example
const readIds = new Set(loadState(currentUser.username, 'read_notifications', []));
readIds.add(newNotificationId);
saveState(currentUser.username, 'read_notifications', Array.from(readIds));
```

---

## 8. Step-by-Step Tutorial: Slack Notifier Plugin

This plugin adds a Slack Webhook URL field to Settings and posts alerts when a lead is qualified.

### Step 1: Create the plugin folder
```
plugins/slack-notifier/
```

### Step 2: `plugin.json`
```json
{
  "name": "Slack Notifier",
  "version": "1.0.0",
  "description": "Logs lead qualifications as Slack alerts and adds Webhook configuration to App settings.",
  "author": "Mark Dev",
  "entry_php": "app.php",
  "entry_js": "app.js"
}
```

### Step 3: `app.php` — Backend Logic

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// 1. Add settings key to whitelist so it gets saved to DB
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'slack_webhook_url';
    return $keys;
});

// 2. Set a masked display value so the raw webhook URL isn't exposed
HookManager::addFilter('admin_get_settings', function(array $settings): array {
    if (!empty($settings['slack_webhook_url']) && strlen($settings['slack_webhook_url']) > 16) {
        $url = $settings['slack_webhook_url'];
        $settings['slack_webhook_url_masked'] = substr($url, 0, 12) . '...' . substr($url, -8);
    }
    return $settings;
});

// 3. Fire Slack webhook when a lead is qualified
HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) return;

    $settings    = $db->getSettings();
    $webhookUrl  = $settings['slack_webhook_url'] ?? '';
    $companyName = $lead['company_name'] ?? 'Unknown';
    $score       = $lead['score'] ?? 'MEDIUM';

    $logText = "[Slack Notifier] Alert dispatched. Prospect: '{$companyName}' Score: {$score}. Webhook: '{$webhookUrl}'";

    // Dispatch HTTP POST to Slack (if real URL configured)
    if (!empty($webhookUrl) && str_starts_with($webhookUrl, 'https://hooks.slack.com')) {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $client->post($webhookUrl, [
                'json' => [
                    'text' => "🎯 *New Qualified Lead* — *{$companyName}* (Fit Score: `{$score}`)"
                ]
            ]);
            $logText .= ' [Status: Sent]';
        } catch (\Throwable $e) {
            $logText .= ' [Status: Failed — ' . $e->getMessage() . ']';
        }
    }

    $db->logAgentAction($campaignId, 'Slack Notifier', 'SLACK_ALERT', $logText);
});
```

### Step 4: `app.js` — Frontend Settings Field Injection

```javascript
if (window.AppHooks) {
    window.AppHooks.addAction('dom_ready', function() {
        const appPanel = document.getElementById('app-panel');
        if (!appPanel || document.getElementById('slack_webhook_url')) return;

        const container = document.createElement('div');
        container.style.cssText = 'margin-top:20px; padding-top:16px; border-top:1px dashed var(--border-color);';
        container.innerHTML = `
            <div class="form-group">
                <label for="slack_webhook_url" style="display:flex; align-items:center; gap:8px;">
                    <i class="fab fa-slack" style="color:#E01E5A;"></i>
                    <span style="font-weight:600;">Slack Incoming Webhook URL</span>
                </label>
                <input type="text" id="slack_webhook_url" name="slack_webhook_url"
                       placeholder="https://hooks.slack.com/services/T.../B.../..."
                       style="width:100%; margin-top:6px;">
                <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">
                    When configured, the plugin will POST a message to this Slack channel
                    each time a new lead is AI-qualified.
                </small>
            </div>
        `;
        appPanel.appendChild(container);
    });
}
```

### Step 5: Activate the Plugin

Go to **Settings** → **Plugins** tab → Click **Activate** next to **Slack Notifier**. The Webhook URL field will now appear under **App** settings.

---

## 9. Step-by-Step Tutorial: Notification Center Widget Plugin

This premium plugin injects a live notification hub widget onto the dashboard with sound alerts, filtering, unread tracking, and a simulated alert endpoint.

### Step 1: Create the plugin folder
```
plugins/notification-widget/
```

### Step 2: `plugin.json`
```json
{
  "name": "Notification Widget",
  "version": "1.0.0",
  "description": "Adds a premium dashboard widget for monitoring alerts, user mentions, and notification handling.",
  "author": "Mark Dev",
  "entry_php": "app.php",
  "entry_js": "app.js"
}
```

### Step 3: `app.php` — Register Settings & Simulation API Route

```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// 1. Whitelist plugin settings keys
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'notification_widget_max_display';
    $keys[] = 'notification_widget_refresh_rate';
    $keys[] = 'notification_widget_sound_enabled';
    return $keys;
});

// 2. Set sensible defaults if not configured
HookManager::addFilter('admin_get_settings', function(array $settings): array {
    $settings['notification_widget_max_display']  ??= '5';
    $settings['notification_widget_refresh_rate'] ??= '15';
    $settings['notification_widget_sound_enabled'] ??= '1';
    return $settings;
});

// 3. Custom API endpoint: POST /api/plugin-route.php?plugin=notification-widget&action=simulate
HookManager::addAction('api_route_notification-widget_simulate', function(array $input): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB unavailable']);
        exit;
    }

    $session = \Config\Services::session();
    $user    = $session->get('user');
    $userId  = $user['id'] ?? 1;
    $username = $user['username'] ?? 'User';

    // Choose between 'mention' and random alert types
    $type = $input['type'] ?? 'random';
    if ($type === 'mention') {
        $title   = "🔔 New Mention Alert";
        $message = "Hey @{$username}, you were tagged — AI Agent completed lead qualification for Campaign #1.";
    } else {
        $titles   = ["🔥 High-Value Lead Qualified", "⚡ System Update", "⚠️ Usage Limit Approaching"];
        $messages = [
            "AI SDR qualified 'Nexus Corp' (Fit Score: 98/100). Ready for sales closing.",
            "Database maintenance completed. All tables optimized in 18ms.",
            "Your email quota is at 82% of your monthly plan limit."
        ];
        $i       = array_rand($titles);
        $title   = $titles[$i];
        $message = $messages[$i];
    }

    $pdo  = $db->getPdo();
    $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $message]);
    $id = $pdo->lastInsertId();

    header('Content-Type: application/json');
    echo json_encode([
        'success'      => true,
        'notification' => [
            'id'              => $id,
            'sender_id'       => $userId,
            'title'           => $title,
            'message'         => $message,
            'created_at'      => date('Y-m-d H:i:s'),
            'sender_username' => $username,
            'sender_role'     => $user['role'] ?? 'user'
        ]
    ]);
    exit;
});
```

### Step 4: `app.js` — Dashboard Widget & Sound Chime

```javascript
(function() {
    'use strict';

    // --- Sound Chime (Web Audio API) ---
    function playChime() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx  = new Ctx();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(523.25, ctx.currentTime);         // C5
        osc.frequency.exponentialRampToValueAtTime(783.99, ctx.currentTime + 0.2);  // G5
        gain.gain.setValueAtTime(0.07, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.5);
    }

    // --- Sliding Toast ---
    function showToast(notification) {
        let container = document.getElementById('notif-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notif-toast-container';
            container.style.cssText = `
                position:fixed; bottom:24px; right:24px; z-index:10110;
                display:flex; flex-direction:column; gap:10px; pointer-events:none;
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            padding: 14px 16px;
            box-shadow: var(--shadow-lg);
            max-width: 320px;
            pointer-events: auto;
            border-left: 4px solid var(--accent-primary);
            animation: slideInNotif 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        `;
        toast.innerHTML = `
            <div style="font-weight:700; font-size:13px; color:var(--text-primary); margin-bottom:4px;">
                <i class="fas fa-bell" style="color:var(--accent-primary);"></i>
                ${notification.title}
            </div>
            <div style="font-size:11px; color:var(--text-secondary); line-height:1.4;">
                ${notification.message}
            </div>
        `;

        container.appendChild(toast);
        playChime();

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // --- Inject Settings fields ---
    function injectSettings() {
        const appPanel = document.getElementById('app-panel');
        if (!appPanel || document.getElementById('notification_widget_max_display')) return;

        const section = document.createElement('div');
        section.style.cssText = 'margin-top:20px; padding-top:16px; border-top:1px dashed var(--border-color);';
        section.innerHTML = `
            <h4 style="margin:0 0 12px; font-size:13px; color:var(--accent-primary);">
                <i class="fas fa-bell"></i> Notification Widget
            </h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="notification_widget_max_display">Max Alerts</label>
                    <input type="number" id="notification_widget_max_display"
                           name="notification_widget_max_display" min="1" max="50"
                           placeholder="5" style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="notification_widget_refresh_rate">Refresh Rate (s)</label>
                    <input type="number" id="notification_widget_refresh_rate"
                           name="notification_widget_refresh_rate" min="5" max="300"
                           placeholder="15" style="width:100%;">
                </div>
            </div>
            <div class="form-group">
                <label for="notification_widget_sound_enabled">Sound Alerts</label>
                <select id="notification_widget_sound_enabled"
                        name="notification_widget_sound_enabled" style="width:100%;">
                    <option value="1">Enabled</option>
                    <option value="0">Disabled</option>
                </select>
            </div>
        `;
        appPanel.appendChild(section);
    }

    // --- Inject Dashboard Widget ---
    async function injectWidget() {
        const dashboardTab = document.getElementById('tab-admin-dashboard');
        if (!dashboardTab || document.getElementById('notif-center-widget')) return;

        const widget = document.createElement('div');
        widget.id = 'notif-center-widget';
        widget.className = 'panel-section card-box';
        widget.style.cssText = `
            margin-bottom:24px; padding:20px;
            background:var(--bg-card); border:1px solid var(--border-color);
            border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);
        `;
        widget.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center;
                        border-bottom:1px solid var(--border-color); padding-bottom:12px; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
                <h3 style="margin:0; font-size:16px; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-bell" style="color:var(--accent-primary);"></i>
                    Live Notification Hub
                </h3>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <input type="text" id="notif-search" placeholder="Search..."
                           style="padding:6px 10px; font-size:11px; border-radius:6px;
                                  border:1px solid var(--border-color); background:var(--bg-primary);
                                  color:var(--text-primary); outline:none; width:120px;">
                    <select id="notif-filter"
                            style="padding:6px 10px; font-size:11px; border-radius:6px;
                                   border:1px solid var(--border-color); background:var(--bg-primary);
                                   color:var(--text-primary); outline:none;">
                        <option value="all">All</option>
                        <option value="unread">Unread</option>
                        <option value="mentions">Mentions</option>
                    </select>
                    <button id="notif-simulate-btn" class="btn-primary"
                            style="padding:6px 10px; font-size:11px; margin:0; height:auto; box-shadow:none;">
                        <i class="fas fa-vial"></i> Simulate
                    </button>
                </div>
            </div>
            <div id="notif-widget-list" style="max-height:200px; overflow-y:auto;
                                               display:flex; flex-direction:column; gap:8px;">
                <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">
                    <i class="fas fa-sync fa-spin"></i> Loading...
                </div>
            </div>
        `;

        // Position after stats grid
        const statsGrid = document.getElementById('user-only-stats') || document.getElementById('admin-only-stats');
        if (statsGrid) {
            statsGrid.parentNode.insertBefore(widget, statsGrid.nextSibling);
        } else {
            dashboardTab.appendChild(widget);
        }

        // Fetch & Render notifications
        async function refreshList(silent = false) {
            const res  = await fetch('api/notifications.php');
            const data = await res.json();
            if (!data.success) return;

            const list = document.getElementById('notif-widget-list');
            if (!list) return;

            const search = (document.getElementById('notif-search')?.value || '').toLowerCase();
            const filter = document.getElementById('notif-filter')?.value || 'all';

            let items = data.notifications;
            if (filter === 'mentions' && window._nwCurrentUser) {
                items = items.filter(n => n.message.includes(`@${window._nwCurrentUser.username}`));
            }
            if (search) {
                items = items.filter(n =>
                    n.title.toLowerCase().includes(search) ||
                    n.message.toLowerCase().includes(search)
                );
            }

            list.innerHTML = '';
            if (items.length === 0) {
                list.innerHTML = `<div style="text-align:center; padding:16px; color:var(--text-muted); font-size:12px;">No matching alerts.</div>`;
                return;
            }

            items.slice(0, 5).forEach(n => {
                const card = document.createElement('div');
                card.style.cssText = `
                    background:var(--bg-primary); border:1px solid var(--border-color);
                    border-radius:var(--border-radius-sm); padding:12px 14px;
                    display:flex; flex-direction:column; gap:4px;
                    border-left:4px solid var(--accent-primary);
                `;
                card.innerHTML = `
                    <div style="font-weight:600; font-size:13px;">${n.title}</div>
                    <div style="font-size:11px; color:var(--text-secondary);">${n.message}</div>
                    <div style="font-size:10px; color:var(--text-muted);">${new Date(n.created_at).toLocaleString()}</div>
                `;
                list.appendChild(card);
            });
        }

        // Simulate alert button
        document.getElementById('notif-simulate-btn').addEventListener('click', async () => {
            const res  = await fetch('api/plugin-route.php?plugin=notification-widget&action=simulate', { method: 'POST' });
            const data = await res.json();
            if (data.success && data.notification) {
                showToast(data.notification);
                refreshList(false);
            }
        });

        document.getElementById('notif-search')?.addEventListener('input', () => refreshList(true));
        document.getElementById('notif-filter')?.addEventListener('change', () => refreshList(true));

        // Initial load (silent - no toast/chime for pre-existing items)
        await refreshList(true);
        // Poll every 15 seconds
        setInterval(() => refreshList(false), 15000);
    }

    // --- Bootstrap ---
    if (window.AppHooks) {
        window.AppHooks.addAction('dom_ready', async function() {
            // Store current user for mention detection
            try {
                const r = await fetch('api/auth-status.php');
                const d = await r.json();
                if (d.success) window._nwCurrentUser = d.user;
            } catch (e) {}

            injectSettings();
            await injectWidget();
        });
    }
})();
```

### Step 5: Activate the Plugin

Go to **Settings** → **Plugins** → Click **Activate** next to **Notification Widget**.

You will see:
- A **Live Notification Hub** card below the stats on the dashboard with search, filter, and Simulate button.
- Sound settings fields under **Settings → App**.
- A chime plays and a slide-in toast appears when new notifications are fetched.

---

## 10. Plugin Development Checklist

Before releasing a plugin, verify the following:

- [ ] `plugin.json` is present with all required fields (`name`, `version`, `description`, `entry_php`).
- [ ] Your PHP entry file (`app.php`) registers all hooks using `HookManager::addAction()` or `HookManager::addFilter()`.
- [ ] All custom settings keys are whitelisted using the `admin_settings_allowed_keys` filter.
- [ ] All settings keys have default values set via `admin_get_settings` filter.
- [ ] The plugin is free of direct output (`echo`, `print`) in PHP except inside `api_route_*` action handlers.
- [ ] Your JS file (`app.js`) is wrapped in an IIFE (`(function() { ... })()`) to prevent variable leaks.
- [ ] All DOM injection code guards against double-injection with `if (document.getElementById('my-element-id')) return;`.
- [ ] Plugin API route handlers always end with `exit;` after outputting JSON.
- [ ] Plugin has been activated and tested end-to-end in the application Settings panel.

---

## 11. Common Pitfalls

| Problem | Solution |
| :--- | :--- |
| Settings value not saving | Add the key to `admin_settings_allowed_keys` filter |
| JS injected twice on tab switch | Guard with `if (document.getElementById('my-id')) return;` |
| PHP hook not firing | Ensure `app.php` is loaded (plugin must be **Activated**) |
| API route returns 400 Bad Request | Ensure `plugin` and `action` GET params match exactly, including hyphens |
| API route returns 404 Not Found | The hook name `api_route_{plugin}_{action}` is not registered in `app.php` |
| Audio chime doesn't play | Browsers require a user gesture before creating AudioContext; wrap in click event |
| `Config\Services` not found in CLI scripts | This CI4 class requires full framework boot — test via HTTP or use raw PDO |
