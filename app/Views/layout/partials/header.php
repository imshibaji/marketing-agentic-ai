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
