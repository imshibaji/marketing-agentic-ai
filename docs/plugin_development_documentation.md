# Marketing AI Agent - Plugin Development Documentation

Welcome to the **Plugin Development Guide** for the Marketing AI Agent Suite! This document details how to extend and customize the application by creating plugins. The application features a unified pluggable architecture with hook execution patterns on both the backend (PHP) and client frontend (JavaScript).

---

## 1. Architecture Overview

Plugins reside in the `plugins/` directory at the project root. The core system discovers and manages plugins dynamically:

```mermaid
graph TD
    PM[PluginManager] -->|Scans| Dir[plugins/ directory]
    PM -->|Loads enabled| Ent[plugin-folder/plugin-name.php]
    Ent -->|Registers callbacks| HM[HookManager]
    
    Index[index.php] -->|Checks active| PM
    Index -->|Injects styles| Head[link rel="stylesheet"]
    Index -->|Injects scripts| Script[script src="plugins/...js"]
    Script -->|Registers callbacks| JS[window.AppHooks]
```

*   **Backend Extensions**: Plugins hook into database migrations, modify configuration whitelists, register custom LLM adapters, and intercept pipeline events (like lead generation).
*   **Frontend Extensions**: Plugins inject styles, modify the DOM, listen to page navigation, and extend forms dynamically.

---

## 2. Directory & Metadata Structure

Each plugin must be contained in its own folder under `plugins/`. The folder name and the main entry file name **must match exactly**.

### Example Directory Layout
```
plugins/
└── my-custom-plugin/
    ├── plugin.json                # Plugin metadata (Name, Version, etc.)
    ├── my-custom-plugin.php       # PHP Entry Point (Required)
    ├── my-custom-plugin.js        # JS Entry Point (Optional)
    └── my-custom-plugin.css       # CSS Stylesheet (Optional)
```

### Metadata Configuration (`plugin.json`)
The `plugin.json` file defines details displayed in the admin Settings panel:
```json
{
  "name": "My Custom Plugin",
  "version": "1.0.0",
  "description": "Explains what the plugin does and how it extends the system.",
  "author": "Your Name"
}
```

---

## 3. Backend Hook System (PHP)

The backend provides a WordPress-style event hook system managed by `MarketingAgent\Plugin\HookManager`.

### Action Hooks
Actions allow you to execute code at specific execution points. They do not return values.

| Hook Name | Description | Arguments Passed |
| :--- | :--- | :--- |
| `db_initialize_schema` | Triggered when database tables are initialized or rebuilt. Use this to run custom SQL migrations. | `DatabaseService $db` |
| `lead_qualified` | Triggered after an SDR AI agent qualifies a prospect. | `int $leadId, int $campaignId, array $lead` |
| `leads_pipeline_complete`| Triggered when a campaign's lead generation run completes. | `int $campaignId, array $qualifiedLeads` |
| `api_route_{plugin}_{action}`| Triggered when an API request is made to the plugin dispatcher route. | `array $requestData` |

#### Example: Running a Schema Migration
```php
use MarketingAgent\Plugin\HookManager;

HookManager::addAction('db_initialize_schema', function($db) {
    $pdo = $db->getPdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS my_plugin_data (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER,
        extra_note TEXT
    )");
});
```

### Filter Hooks
Filters allow you to intercept, modify, and return data before it is saved or output.

| Filter Name | Description | Expected Return Value |
| :--- | :--- | :--- |
| `admin_settings_allowed_keys`| Appends custom keys to the settings whitelist so they are saved to the database. | `array $allowedKeys` |
| `admin_get_settings` | Modifies or masks settings before they are returned to the administrator. | `array $settings` |
| `public_settings` | Modifies public-safe settings returned to non-admin users. | `array $publicSettings` |
| `llm_provider_instance` | Registers custom LLM adapters (e.g. OpenAI or Claude). | `LlmProviderInterface or null` |

#### Example: Registering Custom Config Whitelists
```php
use MarketingAgent\Plugin\HookManager;

HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'my_plugin_api_key';
    return $keys;
});
```

---

## 4. Frontend Hook System (JavaScript)

The frontend exposes a global `window.AppHooks` object to allow scripts to interact with the SPA.

### JS Action Hooks
Actions execute custom client-side code at lifecycle events.

