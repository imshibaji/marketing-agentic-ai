<?php $activeTab = 'admin-dashboard'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab 0: Unified Dashboard -->
<div id="tab-admin-dashboard" class="workspace-panel">
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
                        <a class="btn-primary" href="/campaigns" style="width:100%; margin-top:0; padding:10px; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-decoration:none; box-sizing:border-box;"><i class="fas fa-plus"></i> New Campaign</a>
                        <a class="btn-secondary" href="/users" style="width:100%; border-color:var(--accent-primary); color:var(--accent-primary); font-weight:600; padding:8px; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-decoration:none; box-sizing:border-box;"><i class="fas fa-users-cog"></i> Manage Users</a>
                        <a class="btn-secondary" href="/leads" style="width:100%; padding:8px; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-decoration:none; box-sizing:border-box;"><i class="fas fa-users-rectangle"></i> View Leads CRM</a>
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
<?= $this->endSection() ?>
