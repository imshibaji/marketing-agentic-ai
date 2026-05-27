<?php
// Check if installation lock exists. If not, redirect to installer.
if (!file_exists(__DIR__ . '/../database/install.lock')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/../autoload.php';
$db = new \MarketingAgent\Service\DatabaseService();
?>
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

    <link rel="stylesheet" href="style.css">
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Load Active Plugins CSS -->
    <?php
    $plugins = \MarketingAgent\Plugin\PluginManager::getInstalledPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['active'] && $plugin['has_css']) {
            echo '    <link rel="stylesheet" href="plugins/' . htmlspecialchars($plugin['id']) . '/' . htmlspecialchars($plugin['id']) . '.css">' . "\n";
        }
    }
    ?>
    <style>
        /* ==========================================
           AUTH OVERLAY — Login / Registration Screen
           ========================================== */
        #auth-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none; /* Hidden by default; JS shows it if not logged in */
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at 20% 50%, hsla(265,80%,15%,0.95) 0%, hsla(220,30%,8%,0.98) 60%);
            backdrop-filter: blur(30px);
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 32px 80px hsla(265,80%,10%,0.6), 0 0 0 1px hsla(265,80%,50%,0.08);
            overflow: hidden;
            animation: auth-enter 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes auth-enter {
            from { opacity: 0; transform: translateY(24px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .auth-header {
            background: linear-gradient(135deg, hsla(265,80%,30%,0.5), hsla(220,60%,20%,0.3));
            border-bottom: 1px solid var(--border-color);
            padding: 32px 40px 28px;
            text-align: center;
        }

        .auth-logo {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            color: white;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px hsla(265,80%,50%,0.4);
        }

        .auth-app-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.3px;
        }

        .auth-tagline {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 4px 0 0;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .auth-tab {
            flex: 1;
            padding: 16px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .auth-tab.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
            background: hsla(265,80%,50%,0.06);
        }

        .auth-body {
            padding: 32px 40px 36px;
        }

        .auth-form { display: flex; flex-direction: column; gap: 18px; }
        .auth-form.hidden { display: none !important; }

        .auth-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .auth-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-field input,
        .auth-field select {
            padding: 12px 16px;
            background: var(--bg-primary);
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: var(--font-body);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .auth-field input:focus,
        .auth-field select:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px hsla(265,80%,50%,0.12);
        }

        .auth-submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 10px;
            color: white;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
            box-shadow: 0 4px 16px hsla(265,80%,50%,0.4);
        }

        .auth-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px hsla(265,80%,50%,0.5);
        }

        .auth-submit-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .auth-error {
            background: hsla(0,80%,50%,0.1);
            border: 1px solid hsla(0,80%,50%,0.3);
            border-radius: 8px;
            color: var(--accent-error);
            font-size: 13px;
            padding: 10px 14px;
            display: none;
        }

        .auth-error.visible { display: block; }

        /* ==========================================
           USER BADGE IN HEADER
           ========================================== */
        .user-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .user-badge .user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: white;
        }

        .user-badge .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-badge .user-role {
            font-size: 10px;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            background: hsla(265,80%,50%,0.15);
            color: var(--accent-primary);
            font-weight: 700;
        }

        .btn-logout {
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: var(--font-body);
        }

        .btn-logout:hover {
            background: hsla(0,80%,50%,0.1);
            border-color: hsla(0,80%,50%,0.3);
            color: var(--accent-error);
        }

        /* ==========================================
           SETTINGS MODAL — tabbed SMTP/WhatsApp
           ========================================== */
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

    <!-- ================================================
         AUTH OVERLAY (shown when not logged in)
         ================================================ -->
    <div id="auth-overlay">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo" id="auth-logo-letter">A</div>
                <h1 class="auth-app-name" id="auth-app-name">Marketing AI Agent</h1>
                <p class="auth-tagline">Multi-Agent AI Marketing Automation</p>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab active" id="auth-tab-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                <button class="auth-tab" id="auth-tab-register">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </div>

            <div class="auth-body">
                <!-- Login Form -->
                <form class="auth-form" id="login-form" autocomplete="on">
                    <div id="login-error" class="auth-error"></div>
                    
                    <div id="password-login-fields">
                        <div class="auth-field">
                            <label for="login-username"><i class="fas fa-user"></i> Username</label>
                            <input type="text" id="login-username" placeholder="Enter your username" autocomplete="username">
                        </div>
                        <div class="auth-field">
                            <label for="login-password"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" id="login-password" placeholder="Enter your password" autocomplete="current-password">
                        </div>
                    </div>

                    <div id="otp-login-fields" class="hidden">
                        <div id="otp-dev-banner" class="hidden" style="margin-bottom:12px; font-size:11px; padding:10px 14px; background:rgba(var(--accent-primary-rgb), 0.1); border:1px solid var(--accent-primary); border-radius:8px; color:var(--accent-primary); line-height:1.4;"></div>
                        <div class="auth-field">
                            <label for="login-email"><i class="fas fa-envelope"></i> Email Address</label>
                            <div style="display:flex; gap:8px;">
                                <input type="email" id="login-email" placeholder="Enter your registered email ID" style="flex:1;">
                                <button type="button" class="btn-primary" id="send-otp-btn" style="margin-top:0; font-size:11px; padding:8px 12px; width:auto; white-space:nowrap; height:auto; border-radius:6px; line-height:1;">Send OTP</button>
                            </div>
                        </div>
                        <div class="auth-field hidden" id="otp-code-group">
                            <label for="login-otp"><i class="fas fa-key"></i> One-Time Password (OTP)</label>
                            <input type="text" id="login-otp" placeholder="Enter 6-digit OTP code">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
                        <a href="#" id="toggle-otp-login" style="font-size:11px; color:var(--accent-primary); text-decoration:none; font-weight:600;">Log in with Email OTP instead</a>
                    </div>

                    <button type="submit" class="auth-submit-btn" id="login-submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <!-- Register Form -->
                <form class="auth-form hidden" id="register-form" autocomplete="off">
                    <div id="register-error" class="auth-error"></div>
                    <div class="auth-field">
                        <label for="reg-username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="reg-username" placeholder="Choose a username (min 3 chars)" required>
                    </div>
                    <div class="auth-field">
                        <label for="reg-fullname"><i class="fas fa-id-card"></i> Full Name</label>
                        <input type="text" id="reg-fullname" placeholder="Enter your full name" required>
                    </div>
                    <div class="auth-field">
                        <label for="reg-email"><i class="fas fa-envelope"></i> Email ID</label>
                        <input type="email" id="reg-email" placeholder="Enter email ID" required>
                    </div>
                    <div class="auth-field">
                        <label for="reg-mobile"><i class="fas fa-phone"></i> Mobile / WhatsApp Number</label>
                        <input type="tel" id="reg-mobile" placeholder="Enter mobile / WhatsApp number" required>
                    </div>
                    <div class="auth-field">
                        <label for="reg-password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="reg-password" placeholder="Choose a password (min 6 chars)" required>
                    </div>
                    <div class="auth-field">
                        <label><i class="fas fa-info-circle"></i> Account Role</label>
                        <p style="font-size:12px; color:var(--text-muted); padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; margin:0;">
                            <i class="fas fa-user" style="color:var(--accent-primary);"></i>
                            New accounts start as <strong>User</strong> role. Admins can promote accounts later via Settings.
                        </p>
                        <input type="hidden" id="reg-role" value="user">
                    </div>
                    <button type="submit" class="auth-submit-btn" id="register-submit-btn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================
         MAIN APP (hidden until logged in)
         ================================================ -->
    <div class="app-container" id="main-app" style="display:none;">
        
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
                    <button class="tab-button hidden" id="tab-btn-dashboard">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </button>
                    <button class="tab-button active" id="tab-btn-campaigns">
                        <i class="fas fa-bullhorn"></i> Campaigns
                    </button>
                    <button class="tab-button" id="tab-btn-leads">
                        <i class="fas fa-search-dollar"></i> Leads Generator
                    </button>
                    <button class="tab-button" id="tab-btn-contacts">
                        <i class="fas fa-address-book"></i> Contacts
                    </button>
                    <button class="tab-button" id="tab-btn-outreach">
                        <i class="fas fa-paper-plane"></i> Outreach
                    </button>
                    <button class="tab-button hidden" id="tab-btn-users">
                        <i class="fas fa-user-shield"></i> Users
                    </button>
                    <button class="tab-button hidden" id="tab-btn-plans">
                        <i class="fas fa-tags"></i> Plans
                    </button>
                </div>

                <!-- User Badge -->
                <div class="user-badge" id="user-badge" style="display:none;">
                    <div class="user-avatar" id="user-avatar-letter">U</div>
                    <span class="user-name" id="user-badge-name">User</span>
                    <span class="user-role" id="user-badge-role">user</span>
                </div>

                <button class="action-icon-button" id="theme-toggle" title="Toggle theme">
                    <i class="fas fa-sun"></i>
                </button>

                <!-- Settings gear — admin only, shown dynamically -->
                <button class="action-icon-button" id="settings-btn" title="AI Settings" style="display:none;">
                    <i class="fas fa-cog"></i>
                </button>

                <button class="action-icon-button" id="profile-btn" title="Edit My Profile" style="margin-right: 4px;">
                    <i class="fas fa-user-circle"></i>
                </button>

                <button class="btn-logout" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <!-- Main Workspace Grid -->
        <div class="dashboard-grid">
            
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

            <!-- Right Workspace Pane -->
            <main class="workspace">
                
                <!-- Tab 0: Unified Dashboard -->
                <div id="tab-admin-dashboard" class="workspace-panel hidden">
                    <h2 id="dashboard-welcome-title" style="margin-top:0; font-size:22px; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-chart-pie" style="color:var(--accent-primary);"></i> Dashboard Overview
                    </h2>

                    <!-- Dashboard Quotas Section -->
                    <div class="panel-section card-box" style="margin-bottom:24px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                        <h3 style="margin:0 0 16px; font-size:16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                            <span><i class="fas fa-tachometer-alt" style="color:var(--accent-primary); margin-right:6px;"></i> My Resource Quotas & Balances</span>
                            <span id="dashboard-plan-badge" style="font-size:11px; padding:4px 8px; background:var(--accent-primary-gradient); color:white; border-radius:12px; font-weight:600;">Plan: loading...</span>
                        </h3>
                        <div id="dashboard-quota-container" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                            <!-- Dynamic Quota Cards filled by JS -->
                        </div>
                    </div>

                    <!-- Admin-Only Stats Grid -->
                    <div id="admin-only-stats" class="stats-grid" style="display:none; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:24px;">
                        <div class="stat-card card-box" style="display:flex; align-items:center; gap:16px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                            <div class="stat-icon" style="width:48px; height:48px; border-radius:12px; background:rgba(99,102,241,0.15); color:var(--accent-primary); display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-users"></i></div>
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px;">Total Users</span>
                                <h3 id="stat-total-users" style="font-size:24px; margin:2px 0 0;">0</h3>
                            </div>
                        </div>
                        <div class="stat-card card-box" style="display:flex; align-items:center; gap:16px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                            <div class="stat-icon" style="width:48px; height:48px; border-radius:12px; background:rgba(6,182,212,0.15); color:var(--accent-secondary); display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-bullhorn"></i></div>
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px;">Total Campaigns</span>
                                <h3 id="stat-total-campaigns" style="font-size:24px; margin:2px 0 0;">0</h3>
                            </div>
                        </div>
                        <div class="stat-card card-box" style="display:flex; align-items:center; gap:16px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                            <div class="stat-icon" style="width:48px; height:48px; border-radius:12px; background:rgba(16,185,129,0.15); color:var(--accent-success); display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-users-rectangle"></i></div>
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px;">Total Leads CRM</span>
                                <h3 id="stat-total-leads" style="font-size:24px; margin:2px 0 0;">0</h3>
                            </div>
                        </div>
                    </div>

                    <!-- User-Only Stats Grid -->
                    <div id="user-only-stats" class="stats-grid" style="display:none; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:24px;">
                        <div class="stat-card card-box" style="display:flex; align-items:center; gap:16px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                            <div class="stat-icon" style="width:48px; height:48px; border-radius:12px; background:rgba(6,182,212,0.15); color:var(--accent-secondary); display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-bullhorn"></i></div>
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px;">My Campaigns</span>
                                <h3 id="stat-user-campaigns" style="font-size:24px; margin:2px 0 0;">0</h3>
                            </div>
                        </div>
                        <div class="stat-card card-box" style="display:flex; align-items:center; gap:16px; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm);">
                            <div class="stat-icon" style="width:48px; height:48px; border-radius:12px; background:rgba(16,185,129,0.15); color:var(--accent-success); display:flex; align-items:center; justify-content:center; font-size:20px;"><i class="fas fa-users-rectangle"></i></div>
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px;">My Leads CRM</span>
                                <h3 id="stat-user-leads" style="font-size:24px; margin:2px 0 0;">0</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Split Section: Outreach Mini Panel, Public Chat & Notifications -->
                    <div style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom:24px; justify-content:flex-start; align-items:stretch; width:100%;">
                        
                        <!-- Outreach Mini Panel -->
                        <div class="panel-section card-box" id="dashboard-outreach-pane" style="display:flex; flex-direction:column; gap:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm); height:500px; flex: 1 1 calc(20% - 8px); min-width:240px;">
                            <h3 style="margin:0; font-size:16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                                <span><i class="fas fa-paper-plane" style="color:var(--accent-primary); margin-right:6px;"></i> Outreach Quick Actions</span>
                                <span id="outreach-mini-count" style="font-size:11px; padding:2px 8px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:12px; font-weight:600;">0 Leads</span>
                            </h3>
                            
                            <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:6px;">
                                <label for="outreach-mini-campaign-select" style="font-size:11px; font-weight:600; color:var(--text-secondary);">Select Campaign</label>
                                <select id="outreach-mini-campaign-select" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                                    <option value="">— Select Campaign —</option>
                                </select>
                            </div>

                            <div id="outreach-mini-contacts-list" style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:8px; padding-right:4px;">
                                <div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">Select a campaign above to load contacts.</div>
                            </div>
                        </div>

                        <!-- Public Chat Room Pane -->
                        <div class="panel-section card-box" id="dashboard-chat-pane" style="display:flex; flex-direction:column; gap:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm); height:500px; flex: 2 1 calc(40% - 16px); min-width:320px;">
                            <h3 style="margin:0; font-size:16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                                <span><i class="fas fa-comments" style="color:var(--accent-success); margin-right:6px;"></i> Public Chat Room</span>
                                <button class="action-icon-button" id="refresh-chat-btn" title="Refresh Chat" style="width:28px; height:28px; font-size:12px;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </h3>

                            <!-- Disabled Chat Placeholder -->
                            <div id="chat-disabled-placeholder" style="display:none; flex-grow:1; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted); gap:12px; text-align:center;">
                                <i class="fas fa-lock" style="font-size:36px; color:var(--accent-error);"></i>
                                <span style="font-size:13px; font-weight:600;">Public Chat is currently disabled by administrator.</span>
                            </div>

                            <!-- Chat Messages & Input Container -->
                            <div id="chat-room-container" style="display:flex; flex-direction:column; flex-grow:1; overflow:hidden;">
                                <div id="chat-messages-box" style="flex-grow:1; overflow-y:auto; display:flex; flex-direction:column; gap:10px; padding:10px; margin-bottom:12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); min-height:220px;">
                                    <!-- Dynamic chat messages -->
                                </div>
                                
                                <form id="chat-send-form" style="display:flex; gap:8px;">
                                    <input type="text" id="chat-input" placeholder="Type a message..." required style="flex-grow:1; padding:10px 12px; font-size:12px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
                                    <button type="submit" class="btn-primary" style="margin-top:0; padding:10px 16px; font-size:12px;">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Notifications Pane -->
                        <div class="panel-section card-box" id="dashboard-notifications-pane" style="display:flex; flex-direction:column; gap:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm); height:500px; flex: 2 1 calc(40% - 16px); min-width:320px;">
                            <h3 style="margin:0; font-size:16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                                <span><i class="fas fa-bell" style="color:var(--accent-secondary); margin-right:6px;"></i> System Notifications</span>
                                <button class="action-icon-button" id="refresh-notifications-btn" title="Refresh Notifications" style="width:28px; height:28px; font-size:12px;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </h3>
                            
                            <!-- Admin Notification Publisher Form -->
                            <div id="admin-notification-publisher" style="display:none; border-bottom:1px solid var(--border-color); padding-bottom:16px; margin-bottom:4px;">
                                <h4 style="margin:0 0 10px; font-size:13px; color:var(--text-primary);">Publish New Notification</h4>
                                <form id="publish-notification-form" style="display:flex; flex-direction:column; gap:8px;">
                                    <input type="text" id="notification-title" placeholder="Notification Title" required style="width:100%; padding:8px 12px; font-size:12px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
                                    <textarea id="notification-message" placeholder="Message content... Mention users using @username (sends email)" required style="width:100%; height:60px; padding:8px 12px; font-size:12px; border-radius:var(--border-radius-sm); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); resize:none;"></textarea>
                                    <button type="submit" class="btn-primary" style="margin-top:0; padding:8px 12px; font-size:12px; width:fit-content; align-self:flex-end;">
                                        <i class="fas fa-paper-plane"></i> Publish
                                    </button>
                                </form>
                            </div>

                            <div id="notifications-feed-list" style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:12px; padding-right:4px;">
                                <!-- Dynamic notifications list -->
                            </div>
                        </div>

                    </div>

                    <!-- Admin-Only Dashboard Elements Section -->
                    <div id="admin-only-dashboard-elements" style="display:none;">
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                            <!-- Left Side: Activity Logs -->
                            <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; max-height:400px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm);">
                                <h3 style="margin:0; font-size:16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;"><i class="fas fa-history" style="color:var(--accent-primary); margin-right:6px;"></i> System Activity Logs</h3>
                                <div id="dashboard-activity-logs" style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:10px; padding-right:4px;">
                                    <!-- Loaded dynamically via JS -->
                                </div>
                            </div>

                            <!-- Right Side: Quick Actions & Plan Details -->
                            <div style="display:flex; flex-direction:column; gap:20px;">
                                <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm); max-height:220px;">
                                    <h3 style="margin:0; font-size:16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;"><i class="fas fa-rocket" style="color:var(--accent-secondary); margin-right:6px;"></i> Quick Actions</h3>
                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                        <button class="btn-primary" id="dash-new-campaign-btn" style="width:100%; margin-top:0; padding:10px;"><i class="fas fa-plus"></i> New Campaign</button>
                                        <button class="btn-secondary" id="dash-view-users-btn" style="width:100%; border-color:var(--accent-primary); color:var(--accent-primary); font-weight:600; padding:8px;"><i class="fas fa-users-cog"></i> Manage Users</button>
                                        <button class="btn-secondary" id="dash-view-leads-btn" style="width:100%; padding:8px;"><i class="fas fa-users-rectangle"></i> View Leads CRM</button>
                                    </div>
                                </div>

                                <!-- Plan Details Card -->
                                <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); padding:20px; box-shadow:var(--shadow-sm); overflow-y:auto; max-height:250px;">
                                    <h3 style="margin:0; font-size:16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;"><i class="fas fa-tags" style="color:var(--accent-primary); margin-right:6px;"></i> Plan Details</h3>
                                    <div style="overflow-x:auto;">
                                        <table class="custom-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:11px;">
                                            <thead>
                                                <tr style="border-bottom:1px solid var(--border-color); color:var(--text-secondary); font-weight:600; text-transform:uppercase; font-size:9px; letter-spacing:0.5px;">
                                                    <th style="padding:6px 2px;">Plan Name</th>
                                                    <th style="padding:6px 2px;">Campaigns</th>
                                                    <th style="padding:6px 2px;">Leads</th>
                                                    <th style="padding:6px 2px;">LLM</th>
                                                    <th style="padding:6px 2px;">Email</th>
                                                    <th style="padding:6px 2px;">WhatsApp</th>
                                                    <th style="padding:6px 2px;">SMS</th>
                                                </tr>
                                            </thead>
                                            <tbody id="dashboard-plans-tbody">
                                                <!-- Loaded dynamically via JS -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 1: Campaigns & Agent Output -->
                <div id="tab-campaigns" class="workspace-panel">
                    
                    <!-- View A: Campaign Creator Form -->
                    <div id="campaign-creator-panel" class="creator-container">
                        <h2>Build Marketing Campaigns</h2>
                        <p>Set parameters and let Researcher, Copywriter, and Editor agents build SEO reports and channel copy automatically.</p>
                        
                        <form id="campaign-creator-form">
                            <div class="form-group">
                                <label for="campaign_title">Campaign / Product Title</label>
                                <input type="text" id="campaign_title" placeholder="e.g. Acme DevFlow Planner" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="product_description">Product / Service Description</label>
                                <textarea id="product_description" rows="4" placeholder="Describe the core features, value proposition, and problems it solves..." required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="target_audience">Target Audience</label>
                                    <input type="text" id="target_audience" placeholder="e.g. B2B Software Developers, CTOs" required>
                                </div>
                                <div class="form-group">
                                    <label for="channel">Marketing Channel</label>
                                    <select id="channel" required>
                                        <option value="email">Email Outreach Campaign</option>
                                        <option value="social">Social Media Posts (LinkedIn, X)</option>
                                        <option value="blog">SEO Blog Post &amp; Outline</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="campaign_crawl_type">Research &amp; Scraping Source</label>
                                    <select id="campaign_crawl_type" required>
                                        <option value="none">None (AI Generated Search Metrics)</option>
                                        <option value="website">Website Link (Analyze target website)</option>
                                        <option value="maps_link">Google Maps URL (Analyze business list from URL)</option>
                                        <option value="maps_search">Google Maps Search (Location &amp; Keywords)</option>
                                    </select>
                                </div>
                                <div class="form-group hidden" id="campaign-crawl-target-group">
                                    <label id="campaign-crawl-target-label" for="campaign_crawl_target">Crawl Link / URL</label>
                                    <input type="text" id="campaign_crawl_target" placeholder="e.g. https://example.com">
                                </div>
                                <div class="form-group hidden" id="campaign-crawl-search-group">
                                    <label>Location &amp; Keywords</label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" id="campaign_crawl_loc" placeholder="Location (e.g. Dallas, TX)" style="flex:1;">
                                        <input type="text" id="campaign_crawl_kw" placeholder="Keywords (e.g. Gyms)" style="flex:1;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="campaign_language">Campaign Target Language</label>
                                    <select id="campaign_language" required>
                                        <option value="English">English</option>
                                        <option value="Bengali">Bengali (বাংলা)</option>
                                        <option value="Hindi">Hindi (हिन्दी)</option>
                                        <option value="Tamil">Tamil (தமிழ்)</option>
                                        <option value="Telugu">Telugu (తెలుగు)</option>
                                        <option value="Marathi">Marathi (मराठी)</option>
                                        <option value="Kannada">Kannada (ಕನ್ನಡ)</option>
                                        <option value="Gujarati">Gujarati (ગુજરાતી)</option>
                                        <option value="Malayalam">Malayalam (മലയാളം)</option>
                                        <option value="Punjabi">Punjabi (ਪੰਜਾਬੀ)</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary" style="width: 100%;">
                                <i class="fas fa-circle-plus"></i> Save Campaign Details
                            </button>
                        </form>
                    </div>

                    <!-- View B: Pipeline View (Visible when Campaign is Selected) -->
                    <div id="campaign-workspace-panel" class="pipeline-layout hidden">
                        
                        <!-- Left Console (Agent states & Logs) -->
                        <div class="pipeline-left">
                            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px; border-bottom:1px solid var(--border-color); padding-bottom:14px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                    <h3 style="font-size:16px; margin:0;">Agent Execution Pipeline</h3>
                                    <button class="btn-primary" id="run-campaign-btn" style="font-size:12px; padding: 8px 12px; margin:0;">
                                        <i class="fas fa-play"></i> Run Agents
                                    </button>
                                </div>

                                <!-- LLM Selector (dynamic — populated from /api/usage.php) -->
                                <div style="display:flex; align-items:center; gap:8px; width:100%;">
                                    <label for="campaign-llm-provider" style="font-size:11px; color:var(--text-secondary); white-space:nowrap; font-weight:600;"><i class="fas fa-brain"></i> SELECT LLM MODEL:</label>
                                    <select id="campaign-llm-provider" class="form-control" style="font-size:12px; padding:4px 8px; height:auto; margin:0; flex-grow:1; background:var(--bg-card); color:var(--text-primary); border:1px solid var(--border-color); border-radius:4px;">
                                        <option value="">— Loading active models… —</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="agent-stages">
                                <!-- Stage 1 -->
                                <div class="agent-stage-card" id="stage-researcher">
                                    <div class="agent-icon"><i class="fas fa-magnifying-glass"></i></div>
                                    <div class="agent-details">
                                        <h4>Researcher Agent</h4>
                                        <span>Queries SEO metrics &amp; competitors</span>
                                    </div>
                                </div>
                                <!-- Stage 2 -->
                                <div class="agent-stage-card" id="stage-copywriter">
                                    <div class="agent-icon"><i class="fas fa-pen-nib"></i></div>
                                    <div class="agent-details">
                                        <h4>Creative Copywriter</h4>
                                        <span>Drafts channel copy based on research</span>
                                    </div>
                                </div>
                                <!-- Stage 3 -->
                                <div class="agent-stage-card" id="stage-editor">
                                    <div class="agent-icon"><i class="fas fa-check-double"></i></div>
                                    <div class="agent-details">
                                        <h4>Editorial Specialist</h4>
                                        <span>Refines tone, readability &amp; formats</span>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                                <h4 style="font-size:13px; color:var(--text-secondary);"><i class="fas fa-terminal"></i> Agent Console Logs</h4>
                            </div>
                            <div class="console-log" id="console-log">
                                <!-- Logs append here -->
                            </div>
                            
                            <div style="display:flex; flex-direction:column; gap:6px; margin-top:15px; margin-bottom:12px; width:100%;">
                                <label for="lead-source-input" style="font-size:11px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-search-location"></i> Lead Source URL / Google Maps Query</label>
                                <input type="text" id="lead-source-input" placeholder="Google Maps (Default) or Directory URL" style="font-size:12px; padding:10px 12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                            </div>

                            <button class="btn-secondary" id="generate-leads-btn" style="width: 100%; border-color:var(--accent-primary); color:var(--accent-primary); font-weight:600; margin-top:0;">
                                <i class="fas fa-bolt"></i> Generate &amp; Qualify Leads
                            </button>
                        </div>

                        <!-- Right Output (Final Result) -->
                        <div class="pipeline-right">
                            <div class="output-header">
                                <div>
                                    <h3 id="workspace-title">Campaign Name</h3>
                                    <span id="workspace-meta" style="font-size:12px; color:var(--text-muted);">Metadata...</span>
                                </div>
                                <div class="output-actions" style="display:flex; gap:8px; align-items:center;">
                                    <span class="campaign-badge" id="workspace-badge">Status</span>
                                    <button class="btn-secondary hidden" id="share-campaign-btn" style="padding:8px 12px; font-size:12px;"><i class="fas fa-share-nodes"></i> Share</button>
                                    <button class="btn-secondary hidden" id="copy-copy-btn"><i class="fas fa-copy"></i> Copy</button>
                                    <button class="btn-secondary hidden" id="edit-copy-btn"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn-primary hidden" id="save-copy-btn" style="padding: 8px 12px; margin-top:0;"><i class="fas fa-save"></i> Save</button>
                                    <button class="btn-secondary hidden" id="cancel-edit-btn" style="padding: 8px 12px;"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </div>
                            <div class="result-content" style="position:relative; display:flex; flex-direction:column; padding:0;">
                                <div class="markdown-body" id="result-output" style="padding: 24px; flex-grow:1; overflow-y:auto; width:100%; height:100%;">
                                    <!-- Markdown content loaded here -->
                                </div>
                                <textarea id="result-editor" class="hidden" style="width:100%; height:100%; flex-grow:1; min-height:400px; padding:24px; background:var(--bg-primary); color:var(--text-primary); border:none; font-family:monospace; font-size:14px; outline:none; resize:none; line-height:1.5;"></textarea>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Tab 2: Leads CRM & Qualification -->
                <div id="tab-leads" class="workspace-panel hidden" style="overflow:hidden; height:100%; min-height:0;">
                    
                    <div class="crm-layout" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; height:100%; min-height:0; overflow:hidden;">
                        <!-- Column 1: Contacts List & CRUD (Left) -->
                        <div class="crm-sidebar" style="display:flex; flex-direction:column; gap:16px; border-right:1px solid var(--border-color); padding-right:20px; height:100%; max-height:100%; min-height:0; overflow:hidden;">
                            <div class="crm-board-header" style="display:flex; flex-direction:column; gap:10px; align-items:flex-start; margin-bottom:0; width:100%;">
                                <h3 id="crm-board-title" style="font-size:18px;">Scraped Prospects</h3>
                            </div>
                            
                            <div id="leads-vertical-list" style="display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex-grow:1; padding-right:4px;">
                                <!-- Lead Cards rendered here -->
                            </div>
                        </div>

                        <!-- Column 2: Leads Scraper Panel (Middle) -->
                        <div class="crm-scraper-column" style="display:flex; flex-direction:column; gap:16px; border-right:1px solid var(--border-color); padding-right:20px; overflow-y:auto; height:100%; max-height:100%;">
                            <!-- SDR Scraper Panel -->
                            <div class="sdr-panel" style="background:var(--bg-card); border:1px solid var(--border-color); padding:16px; border-radius:var(--border-radius-sm); display:flex; flex-direction:column; gap:12px; box-shadow:var(--shadow-sm);">
                                <h4 style="font-size:14px; margin:0; display:flex; align-items:center; gap:6px;"><i class="fas fa-search-dollar" style="color:var(--accent-primary);"></i> Leads Scraper Settings</h4>
                                <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:4px;">
                                    <label for="crm-lead-source-type" style="font-size:10px; font-weight:600; color:var(--text-secondary);">Lead Scraping Source</label>
                                    <select id="crm-lead-source-type" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                                        <option value="maps_search">Google Maps Search</option>
                                        <option value="maps_link">Custom Google Maps URL</option>
                                        <option value="website">Custom Website URL</option>
                                    </select>
                                </div>

                                <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:4px;">
                                    <label for="scraper-llm-provider" style="font-size:10px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-brain"></i> Select LLM Model (Only Active Can Be Selected)</label>
                                    <select id="scraper-llm-provider" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                                        <option value="">— Loading active models… —</option>
                                    </select>
                                </div>
                                
                                <!-- Dynamic custom inputs -->
                                <div id="crm-source-target-container" class="hidden" style="display:flex; flex-direction:column; gap:4px;">
                                    <label id="crm-source-target-label" style="font-size:10px; font-weight:600; color:var(--text-secondary);">Target Link / URL</label>
                                    <input type="text" id="crm-source-target-input" placeholder="e.g. https://example.com" style="width:100%; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                                </div>
                                <div id="crm-source-search-container" style="display:flex; flex-direction:column; gap:4px;">
                                    <label style="font-size:10px; font-weight:600; color:var(--text-secondary);">Location &amp; Keywords</label>
                                    <div style="display:flex; gap:8px; width:100%;">
                                        <input type="text" id="crm-source-loc-input" placeholder="City, State" style="flex:1; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                                        <input type="text" id="crm-source-kw-input" placeholder="Keywords" style="flex:1; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                                    </div>
                                </div>
                                
                                <button class="btn-primary" id="crm-generate-leads-btn" style="width: 100%; margin-top:4px; font-size:12px; padding:8px 12px; height:auto; line-height:1;">
                                    <i class="fas fa-bolt"></i> Run Scraper
                                </button>
                            </div>

                            <!-- Real-time SDR Scraper Console Logs -->
                            <div class="crm-console-section" style="display:flex; flex-direction:column; gap:6px; flex-grow:1; min-height:180px;">
                                <h4 style="font-size:11px; font-weight:600; color:var(--text-secondary); margin:0; display:flex; align-items:center; gap:6px;"><i class="fas fa-terminal"></i> Scraper Agent Run Logs</h4>
                                <div class="console-log" id="crm-console-log" style="flex-grow:1; min-height:160px; font-size:11px; padding:10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); font-family:monospace; overflow-y:auto;">
                                    <!-- Logs append here -->
                                </div>
                            </div>
                        </div>

                        <!-- Column 3: Lead Details Panel (Right) -->
                        <div class="lead-detail-panel" style="border-left:none; padding-left:0; overflow-y:auto; height:100%; max-height:100%;">
                            <div id="lead-detail-empty" class="lead-detail-empty">
                                <i class="fas fa-address-card" style="font-size:32px;"></i>
                                <p>Select a scraped prospect card from the list to view contact details and save them to contacts.</p>
                            </div>
                            <div id="lead-detail-content" class="hidden">
                                <!-- Rendered dynamically via JS -->
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Tab 2.5: Contacts Directory -->
                <div id="tab-contacts" class="workspace-panel hidden" style="overflow:hidden; display:flex; flex-direction:column; gap:20px; height:100%;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div>
                            <h2 style="font-size:22px; margin:0; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-address-book" style="color:var(--accent-primary);"></i> Contacts Directory
                            </h2>
                            <p style="font-size:12px; color:var(--text-secondary); margin:4px 0 0;">
                                View and inspect qualification reports and custom outreach drafts for all your contacts.
                            </p>
                        </div>
                        <!-- Search & Filter Controls -->
                        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                            <div class="search-box" style="position:relative; width:220px;">
                                <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:12px;"></i>
                                <input type="text" id="contacts-search-input" placeholder="Search contacts..." style="width:100%; padding:8px 10px 8px 30px; font-size:12px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-primary); color:var(--text-primary); outline:none;">
                            </div>
                            <select id="contacts-filter-score" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary);">
                                <option value="ALL">All Scores</option>
                                <option value="HIGH">High Fit</option>
                                <option value="MEDIUM">Medium Fit</option>
                                <option value="LOW">Low Fit</option>
                            </select>
                            <select id="contacts-filter-status" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary);">
                                <option value="ALL">All Statuses</option>
                                <option value="GENERATED">Generated</option>
                                <option value="QUALIFIED">Qualified</option>
                                <option value="OUTREACHED">Outreached</option>
                                <option value="CLOSED">Closed Leads</option>
                            </select>
                            <button id="contacts-add-btn" class="btn-primary" style="font-size:12px; padding:8px 14px; margin:0; height:auto;">
                                <i class="fas fa-user-plus"></i> Add Contact
                            </button>
                        </div>
                    </div>

                    <!-- Contacts Table Card -->
                    <div class="panel-section card-box" style="flex-grow:1; display:flex; flex-direction:column; overflow:hidden; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm); height:calc(100% - 80px);">
                        <div style="overflow-y:auto; flex-grow:1;">
                            <table class="custom-table" id="contacts-table" style="width:100%; border-collapse:collapse; text-align:left;">
                                <thead>
                                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                                        <th style="padding:12px 10px;">Contact</th>
                                        <th style="padding:12px 10px;">Company</th>
                                        <th style="padding:12px 10px;">Industry</th>
                                        <th style="padding:12px 10px;">Email</th>
                                        <th style="padding:12px 10px;">WhatsApp</th>
                                        <th style="padding:12px 10px;">Mobile</th>
                                        <th style="padding:12px 10px;">Fit Score</th>
                                        <th style="padding:12px 10px;">Status</th>
                                        <th style="padding:12px 10px;">Source</th>
                                        <th style="padding:12px 10px;">Campaign</th>
                                        <th style="padding:12px 10px;">Owner</th>
                                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="contacts-tbody" style="font-size:12px;">
                                    <!-- Loaded dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Contact Details Modal (Slide Drawer style) -->
                <div class="modal-overlay" id="contact-details-modal" style="z-index:10090;">
                    <div class="modal-content" style="max-width:700px; width:95%; padding:24px; max-height:90vh; display:flex; flex-direction:column; gap:16px; overflow-y:auto;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h3 id="contact-modal-company" style="font-size:22px; margin:0; font-family:var(--font-heading);">Company Name</h3>
                                <span id="contact-modal-score" class="lead-score-badge" style="display:inline-block; margin-top:6px;">HIGH FIT</span>
                            </div>
                            <button class="modal-close" id="contact-modal-close"><i class="fas fa-times"></i></button>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:var(--bg-primary); padding:16px; border-radius:8px; border:1px solid var(--border-color); font-size:13px;">
                            <div><strong><i class="fas fa-user-tie"></i> Contact:</strong> <span id="contact-modal-name">Name</span></div>
                            <div><strong><i class="fas fa-briefcase"></i> Industry:</strong> <span id="contact-modal-industry">Industry</span></div>
                            <div><strong><i class="fas fa-envelope"></i> Email:</strong> <span id="contact-modal-email">Email</span></div>
                            <div><strong><i class="fab fa-whatsapp"></i> WhatsApp:</strong> <span id="contact-modal-whatsapp">WhatsApp</span></div>
                            <div><strong><i class="fas fa-phone"></i> Mobile:</strong> <span id="contact-modal-mobile">Mobile</span></div>
                            <div><strong><i class="fas fa-database"></i> Data Source:</strong> <span id="contact-modal-source">Source</span></div>
                            <div><strong><i class="fas fa-bullhorn"></i> Campaign Context:</strong> <span id="contact-modal-campaign">Campaign</span></div>
                            <div><strong><i class="fas fa-user-circle"></i> Owner:</strong> <span id="contact-modal-owner">Owner</span></div>
                            <div style="grid-column: span 2;"><strong><i class="fas fa-map-marker-alt"></i> Postal Address:</strong> <span id="contact-modal-postal-address">Postal Address</span></div>
                        </div>

                        <div id="contact-modal-desc-container" style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-weight:600; font-size:13px;">Description / Requirements / Notes:</label>
                            <div id="contact-modal-description" style="background:var(--bg-primary); padding:12px; border-radius:6px; border:1px solid var(--border-color); font-size:12px; line-height:1.5; max-height:120px; overflow-y:auto;">
                            </div>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-weight:600; font-size:13px;">SDR AI Qualification Reasoning:</label>
                            <div id="contact-modal-reasoning" style="background:var(--bg-primary); padding:12px; border-radius:6px; border:1px solid var(--border-color); font-size:12px; line-height:1.5; max-height:120px; overflow-y:auto;">
                                Reasoning details...
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                            <button class="btn-primary" id="contact-modal-close-btn" style="padding:8px 16px; margin-top:0;">Close</button>
                        </div>
                    </div>
                </div>

                <!-- Outreach / Followup Panel -->
                <div id="tab-outreach" class="workspace-panel hidden" style="overflow:hidden; height:100%; min-height:0; display:flex; flex-direction:column; gap:16px;">
                    <div class="pipeline-layout" style="display:grid; grid-template-columns: 320px 1fr; gap:20px; height:100%; max-height:100%; min-height:0; overflow:hidden;">
                        
                        <!-- Sidebar: Settings & Contacts List -->
                        <div class="card-box" style="display:flex; flex-direction:column; gap:16px; height:100%; max-height:100%; overflow:hidden; padding:20px;">
                            <h3 style="margin:0; font-size:16px;"><i class="fas fa-paper-plane" style="color:var(--accent-primary); margin-right:6px;"></i> Outreach Settings</h3>
                            
                            <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:6px;">
                                <label for="outreach-campaign-select" style="font-size:11px; font-weight:600; color:var(--text-secondary);">Select Campaign</label>
                                <select id="outreach-campaign-select" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                                    <option value="">— Select Campaign —</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:6px;">
                                <label for="outreach-llm-select" style="font-size:11px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-brain"></i> Select LLM Model</label>
                                <select id="outreach-llm-select" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                                    <option value="">— Loading models… —</option>
                                </select>
                            </div>

                            <hr style="border:0; border-top:1px solid var(--border-color); margin:4px 0;">

                            <div style="display:flex; flex-direction:column; flex-grow:1; min-height:0; overflow:hidden;">
                                <h4 style="font-size:12px; font-weight:600; color:var(--text-secondary); margin:0 0 10px 0; display:flex; align-items:center; justify-content:space-between;">
                                    <span>Campaign Contacts</span>
                                    <span id="outreach-contacts-count" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:2px 6px; border-radius:10px; font-size:10px;">0</span>
                                </h4>
                                <div id="outreach-contacts-list" style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:8px; padding-right:4px;">
                                    <div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">Choose a campaign above to load contacts.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Workspace Area -->
                        <div class="card-box" style="display:flex; flex-direction:column; height:100%; max-height:100%; overflow:hidden; padding:24px; position:relative;">
                            
                            <!-- Empty State -->
                            <div id="outreach-empty-state" style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; text-align:center; gap:12px; color:var(--text-muted);">
                                <i class="fas fa-envelope-open-text" style="font-size:48px; color:var(--border-color);"></i>
                                <h4 style="margin:0; font-size:16px; color:var(--text-secondary);">Outreach Workstation</h4>
                                <p style="margin:0; font-size:13px; max-width:400px; line-height:1.5;">Select a campaign and a contact card from the sidebar list to generate and manage outreach emails, WhatsApp messages, or SMS drafts.</p>
                            </div>

                            <!-- Workspace Content (hidden by default) -->
                            <div id="outreach-workspace-content" class="hidden" style="display:flex; flex-direction:column; height:100%; max-height:100%; overflow:hidden; gap:16px;">
                                <!-- Contact Details Card -->
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:16px; font-size:12px; display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                        <div>
                                            <h4 id="outreach-contact-company" style="font-size:18px; margin:0 0 4px 0; font-family:var(--font-heading);">Company Name</h4>
                                            <span id="outreach-contact-score" class="lead-score-badge">HIGH FIT</span>
                                        </div>
                                    </div>
                                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:4px;">
                                        <div><strong><i class="fas fa-user-tie"></i> Contact:</strong> <span id="outreach-contact-name">Name</span></div>
                                        <div><strong><i class="fas fa-briefcase"></i> Industry:</strong> <span id="outreach-contact-industry">Industry</span></div>
                                        <div><strong><i class="fas fa-envelope"></i> Email:</strong> <span id="outreach-contact-email">Email</span></div>
                                        <div><strong><i class="fab fa-whatsapp"></i> WhatsApp:</strong> <span id="outreach-contact-whatsapp">WhatsApp</span></div>
                                        <div><strong><i class="fas fa-phone"></i> Mobile:</strong> <span id="outreach-contact-mobile">Mobile</span></div>
                                        <div><strong><i class="fas fa-database"></i> Source:</strong> <span id="outreach-contact-source">Source</span></div>
                                        <div style="grid-column: span 3;"><strong><i class="fas fa-map-marker-alt"></i> Address:</strong> <span id="outreach-contact-address">Address</span></div>
                                    </div>
                                    
                                    <div id="outreach-contact-desc-container" style="border-top:1px solid var(--border-color); padding-top:8px; margin-top:4px; display:none;">
                                        <strong>Description / Requirements:</strong>
                                        <div id="outreach-contact-desc" style="color:var(--text-secondary); line-height:1.4; margin-top:2px;"></div>
                                    </div>
                                </div>

                                <!-- Channels Navigation & Draft Edit Pane -->
                                <div style="display:flex; flex-direction:column; flex-grow:1; min-height:0; overflow:hidden; gap:10px;">
                                    <div class="lead-outreach-tabs" style="margin-bottom:0;">
                                        <button class="outreach-tab-btn active" id="outreach-pane-tab-email"><i class="fas fa-envelope"></i> Email Draft</button>
                                        <button class="outreach-tab-btn" id="outreach-pane-tab-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Draft</button>
                                        <button class="outreach-tab-btn" id="outreach-pane-tab-sms"><i class="fas fa-comment-alt"></i> SMS Draft</button>
                                    </div>

                                    <textarea id="outreach-pane-textarea" style="width:100%; flex-grow:1; min-height:150px; padding:16px; font-family:monospace; font-size:13px; background:var(--bg-primary); color:var(--text-primary); border:1px solid var(--border-color); border-radius:6px; outline:none; resize:none; line-height:1.5;"></textarea>
                                </div>

                                <!-- Actions Row -->
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <button class="btn-secondary" id="outreach-generate-btn" style="background:var(--bg-primary);"><i class="fas fa-bolt"></i> Generate Outreach (AI)</button>
                                    <button class="btn-secondary" id="outreach-save-btn"><i class="fas fa-save"></i> Save Draft</button>
                                    <button class="btn-secondary" id="outreach-copy-btn"><i class="fas fa-copy"></i> Copy Draft</button>
                                    
                                    <button class="btn-primary" id="outreach-send-btn" style="margin-left:auto; margin-top:0; border-color:var(--accent-success); background:linear-gradient(135deg, var(--accent-success), var(--accent-secondary));"><i class="fas fa-paper-plane"></i> Send Outreach</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div id="tab-users" class="workspace-panel hidden">
                    <!-- User Management Panel -->
                    <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; max-height:calc(100vh - 180px); overflow-y:auto; padding-right:4px; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px;"><i class="fas fa-users-cog" style="color:var(--accent-primary); margin-right:6px;"></i> User Management</h3>
                            <button class="btn-primary" id="admin-add-user-btn" style="font-size:12px; padding:6px 12px; height:auto; width:auto; margin-top:0;">
                                <i class="fas fa-plus"></i> Add New User
                            </button>
                        </div>
                        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
                            <table class="custom-table" id="admin-users-table" style="width:100%; border-collapse:collapse; text-align:left;">
                                <thead>
                                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                                        <th style="padding:12px 10px;">Username</th>
                                        <th style="padding:12px 10px;">Full Name</th>
                                        <th style="padding:12px 10px;">Email</th>
                                        <th style="padding:12px 10px;">Mobile / WhatsApp</th>
                                        <th style="padding:12px 10px;">Role</th>
                                        <th style="padding:12px 10px;">Plan</th>
                                        <th style="padding:12px 10px;">Campaigns Usage</th>
                                        <th style="padding:12px 10px;">Leads Usage</th>
                                        <th style="padding:12px 10px;">LLM Usage</th>
                                        <th style="padding:12px 10px;">Emails Sent</th>
                                        <th style="padding:12px 10px;">WhatsApp Sent</th>
                                        <th style="padding:12px 10px;">SMS Sent</th>
                                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-users-tbody" style="font-size:12px;">
                                    <!-- Loaded dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-plans" class="workspace-panel hidden">
                    <!-- Usage Plans Management Panel -->
                    <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; max-height:calc(100vh - 180px); overflow-y:auto; padding-right:4px; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px;"><i class="fas fa-tags" style="color:var(--accent-primary); margin-right:6px;"></i> Usage Plans Management</h3>
                            <button class="btn-primary" id="admin-add-plan-btn" style="font-size:12px; padding:6px 12px; height:auto; width:auto; margin-top:0;">
                                <i class="fas fa-plus"></i> Create New Plan
                            </button>
                        </div>
                        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
                            <table class="custom-table" id="admin-plans-table" style="width:100%; border-collapse:collapse; text-align:left;">
                                <thead>
                                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                                        <th style="padding:12px 10px;">Plan Name</th>
                                        <th style="padding:12px 10px;">Campaigns</th>
                                        <th style="padding:12px 10px;">Leads</th>
                                        <th style="padding:12px 10px;">LLM Limit</th>
                                        <th style="padding:12px 10px;">Email Limit</th>
                                        <th style="padding:12px 10px;">WhatsApp Limit</th>
                                        <th style="padding:12px 10px;">SMS Limit</th>
                                        <th style="padding:12px 10px;">Active Users</th>
                                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-plans-tbody" style="font-size:12px;">
                                    <!-- Loaded dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

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
                                Download a full copy of the current SQLite database (includes all users, campaigns, leads, logs, and settings).
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
                                Upload a valid database backup file (`.sqlite` or `.db`) to overwrite the current database. 
                                <strong style="color:var(--accent-error);">Warning: This will permanently overwrite all current data!</strong>
                            </p>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="file" id="restore-db-file" accept=".sqlite,.db" style="font-size:12px; color:var(--text-primary);">
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
                                <button type="button" class="btn-primary" id="btn-load-demo" style="margin-top:0; padding:8px 16px; font-size:12px; background:var(--accent-secondary); border-color:var(--accent-secondary); display:inline-flex; align-items:center; gap:8px;">
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

    <!-- =============================================
         USER EDIT / ADD MODAL
         ============================================= -->
    <div class="modal-overlay" id="user-modal" style="z-index:10100;">
        <div class="modal-content" style="max-width:460px;">
            <div class="modal-header">
                <h3 id="user-modal-title" style="font-size:18px;"><i class="fas fa-user-edit"></i> Edit User</h3>
                <button class="modal-close" id="user-modal-close"><i class="fas fa-times"></i></button>
            </div>

            <form id="user-edit-form" style="display:flex; flex-direction:column; gap:12px; margin-top:8px; max-height:80vh; overflow-y:auto; padding-right:4px;">
                <input type="hidden" id="edit-user-id" value="">

                <div class="form-group">
                    <label for="edit-username"><i class="fas fa-user"></i> Username *</label>
                    <input type="text" id="edit-username" placeholder="e.g. john_doe" required minlength="3">
                </div>

                <div class="form-group">
                    <label for="edit-password"><i class="fas fa-lock"></i> Password
                        <span id="edit-password-hint" style="font-weight:400; font-size:11px; color:var(--text-muted);"> (leave blank to keep current)</span>
                    </label>
                    <input type="password" id="edit-password" placeholder="New password (min 6 chars)">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="edit-fullname"><i class="fas fa-id-card"></i> Full Name</label>
                        <input type="text" id="edit-fullname" placeholder="Enter full name">
                    </div>
                    <div class="form-group">
                        <label for="edit-email"><i class="fas fa-envelope"></i> Email ID</label>
                        <input type="email" id="edit-email" placeholder="Enter email">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="edit-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                        <input type="tel" id="edit-mobile" placeholder="Enter mobile">
                    </div>
                    <div class="form-group">
                        <label for="edit-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                        <input type="tel" id="edit-whatsapp" placeholder="Enter WhatsApp">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit-role"><i class="fas fa-shield-alt"></i> Role</label>
                    <select id="edit-role">
                        <option value="user">User — Own data only</option>
                        <option value="admin">Admin — Full access + settings</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit-user-plan"><i class="fas fa-tags"></i> Assigned Usage Plan</label>
                    <select id="edit-user-plan">
                        <!-- Loaded dynamically via JS -->
                    </select>
                </div>

                <div id="user-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary" id="user-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                        <i class="fas fa-save"></i> Save User
                    </button>
                    <button type="button" class="btn-secondary" id="user-modal-cancel-btn" style="padding:10px 16px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
         PLAN MODAL DIALOG (Admin Only)
         ============================================= -->
    <div class="modal-overlay" id="plan-modal" style="z-index:10080;">
        <div class="modal-content" style="max-width:520px;">
            <div class="modal-header">
                <h3 id="plan-modal-title" style="font-size:18px;"><i class="fas fa-tags"></i> Create New Plan</h3>
                <button class="modal-close" id="plan-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <form id="plan-modal-form" style="display:flex; flex-direction:column; gap:14px; margin-top:8px;">
                <input type="hidden" id="plan-modal-id" value="">
                
                <div class="form-group">
                    <label for="plan-modal-name"><i class="fas fa-signature"></i> Plan Name *</label>
                    <input type="text" id="plan-modal-name" required placeholder="e.g. Premium Tier">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label for="plan-modal-campaigns"><i class="fas fa-bullhorn"></i> Campaigns (-1 = unl)</label>
                        <input type="number" id="plan-modal-campaigns" value="10" required>
                    </div>
                    <div class="form-group">
                        <label for="plan-modal-leads"><i class="fas fa-users-rectangle"></i> Leads (-1 = unl)</label>
                        <input type="number" id="plan-modal-leads" value="50" required>
                    </div>
                </div>

