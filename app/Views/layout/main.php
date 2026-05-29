<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Marketing AI Agentic Automation</title>
    
    <meta name="color-scheme" content="light dark">
    <script>
    // Prevent Flash of Unstyled Content (FOUC) by resolving theme immediately
    {
        const colorScheme = localStorage.getItem("color-scheme");
        if (colorScheme) {
            document.documentElement.setAttribute('data-theme', colorScheme);
        } else {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', systemPrefersDark ? 'dark' : 'light');
        }
    }
    </script>

    <link rel="stylesheet" href="/style.css">
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .tab-button { text-decoration: none; }
    </style>

    <!-- Load Active Plugins CSS -->
    <?php
    $plugins = \MarketingAgent\Plugin\PluginManager::getInstalledPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['active'] && $plugin['has_css']) {
            echo '    <link rel="stylesheet" href="/plugin-asset.php?plugin=' . htmlspecialchars($plugin['id']) . '&type=css">' . "\n";
        }
    }
    ?>
    <style>
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
            gap: 0;
        }

        .settings-tab {
            flex: 1;
            padding: 10px 8px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .settings-tab.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
        }

        .settings-panel { display: none; }
        .settings-panel.active { display: block; }
    </style>
</head>
<body>

    <!-- MAIN APP -->
    <div class="app-container" id="main-app">
        
        <!-- Header -->
        <header>
            <div class="brand-section">
                <div class="brand-logo" id="header-logo-letter">A</div>
                <div class="brand-title">
                    <h1 id="header-app-name">Marketing AI Agent</h1>
                    <span>Multi-Agent AI Automation</span>
                </div>
            </div>

            <div class="controls-section">
                <div class="tab-nav">
                    <a href="/dashboard" class="tab-button <?= ($activeTab === 'admin-dashboard') ? 'active' : '' ?>" id="tab-btn-dashboard">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                    <a href="/campaigns" class="tab-button <?= ($activeTab === 'campaigns') ? 'active' : '' ?>" id="tab-btn-campaigns">
                        <i class="fas fa-bullhorn"></i> Campaigns
                    </a>
                    <a href="/leads" class="tab-button <?= ($activeTab === 'leads') ? 'active' : '' ?>" id="tab-btn-leads">
                        <i class="fas fa-search-dollar"></i> Leads Generator
                    </a>
                    <a href="/contacts" class="tab-button <?= ($activeTab === 'contacts') ? 'active' : '' ?>" id="tab-btn-contacts">
                        <i class="fas fa-address-book"></i> Contacts
                    </a>
                    <a href="/outreach" class="tab-button <?= ($activeTab === 'outreach') ? 'active' : '' ?>" id="tab-btn-outreach">
                        <i class="fas fa-paper-plane"></i> Outreach
                    </a>
                    <?php 
                    $sessionUser = session()->get('user');
                    if ($sessionUser && $sessionUser['role'] === 'admin'): 
                    ?>
                        <a href="/users" class="tab-button <?= ($activeTab === 'users') ? 'active' : '' ?>" id="tab-btn-users">
                            <i class="fas fa-user-shield"></i> Users
                        </a>
                        <a href="/plans" class="tab-button <?= ($activeTab === 'plans') ? 'active' : '' ?>" id="tab-btn-plans">
                            <i class="fas fa-tags"></i> Plans
                        </a>
                    <?php endif; ?>
                </div>

                <!-- User Badge -->
                <div class="user-badge" id="user-badge">
                    <div class="user-avatar" id="user-avatar-letter">U</div>
                    <span class="user-name" id="user-badge-name">User</span>
                    <span class="user-role" id="user-badge-role">user</span>
                </div>

                <button class="action-icon-button" id="theme-toggle" title="Toggle theme">
                    <i class="fas fa-sun"></i>
                </button>

                <!-- Settings gear — admin only -->
                <button class="action-icon-button" id="settings-btn" title="AI Settings" style="display:none;">
                    <i class="fas fa-cog"></i>
                </button>

                <button class="action-icon-button" id="profile-btn" title="Edit My Profile" style="margin-right: 4px;">
                    <i class="fas fa-user-circle"></i>
                </button>

                <a href="/logout" class="btn-logout" id="logout-link" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- Main Workspace Grid -->
        <div class="dashboard-grid <?= ($activeTab !== 'campaigns') ? 'sidebar-hidden' : '' ?>">
            
            <?php if ($activeTab === 'campaigns'): ?>
            <!-- Left Sidebar (Campaign History) -->
            <aside class="sidebar">
                <h2>
                    Campaigns
                    <button class="action-icon-button" id="new-campaign-btn" title="Create New Campaign" style="width:28px; height:28px; font-size:12px;">
                        <i class="fas fa-plus"></i>
                    </button>
                </h2>
                <ul class="campaign-list" id="campaign-list">
                    <!-- Loaded dynamically via JS -->
                </ul>
            </aside>
            <?php endif; ?>

            <!-- Right Workspace Pane -->
            <main class="workspace">
                <?= $this->renderSection('content') ?>
            </main>
        </div>

    </div>

    <!-- Settings Modal Dialog -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal-content" style="max-width:560px;">
            <div class="modal-header">
                <h3 style="font-size: 20px;"><i class="fas fa-cog"></i> System Configuration</h3>
                <button class="modal-close" id="settings-close"><i class="fas fa-times"></i></button>
            </div>
            
            <!-- Settings Navigation Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" data-panel="llm-panel"><i class="fas fa-robot"></i> LLM / AI</button>
                <button class="settings-tab" data-panel="app-panel"><i class="fas fa-paint-brush"></i> App</button>
                <button class="settings-tab" data-panel="smtp-panel"><i class="fas fa-envelope"></i> Email SMTP</button>
                <button class="settings-tab" data-panel="whatsapp-panel"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                <button class="settings-tab" data-panel="sms-panel"><i class="fas fa-sms"></i> SMS</button>
                <button class="settings-tab" data-panel="backup-panel"><i class="fas fa-database"></i> Backup, Reset & Demo</button>
                <button class="settings-tab" data-panel="plugins-panel"><i class="fas fa-plug"></i> Plugins</button>
            </div>

            <form id="settings-form">

                <!-- LLM Panel -->
                <div class="settings-panel active" id="llm-panel">
                    <div class="form-group">
                        <label for="llm_provider">Active LLM Provider</label>
                        <select id="llm_provider" name="llm_provider">
                            <option value="gemini">Google Gemini API (Cloud)</option>
                            <option value="lm_studio">LM Studio (Local Host)</option>
                            <option value="ollama">Ollama (Local Host)</option>
                            <option value="openrouter">OpenRouter (200+ Cloud Models)</option>
                            <option value="openai_compatible">OpenAI-Compatible API</option>
                        </select>
                    </div>

                    <!-- Gemini Fields -->
                    <div class="provider-fields provider-gemini">
                        <div class="form-group">
                            <label for="gemini_api_key">Gemini API Key</label>
                            <input type="password" id="gemini_api_key" name="gemini_api_key" placeholder="AIzaSy...">
                        </div>
                        <div class="form-group">
                            <label for="gemini_model">Gemini Model</label>
                            <input type="text" id="gemini_model" name="gemini_model" placeholder="gemini-1.5-flash">
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="gemini_active" name="gemini_active" value="1">
                                <span style="font-weight:600;">Activate Gemini Model</span>
                            </label>
                        </div>
                    </div>

                    <!-- LM Studio Fields -->
                    <div class="provider-fields provider-lmstudio hidden">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lm_studio_url">LM Studio URL</label>
                                <input type="text" id="lm_studio_url" name="lm_studio_url" placeholder="http://localhost:1234/v1">
                            </div>
                            <div class="form-group">
                                <label for="lm_studio_model">LM Studio Model Name</label>
                                <input type="text" id="lm_studio_model" name="lm_studio_model" placeholder="qwen2.5-7b-instruct">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label for="lm_studio_api_key">LM Studio API Key (Optional)</label>
                                <input type="password" id="lm_studio_api_key" name="lm_studio_api_key" placeholder="Enter API Key if required">
                            </div>
                            <div class="form-group">
                                <label for="lm_studio_extra_model">Extra Model Name (Optional)</label>
                                <input type="text" id="lm_studio_extra_model" name="lm_studio_extra_model" placeholder="e.g. qwen2.5-14b-instruct">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="lm_studio_active" name="lm_studio_active" value="1">
                                <span style="font-weight:600;">Activate LM Studio Model</span>
                            </label>
                        </div>
                    </div>

                    <!-- Ollama Fields -->
                    <div class="provider-fields provider-ollama hidden">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ollama_url">Ollama Server URL</label>
                                <input type="text" id="ollama_url" name="ollama_url" placeholder="http://localhost:11434">
                            </div>
                            <div class="form-group">
                                <label for="ollama_model">Ollama Model Name</label>
                                <input type="text" id="ollama_model" name="ollama_model" placeholder="llama3">
                            </div>
                        </div>
                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label for="ollama_api_key">Ollama API Key (Optional)</label>
                                <input type="password" id="ollama_api_key" name="ollama_api_key" placeholder="Enter API Key if required">
                            </div>
                            <div class="form-group">
                                <label for="ollama_extra_model">Extra Model Name (Optional)</label>
                                <input type="text" id="ollama_extra_model" name="ollama_extra_model" placeholder="e.g. llama3.1">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="ollama_active" name="ollama_active" value="1">
                                <span style="font-weight:600;">Activate Ollama Model</span>
                            </label>
                        </div>
                    </div>

                    <!-- OpenRouter Fields -->
                    <div class="provider-fields provider-openrouter hidden">
                        <p style="font-size:12px; color:var(--text-secondary); margin-bottom:12px;">
                            <i class="fas fa-info-circle"></i>
                            OpenRouter routes to <strong>200+ models</strong> (GPT-4o, Claude, LLaMA, Mistral, Gemini, etc.) via a single API key.
                            Get your key at <a href="https://openrouter.ai/keys" target="_blank" style="color:var(--accent-primary);">openrouter.ai/keys</a>.
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="openrouter_api_key">OpenRouter API Key</label>
                                <input type="password" id="openrouter_api_key" name="openrouter_api_key" placeholder="sk-or-v1-...">
                            </div>
                            <div class="form-group">
                                <label for="openrouter_model">Model Slug</label>
                                <input type="text" id="openrouter_model" name="openrouter_model" placeholder="openai/gpt-4o-mini">
                                <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">Examples: <code>anthropic/claude-3-5-sonnet</code>, <code>meta-llama/llama-3-70b-instruct</code>, <code>mistralai/mistral-7b-instruct</code></small>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label for="openrouter_site_url">Your Site URL (Optional)</label>
                            <input type="text" id="openrouter_site_url" name="openrouter_site_url" placeholder="https://yourdomain.com">
                            <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">Used for OpenRouter analytics and model leaderboard rankings.</small>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="openrouter_active" name="openrouter_active" value="1">
                                <span style="font-weight:600;">Activate OpenRouter</span>
                            </label>
                        </div>
                    </div>

                    <!-- OpenAI-Compatible Fields -->
                    <div class="provider-fields provider-openai-compat hidden">
                        <p style="font-size:12px; color:var(--text-secondary); margin-bottom:12px;">
                            <i class="fas fa-info-circle"></i>
                            Connect to any server implementing the OpenAI <code>/chat/completions</code> API.
                            Compatible with: <strong>OpenAI, Groq, Together AI, DeepSeek, Mistral, xAI Grok, Fireworks, vLLM, Text-Gen-WebUI</strong> and more.
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="openai_compat_url">Base API URL</label>
                                <input type="text" id="openai_compat_url" name="openai_compat_url" placeholder="https://api.openai.com/v1">
                                <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">Examples: <code>https://api.groq.com/openai/v1</code>, <code>https://api.deepseek.com/v1</code>, <code>https://api.mistral.ai/v1</code></small>
                            </div>
                            <div class="form-group">
                                <label for="openai_compat_model">Model Name</label>
                                <input type="text" id="openai_compat_model" name="openai_compat_model" placeholder="gpt-4o-mini">
                                <small style="color:var(--text-muted); font-size:11px; margin-top:4px; display:block;">Examples: <code>llama-3.3-70b-versatile</code> (Groq), <code>deepseek-chat</code>, <code>mistral-large-latest</code></small>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label for="openai_compat_api_key">API Key</label>
                            <input type="password" id="openai_compat_api_key" name="openai_compat_api_key" placeholder="API key (leave blank for keyless local servers)">
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="openai_compat_active" name="openai_compat_active" value="1">
                                <span style="font-weight:600;">Activate OpenAI-Compatible Provider</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- App Panel -->
                <div class="settings-panel" id="app-panel">
                    <div class="form-group">
                        <label for="app_name">Application Name</label>
                        <input type="text" id="app_name" name="app_name" placeholder="e.g. My Marketing Suite">
                        <small style="color:var(--text-muted); font-size:11px;">This name appears in the header and login screen.</small>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="enable_public_chat" name="enable_public_chat" value="1">
                            <span style="font-weight:600;">Enable Public Chat Section</span>
                        </label>
                        <small style="color:var(--text-muted); font-size:11px; display:block; margin-top:4px;">Turn off this setting to disable the public chat board for all users and admins.</small>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="enable_public_notifications" name="enable_public_notifications" value="1">
                            <span style="font-weight:600;">Enable System Notifications Section</span>
                        </label>
                        <small style="color:var(--text-muted); font-size:11px; display:block; margin-top:4px;">Turn off this setting to disable the notifications feed section for all users.</small>
                    </div>
                </div>

                <!-- SMTP Panel -->
                <div class="settings-panel" id="smtp-panel">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        <i class="fas fa-info-circle"></i> Leave <strong>SMTP Host</strong> blank or set to <code>mock</code> to simulate email outreach without a real server.
                    </p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com (or 'mock')">
                        </div>
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" placeholder="587" min="1" max="65535">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_user">SMTP Username</label>
                            <input type="text" id="smtp_user" name="smtp_user" placeholder="you@gmail.com">
                        </div>
                        <div class="form-group">
                            <label for="smtp_pass">SMTP Password / App Password</label>
                            <input type="password" id="smtp_pass" name="smtp_pass" placeholder="••••••••">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_from_email">From Email Address</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" placeholder="outreach@yourcompany.com">
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_name">From Display Name</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" placeholder="Your Name / Company">
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Panel -->
                <div class="settings-panel" id="whatsapp-panel">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        <i class="fas fa-info-circle"></i> Requires a <strong>Meta WhatsApp Cloud API</strong> account. Leave blank to simulate WhatsApp outreach.
                    </p>
                    <div class="form-group">
                        <label for="whatsapp_token">WhatsApp API Bearer Token</label>
                        <input type="password" id="whatsapp_token" name="whatsapp_token" placeholder="EAABs...your_meta_token">
                    </div>
                    <div class="form-group">
                        <label for="whatsapp_phone_id">WhatsApp Phone Number ID</label>
                        <input type="text" id="whatsapp_phone_id" name="whatsapp_phone_id" placeholder="1234567890123456">
                    </div>
                    <p style="font-size:11px; color:var(--text-muted); margin-top:8px;">
                        Find these credentials in <strong>Meta for Developers → Your App → WhatsApp → API Setup</strong>.
                    </p>
                </div>

                <div class="settings-panel" id="sms-panel">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        <i class="fas fa-info-circle"></i> Configure SMS delivery settings. Leave provider set to <strong>mock</strong> to simulate SMS sending locally.
                    </p>
                    <div class="form-group">
                        <label for="sms_provider">SMS Provider</label>
                        <select id="sms_provider" name="sms_provider">
                            <option value="mock">Mock SMS (no real messages)</option>
                            <option value="twilio">Twilio</option>
                            <option value="custom">Custom SMS Gateway</option>
                        </select>
                    </div>
                    <div class="provider-fields provider-twilio hidden">
                        <div class="form-group">
                            <label for="sms_twilio_account_sid">Twilio Account SID</label>
                            <input type="text" id="sms_twilio_account_sid" name="sms_twilio_account_sid" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label for="sms_twilio_auth_token">Twilio Auth Token</label>
                            <input type="password" id="sms_twilio_auth_token" name="sms_twilio_auth_token" placeholder="your_twilio_auth_token">
                        </div>
                        <div class="form-group">
                            <label for="sms_twilio_from_number">Twilio From Number</label>
                            <input type="text" id="sms_twilio_from_number" name="sms_twilio_from_number" placeholder="+1234567890">
                        </div>
                    </div>
                    <div class="provider-fields provider-custom-sms hidden" style="display:flex; flex-direction:column; gap:12px;">
                        <div class="form-group">
                            <label for="sms_custom_url">Custom API Gateway URL *</label>
                            <input type="text" id="sms_custom_url" name="sms_custom_url" placeholder="e.g. https://api.gateway.com/send?to={to}&msg={message}">
                            <small style="font-size:10px; color:var(--text-muted); margin-top:2px; display:block;">Use <code>{to}</code> and <code>{message}</code> as placeholders.</small>
                        </div>
                        <div class="form-group">
                            <label for="sms_custom_method">HTTP Method</label>
                            <select id="sms_custom_method" name="sms_custom_method">
                                <option value="POST">POST (Recommended)</option>
                                <option value="GET">GET</option>
                                <option value="PUT">PUT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sms_custom_headers">HTTP Headers (One per line)</label>
                            <textarea id="sms_custom_headers" name="sms_custom_headers" placeholder="Authorization: Bearer token-here&#10;Content-Type: application/json" style="height:60px; font-family:monospace; font-size:11px; resize:none; padding:8px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none;"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="sms_custom_body">HTTP Post Body (Optional for POST)</label>
                            <textarea id="sms_custom_body" name="sms_custom_body" placeholder='{"phone": "{to}", "text": "{message}"}' style="height:60px; font-family:monospace; font-size:11px; resize:none; padding:8px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none;"></textarea>
                            <small style="font-size:10px; color:var(--text-muted); margin-top:2px; display:block;">Placeholders <code>{to}</code> and <code>{message}</code> will be replaced automatically.</small>
                        </div>
                    </div>
                    <p style="font-size:11px; color:var(--text-muted); margin-top:8px;">
                        Provide the Twilio credentials or Custom SMS Gateway configurations to enable real-time SMS delivery.
                    </p>
                </div>

                <!-- Backup Panel -->
                <div class="settings-panel" id="backup-panel">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        Manage your application database backups. You can download a backup of the current database or upload a previously downloaded backup file to restore it.
                    </p>

                    <!-- Sub-tabs for Backup, Restore, and Reset & Demo -->
                    <div style="display:flex; border-bottom:1px solid var(--border-color); margin-bottom:20px; gap:8px;">
                        <button type="button" class="backup-sub-tab active" data-subpanel="subpanel-backup" style="background:none; border:none; border-bottom:2px solid var(--accent-primary); color:var(--text-primary); padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; outline:none; transition: all 0.2s ease;">
                            <i class="fas fa-download"></i> Backup
                        </button>
                        <button type="button" class="backup-sub-tab" data-subpanel="subpanel-restore" style="background:none; border:none; border-bottom:2px solid transparent; color:var(--text-secondary); padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; outline:none; transition: all 0.2s ease;">
                            <i class="fas fa-upload"></i> Restore
                        </button>
                        <button type="button" class="backup-sub-tab" data-subpanel="subpanel-reset-demo" style="background:none; border:none; border-bottom:2px solid transparent; color:var(--text-secondary); padding:8px 16px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; outline:none; transition: all 0.2s ease;">
                            <i class="fas fa-tools"></i> Reset & Demo
                        </button>
                    </div>
                    
                    <div class="backup-subpanel-content" id="subpanel-backup" style="display:block;">
                        <!-- Backup Section -->
                        <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; padding:16px;">
                            <h4 style="margin:0 0 8px; font-size:14px; color:var(--text-primary);"><i class="fas fa-download" style="color:var(--accent-primary); margin-right:6px;"></i> Backup Database</h4>
                            <p style="font-size:11px; color:var(--text-muted); margin:0 0 16px;">
                                Download a full backup copy of the current database (includes all users, campaigns, leads, logs, and settings). Downloads as a SQLite file for SQLite databases, or as a JSON file for server-based databases.
                            </p>
                            <button type="button" class="btn-primary" id="btn-download-backup" style="margin-top:0; padding:8px 16px; font-size:12px; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fas fa-file-download"></i> Download Backup File
                            </button>
                        </div>
                    </div>

                    <div class="backup-subpanel-content" id="subpanel-restore" style="display:none;">
                        <!-- Restore Section -->
                        <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; padding:16px;">
                            <h4 style="margin:0 0 8px; font-size:14px; color:var(--text-primary);"><i class="fas fa-upload" style="color:var(--accent-error); margin-right:6px;"></i> Restore Database</h4>
                            <p style="font-size:11px; color:var(--text-muted); margin:0 0 16px;">
                                Upload a valid database backup file (`.sqlite`, `.db`, or `.json`) to overwrite the current database. 
                                <strong style="color:var(--accent-error);">Warning: This will permanently overwrite all current data!</strong>
                            </p>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="file" id="restore-db-file" accept=".sqlite,.db,.json" style="font-size:12px; color:var(--text-primary);">
                                </div>
                                <button type="button" class="btn-primary" id="btn-restore-backup" style="margin-top:4px; padding:8px 16px; font-size:12px; background:var(--accent-error); border-color:var(--accent-error); width:fit-content; display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fas fa-file-upload"></i> Restore Backup
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="backup-subpanel-content" id="subpanel-reset-demo" style="display:none;">
                        <!-- Reset & Demo Section -->
                        <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; padding:16px;">
                            <h4 style="margin:0 0 8px; font-size:14px; color:var(--text-primary);"><i class="fas fa-tools" style="color:var(--accent-warning); margin-right:6px;"></i> Reset & Demo Options</h4>
                            <p style="font-size:11px; color:var(--text-muted); margin:0 0 16px;">
                                Populate the application with sample campaigns, leads, notifications, and chats for testing, or execute a factory reset of the system database.
                            </p>
                            <div style="display:flex; gap:12px;">
                                <button type="button" class="btn-primary" id="btn-load-magic" style="margin-top:0; padding:8px 16px; font-size:12px; background:var(--accent-secondary); border-color:var(--accent-secondary); display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fas fa-magic"></i> Load Demo Data
                                </button>
                                <button type="button" class="btn-primary" id="btn-reset-app" style="margin-top:0; padding:8px 16px; font-size:12px; background:var(--accent-error); border-color:var(--accent-error); display:inline-flex; align-items:center; gap:8px;">
                                    <i class="fas fa-trash-alt"></i> Reset Application
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plugins Panel -->
                <div class="settings-panel" id="plugins-panel">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        <i class="fas fa-info-circle"></i> Enable or disable application plugins. Active plugins reload automatically to register hooks.
                    </p>
                    <div id="plugins-list-container" style="display:flex; flex-direction:column; gap:12px;">
                        <div style="text-align:center; padding:20px; color:var(--text-muted);">
                            <i class="fas fa-spinner fa-spin"></i> Loading plugins...
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="settings-save-btn" style="width: 100%; margin-top: 20px;">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </form>
        </div>
    </div>

    <!-- MY PROFILE MODAL -->
    <div class="modal-overlay" id="profile-modal" style="z-index:10050;">
        <div class="modal-content" style="max-width:420px;">
            <div class="modal-header">
                <h3 style="font-size:18px;"><i class="fas fa-user-circle"></i> My Profile</h3>
                <button class="modal-close" id="profile-modal-close"><i class="fas fa-times"></i></button>
            </div>

            <form id="profile-edit-form" style="display:flex; flex-direction:column; gap:16px; margin-top:8px;">
                <div class="form-group">
                    <label for="profile-username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="profile-username" required minlength="3">
                </div>

                <div class="form-group">
                    <label for="profile-fullname"><i class="fas fa-id-card"></i> Full Name</label>
                    <input type="text" id="profile-fullname" placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label for="profile-email"><i class="fas fa-envelope"></i> Email ID</label>
                    <input type="email" id="profile-email" placeholder="Enter your email ID">
                </div>

                <div class="form-group">
                    <label for="profile-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                    <input type="tel" id="profile-mobile" placeholder="Enter mobile number">
                </div>

                <div class="form-group">
                    <label for="profile-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                    <input type="tel" id="profile-whatsapp" placeholder="Enter WhatsApp number">
                </div>

                <div class="form-group">
                    <label for="profile-password"><i class="fas fa-lock"></i> Password
                        <span style="font-weight:400; font-size:11px; color:var(--text-muted);"> (leave blank to keep current)</span>
                    </label>
                    <input type="password" id="profile-password" placeholder="New password (min 6 chars)">
                </div>

                <div id="profile-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary" id="profile-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn-secondary" id="profile-modal-cancel-btn" style="padding:10px 16px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MANUAL LEAD ADD MODAL -->
    <div class="modal-overlay" id="manual-lead-modal" style="z-index:10080;">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header">
                <h3 style="font-size:18px;"><i class="fas fa-user-plus"></i> Add Lead Manually</h3>
                <button class="modal-close" id="manual-lead-modal-close"><i class="fas fa-times"></i></button>
            </div>

            <form id="manual-lead-form" style="display:flex; flex-direction:column; gap:14px; margin-top:8px; max-height:80vh; overflow-y:auto; padding-right:4px;">
                <input type="hidden" id="manual-lead-id" value="">
                <div class="form-group">
                    <label for="manual-lead-campaign"><i class="fas fa-bullhorn"></i> Associate with Campaign</label>
                    <select id="manual-lead-campaign">
                        <option value="">None (General CRM Lead)</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="manual-lead-company"><i class="fas fa-building"></i> Company Name *</label>
                        <input type="text" id="manual-lead-company" required placeholder="e.g. Acme Corp">
                    </div>
                    <div class="form-group">
                        <label for="manual-lead-contact"><i class="fas fa-user"></i> Contact Name</label>
                        <input type="text" id="manual-lead-contact" placeholder="e.g. John Doe">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="manual-lead-email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="manual-lead-email" placeholder="e.g. contact@acme.com">
                    </div>
                    <div class="form-group">
                        <label for="manual-lead-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                        <input type="text" id="manual-lead-whatsapp" placeholder="e.g. +919876543210">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="manual-lead-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                        <input type="text" id="manual-lead-mobile" placeholder="e.g. +919876543210">
                    </div>
                    <div class="form-group">
                        <label for="manual-lead-source"><i class="fas fa-database"></i> Data Source</label>
                        <input type="text" id="manual-lead-source" placeholder="e.g. manual, linkedin, website" value="manual">
                    </div>
                </div>

                <div class="form-group">
                    <label for="manual-lead-postal-address"><i class="fas fa-map-marker-alt"></i> Postal Address</label>
                    <input type="text" id="manual-lead-postal-address" placeholder="e.g. 123 Main St, Kolkata, WB 700001">
                </div>

                <div id="manual-lead-owner-group" class="form-group" style="display:none;">
                    <label for="manual-lead-owner"><i class="fas fa-user-shield"></i> Contact Owner (Admin)</label>
                    <select id="manual-lead-owner">
                        <option value="">— Keep Current Owner —</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="manual-lead-industry"><i class="fas fa-industry"></i> Industry</label>
                        <input type="text" id="manual-lead-industry" placeholder="e.g. Tech, Retail">
                    </div>
                    <div class="form-group">
                        <label for="manual-lead-score"><i class="fas fa-star"></i> Fit Score</label>
                        <select id="manual-lead-score">
                            <option value="HIGH">High Fit</option>
                            <option value="MEDIUM" selected>Medium Fit</option>
                            <option value="LOW">Low Fit</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="manual-lead-desc"><i class="fas fa-info-circle"></i> Description / Requirements</label>
                    <textarea id="manual-lead-desc" placeholder="e.g. Looking for digital marketing services" style="height:60px; resize:none; padding:8px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:var(--font-body); font-size:12px;"></textarea>
                </div>

                <div class="form-group">
                    <label for="manual-lead-reasoning"><i class="fas fa-brain"></i> Qualification Reasoning</label>
                    <input type="text" id="manual-lead-reasoning" value="Manually added" placeholder="Reasoning for fit score">
                </div>

                <div id="manual-lead-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary" id="manual-lead-save-btn" style="flex:1; margin-top:0; padding:10px;">
                        <i class="fas fa-plus"></i> Add Lead
                    </button>
                    <button type="button" class="btn-secondary" id="manual-lead-cancel-btn" style="padding:10px 16px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Share Campaign Modal -->
    <div class="modal-overlay" id="share-campaign-modal" style="z-index:10095;">
        <div class="modal-content" style="max-width:480px; padding:28px;">
            <div class="modal-header">
                <h3 style="font-size:18px;"><i class="fas fa-share-nodes" style="color:var(--accent-primary);"></i> Share Campaign</h3>
                <button class="modal-close" id="share-campaign-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <p style="font-size:13px; color:var(--text-secondary); margin:8px 0 16px;">Select users to share this campaign with. They will be able to view and use this campaign but cannot delete it.</p>
            <div id="share-campaign-user-list" style="display:flex; flex-direction:column; gap:10px; max-height:320px; overflow-y:auto; padding-right:4px;">
                <!-- User checkboxes loaded dynamically -->
            </div>
            <div id="share-campaign-error" style="color:var(--accent-error); font-size:12px; display:none; margin-top:8px;"></div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button id="share-campaign-save-btn" class="btn-primary" style="flex:1; padding:10px; margin:0;">
                    <i class="fas fa-save"></i> Save Sharing
                </button>
                <button id="share-campaign-cancel-btn" class="btn-secondary" style="padding:10px 16px;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Global JS Hook Manager for Frontend Plugins -->
    <script>
    window.AppHooks = {
        actions: {},
        filters: {},
        addAction: function(hook, callback, priority = 10) {
            if (!this.actions[hook]) this.actions[hook] = [];
            this.actions[hook].push({ callback, priority });
            this.actions[hook].sort((a, b) => a.priority - b.priority);
        },
        addFilter: function(hook, callback, priority = 10) {
            if (!this.filters[hook]) this.filters[hook] = [];
            this.filters[hook].push({ callback, priority });
            this.filters[hook].sort((a, b) => a.priority - b.priority);
        },
        doAction: function(hook, ...args) {
            if (!this.actions[hook]) return;
            this.actions[hook].forEach(item => {
                try {
                    item.callback(...args);
                } catch (e) {
                    console.error("Error in action hook '" + hook + "':", e);
                }
            });
        },
        applyFilters: function(hook, value, ...args) {
            if (!this.filters[hook]) return value;
            let val = value;
            this.filters[hook].forEach(item => {
                try {
                    val = item.callback(val, ...args);
                } catch (e) {
                    console.error("Error in filter hook '" + hook + "':", e);
                }
            });
            return val;
        }
    };
    </script>

    <!-- Pass Auth and Page Context to JavaScript -->
    <script>
        window.currentUser = <?= json_encode(session()->get('user')) ?>;
        window.currentPageTab = <?= json_encode($activeTab) ?>;
    </script>

    <!-- Load Active Plugins JS -->
    <?php
    foreach ($plugins as $plugin) {
        if ($plugin['active'] && $plugin['has_js']) {
            echo '    <script src="/plugin-asset.php?plugin=' . htmlspecialchars($plugin['id']) . '&type=js"></script>' . "\n";
        }
    }
    ?>

    <script src="/app.js"></script>

</body>
</html>