| Action Name | Description | Arguments Passed |
| :--- | :--- | :--- |
| `dom_ready` | Triggered when the browser finishes loading the HTML DOM. | None |
| `app_init` | Triggered immediately when the application script is loaded. | None |
| `tab_switched` | Triggered when the user navigates between navigation tabs. | `string $tabId` |

#### Example: Injecting HTML into Settings Panel
```javascript
if (window.AppHooks) {
    window.AppHooks.addAction('dom_ready', function() {
        const appPanel = document.getElementById('app-panel');
        if (appPanel) {
            const div = document.createElement('div');
            div.className = 'form-group';
            div.innerHTML = `
                <label for="custom_config">My Custom Option</label>
                <input type="text" id="custom_config" name="custom_config">
            `;
            appPanel.appendChild(div);
        }
    });
}
```

---

## 5. Plugin API Routing

To expose dynamic endpoints without creating standalone PHP files, register an API hook with the dispatcher.

Requests must be routed to `/api/plugin-route.php` with `plugin` and `action` parameters:
`GET/POST` → `/public/api/plugin-route.php?plugin=my-plugin&action=my-action`

### Registering the Dispatcher callback in PHP:
```php
use MarketingAgent\Plugin\HookManager;

HookManager::addAction('api_route_my-plugin_my-action', function(array $request) {
    // Perform processing...
    
    // Output JSON response and exit
    echo json_encode([
        'success' => true,
        'data' => 'Processed successfully'
    ]);
    exit;
});
```

---

## 6. Step-by-Step Tutorial: Slack Notifier Plugin

Let's walk through creating a plugin that adds a Slack Webhook field to the settings form and posts simulated activity alerts when a lead is qualified.

### Step 1: Create folders and files
Create a folder named `slack-notifier` inside the `plugins/` directory:
`plugins/slack-notifier/`

### Step 2: Write `plugin.json`
```json
{
  "name": "Slack Notifier",
  "version": "1.0.0",
  "description": "Logs lead qualifications as simulated Slack notifications and adds Slack Webhook configuration to settings.",
  "author": "Mark Dev"
}
```

### Step 3: Write PHP Logic (`slack-notifier.php`)
```php
<?php
use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// 1. Whitelist the custom setting key
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'slack_webhook_url';
    return $keys;
});

// 2. Intercept lead qualification and log simulated webhook dispatches
HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    $db = PluginManager::getDatabaseService();
    if ($db) {
        $settings = $db->getSettings();
        $webhook = $settings['slack_webhook_url'] ?? 'Not Configured';
        
        $company = $lead['company_name'] ?? 'Target Corp';
        $score = $lead['score'] ?? 'MEDIUM';
        
        $logText = "[Slack Notifier Plugin] Dynamic alert dispatched to Slack. Prospect: '{$company}' Qualified fit: {$score}. Target Webhook: '{$webhook}'";
        $db->logAgentAction($campaignId, 'Slack Notifier', 'SLACK_ALERT', $logText);
    }
});
```

### Step 4: Write JS Extension (`slack-notifier.js`)
```javascript
if (window.AppHooks) {
    window.AppHooks.addAction('dom_ready', function() {
        const appPanel = document.getElementById('app-panel');
        if (appPanel) {
            const container = document.createElement('div');
            container.className = 'form-group';
            container.style.marginTop = '20px';
            container.style.paddingTop = '16px';
            container.style.borderTop = '1px dashed var(--border-color)';

            container.innerHTML = `
                <label for="slack_webhook_url" style="display:flex; align-items:center; gap:8px;">
                    <i class="fab fa-slack" style="color:#E01E5A; font-size:16px;"></i>
                    <span style="font-weight:600;">Slack Webhook URL</span>
                </label>
                <input type="text" id="slack_webhook_url" name="slack_webhook_url" placeholder="https://hooks.slack.com/services/T.../B.../..." style="width:100%; margin-top:6px;">
                <small style="color:var(--text-muted); font-size:11px; display:block; margin-top:4px;">
                    Enter your Slack Incoming Webhook URL. The plugin will post alert notifications to this channel when new leads are qualified.
                </small>
            `;
            appPanel.appendChild(container);
        }
    });
}
```

### Step 5: Activate
Open the settings panel in the application, navigate to the **Plugins** tab, and click **Activate** next to **Slack Notifier**. The page will reload and the Slack Webhook field will appear under **App** settings!