<div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px;">
                            <div class="form-group">
                                <label for="plan-modal-llm" class="label-compact"><i class="fas fa-brain"></i> LLM (-1 = unl)</label>
                                <input type="number" id="plan-modal-llm" value="100" required>
                            </div>
                            <div class="form-group">
                                <label for="plan-modal-email" class="label-compact"><i class="fas fa-envelope"></i> Email (-1 = unl)</label>
                                <input type="number" id="plan-modal-email" value="100" required>
                            </div>
                            <div class="form-group">
                                <label for="plan-modal-whatsapp" class="label-compact"><i class="fab fa-whatsapp"></i> WA (-1 = unl)</label>
                                <input type="number" id="plan-modal-whatsapp" value="100" required>
                            </div>
                            <div class="form-group">
                                <label for="plan-modal-sms" class="label-compact"><i class="fas fa-sms"></i> SMS (-1 = unl)</label>
                                <input type="number" id="plan-modal-sms" value="100" required>
                    </div>
                </div>

                <div id="plan-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-primary" id="plan-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                        <i class="fas fa-save"></i> Save Plan
                    </button>
                    <button type="button" class="btn-secondary" id="plan-modal-cancel-btn" style="padding:10px 16px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
         CONFIRM DELETE MODAL
         ============================================= -->
    <div class="modal-overlay" id="delete-user-modal" style="z-index:10200;">
        <div class="modal-content" style="max-width:360px; gap:16px;">
            <div class="modal-header">
                <h3 style="font-size:17px; color:var(--accent-error);"><i class="fas fa-exclamation-triangle"></i> Delete User</h3>
                <button class="modal-close" id="delete-user-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <p style="font-size:14px; color:var(--text-secondary);">Are you sure you want to delete <strong id="delete-user-name"></strong>? This action cannot be undone. Their campaigns will become unassigned.</p>
            <div style="display:flex; gap:10px;">
                <button class="btn-primary" id="delete-user-confirm-btn" style="flex:1; margin-top:0; padding:10px; background:linear-gradient(135deg,var(--accent-error),#c0392b);">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
                <button class="btn-secondary" id="delete-user-cancel-btn" style="padding:10px 16px;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- =============================================
         MY PROFILE MODAL
         ============================================= -->
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

    <!-- =============================================
         MANUAL LEAD ADD MODAL
         ============================================= -->
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

    <!-- Load Active Plugins JS -->
    <?php
    foreach ($plugins as $plugin) {
        if ($plugin['active'] && $plugin['has_js']) {
            echo '    <script src="plugins/' . htmlspecialchars($plugin['id']) . '/' . htmlspecialchars($plugin['id']) . '.js"></script>' . "\n";
        }
    }
    ?>

    <script src="app.js"></script>

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

</body>
</html>
