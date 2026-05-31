// Safeguard DOM queries to prevent crashes in multi-page layout
(function() {
    const createMockElement = () => ({
        addEventListener: () => {},
        removeEventListener: () => {},
        setAttribute: () => {},
        removeAttribute: () => {},
        getAttribute: () => null,
        hasAttribute: () => false,
        classList: {
            add: () => {},
            remove: () => {},
            toggle: () => {},
            contains: () => false
        },
        style: {},
        innerText: '',
        textContent: '',
        innerHTML: '',
        value: '',
        querySelector: () => null,
        querySelectorAll: () => [],
        dispatchEvent: () => true,
        click: () => {},
        reset: () => {},
        appendChild: (el) => el,
        removeChild: (el) => el,
        insertBefore: (el) => el,
        focus: () => {},
        blur: () => {}
    });

    const originalGet = document.getElementById;
    document.getElementById = function(id) {
        const el = originalGet.call(document, id);
        if (el) return el;
        return createMockElement();
    };

    const originalQuery = document.querySelector;
    document.querySelector = function(selector) {
        const el = originalQuery.call(document, selector);
        if (el) return el;
        return createMockElement();
    };
})();

// Fire frontend hooks for app_init
if (window.AppHooks) {
    window.AppHooks.doAction('app_init');
}

document.addEventListener('DOMContentLoaded', () => {
    // Fire frontend hooks for dom_ready
    if (window.AppHooks) {
        window.AppHooks.doAction('dom_ready');
    }
    function safeParseDate(dateStr) {
        if (!dateStr) return new Date();
        if (typeof dateStr !== 'string') return new Date(dateStr);
        // Replace space with T for ISO format compatibility (e.g. YYYY-MM-DD HH:MM:SS)
        const normalized = dateStr.replace(' ', 'T');
        const d = new Date(normalized);
        if (isNaN(d.getTime())) {
            // Fallback for Safari/Firefox if the string is still not parsed
            const clean = dateStr.replace(/-/g, '/');
            const d2 = new Date(clean);
            if (!isNaN(d2.getTime())) return d2;
        }
        return d;
    }

    // State management
    const state = {
        theme: 'dark',
        activeTab: 'campaigns',
        campaigns: [],
        contacts: [],
        activeCampaignId: null,
        activeCampaign: null,
        activeLead: null,
        activeLeadTab: 'email',
        currentUser: null, // { id, username, role }
        chatIntervalId: null,
        settings: {
            llm_provider: 'gemini',
            gemini_api_key: '',
            gemini_model: 'gemini-1.5-flash',
            lm_studio_url: 'http://localhost:1234/v1',
            lm_studio_model: 'qwen2.5-7b-instruct',
            ollama_url: 'http://localhost:11434',
            ollama_model: 'llama3',
            app_name: 'Marketing AI Agent',
            smtp_host: '', smtp_port: '587',
            smtp_user: '', smtp_pass: '',
            smtp_from_email: '', smtp_from_name: '',
            whatsapp_token: '', whatsapp_phone_id: '',
            sms_provider: 'mock',
            sms_twilio_account_sid: '',
            sms_twilio_auth_token: '',
            sms_twilio_from_number: ''
        }
    };

    // DOM Elements
    const elements = {
        html: document.documentElement,
        themeToggle: document.getElementById('theme-toggle'),
        themeIcon: document.querySelector('#theme-toggle i'),

        // Auth overlay
        authOverlay: document.getElementById('auth-overlay'),
        mainApp: document.getElementById('main-app'),
        authTabLogin: document.getElementById('auth-tab-login'),
        authTabRegister: document.getElementById('auth-tab-register'),
        loginForm: document.getElementById('login-form'),
        loginUsername: document.getElementById('login-username'),
        loginPassword: document.getElementById('login-password'),
        loginError: document.getElementById('login-error'),
        loginSubmitBtn: document.getElementById('login-submit-btn'),
        registerForm: document.getElementById('register-form'),
        regUsername: document.getElementById('reg-username'),
        regPassword: document.getElementById('reg-password'),
        regRole: document.getElementById('reg-role'),
        registerError: document.getElementById('register-error'),
        registerSubmitBtn: document.getElementById('register-submit-btn'),

        // User badge & logout
        userBadge: document.getElementById('user-badge'),
        userAvatarLetter: document.getElementById('user-avatar-letter'),
        userBadgeName: document.getElementById('user-badge-name'),
        userBadgeRole: document.getElementById('user-badge-role'),
        logoutBtn: document.getElementById('logout-btn'),

        // App name elements
        pageTitle: document.getElementById('page-title'),
        headerAppName: document.getElementById('header-app-name'),
        headerLogoLetter: document.getElementById('header-logo-letter'),
        authAppName: document.getElementById('auth-app-name'),
        authLogoLetter: document.getElementById('auth-logo-letter'),
        
        tabCampaigns: document.getElementById('tab-campaigns'),
        tabLeads: document.getElementById('tab-leads'),
        tabBtnCampaigns: document.getElementById('tab-btn-campaigns'),
        tabBtnLeads: document.getElementById('tab-btn-leads'),
        tabBtnOutreach: document.getElementById('tab-btn-outreach'),
        tabOutreach: document.getElementById('tab-outreach'),
        
        settingsBtn: document.getElementById('settings-btn'),
        settingsModal: document.getElementById('settings-modal'),
        settingsClose: document.getElementById('settings-close'),
        settingsForm: document.getElementById('settings-form'),
        llmProviderSelect: document.getElementById('llm_provider'),
        smsProviderSelect: document.getElementById('sms_provider'),
        
        newCampaignBtn: document.getElementById('new-campaign-btn'),
        campaignList: document.getElementById('campaign-list'),
        
        // Campaign Creator Panel
        creatorPanel: document.getElementById('campaign-creator-panel'),
        creatorForm: document.getElementById('campaign-creator-form'),
        campaignCrawlType: document.getElementById('campaign_crawl_type'),
        campaignCrawlTargetGroup: document.getElementById('campaign-crawl-target-group'),
        campaignCrawlTargetLabel: document.getElementById('campaign-crawl-target-label'),
        campaignCrawlTarget: document.getElementById('campaign_crawl_target'),
        campaignCrawlSearchGroup: document.getElementById('campaign-crawl-search-group'),
        campaignCrawlLoc: document.getElementById('campaign_crawl_loc'),
        campaignCrawlKw: document.getElementById('campaign_crawl_kw'),
        campaignLanguage: document.getElementById('campaign_language'),
        
        // Pipeline/Workspace Panel
        workspacePanel: document.getElementById('campaign-workspace-panel'),
        workspaceTitle: document.getElementById('workspace-title'),
        workspaceMeta: document.getElementById('workspace-meta'),
        workspaceBadge: document.getElementById('workspace-badge'),
        runCampaignBtn: document.getElementById('run-campaign-btn'),
        
        // Agents Pipeline View
        agentStages: {
            researcher: document.getElementById('stage-researcher'),
            copywriter: document.getElementById('stage-copywriter'),
            editor: document.getElementById('stage-editor')
        },
        consoleLog: document.getElementById('console-log'),
        resultOutput: document.getElementById('result-output'),
        copyCopyBtn: document.getElementById('copy-copy-btn'),
        editCopyBtn: document.getElementById('edit-copy-btn'),
        saveCopyBtn: document.getElementById('save-copy-btn'),
        cancelEditBtn: document.getElementById('cancel-edit-btn'),
        resultEditor: document.getElementById('result-editor'),
        
        // Leads CRM View
        crmView: document.getElementById('leads-crm-view'),
        crmBoardHeader: document.getElementById('crm-board-title'),
        generateLeadsBtn: document.getElementById('generate-leads-btn'),
        leadsVerticalList: document.getElementById('leads-vertical-list'),
        leadFilterStatus: document.getElementById('lead-filter-status'),
        leadFilterScore: document.getElementById('lead-filter-score'),
        leadDetailContent: document.getElementById('lead-detail-content'),
        leadDetailEmpty: document.getElementById('lead-detail-empty'),
        crmLeadSourceType: document.getElementById('crm-lead-source-type'),
        crmSourceTargetContainer: document.getElementById('crm-source-target-container'),
        crmSourceTargetLabel: document.getElementById('crm-source-target-label'),
        crmSourceTargetInput: document.getElementById('crm-source-target-input'),
        crmSourceSearchContainer: document.getElementById('crm-source-search-container'),
        crmSourceLocInput: document.getElementById('crm-source-loc-input'),
        crmSourceKwInput: document.getElementById('crm-source-kw-input'),
        crmGenerateLeadsBtn: document.getElementById('crm-generate-leads-btn'),
        crmOutreachLanguage: document.getElementById('crm-outreach-language'),
        crmConsoleLog: document.getElementById('crm-console-log'),
        manualLeadId: document.getElementById('manual-lead-id')
    };

    // ----------------------------------------------------
    // THEME HANDLING (Day & Night Mode)
    // ----------------------------------------------------
    function initTheme() {
        const savedTheme = localStorage.getItem('color-scheme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme) {
            setTheme(savedTheme);
        } else {
            setTheme(systemPrefersDark ? 'dark' : 'light');
        }

        // Listen for OS system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('color-scheme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    function setTheme(theme) {
        state.theme = theme;
        elements.html.setAttribute('data-theme', theme);
        localStorage.setItem('color-scheme', theme);
        
        if (theme === 'dark') {
            elements.themeIcon.className = 'fas fa-sun';
            elements.themeToggle.title = 'Switch to Day Mode';
        } else {
            elements.themeIcon.className = 'fas fa-moon';
            elements.themeToggle.title = 'Switch to Night Mode';
        }
    }

    elements.themeToggle.addEventListener('click', () => {
        setTheme(state.theme === 'dark' ? 'light' : 'dark');
    });

    // ----------------------------------------------------
    // TABS HANDLING
    // ----------------------------------------------------
    function switchTab(tab) {
        state.activeTab = tab;
        
        const grid = document.querySelector('.dashboard-grid');
        if (grid) {
            if (tab === 'campaigns') {
                grid.classList.remove('sidebar-hidden');
            } else {
                grid.classList.add('sidebar-hidden');
            }
        }
        
        const tabBtnUsers = document.getElementById('tab-btn-users');
        const tabUsers = document.getElementById('tab-users');
        const tabBtnPlans = document.getElementById('tab-btn-plans');
        const tabPlans = document.getElementById('tab-plans');
        const tabBtnDashboard = document.getElementById('tab-btn-dashboard');
        const tabAdminDashboard = document.getElementById('tab-admin-dashboard');
        const tabBtnContacts = document.getElementById('tab-btn-contacts');
        const tabContacts = document.getElementById('tab-contacts');
        const tabBtnOutreach = document.getElementById('tab-btn-outreach');
        const tabOutreach = document.getElementById('tab-outreach');

        elements.tabBtnCampaigns.classList.remove('active');
        elements.tabBtnLeads.classList.remove('active');
        if (tabBtnUsers) tabBtnUsers.classList.remove('active');
        if (tabBtnPlans) tabBtnPlans.classList.remove('active');
        if (tabBtnDashboard) tabBtnDashboard.classList.remove('active');
        if (tabBtnContacts) tabBtnContacts.classList.remove('active');
        if (tabBtnOutreach) tabBtnOutreach.classList.remove('active');

        elements.tabCampaigns.classList.add('hidden');
        elements.tabLeads.classList.add('hidden');
        if (tabUsers) tabUsers.classList.add('hidden');
        if (tabPlans) tabPlans.classList.add('hidden');
        if (tabAdminDashboard) tabAdminDashboard.classList.add('hidden');
        if (tabContacts) tabContacts.classList.add('hidden');
        if (tabOutreach) tabOutreach.classList.add('hidden');

        if (tab === 'campaigns') {
            elements.tabBtnCampaigns.classList.add('active');
            elements.tabCampaigns.classList.remove('hidden');
        } else if (tab === 'leads') {
            elements.tabBtnLeads.classList.add('active');
            elements.tabLeads.classList.remove('hidden');
            
            const filterCampaignSelect = document.getElementById('crm-campaign-filter');
            if (filterCampaignSelect) {
                // Populate options
                filterCampaignSelect.innerHTML = '<option value="">All Campaigns &amp; Manual Leads</option>';
                filterCampaignSelect.innerHTML += '<option value="unsaved_scraper">Latest Scraper Results (Unsaved)</option>';
                if (state.campaigns && state.campaigns.length > 0) {
                    state.campaigns.forEach(c => {
                        filterCampaignSelect.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
                    });
                }
                
                // Select correct value
                if (state.activeCampaignId) {
                    filterCampaignSelect.value = state.activeCampaignId;
                    loadLeadsCrmData();
                } else if (state.scrapedLeads && state.scrapedLeads.length > 0) {
                    filterCampaignSelect.value = 'unsaved_scraper';
                    renderScrapedLeadsList();
                } else {
                    filterCampaignSelect.value = '';
                    loadLeadsCrmData();
                }
            } else {
                renderScrapedLeadsList();
            }
        } else if (tab === 'contacts') {
            if (tabBtnContacts) tabBtnContacts.classList.add('active');
            if (tabContacts) tabContacts.classList.remove('hidden');
            loadContactsDirectory();
        } else if (tab === 'outreach') {
            if (tabBtnOutreach) tabBtnOutreach.classList.add('active');
            if (tabOutreach) tabOutreach.classList.remove('hidden');
            loadOutreachTab();
        } else if (tab === 'users') {
            if (tabBtnUsers) tabBtnUsers.classList.add('active');
            if (tabUsers) tabUsers.classList.remove('hidden');
            loadUsers();
            loadActivityLogs();
        } else if (tab === 'plans') {
            if (tabBtnPlans) tabBtnPlans.classList.add('active');
            if (tabPlans) tabPlans.classList.remove('hidden');
            loadPlans();
        } else if (tab === 'admin-dashboard') {
            if (tabBtnDashboard) tabBtnDashboard.classList.add('active');
            if (tabAdminDashboard) tabAdminDashboard.classList.remove('hidden');
            
            // Set up Dashboard Roles UI
            const isAdmin = state.currentUser && state.currentUser.role === 'admin';
            const adminStats = document.getElementById('admin-only-stats');
            const userStats = document.getElementById('user-only-stats');
            const adminElements = document.getElementById('admin-only-dashboard-elements');
            const adminPublisher = document.getElementById('admin-notification-publisher');
            const welcomeTitle = document.getElementById('dashboard-welcome-title');

            if (adminStats) adminStats.style.display = isAdmin ? 'grid' : 'none';
            if (userStats) userStats.style.display = !isAdmin ? 'grid' : 'none';
            if (adminElements) adminElements.style.display = isAdmin ? 'block' : 'none';
            if (adminPublisher) adminPublisher.style.display = isAdmin ? 'block' : 'none';

            if (welcomeTitle && state.currentUser) {
                welcomeTitle.innerHTML = `<i class="fas fa-chart-pie" style="color:var(--accent-primary);"></i> Welcome back, ${escapeHtml(state.currentUser.full_name || state.currentUser.username)}!`;
            }

            // Load resources
            loadDashboardQuotas();
            loadNotifications();
            loadChatMessages();
            loadDashboardStats();

            if (isAdmin) {
                loadDashboardActivityLogs();
                loadDashboardPlans();
            }
        }

        if (window.AppHooks) {
            window.AppHooks.doAction('tab_switched', tab);
        }
    }

    elements.tabBtnCampaigns.addEventListener('click', () => switchTab('campaigns'));
    elements.tabBtnLeads.addEventListener('click', () => switchTab('leads'));
    
    const tabBtnUsers = document.getElementById('tab-btn-users');
    if (tabBtnUsers) {
        tabBtnUsers.addEventListener('click', () => switchTab('users'));
    }

    const tabBtnPlans = document.getElementById('tab-btn-plans');
    if (tabBtnPlans) {
        tabBtnPlans.addEventListener('click', () => switchTab('plans'));
    }

    const tabBtnDashboard = document.getElementById('tab-btn-dashboard');
    if (tabBtnDashboard) {
        tabBtnDashboard.addEventListener('click', () => switchTab('admin-dashboard'));
    }

    // Dashboard Quick Actions
    const dashNewCampaignBtn = document.getElementById('dash-new-campaign-btn');
    if (dashNewCampaignBtn) {
        dashNewCampaignBtn.addEventListener('click', () => {
            switchTab('campaigns');
            if (elements.newCampaignBtn) elements.newCampaignBtn.click();
        });
    }

    const dashViewUsersBtn = document.getElementById('dash-view-users-btn');
    if (dashViewUsersBtn) {
        dashViewUsersBtn.addEventListener('click', () => switchTab('users'));
    }

    const dashViewLeadsBtn = document.getElementById('dash-view-leads-btn');
    if (dashViewLeadsBtn) {
        dashViewLeadsBtn.addEventListener('click', () => switchTab('leads'));
    }

    // ----------------------------------------------------
    // SETTINGS PANEL & LLM PROVIDERS FORM
    // ----------------------------------------------------
    async function loadSettings() {
        try {
            const res = await fetch('api/settings.php');
            const data = await res.json();
            if (data.success) {
                state.settings = { ...state.settings, ...data.settings };
                // Populate Form Fields
                for (const key in data.settings) {
                    const el = document.getElementById(key);
                    if (el) {
                        if (el.type === 'checkbox') {
                            el.checked = (data.settings[key] === '1');
                        } else {
                            el.value = data.settings[key];
                        }
                    }
                }
                toggleLlmFields();
                if (data.settings.app_name) applyAppName(data.settings.app_name);
                toggleLlmFields();
                toggleSmsFields();
                updateChatSectionVisibility();
            }
        } catch (err) {
            console.error('Failed to load settings', err);
        }
    }

    function toggleLlmFields() {
        const provider = elements.llmProviderSelect.value;
        
        // Hide all conditional classes
        document.querySelectorAll('.provider-fields').forEach(el => el.classList.add('hidden'));
        
        // Show active provider fields
        if (provider === 'gemini') {
            document.querySelectorAll('.provider-gemini').forEach(el => el.classList.remove('hidden'));
        } else if (provider === 'lm_studio') {
            document.querySelectorAll('.provider-lmstudio').forEach(el => el.classList.remove('hidden'));
        } else if (provider === 'ollama') {
            document.querySelectorAll('.provider-ollama').forEach(el => el.classList.remove('hidden'));
        } else if (provider === 'openrouter') {
            document.querySelectorAll('.provider-openrouter').forEach(el => el.classList.remove('hidden'));
        } else if (provider === 'openai_compatible') {
            document.querySelectorAll('.provider-openai-compat').forEach(el => el.classList.remove('hidden'));
        }
    }

    function toggleSmsFields() {
        const provider = elements.smsProviderSelect ? elements.smsProviderSelect.value : 'mock';
        document.querySelectorAll('.provider-twilio').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.provider-custom-sms').forEach(el => el.classList.add('hidden'));
        if (provider === 'twilio') {
            document.querySelectorAll('.provider-twilio').forEach(el => el.classList.remove('hidden'));
        } else if (provider === 'custom') {
            document.querySelectorAll('.provider-custom-sms').forEach(el => el.classList.remove('hidden'));
        }
    }

    // --------------------------------------------------------
    // USAGE QUOTA + ACTIVE LLM PROVIDERS
    // --------------------------------------------------------
    async function loadUsageAndProviders() {
        try {
            const res  = await fetch('api/usage.php');
            const data = await res.json();
            if (!data.success) return;

            // ── Populate LLM dropdown with only active providers ──────────
            const select = document.getElementById('campaign-llm-provider');
            if (select) {
                const prev = select.value;
                select.innerHTML = '';
                if (!data.active_providers || data.active_providers.length === 0) {
                    select.innerHTML = '<option value="">⚠ No LLM configured — contact Admin</option>';
                } else {
                    data.active_providers.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.model || p.label;
                        select.appendChild(opt);
                    });
                    // Restore previous selection if still available
                    if (prev && [...select.options].some(o => o.value === prev)) {
                        select.value = prev;
                    }
                }
            }

            // ── Populate Scraper LLM dropdown with only active providers ──────────
            const scraperSelect = document.getElementById('scraper-llm-provider');
            if (scraperSelect) {
                const prev = scraperSelect.value;
                scraperSelect.innerHTML = '';
                if (!data.active_providers || data.active_providers.length === 0) {
                    scraperSelect.innerHTML = '<option value="">⚠ No LLM configured — contact Admin</option>';
                } else {
                    data.active_providers.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.model || p.label;
                        scraperSelect.appendChild(opt);
                    });
                    // Restore previous selection if still available
                    if (prev && [...scraperSelect.options].some(o => o.value === prev)) {
                        scraperSelect.value = prev;
                    }
                }
            }

            // ── Populate Outreach LLM dropdown with only active providers ──────────
            const outreachLlmSelect = document.getElementById('outreach-llm-select');
            if (outreachLlmSelect) {
                const prev = outreachLlmSelect.value;
                outreachLlmSelect.innerHTML = '';
                if (!data.active_providers || data.active_providers.length === 0) {
                    outreachLlmSelect.innerHTML = '<option value="">⚠ No LLM configured — contact Admin</option>';
                } else {
                    data.active_providers.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.model || p.label;
                        outreachLlmSelect.appendChild(opt);
                    });
                    // Restore previous selection if still available
                    if (prev && [...outreachLlmSelect.options].some(o => o.value === prev)) {
                        outreachLlmSelect.value = prev;
                    }
                }
            }

        } catch (err) {
            console.warn('Failed to load active LLM models.', err);
        }
    }

    elements.settingsBtn.addEventListener('click', () => {
        if (!state.currentUser || state.currentUser.role !== 'admin') {
            showToast('Forbidden. Only administrators can open AI Settings.');
            return;
        }
        // Reset tabs to default active (LLM Panel) when opening
        document.querySelectorAll('.settings-tab').forEach(t => {
            if (t.dataset.panel === 'llm-panel') {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        });
        document.querySelectorAll('.settings-panel').forEach(p => {
            if (p.id === 'llm-panel') {
                p.classList.add('active');
            } else {
                p.classList.remove('active');
            }
        });
        const saveBtn = document.getElementById('settings-save-btn');
        if (saveBtn) {
            saveBtn.style.display = '';
        }
        elements.settingsModal.style.display = 'flex';
    });

    elements.settingsClose.addEventListener('click', () => {
        elements.settingsModal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === elements.settingsModal) {
            elements.settingsModal.style.display = 'none';
        }
    });

    elements.llmProviderSelect.addEventListener('change', toggleLlmFields);
    if (elements.smsProviderSelect) {
        elements.smsProviderSelect.addEventListener('change', toggleSmsFields);
    }

    // NOTE: settingsForm submit is handled by the comprehensive handler added at the bottom of this file.

    // ----------------------------------------------------
    // TOAST NOTIFICATIONS
    // ----------------------------------------------------
    function showToast(message) {
        const toast = document.createElement('div');
        toast.style.position = 'fixed';
        toast.style.bottom = '24px';
        toast.style.right = '24px';
        toast.style.background = 'var(--accent-primary)';
        toast.style.color = 'white';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = 'var(--shadow-lg)';
        toast.style.zIndex = '1000';
        toast.style.fontFamily = 'var(--font-body)';
        toast.style.fontWeight = '600';
        toast.style.fontSize = '14px';
        toast.style.transition = 'all 0.3s ease';
        toast.innerText = message;
        
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    // ----------------------------------------------------
    // CAMPAIGNS CORE & WORKFLOW
    // ----------------------------------------------------
    async function loadCampaigns() {
        try {
            const res = await fetch('api/campaigns.php');
            const data = await res.json();
            if (data.success) {
                state.campaigns = data.campaigns;
                renderCampaignList();
                if (typeof populateOutreachMiniCampaignSelect === 'function') {
                    populateOutreachMiniCampaignSelect();
                }
                
                // Persist selection on reload
                let activeId = localStorage.getItem('active_campaign_id');
                if (activeId && state.campaigns.find(c => c.id == activeId)) {
                    selectCampaign(parseInt(activeId));
                } else if (state.campaigns.length > 0) {
                    selectCampaign(state.campaigns[0].id);
                } else {
                    state.activeCampaignId = null;
                    elements.workspacePanel.classList.add('hidden');
                    elements.creatorPanel.classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('Failed to load campaigns', err);
        }
    }

    function renderCampaignList() {
        elements.campaignList.innerHTML = '';
        if (state.campaigns.length === 0) {
            elements.campaignList.innerHTML = '<li style="color: var(--text-muted); font-size: 13px; text-align: center; margin-top: 20px;">No campaigns found. Create one to begin.</li>';
            return;
        }

        state.campaigns.forEach(c => {
            const li = document.createElement('li');
            li.className = `campaign-item ${c.id == state.activeCampaignId ? 'active' : ''}`;
            
            const badgeClass = `badge-${c.status.toLowerCase()}`;
            const isShared = parseInt(c.is_shared) === 1;
            
            li.innerHTML = `
                <div class="campaign-item-header">
                    <span class="campaign-item-title">${escapeHtml(c.title)}${isShared ? ' <span style="font-size:9px; color:var(--accent-secondary); font-weight:700; vertical-align:middle;">(Shared)</span>' : ''}</span>
                    <span class="campaign-badge ${badgeClass}">${c.status}</span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="campaign-item-meta">${c.channel.toUpperCase()} &bull; ${safeParseDate(c.created_at).toLocaleDateString()}</span>
                    ${!isShared ? `<button class="delete-campaign-btn" data-id="${c.id}" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px;"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            `;

            li.addEventListener('click', (e) => {
                if (e.target.closest('.delete-campaign-btn')) {
                    const id = e.target.closest('.delete-campaign-btn').dataset.id;
                    deleteCampaign(id);
                    return;
                }
                selectCampaign(c.id);
            });

            elements.campaignList.appendChild(li);
        });
    }

    async function deleteCampaign(id) {
        if (!confirm('Are you sure you want to delete this campaign? All logs and qualified leads will be deleted.')) {
            return;
        }

        try {
            const res = await fetch(`api/campaigns.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) {
                showToast('Campaign deleted.');
                if (state.activeCampaignId == id) {
                    state.activeCampaignId = null;
                    state.activeCampaign = null;
                    elements.workspacePanel.classList.add('hidden');
                    elements.creatorPanel.classList.remove('hidden');
                }
                loadCampaigns();
            }
        } catch (err) {
            console.error('Delete campaign failed', err);
        }
    }

    function selectCampaign(id) {
        state.activeCampaignId = id;
        localStorage.setItem('active_campaign_id', id);
        renderCampaignList();
        
        elements.creatorPanel.classList.add('hidden');
        elements.workspacePanel.classList.remove('hidden');
        
        loadCampaignDetails(id);
    }

    async function loadCampaignDetails(id) {
        try {
            const res = await fetch(`api/campaigns.php?id=${id}`);
            const data = await res.json();
            if (data.success) {
                state.activeCampaign = data.campaign;
                elements.workspaceTitle.innerText = data.campaign.title;
                elements.workspaceMeta.innerText = `Channel: ${data.campaign.channel.toUpperCase()} | Target Audience: ${data.campaign.target_audience} | Language: ${data.campaign.language || 'English'}`;
                
                // Pre-select the campaign LLM provider if saved
                const campaignLlmSelect = document.getElementById('campaign-llm-provider');
                if (campaignLlmSelect && data.campaign.llm_provider) {
                    campaignLlmSelect.value = data.campaign.llm_provider;
                }

                // Pre-fill lead source input on campaign panel with campaign's crawl target
                const sourceInput = document.getElementById('lead-source-input');
                if (sourceInput) {
                    sourceInput.value = data.campaign.crawl_target || '';
                }

                // Reset CRM scraper selectors to defaults
                if (elements.crmLeadSourceType) {
                    elements.crmLeadSourceType.value = 'maps_search';
                    elements.crmLeadSourceType.dispatchEvent(new Event('change'));
                }
                if (elements.crmOutreachLanguage) {
                    elements.crmOutreachLanguage.value = 'default';
                }

                const badge = elements.workspaceBadge;
                badge.className = `campaign-badge badge-${data.campaign.status.toLowerCase()}`;
                badge.innerText = data.campaign.status;
                
                // Show final result if complete
                if (data.campaign.status === 'COMPLETED' && data.campaign.final_content) {
                    elements.resultOutput.innerHTML = renderMarkdown(data.campaign.final_content);
                    elements.copyCopyBtn.classList.remove('hidden');
                    elements.editCopyBtn.classList.remove('hidden');
                    resetPipelineUi();
                    setPipelineStagesCompleted();
                } else if (data.campaign.status === 'FAILED') {
                    elements.resultOutput.innerHTML = '<div style="color:var(--accent-error); text-align:center; padding: 20px;">Campaign generation failed. Please check the logs on the left console panel.</div>';
                    elements.copyCopyBtn.classList.add('hidden');
                    elements.editCopyBtn.classList.add('hidden');
                } else {
                    elements.resultOutput.innerHTML = '<div style="color:var(--text-muted); text-align:center; padding: 20px;">Campaign setup is ready. Click "Execute Campaign Agent Pipeline" on the left to begin generation.</div>';
                    elements.copyCopyBtn.classList.add('hidden');
                    elements.editCopyBtn.classList.add('hidden');
                    resetPipelineUi();
                }
                
                // Hide editor and reset buttons
                elements.resultEditor.classList.add('hidden');
                elements.resultOutput.classList.remove('hidden');
                elements.saveCopyBtn.classList.add('hidden');
                elements.cancelEditBtn.classList.add('hidden');

                // Render Logs in console
                renderLogs(data.logs);

                // Render CRM Kanban Board
                renderCrmBoard(data.leads);
                
                elements.crmBoardHeader.innerText = `Outreach Pipeline: ${data.campaign.title}`;

                // Show / hide Share button (admin only) and store current shares
                const shareCampaignBtn = document.getElementById('share-campaign-btn');
                if (shareCampaignBtn) {
                    if (state.currentUser && state.currentUser.role === 'admin') {
                        shareCampaignBtn.classList.remove('hidden');
                        shareCampaignBtn._shares = data.shares || [];
                    } else {
                        shareCampaignBtn.classList.add('hidden');
                    }
                }
            }
        } catch (err) {
            console.error('Failed to load campaign details', err);
        }
    }

    function resetPipelineUi() {
        Object.values(elements.agentStages).forEach(stage => {
            stage.classList.remove('active', 'completed');
        });
        elements.consoleLog.innerHTML = '';
    }

    function setPipelineStagesCompleted() {
        Object.values(elements.agentStages).forEach(stage => {
            stage.classList.add('completed');
        });
    }

    function renderLogs(logs) {
        elements.consoleLog.innerHTML = '';
        if (logs.length === 0) {
            elements.consoleLog.innerHTML = '<div style="color:var(--text-muted); font-size:11px; font-style:italic;">Awaiting execution logs...</div>';
            return;
        }

        logs.forEach(log => {
            appendConsoleLogLine(log.agent_name, log.action, log.log_text, log.created_at);
        });
    }

    function appendConsoleLogLine(agentName, action, text, timestamp) {
        let time = '';
        if (timestamp) {
            if (typeof timestamp === 'string' && /^\d{2}:\d{2}:\d{2}$/.test(timestamp.trim())) {
                time = timestamp.trim();
            } else {
                const parsedDate = safeParseDate(timestamp);
                time = isNaN(parsedDate.getTime()) ? timestamp : parsedDate.toLocaleTimeString();
            }
        } else {
            time = new Date().toLocaleTimeString();
        }
        
        const line = document.createElement('div');
        line.className = 'console-line';
        line.innerHTML = `
            <span class="console-time">[${time}]</span>
            <span class="console-agent">${escapeHtml(agentName)}:</span>
            <span class="console-text">${escapeHtml(text)}</span>
        `;
        
        if (elements.consoleLog) {
            elements.consoleLog.appendChild(line.cloneNode(true));
            elements.consoleLog.scrollTop = elements.consoleLog.scrollHeight;
        }
        if (elements.crmConsoleLog) {
            elements.crmConsoleLog.appendChild(line.cloneNode(true));
            elements.crmConsoleLog.scrollTop = elements.crmConsoleLog.scrollHeight;
        }
    }

    // ----------------------------------------------------
    // SSE CAMPAIGN EXECUTION
    // ----------------------------------------------------
    elements.runCampaignBtn.addEventListener('click', () => {
        if (!state.activeCampaignId) return;

        const selectedProvider = document.getElementById('campaign-llm-provider')?.value || 'gemini';

        // Check if LLM Settings are set for the selected provider
        if (selectedProvider === 'gemini' && !state.settings.gemini_api_key) {
            if (state.currentUser && state.currentUser.role === 'admin') {
                alert('Please configure your Gemini API Key in Settings first.');
            } else {
                alert('Gemini provider is not configured. Please contact an administrator to set the Gemini API Key.');
            }
            return;
        }

        resetPipelineUi();
        elements.runCampaignBtn.disabled = true;
        elements.runCampaignBtn.innerText = 'Agent System Working...';
        elements.workspaceBadge.className = 'campaign-badge badge-running';
        elements.workspaceBadge.innerText = 'RUNNING';
        
        elements.resultOutput.innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:15px; color:var(--text-secondary);">
                <i class="fas fa-spinner fa-spin" style="font-size:28px; color:var(--accent-primary);"></i>
                <span>Orchestrating specialized marketing agents to research, draft, and polish copy...</span>
            </div>
        `;

        const eventSource = new EventSource(`api/run-campaign.php?id=${state.activeCampaignId}&llm_provider=${selectedProvider}`);

        eventSource.addEventListener('log', (e) => {
            const data = JSON.parse(e.data);
            appendConsoleLogLine(data.agent, data.action, data.message, data.timestamp);
            updateAgentStageUi(data.agent, data.action);
        });

        eventSource.addEventListener('complete', (e) => {
            const data = JSON.parse(e.data);
            elements.resultOutput.innerHTML = renderMarkdown(data.final_content);
            elements.copyCopyBtn.classList.remove('hidden');
            elements.editCopyBtn.classList.remove('hidden');
            
            showToast('Campaign successfully generated!');
            eventSource.close();
            
            elements.runCampaignBtn.disabled = false;
            elements.runCampaignBtn.innerText = 'Execute Campaign Agent Pipeline';
            
            loadCampaigns();
            selectCampaign(state.activeCampaignId);
            loadUsageAndProviders(); // refresh quota after run
        });

        eventSource.addEventListener('error', (e) => {
            let msg = 'An unexpected error occurred during execution.';
            try {
                const data = JSON.parse(e.data);
                msg = data.message || msg;
            } catch(err) {}

            appendConsoleLogLine("SystemError", "FAILED", msg);
            elements.resultOutput.innerHTML = `<div style="color:var(--accent-error); text-align:center; padding: 20px;">Execution Failed: ${escapeHtml(msg)}</div>`;
            
            eventSource.close();
            elements.runCampaignBtn.disabled = false;
            elements.runCampaignBtn.innerText = 'Execute Campaign Agent Pipeline';
            loadCampaigns();
            selectCampaign(state.activeCampaignId);
        });
    });

    function updateAgentStageUi(agent, action) {
        if (agent === 'Market Researcher') {
            elements.agentStages.researcher.className = 'agent-stage-card active';
        } else if (agent === 'Creative Copywriter') {
            elements.agentStages.researcher.className = 'agent-stage-card completed';
            elements.agentStages.copywriter.className = 'agent-stage-card active';
        } else if (agent === 'Editorial Specialist') {
            elements.agentStages.copywriter.className = 'agent-stage-card completed';
            elements.agentStages.editor.className = 'agent-stage-card active';
        }
    }

    // Crawl Settings toggles in Campaign Creator
    if (elements.campaignCrawlType) {
        elements.campaignCrawlType.addEventListener('change', () => {
            const val = elements.campaignCrawlType.value;
            if (val === 'none') {
                elements.campaignCrawlTargetGroup.classList.add('hidden');
                elements.campaignCrawlSearchGroup.classList.add('hidden');
                elements.campaignCrawlTarget.required = false;
                elements.campaignCrawlLoc.required = false;
                elements.campaignCrawlKw.required = false;
            } else if (val === 'website' || val === 'maps_link') {
                elements.campaignCrawlTargetGroup.classList.remove('hidden');
                elements.campaignCrawlSearchGroup.classList.add('hidden');
                elements.campaignCrawlTarget.required = true;
                elements.campaignCrawlLoc.required = false;
                elements.campaignCrawlKw.required = false;
                
                if (val === 'website') {
                    elements.campaignCrawlTargetLabel.innerText = 'Crawl Website Link / URL';
                    elements.campaignCrawlTarget.placeholder = 'e.g. https://example.com';
                } else {
                    elements.campaignCrawlTargetLabel.innerText = 'Crawl Google Maps Link / URL';
                    elements.campaignCrawlTarget.placeholder = 'e.g. https://google.com/maps/...';
                }
            } else if (val === 'maps_search') {
                elements.campaignCrawlTargetGroup.classList.add('hidden');
                elements.campaignCrawlSearchGroup.classList.remove('hidden');
                elements.campaignCrawlTarget.required = false;
                elements.campaignCrawlLoc.required = true;
                elements.campaignCrawlKw.required = true;
            }
        });
    }

    // Lead Source overrides toggles in CRM
    if (elements.crmLeadSourceType) {
        elements.crmLeadSourceType.addEventListener('change', () => {
            const val = elements.crmLeadSourceType.value;
            if (val === 'website' || val === 'maps_link') {
                elements.crmSourceTargetContainer.classList.remove('hidden');
                elements.crmSourceSearchContainer.classList.add('hidden');
                elements.crmSourceTargetInput.required = true;
                elements.crmSourceLocInput.required = false;
                elements.crmSourceKwInput.required = false;
                if (val === 'website') {
                    elements.crmSourceTargetLabel.innerText = 'Target Website Link / URL';
                    elements.crmSourceTargetInput.placeholder = 'e.g. https://example.com';
                } else {
                    elements.crmSourceTargetLabel.innerText = 'Target Google Maps Link / URL';
                    elements.crmSourceTargetInput.placeholder = 'e.g. https://www.google.com/maps/search/doctors+in+kolkata/...';
                }
            } else if (val === 'maps_search') {
                elements.crmSourceTargetContainer.classList.add('hidden');
                elements.crmSourceSearchContainer.classList.remove('hidden');
                elements.crmSourceTargetInput.required = false;
                elements.crmSourceLocInput.required = true;
                elements.crmSourceKwInput.required = true;
            }
        });
        // Dispatch initial change event to set correct visibility states on load
        elements.crmLeadSourceType.dispatchEvent(new Event('change'));
    }

    // New Campaign Form submit
    elements.creatorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const crawlType = elements.campaignCrawlType.value;
        let crawlTarget = '';
        if (crawlType === 'website' || crawlType === 'maps_link') {
            crawlTarget = elements.campaignCrawlTarget.value.trim();
        } else if (crawlType === 'maps_search') {
            const loc = elements.campaignCrawlLoc.value.trim();
            const kw = elements.campaignCrawlKw.value.trim();
            crawlTarget = `Location: ${loc} | Keywords: ${kw}`;
        }

        const language = elements.campaignLanguage ? elements.campaignLanguage.value : 'English';

        const payload = {
            title: document.getElementById('campaign_title').value,
            product_description: document.getElementById('product_description').value,
            target_audience: document.getElementById('target_audience').value,
            channel: document.getElementById('channel').value,
            crawl_type: crawlType,
            crawl_target: crawlTarget,
            language: language
        };

        try {
            const res = await fetch('api/campaigns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                showToast('Campaign setup saved.');
                elements.creatorForm.reset();
                if (elements.campaignCrawlType) {
                    elements.campaignCrawlType.value = 'none';
                    elements.campaignCrawlType.dispatchEvent(new Event('change'));
                }
                if (elements.campaignLanguage) {
                    elements.campaignLanguage.value = 'English';
                    if (window.updateCampaignLanguage) {
                        window.updateCampaignLanguage('English');
                    }
                }
                await loadCampaigns();
                selectCampaign(data.campaign_id);
            }
        } catch (err) {
            console.error('Failed to create campaign', err);
        }
    });

    elements.newCampaignBtn.addEventListener('click', () => {
        state.activeCampaignId = null;
        state.activeCampaign = null;
        renderCampaignList();
        elements.workspacePanel.classList.add('hidden');
        elements.creatorPanel.classList.remove('hidden');
    });

    // Copy campaign copy draft
    elements.copyCopyBtn.addEventListener('click', () => {
        if (!state.activeCampaign || !state.activeCampaign.final_content) return;
        navigator.clipboard.writeText(state.activeCampaign.final_content);
        showToast('Campaign copy copied to clipboard!');
    });

    // Edit campaign copy
    elements.editCopyBtn.addEventListener('click', () => {
        if (!state.activeCampaign || !state.activeCampaign.final_content) return;
        
        elements.resultEditor.value = state.activeCampaign.final_content;
        
        elements.resultOutput.classList.add('hidden');
        elements.resultEditor.classList.remove('hidden');
        
        elements.copyCopyBtn.classList.add('hidden');
        elements.editCopyBtn.classList.add('hidden');
        elements.saveCopyBtn.classList.remove('hidden');
        elements.cancelEditBtn.classList.remove('hidden');
    });

    // Cancel editing
    elements.cancelEditBtn.addEventListener('click', () => {
        elements.resultEditor.classList.add('hidden');
        elements.resultOutput.classList.remove('hidden');
        
        elements.copyCopyBtn.classList.remove('hidden');
        elements.editCopyBtn.classList.remove('hidden');
        elements.saveCopyBtn.classList.add('hidden');
        elements.cancelEditBtn.classList.add('hidden');
    });

    // Save edited campaign copy
    elements.saveCopyBtn.addEventListener('click', async () => {
        if (!state.activeCampaignId) return;
        
        const newContent = elements.resultEditor.value;
        
        try {
            elements.saveCopyBtn.disabled = true;
            elements.saveCopyBtn.innerText = 'Saving...';
            
            const res = await fetch('api/campaigns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_content',
                    id: state.activeCampaignId,
                    content: newContent
                })
            });
            
            const data = await res.json();
            if (data.success) {
                showToast('Campaign copy updated successfully!');
                state.activeCampaign.final_content = newContent;
                elements.resultOutput.innerHTML = renderMarkdown(newContent);
                
                // Switch back to preview mode
                elements.cancelEditBtn.click();
            } else {
                alert('Failed to save changes: ' + data.error);
            }
        } catch (err) {
            alert('Error saving edits: ' + err.message);
        } finally {
            elements.saveCopyBtn.disabled = false;
            elements.saveCopyBtn.innerText = 'Save';
        }
    });

    // ----------------------------------------------------
    // CRM LEADS PIPELINE WORKFLOW & OUTREACH
    // ----------------------------------------------------
    // Unified lead generation execution logic
    function executeLeadFinder(sourceUrl, language = '') {
        if (!state.activeCampaignId) return;

        const selectedProvider = document.getElementById('campaign-llm-provider')?.value || 'gemini';

        if (selectedProvider === 'gemini' && !state.settings.gemini_api_key) {
            alert('Please configure your Gemini API Key in Settings first.');
            return;
        }

        // Toggle UI loading for both buttons if they exist
        const buttons = [elements.generateLeadsBtn, elements.crmGenerateLeadsBtn];
        buttons.forEach(btn => {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SDR Scraping...';
            }
        });
        
        if (elements.consoleLog) elements.consoleLog.innerHTML = '';
        if (elements.crmConsoleLog) elements.crmConsoleLog.innerHTML = '';
        if (state.activeTab !== 'leads') {
            elements.tabBtnCampaigns.click(); // Switch to Campaign Console to watch logs
        }
        appendConsoleLogLine("LeadAgent", "START", "Starting Lead Generation and Qualification SDR Agent pipeline.");

        const eventSource = new EventSource(`api/run-leads.php?id=${state.activeCampaignId}&source_url=${encodeURIComponent(sourceUrl)}&language=${encodeURIComponent(language)}&llm_provider=${encodeURIComponent(selectedProvider)}`);

        eventSource.addEventListener('log', (e) => {
            const data = JSON.parse(e.data);
            appendConsoleLogLine(data.agent, data.action, data.message, data.timestamp);
        });

        eventSource.addEventListener('complete', (e) => {
            const data = JSON.parse(e.data);
            showToast(`SDR Agent qualified and loaded ${data.leads.length} prospects!`);
            eventSource.close();
            
            elements.generateLeadsBtn.disabled = false;
            elements.generateLeadsBtn.innerHTML = '<i class="fas fa-bolt"></i> Generate & Qualify Leads';
            if (elements.crmGenerateLeadsBtn) {
                elements.crmGenerateLeadsBtn.disabled = false;
                elements.crmGenerateLeadsBtn.innerHTML = '<i class="fas fa-bolt"></i> Run SDR Lead Finder';
            }
            
            // Switch back to leads CRM view and reload details
            switchTab('leads');
        });

        eventSource.addEventListener('error', (e) => {
            let msg = 'Lead generation failed.';
            try {
                const data = JSON.parse(e.data);
                msg = data.message || msg;
            } catch(err) {}

            appendConsoleLogLine("LeadAgent", "FAILED", msg);
            alert("Lead qualification agent failed: " + msg);
            eventSource.close();
            
            elements.generateLeadsBtn.disabled = false;
            elements.generateLeadsBtn.innerHTML = '<i class="fas fa-bolt"></i> Generate & Qualify Leads';
            if (elements.crmGenerateLeadsBtn) {
                elements.crmGenerateLeadsBtn.disabled = false;
                elements.crmGenerateLeadsBtn.innerHTML = '<i class="fas fa-bolt"></i> Run SDR Lead Finder';
            }
            loadCampaignDetails(state.activeCampaignId);
        });
    }

    elements.generateLeadsBtn.addEventListener('click', () => {
        const sourceInput = document.getElementById('lead-source-input');
        const sourceUrl = sourceInput ? sourceInput.value.trim() : '';
        executeLeadFinder(sourceUrl, '');
    });

    if (elements.crmGenerateLeadsBtn) {
        elements.crmGenerateLeadsBtn.addEventListener('click', () => {
            const type = elements.crmLeadSourceType.value;
            let location = '';
            let keywords = '';
            let sourceUrl = '';
            
            if (type === 'website' || type === 'maps_link') {
                sourceUrl = elements.crmSourceTargetInput.value.trim();
                if (!sourceUrl) {
                    alert(type === 'website' ? 'Please enter a target URL.' : 'Please enter a Google Maps URL.');
                    elements.crmSourceTargetInput.focus();
                    return;
                }
            } else if (type === 'maps_search') {
                location = elements.crmSourceLocInput.value.trim();
                keywords = elements.crmSourceKwInput.value.trim();
                if (!location || !keywords) {
                    alert('Please enter both Location and Keywords.');
                    if (!location) elements.crmSourceLocInput.focus();
                    else elements.crmSourceKwInput.focus();
                    return;
                }
            }
            
            executeLeadsScraper(type, location, keywords, sourceUrl);
        });
    }

    function executeLeadsScraper(sourceType, location, keywords, sourceUrl) {
        const filterCampaignSelect = document.getElementById('crm-campaign-filter');
        const campaignId = filterCampaignSelect ? filterCampaignSelect.value : '';

        if (!campaignId || campaignId === 'unsaved_scraper') {
            alert('Please select a campaign from the dropdown at the top-left before running the scraper.');
            if (filterCampaignSelect) filterCampaignSelect.focus();
            return;
        }

        const scraperSelect = document.getElementById('scraper-llm-provider');
        const selectedProvider = scraperSelect ? (scraperSelect.value || state.settings.llm_provider || 'gemini') : (state.settings.llm_provider || 'gemini');
        if (selectedProvider === 'gemini' && !state.settings.gemini_api_key) {
            alert('Please configure your Gemini API Key in Settings first.');
            return;
        }

        const btn = elements.crmGenerateLeadsBtn;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scraping...';
        }
        
        if (elements.crmConsoleLog) elements.crmConsoleLog.innerHTML = '';
        
        appendConsoleLogLine("LeadsScraper", "START", "Starting Standalone Leads Scraper pipeline.");

        const params = new URLSearchParams({
            campaign_id: campaignId,
            source_type: sourceType,
            location: location,
            keywords: keywords,
            source_url: sourceUrl,
            llm_provider: selectedProvider
        });

        const eventSource = new EventSource(`api/run-scraper.php?${params.toString()}`);

        eventSource.addEventListener('log', (e) => {
            const data = JSON.parse(e.data);
            appendConsoleLogLine(data.agent, data.action, data.message, data.timestamp);
        });

        eventSource.addEventListener('complete', (e) => {
            const data = JSON.parse(e.data);
            showToast(`Scraper finished and generated ${data.leads.length} prospects!`);
            eventSource.close();
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt"></i> Run Scraper';
            }
            
            // Save generated leads to state
            state.scrapedLeads = data.leads.map((l, index) => {
                return {
                    id: 'scraped_' + index,
                    ...l,
                    saved: false
                };
            });
            state.activeCampaignId = null;
            state.activeCampaign = null;
            state.activeLead = state.scrapedLeads.length > 0 ? state.scrapedLeads[0] : null;
            
            const filterCampaignSelect = document.getElementById('crm-campaign-filter');
            if (filterCampaignSelect) {
                filterCampaignSelect.value = 'unsaved_scraper';
            }
            
            renderScrapedLeadsList();
        });

        eventSource.addEventListener('error', (e) => {
            let msg = 'Lead scraping failed.';
            try {
                const data = JSON.parse(e.data);
                msg = data.message || msg;
            } catch(err) {}

            appendConsoleLogLine("LeadsScraper", "FAILED", msg);
            alert("Scraper agent failed: " + msg);
            eventSource.close();
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt"></i> Run Scraper';
            }
        });
    }

    function renderScrapedLeadsList() {
        elements.leadsVerticalList.innerHTML = '';
        const leads = state.scrapedLeads || [];

        if (leads.length === 0) {
            elements.leadsVerticalList.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px; border: 1px dashed var(--border-color); border-radius:6px;">No prospects generated yet.</div>';
            state.activeLead = null;
            renderScrapedLeadDetails();
            return;
        }

        leads.forEach(lead => {
            const card = document.createElement('div');
            card.className = `lead-card ${state.activeLead && state.activeLead.id == lead.id ? 'active' : ''}`;
            card.setAttribute('data-id', lead.id);
            
            const scoreClass = `score-${(lead.score || 'MEDIUM').toLowerCase()}`;
            
            card.innerHTML = `
                <div class="lead-card-header">
                    <span class="lead-card-company">${escapeHtml(lead.company_name)}</span>
                    <div style="display:flex; gap:6px;">
                        <span class="lead-score-badge ${scoreClass}">${lead.score || 'MEDIUM'}</span>
                        ${lead.saved ? `<span class="campaign-badge badge-completed" style="background:var(--accent-success); color:#fff; border:none; text-transform:lowercase;">saved</span>` : ''}
                    </div>
                </div>
                <div class="lead-card-desc">${escapeHtml(lead.description)}</div>
                <div class="lead-card-meta">
                    <span><i class="fas fa-user"></i> ${escapeHtml(lead.contact_name)}</span>
                    <span>${escapeHtml(lead.industry)}</span>
                </div>
            `;

            card.addEventListener('click', () => {
                state.activeLead = lead;
                const cards = document.querySelectorAll('.lead-card');
                cards.forEach(c => {
                    if (c.getAttribute('data-id') == lead.id) {
                        c.classList.add('active');
                    } else {
                        c.classList.remove('active');
                    }
                });
                renderScrapedLeadDetails();
            });

            elements.leadsVerticalList.appendChild(card);
        });

        // Auto-select first if none active
        const activeInScraped = leads.find(l => state.activeLead && l.id == state.activeLead.id);
        if (!activeInScraped && leads.length > 0) {
            state.activeLead = leads[0];
            renderScrapedLeadDetails();
        } else if (state.activeLead) {
            renderScrapedLeadDetails();
        }
    }

    function renderScrapedLeadDetails() {
        const lead = state.activeLead;
        if (!lead) {
            elements.leadDetailEmpty.classList.remove('hidden');
            elements.leadDetailContent.classList.add('hidden');
            return;
        }

        elements.leadDetailEmpty.classList.add('hidden');
        elements.leadDetailContent.classList.remove('hidden');

        const score = lead.score || 'MEDIUM';
        const scoreClass = `score-${score.toLowerCase()}`;

        let detailsHtml = `
            <div class="lead-detail-header" style="margin-bottom: 20px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <div>
                        <h4 class="lead-detail-company" style="margin-bottom:6px;">${escapeHtml(lead.company_name)}</h4>
                        <span class="lead-score-badge ${scoreClass}" style="display:inline-block; width:fit-content; margin-top:2px;">${score} FIT</span>
                    </div>
                </div>
                <div class="lead-detail-contacts">
                    <span><i class="fas fa-briefcase"></i> <strong>Industry:</strong> ${escapeHtml(lead.industry)}</span>
                    <span><i class="fas fa-user-tie"></i> <strong>Contact:</strong> ${escapeHtml(lead.contact_name)}</span>
                    <span><i class="fas fa-envelope"></i> <strong>Email:</strong> ${escapeHtml(lead.email || '—')}</span>
                    ${lead.whatsapp ? `<span><i class="fab fa-whatsapp"></i> <strong>WhatsApp:</strong> ${escapeHtml(lead.whatsapp)}</span>` : ''}
                    ${lead.mobile ? `<span><i class="fas fa-phone"></i> <strong>Mobile:</strong> ${escapeHtml(lead.mobile)}</span>` : ''}
                    ${lead.postal_address ? `<span><i class="fas fa-map-marker-alt"></i> <strong>Postal Address:</strong> ${escapeHtml(lead.postal_address)}</span>` : ''}
                    <span><i class="fas fa-database"></i> <strong>Data Source:</strong> ${escapeHtml(lead.source || 'scraper')}</span>
                </div>
            </div>
            
            ${lead.description ? `
            <div class="lead-detail-description" style="margin-bottom: 16px; font-size:12px; line-height:1.5; color:var(--text-secondary);">
                <strong>Description / Requirements / Notes:</strong><br>
                ${escapeHtml(lead.description)}
            </div>
            ` : ''}

            <div class="lead-detail-reasoning" style="margin-bottom: 24px;">
                <strong>SDR AI Qualification Reasoning:</strong><br>
                ${escapeHtml(lead.reasoning)}
            </div>

            ${lead.email_draft || lead.whatsapp_draft || lead.sms_draft ? `
            <div class="outreach-drafts-section" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 16px; margin-bottom: 20px;">
                <h4 style="font-size: 13px; margin-bottom: 12px; color: var(--text-primary);"><i class="fas fa-paper-plane" style="color:var(--accent-primary);"></i> Sample Generated Outreach Drafts</h4>
                
                <div class="draft-tabs" style="display:flex; gap:8px; margin-bottom:10px;">
                    ${lead.email_draft ? `<button class="draft-tab-btn active" data-target="email-draft-box" style="padding:6px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-card); color:var(--text-primary); cursor:pointer;">Email</button>` : ''}
                    ${lead.whatsapp_draft ? `<button class="draft-tab-btn" data-target="whatsapp-draft-box" style="padding:6px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); cursor:pointer;">WhatsApp</button>` : ''}
                    ${lead.sms_draft ? `<button class="draft-tab-btn" data-target="sms-draft-box" style="padding:6px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); cursor:pointer;">SMS</button>` : ''}
                </div>

                <div class="draft-contents">
                    ${lead.email_draft ? `
                    <div id="email-draft-box" class="draft-box" style="font-size:12px; padding:12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; white-space:pre-wrap; line-height:1.4; color:var(--text-secondary); max-height: 250px; overflow-y: auto;">
                        ${escapeHtml(lead.email_draft)}
                    </div>
                    ` : ''}
                    ${lead.whatsapp_draft ? `
                    <div id="whatsapp-draft-box" class="draft-box ${lead.email_draft ? 'hidden' : ''}" style="font-size:12px; padding:12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; white-space:pre-wrap; line-height:1.4; color:var(--text-secondary); max-height: 250px; overflow-y: auto;">
                        ${escapeHtml(lead.whatsapp_draft)}
                    </div>
                    ` : ''}
                    ${lead.sms_draft ? `
                    <div id="sms-draft-box" class="draft-box ${lead.email_draft || lead.whatsapp_draft ? 'hidden' : ''}" style="font-size:12px; padding:12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; white-space:pre-wrap; line-height:1.4; color:var(--text-secondary); max-height: 250px; overflow-y: auto;">
                        ${escapeHtml(lead.sms_draft)}
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}

            <div style="margin-top: 30px;">
                <button class="btn-primary" id="save-scraped-contact-btn" style="width:100%; display:flex; justify-content:center; align-items:center; gap:8px;" ${lead.saved ? 'disabled style="background:var(--border-color); color:var(--text-muted); border:none; cursor:not-allowed;"' : ''}>
                    <i class="fas ${lead.saved ? 'fa-check' : 'fa-save'}"></i>
                    ${lead.saved ? 'Saved to Contacts' : 'Save to Contacts'}
                </button>
            </div>
        `;

        elements.leadDetailContent.innerHTML = detailsHtml;

        // Hook up outreach drafts tab switching
        const tabBtns = elements.leadDetailContent.querySelectorAll('.draft-tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.background = 'var(--bg-primary)';
                    b.style.color = 'var(--text-secondary)';
                });
                btn.classList.add('active');
                btn.style.background = 'var(--bg-card)';
                btn.style.color = 'var(--text-primary)';

                const boxes = elements.leadDetailContent.querySelectorAll('.draft-box');
                boxes.forEach(box => box.classList.add('hidden'));

                const targetId = btn.getAttribute('data-target');
                const targetBox = elements.leadDetailContent.querySelector('#' + targetId);
                if (targetBox) targetBox.classList.remove('hidden');
            });
        });

        // Hook up Save to Contacts button
        const saveBtn = document.getElementById('save-scraped-contact-btn');
        if (saveBtn && !lead.saved) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                try {
                    const res = await fetch('api/leads.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'add_manual',
                            campaign_id: lead.campaign_id || '',
                            company_name: lead.company_name,
                            contact_name: lead.contact_name,
                            postal_address: lead.postal_address,
                            email: lead.email,
                            whatsapp_number: lead.whatsapp,
                            mobile: lead.mobile,
                            source: lead.source || 'scraper',
                            industry: lead.industry,
                            score: lead.score,
                            description: lead.description,
                            reasoning: lead.reasoning,
                            email_draft: lead.email_draft || '',
                            whatsapp_draft: lead.whatsapp_draft || '',
                            sms_draft: lead.sms_draft || ''
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast('Contact successfully saved to Contacts Directory!');
                        lead.saved = true;
                        renderScrapedLeadsList(); // Refresh list to show saved badge and update active details view
                    } else {
                        alert('Failed to save contact: ' + (data.error || 'Unknown error'));
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save to Contacts';
                    }
                } catch (err) {
                    alert('Network error saving contact: ' + err.message);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save to Contacts';
                }
            });
        }
    }

    function renderCrmBoard(leads) {
        state.activeCampaignLeads = leads;
        elements.leadsVerticalList.innerHTML = '';

        const statusFilter = elements.leadFilterStatus ? elements.leadFilterStatus.value : 'ALL';
        const scoreFilter = elements.leadFilterScore ? elements.leadFilterScore.value : 'ALL';

        if (!leads || leads.length === 0) {
            elements.leadsVerticalList.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px; border: 1px dashed var(--border-color); border-radius:6px;">No prospects generated yet.</div>';
            state.activeLead = null;
            renderLeadDetails();
            return;
        }

        const filteredLeads = leads.filter(lead => {
            let leadStatus = (lead.status || 'GENERATED').toUpperCase();
            if (leadStatus === 'GENERATED') leadStatus = 'QUALIFIED';

            const matchStatus = (statusFilter === 'ALL' || leadStatus === statusFilter);
            const matchScore = (scoreFilter === 'ALL' || (lead.score || '').toUpperCase() === scoreFilter);
            return matchStatus && matchScore;
        });

        if (filteredLeads.length === 0) {
            elements.leadsVerticalList.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">No prospects match the filter settings.</div>';
            state.activeLead = null;
            renderLeadDetails();
            return;
        }

        filteredLeads.forEach(lead => {
            const card = document.createElement('div');
            card.className = `lead-card ${state.activeLead && state.activeLead.id == lead.id ? 'active' : ''}`;
            card.setAttribute('data-id', lead.id);
            
            const scoreClass = `score-${(lead.score || 'MEDIUM').toLowerCase()}`;
            
            let leadStatus = (lead.status || 'GENERATED').toUpperCase();
            if (leadStatus === 'GENERATED') leadStatus = 'QUALIFIED';

            let statusClass = 'created';
            if (leadStatus === 'OUTREACHED') statusClass = 'running';
            if (leadStatus === 'CLOSED') statusClass = 'completed';

            card.innerHTML = `
                <div class="lead-card-header">
                    <span class="lead-card-company">${escapeHtml(lead.company_name)}</span>
                    <div style="display:flex; gap:6px;">
                        <span class="lead-score-badge ${scoreClass}">${lead.score || 'MEDIUM'}</span>
                        <span class="campaign-badge badge-${statusClass}">${leadStatus.toLowerCase()}</span>
                    </div>
                </div>
                <div class="lead-card-desc">${escapeHtml(lead.description)}</div>
                <div class="lead-card-meta">
                    <span><i class="fas fa-user"></i> ${escapeHtml(lead.contact_name)}</span>
                    <span>${escapeHtml(lead.industry)}</span>
                </div>
            `;

            card.addEventListener('click', () => {
                selectLead(lead);
            });

            elements.leadsVerticalList.appendChild(card);
        });

        // Auto-select first lead in the filtered list if no lead is active or active lead not in filtered list
        const activeInFiltered = filteredLeads.find(l => state.activeLead && l.id == state.activeLead.id);
        if (!activeInFiltered && filteredLeads.length > 0) {
            selectLead(filteredLeads[0]);
        } else if (state.activeLead) {
            // Keep current lead selected
            const current = filteredLeads.find(l => l.id == state.activeLead.id);
            if (current) selectLead(current);
        }
    }

    function selectLead(lead) {
        state.activeLead = lead;
        const cards = document.querySelectorAll('.lead-card');
        cards.forEach(c => {
            if (c.getAttribute('data-id') == lead.id) {
                c.classList.add('active');
            } else {
                c.classList.remove('active');
            }
        });
        
        renderLeadDetails();
    }

    function renderLeadDetails() {
        const lead = state.activeLead;
        if (!lead) {
            elements.leadDetailContent.classList.add('hidden');
            elements.leadDetailEmpty.classList.remove('hidden');
            return;
        }

        elements.leadDetailEmpty.classList.add('hidden');
        elements.leadDetailContent.classList.remove('hidden');

        const score = lead.score || 'MEDIUM';
        const scoreClass = `score-${score.toLowerCase()}`;

        let detailsHtml = `
            <div class="lead-detail-header">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <div>
                        <h4 class="lead-detail-company" style="margin-bottom:6px;">${escapeHtml(lead.company_name)}</h4>
                        <span class="lead-score-badge ${scoreClass}" style="display:inline-block; width:fit-content; margin-top:2px;">${score} FIT</span>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        ${canEditLeadMetadata(lead) ? `
                            <button class="btn-secondary" id="edit-lead-btn" style="padding:4px 8px; font-size:11px; height:auto; margin:0;"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-secondary" id="delete-lead-btn" style="padding:4px 8px; font-size:11px; height:auto; margin:0; border-color:var(--accent-error); color:var(--accent-error);"><i class="fas fa-trash-alt"></i> Delete</button>
                        ` : ''}
                    </div>
                </div>
                <div class="lead-detail-contacts">
                    <span><i class="fas fa-briefcase"></i> <strong>Industry:</strong> ${escapeHtml(lead.industry)}</span>
                    <span><i class="fas fa-user-tie"></i> <strong>Contact:</strong> ${escapeHtml(lead.contact_name)}</span>
                    <span><i class="fas fa-envelope"></i> <strong>Email:</strong> ${escapeHtml(lead.email)}</span>
                    ${lead.whatsapp ? `<span><i class="fab fa-whatsapp"></i> <strong>WhatsApp:</strong> ${escapeHtml(lead.whatsapp)}</span>` : ''}
                    ${lead.mobile ? `<span><i class="fas fa-phone"></i> <strong>Mobile:</strong> ${escapeHtml(lead.mobile)}</span>` : ''}
                    ${lead.postal_address ? `<span><i class="fas fa-map-marker-alt"></i> <strong>Postal Address:</strong> ${escapeHtml(lead.postal_address)}</span>` : ''}
                    <span><i class="fas fa-database"></i> <strong>Data Source:</strong> ${escapeHtml(lead.source || 'agent')}</span>
                </div>
            </div>
            
            ${lead.description ? `
            <div class="lead-detail-description" style="margin-bottom: 16px; font-size:12px; line-height:1.5; color:var(--text-secondary);">
                <strong>Description / Requirements / Notes:</strong><br>
                ${escapeHtml(lead.description)}
            </div>
            ` : ''}

            <div class="lead-detail-reasoning">
                <strong>SDR AI Qualification Reasoning:</strong><br>
                ${escapeHtml(lead.reasoning)}
            </div>

            <div class="lead-outreach-tabs">
                <button class="outreach-tab-btn ${state.activeLeadTab === 'email' ? 'active' : ''}" id="outreach-email-tab"><i class="fas fa-envelope"></i> Email Draft</button>
                <button class="outreach-tab-btn ${state.activeLeadTab === 'whatsapp' ? 'active' : ''}" id="outreach-whatsapp-tab"><i class="fab fa-whatsapp"></i> WhatsApp Draft</button>
                <button class="outreach-tab-btn ${state.activeLeadTab === 'sms' ? 'active' : ''}" id="outreach-sms-tab"><i class="fas fa-comment-alt"></i> SMS Draft</button>
            </div>

            <textarea class="outreach-content-pane" id="outreach-text-pane" style="width:100%; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); font-family:monospace; font-size:13px; padding:16px; border-radius:var(--border-radius-sm); outline:none; resize:vertical; min-height:220px; line-height:1.5;"></textarea>
            
            <div class="lead-detail-actions">
                <button class="btn-secondary" id="copy-outreach-btn"><i class="fas fa-copy"></i> Copy Draft</button>
                <button class="btn-secondary" id="save-outreach-btn" style="border-color:var(--accent-primary); color:var(--accent-primary);"><i class="fas fa-save"></i> Save Draft</button>
                <button class="btn-secondary" id="simulate-outreach-btn" style="border-color:var(--accent-success); color:var(--accent-success);"><i class="fas fa-paper-plane"></i> Send Outreach</button>
                
                <div style="margin-left:auto; display:flex; gap:8px;">
                    ${lead.status !== 'OUTREACHED' && lead.status !== 'CLOSED' ? `
                        <button class="btn-primary" id="mark-outreached-btn" style="padding: 8px 12px; font-size:12px; margin-top:0;"><i class="fas fa-check"></i> Outreached</button>
                    ` : ''}
                    ${lead.status === 'OUTREACHED' ? `
                        <button class="btn-primary" id="mark-closed-btn" style="padding: 8px 12px; font-size:12px; margin-top:0; background:linear-gradient(135deg, var(--accent-success), var(--accent-secondary));"><i class="fas fa-handshake"></i> Close Lead</button>
                    ` : ''}
                </div>
            </div>
        `;

        elements.leadDetailContent.innerHTML = detailsHtml;

        // Render draft content
        updateOutreachTextPane();

        // Helper to save current active tab's draft in JavaScript memory
        const saveCurrentTabDraftInMemory = () => {
            const pane = document.getElementById('outreach-text-pane');
            if (!pane) return;
            if (state.activeLeadTab === 'email') {
                lead.email_draft = pane.value;
            } else if (state.activeLeadTab === 'whatsapp') {
                lead.whatsapp_draft = pane.value;
            } else if (state.activeLeadTab === 'sms') {
                lead.sms_draft = pane.value;
            }
        };

        // Setup outreach tabs event listeners
        document.getElementById('outreach-email-tab').addEventListener('click', () => {
            saveCurrentTabDraftInMemory();
            state.activeLeadTab = 'email';
            document.getElementById('outreach-email-tab').classList.add('active');
            document.getElementById('outreach-whatsapp-tab').classList.remove('active');
            document.getElementById('outreach-sms-tab').classList.remove('active');
            updateOutreachTextPane();
        });

        document.getElementById('outreach-whatsapp-tab').addEventListener('click', () => {
            saveCurrentTabDraftInMemory();
            state.activeLeadTab = 'whatsapp';
            document.getElementById('outreach-whatsapp-tab').classList.add('active');
            document.getElementById('outreach-email-tab').classList.remove('active');
            document.getElementById('outreach-sms-tab').classList.remove('active');
            updateOutreachTextPane();
        });

        document.getElementById('outreach-sms-tab').addEventListener('click', () => {
            saveCurrentTabDraftInMemory();
            state.activeLeadTab = 'sms';
            document.getElementById('outreach-sms-tab').classList.add('active');
            document.getElementById('outreach-email-tab').classList.remove('active');
            document.getElementById('outreach-whatsapp-tab').classList.remove('active');
            updateOutreachTextPane();
        });

        // Copy button action
        document.getElementById('copy-outreach-btn').addEventListener('click', () => {
            // Read active value from text area to ensure copied content has edits
            const activeText = document.getElementById('outreach-text-pane').value;
            navigator.clipboard.writeText(activeText);
            showToast('Outreach copy copied to clipboard!');
        });

        // Save draft button action
        document.getElementById('save-outreach-btn').addEventListener('click', async () => {
            saveCurrentTabDraftInMemory();

            try {
                const saveBtn = document.getElementById('save-outreach-btn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                const res = await fetch('api/leads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_drafts',
                        lead_id: lead.id,
                        email_draft: lead.email_draft || '',
                        whatsapp_draft: lead.whatsapp_draft || '',
                        sms_draft: lead.sms_draft || ''
                    })
                });

                const data = await res.json();
                if (data.success) {
                    showToast('Outreach drafts saved successfully!');
                } else {
                    alert('Failed to save draft: ' + data.error);
                }
            } catch (err) {
                alert('Error saving draft: ' + err.message);
            } finally {
                const saveBtn = document.getElementById('save-outreach-btn');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Draft';
                }
            }
        });

        // Send outreach (simulated)
        document.getElementById('simulate-outreach-btn').addEventListener('click', async () => {
            saveCurrentTabDraftInMemory();
            const channelName = state.activeLeadTab === 'email' ? 'Email' : (state.activeLeadTab === 'whatsapp' ? 'WhatsApp' : 'SMS');
            const sendBtn = document.getElementById('simulate-outreach-btn');

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const res = await fetch('api/outreach.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lead_id: lead.id,
                        type: state.activeLeadTab // 'email', 'whatsapp', or 'sms'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`✓ ${channelName} outreach sent! ${data.message}`);
                    // Auto transition to OUTREACHED status
                    if (lead.status === 'GENERATED' || lead.status === 'QUALIFIED') {
                        updateLeadStatus(lead.id, 'OUTREACHED');
                    }
                } else {
                    alert(`Send failed: ${data.error || 'Unknown error'}`);
                }
            } catch (err) {
                alert('Network error sending outreach: ' + err.message);
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Outreach';
            }
        });

        // Status update button listeners
        const markOutreachedBtn = document.getElementById('mark-outreached-btn');
        if (markOutreachedBtn) {
            markOutreachedBtn.addEventListener('click', () => {
                updateLeadStatus(lead.id, 'OUTREACHED');
            });
        }

        const markClosedBtn = document.getElementById('mark-closed-btn');
        if (markClosedBtn) {
            markClosedBtn.addEventListener('click', () => {
                updateLeadStatus(lead.id, 'CLOSED');
            });
        }

        // Edit lead listener
        const editLeadBtn = document.getElementById('edit-lead-btn');
        if (editLeadBtn) {
            editLeadBtn.addEventListener('click', () => {
                const modal = document.getElementById('manual-lead-modal');
                const campSelect = document.getElementById('manual-lead-campaign');
                const manualIdInput = document.getElementById('manual-lead-id');
                const saveBtn = document.getElementById('manual-lead-save-btn');
                
                // Populate campaign dropdown
                campSelect.innerHTML = '<option value="">None (General CRM Lead)</option>';
                state.campaigns.forEach(c => {
                    campSelect.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
                });
                campSelect.value = lead.campaign_id || '';
                
                if (manualIdInput) manualIdInput.value = lead.id;
                
                const titleEl = modal.querySelector('.modal-header h3');
                if (titleEl) titleEl.innerHTML = '<i class="fas fa-edit"></i> Edit CRM Contact';
                if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';

                document.getElementById('manual-lead-company').value = lead.company_name || '';
                document.getElementById('manual-lead-contact').value = lead.contact_name || '';
                document.getElementById('manual-lead-email').value = lead.email || '';
                document.getElementById('manual-lead-whatsapp').value = lead.whatsapp || '';
                document.getElementById('manual-lead-mobile').value = lead.mobile || '';
                document.getElementById('manual-lead-postal-address').value = lead.postal_address || '';
                document.getElementById('manual-lead-source').value = lead.source || 'manual';
                document.getElementById('manual-lead-industry').value = lead.industry || '';
                document.getElementById('manual-lead-score').value = lead.score || 'MEDIUM';
                document.getElementById('manual-lead-desc').value = lead.description || '';
                document.getElementById('manual-lead-reasoning').value = lead.reasoning || '';
                
                const errEl = document.getElementById('manual-lead-error');
                if (errEl) errEl.style.display = 'none';
                
                modal.style.display = 'flex';
            });
        }

        // Delete lead listener
        const deleteLeadBtn = document.getElementById('delete-lead-btn');
        if (deleteLeadBtn) {
            deleteLeadBtn.addEventListener('click', async () => {
                if (!confirm(`Are you sure you want to delete the lead for "${lead.company_name}"?`)) {
                    return;
                }
                
                try {
                    deleteLeadBtn.disabled = true;
                    deleteLeadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    
                    const res = await fetch(`api/leads.php?id=${lead.id}`, {
                        method: 'DELETE'
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('Lead deleted successfully.');
                        state.activeLead = null;
                        loadLeadsCrmData();
                    } else {
                        alert('Failed to delete lead: ' + data.error);
                    }
                } catch (err) {
                    alert('Error deleting lead: ' + err.message);
                } finally {
                    deleteLeadBtn.disabled = false;
                    deleteLeadBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
                }
            });
        }
    }

    function updateOutreachTextPane() {
        const pane = document.getElementById('outreach-text-pane');
        if (!pane || !state.activeLead) return;

        if (state.activeLeadTab === 'email') {
            pane.value = state.activeLead.email_draft || '';
        } else if (state.activeLeadTab === 'whatsapp') {
            pane.value = state.activeLead.whatsapp_draft || '';
        } else if (state.activeLeadTab === 'sms') {
            pane.value = state.activeLead.sms_draft || '';
        }
    }

    async function updateLeadStatus(leadId, status) {
        try {
            const res = await fetch('api/leads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lead_id: leadId, status: status })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`Lead marked as ${status.toLowerCase()}`);
                // Refresh board
                if (state.activeCampaignId) {
                    loadCampaignDetails(state.activeCampaignId);
                }
            }
        } catch (err) {
            console.error('Failed to update lead status', err);
        }
    }

    // ----------------------------------------------------
    // UTILITIES
    // ----------------------------------------------------
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function canEditLeadMetadata(lead) {
        if (!lead || !state.currentUser) return false;
        if (state.currentUser.role === 'admin') return true;
        if (lead.campaign_id !== null && lead.campaign_id !== '' && lead.campaign_id !== undefined) {
            return parseInt(lead.campaign_owner_id) === parseInt(state.currentUser.id);
        }
        return parseInt(lead.user_id) === parseInt(state.currentUser.id);
    }

    // Simple JS Markdown parser for preview rendering
    function renderMarkdown(md) {
        if (!md) return '';
        let html = md
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
        
        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Headings
        html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>');
        
        // Blockquotes
        html = html.replace(/^> (.*?)$/gm, '<blockquote>$1</blockquote>');
        
        // Unordered Lists
        // Replace list elements first
        html = html.replace(/^[\-\*]\s+(.*?)$/gm, '<li>$1</li>');
        
        // Wrap adjacent list elements in <ul> tags
        html = html.replace(/(?:<li>.*?<\/li>\s*)+/g, (match) => {
            return `<ul>${match}</ul>`;
        });

        // Code Blocks
        html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // Inline code
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        
        // Paragraphs - Split by double newline, skip blocks
        const blocks = html.split(/\n{2,}/);
        html = blocks.map(p => {
            const trimmed = p.trim();
            if (!trimmed) return '';
            if (trimmed.startsWith('<h') || trimmed.startsWith('<ul') || trimmed.startsWith('<blockquote') || trimmed.startsWith('<pre') || trimmed.startsWith('<li')) {
                return p;
            }
            return `<p>${p.replace(/\n/g, '<br>')}</p>`;
        }).join('\n');
        
        return html;
    }

    // Filter change listeners
    if (elements.leadFilterStatus) {
        elements.leadFilterStatus.addEventListener('change', () => {
            if (state.activeCampaignLeads) {
                renderCrmBoard(state.activeCampaignLeads);
            }
        });
    }
    if (elements.leadFilterScore) {
        elements.leadFilterScore.addEventListener('change', () => {
            if (state.activeCampaignLeads) {
                renderCrmBoard(state.activeCampaignLeads);
            }
        });
    }

    // ----------------------------------------------------
    // AUTH OVERLAY — Login / Register / Logout
    // ----------------------------------------------------
    async function checkAuthStatus() {
        try {
            const res = await fetch('api/auth-status.php');
            const data = await res.json();
            if (data.loggedIn) {
                state.currentUser = data.user;
                showMainApp();
            } else {
                showAuthOverlay();
            }
        } catch (err) {
            console.error('Auth check failed', err);
            showAuthOverlay();
        }
    }

    function showMainApp() {
        elements.authOverlay.style.display = 'none';
        elements.mainApp.style.display = '';
        const u = state.currentUser;
        if (u) {
            // User badge
            elements.userBadge.style.display = 'flex';
            elements.userBadgeName.textContent = u.username;
            elements.userBadgeRole.textContent = u.role.toUpperCase();
            elements.userAvatarLetter.textContent = u.username.charAt(0).toUpperCase();
            // Settings gear and Users tab only for admins
            elements.settingsBtn.style.display = (u.role === 'admin') ? '' : 'none';
            
            const tabBtnDashboard = document.getElementById('tab-btn-dashboard');
            if (tabBtnDashboard) {
                tabBtnDashboard.classList.remove('hidden');
            }

            const tabBtnUsers = document.getElementById('tab-btn-users');
            if (tabBtnUsers) {
                tabBtnUsers.classList.toggle('hidden', u.role !== 'admin');
            }

            const tabBtnPlans = document.getElementById('tab-btn-plans');
            if (tabBtnPlans) {
                tabBtnPlans.classList.toggle('hidden', u.role !== 'admin');
            }

            // Set default tab on login to current page tab
            const initialTab = window.currentPageTab || 'admin-dashboard';
            switchTab(initialTab);
        } else {
            const initialTab = window.currentPageTab || 'campaigns';
            switchTab(initialTab);
        }
        // Load app data
        loadSettings();
        loadCampaigns();
        loadUsageAndProviders();
        // Pre-load users list for admin-only features (owner assignment, campaign sharing)
        if (u && u.role === 'admin') {
            loadUsersListForAdmin();
        }

        // Set up Chat auto-polling (every 8 seconds)
        if (state.chatIntervalId) clearInterval(state.chatIntervalId);
        state.chatIntervalId = setInterval(() => {
            const tabAdminDashboard = document.getElementById('tab-admin-dashboard');
            if (tabAdminDashboard && !tabAdminDashboard.classList.contains('hidden')) {
                loadChatMessages();
            }
        }, 8000);
    }

    function showAuthOverlay() {
        elements.authOverlay.style.display = 'flex';
        elements.mainApp.style.display = 'none';
        if (state.chatIntervalId) {
            clearInterval(state.chatIntervalId);
            state.chatIntervalId = null;
        }
    }

    function applyAppName(name) {
        if (!name) return;
        const letter = name.charAt(0).toUpperCase();
        elements.pageTitle.textContent = name;
        elements.headerAppName.textContent = name;
        elements.headerLogoLetter.textContent = letter;
        elements.authAppName.textContent = name;
        elements.authLogoLetter.textContent = letter;
    }

    // Auth tab switching
    elements.authTabLogin.addEventListener('click', () => {
        elements.authTabLogin.classList.add('active');
        elements.authTabRegister.classList.remove('active');
        elements.loginForm.classList.remove('hidden');
        elements.registerForm.classList.add('hidden');
    });

    elements.authTabRegister.addEventListener('click', () => {
        elements.authTabRegister.classList.add('active');
        elements.authTabLogin.classList.remove('active');
        elements.registerForm.classList.remove('hidden');
        elements.loginForm.classList.add('hidden');
    });

    // State helper for login method
    state.loginMethod = 'password';

    const toggleOtpBtn = document.getElementById('toggle-otp-login');
    const passwordFields = document.getElementById('password-login-fields');
    const otpFields = document.getElementById('otp-login-fields');
    const sendOtpBtn = document.getElementById('send-otp-btn');
    const otpCodeGroup = document.getElementById('otp-code-group');
    const otpDevBanner = document.getElementById('otp-dev-banner');

    if (toggleOtpBtn) {
        toggleOtpBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (state.loginMethod === 'password') {
                state.loginMethod = 'otp';
                toggleOtpBtn.innerText = 'Log in with Username & Password instead';
                passwordFields.classList.add('hidden');
                otpFields.classList.remove('hidden');
                elements.loginUsername.removeAttribute('required');
                elements.loginPassword.removeAttribute('required');
                document.getElementById('login-email').setAttribute('required', 'required');
            } else {
                state.loginMethod = 'password';
                toggleOtpBtn.innerText = 'Log in with Email OTP instead';
                passwordFields.classList.remove('hidden');
                otpFields.classList.add('hidden');
                elements.loginUsername.setAttribute('required', 'required');
                elements.loginPassword.setAttribute('required', 'required');
                document.getElementById('login-email').removeAttribute('required');
            }
        });
    }

    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', async () => {
            const emailInput = document.getElementById('login-email');
            const email = emailInput.value.trim();
            if (!email) {
                alert('Please enter your registered email address first.');
                return;
            }

            sendOtpBtn.disabled = true;
            sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            otpDevBanner.classList.add('hidden');

            try {
                const res = await fetch('api/otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send',
                        email: email
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('OTP sent successfully!');
                    otpCodeGroup.classList.remove('hidden');
                    document.getElementById('login-otp').setAttribute('required', 'required');
                    
                    if (data.dev_otp) {
                        otpDevBanner.innerHTML = `<strong>Development Mode:</strong> OTP generated.<br>Use test OTP code: <strong style="font-size:14px; text-decoration:underline;">${data.dev_otp}</strong>`;
                        otpDevBanner.classList.remove('hidden');
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Failed to send OTP: ' + err.message);
            } finally {
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerHTML = 'Send OTP';
            }
        });
    }

    // Login submit
    elements.loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        elements.loginError.classList.remove('visible');
        elements.loginSubmitBtn.disabled = true;
        elements.loginSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';

        try {
            let res;
            if (state.loginMethod === 'otp') {
                res = await fetch('api/otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'verify',
                        email: document.getElementById('login-email').value.trim(),
                        otp: document.getElementById('login-otp').value.trim()
                    })
                });
            } else {
                res = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: elements.loginUsername.value.trim(),
                        password: elements.loginPassword.value
                    })
                });
            }
            
            const data = await res.json();
            if (data.success) {
                state.currentUser = data.user;
                elements.loginForm.reset();
                if (state.loginMethod === 'otp') {
                    state.loginMethod = 'password';
                    toggleOtpBtn.innerText = 'Log in with Email OTP instead';
                    passwordFields.classList.remove('hidden');
                    otpFields.classList.add('hidden');
                    otpCodeGroup.classList.add('hidden');
                    elements.loginUsername.setAttribute('required', 'required');
                    elements.loginPassword.setAttribute('required', 'required');
                    document.getElementById('login-email').removeAttribute('required');
                }
                window.location.href = '/dashboard';
            } else {
                elements.loginError.textContent = data.error || 'Login failed.';
                elements.loginError.classList.add('visible');
            }
        } catch (err) {
            elements.loginError.textContent = 'Network error. Please try again.';
            elements.loginError.classList.add('visible');
        } finally {
            elements.loginSubmitBtn.disabled = false;
            elements.loginSubmitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    });

    // Register submit
    elements.registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        elements.registerError.classList.remove('visible');
        elements.registerSubmitBtn.disabled = true;
        elements.registerSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';

        try {
            const res = await fetch('api/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: elements.regUsername.value.trim(),
                    password: elements.regPassword.value,
                    full_name: document.getElementById('reg-fullname').value.trim(),
                    email: document.getElementById('reg-email').value.trim(),
                    mobile: document.getElementById('reg-mobile').value.trim(),
                    whatsapp_number: document.getElementById('reg-mobile').value.trim(),
                    role: elements.regRole.value
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Account created! Please sign in.');
                elements.registerForm.reset();
                window.location.href = '/login';
            } else {
                elements.registerError.textContent = data.error || 'Registration failed.';
                elements.registerError.classList.add('visible');
            }
        } catch (err) {
            elements.registerError.textContent = 'Network error. Please try again.';
            elements.registerError.classList.add('visible');
        } finally {
            elements.registerSubmitBtn.disabled = false;
            elements.registerSubmitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        }
    });

    // Logout
    elements.logoutBtn.addEventListener('click', async () => {
        try {
            await fetch('api/logout.php', { method: 'POST' });
        } catch (e) {}
        state.currentUser = null;
        state.campaigns = [];
        state.activeCampaignId = null;
        state.activeCampaign = null;
        state.activeLead = null;
        localStorage.removeItem('active_campaign_id');
        window.location.href = '/login';
    });

    // ----------------------------------------------------
    // SETTINGS TABS + Save btn visibility + Plugins loader
    // ----------------------------------------------------
    const settingsSaveBtn = document.getElementById('settings-save-btn');

    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            const targetPanel = document.getElementById(tab.dataset.panel);
            if (targetPanel) targetPanel.classList.add('active');

            // Hide save button for backup and plugins panels since actions are immediate
            if (settingsSaveBtn) {
                if (tab.dataset.panel === 'backup-panel' || tab.dataset.panel === 'plugins-panel') {
                    settingsSaveBtn.style.display = 'none';
                    
                    if (tab.dataset.panel === 'backup-panel') {
                        // Reset backup sub-tabs to default active (Backup)
                        document.querySelectorAll('.backup-sub-tab').forEach(st => {
                            if (st.dataset.subpanel === 'subpanel-backup') {
                                st.classList.add('active');
                                st.style.borderBottom = '2px solid var(--accent-primary)';
                                st.style.color = 'var(--text-primary)';
                            } else {
                                st.classList.remove('active');
                                st.style.borderBottom = '2px solid transparent';
                                st.style.color = 'var(--text-secondary)';
                            }
                        });
                        document.querySelectorAll('.backup-subpanel-content').forEach(sp => {
                            sp.style.display = (sp.id === 'subpanel-backup') ? 'block' : 'none';
                        });
                    } else if (tab.dataset.panel === 'plugins-panel') {
                        loadPluginsList();
                    }
                } else {
                    settingsSaveBtn.style.display = '';
                }
            }
        });
    });

    // ----------------------------------------------------
    // BACKUP SUB-TABS INTERACTIVE SWITCHING
    // ----------------------------------------------------
    document.querySelectorAll('.backup-sub-tab').forEach(subTab => {
        subTab.addEventListener('click', () => {
            document.querySelectorAll('.backup-sub-tab').forEach(t => {
                t.classList.remove('active');
                t.style.borderBottom = '2px solid transparent';
                t.style.color = 'var(--text-secondary)';
            });
            document.querySelectorAll('.backup-subpanel-content').forEach(p => {
                p.style.display = 'none';
            });

            subTab.classList.add('active');
            subTab.style.borderBottom = '2px solid var(--accent-primary)';
            subTab.style.color = 'var(--text-primary)';

            const targetSubpanel = document.getElementById(subTab.dataset.subpanel);
            if (targetSubpanel) {
                targetSubpanel.style.display = 'block';
            }
        });
    });

    // ----------------------------------------------------
    // DATABASE BACKUP & RESTORE HANDLERS
    // ----------------------------------------------------
    const btnDownloadBackup = document.getElementById('btn-download-backup');
    if (btnDownloadBackup) {
        btnDownloadBackup.addEventListener('click', () => {
            window.location.href = 'api/backup.php';
        });
    }

    const btnRestoreBackup = document.getElementById('btn-restore-backup');
    const restoreDbFile = document.getElementById('restore-db-file');
    if (btnRestoreBackup && restoreDbFile) {
        btnRestoreBackup.addEventListener('click', async () => {
            const file = restoreDbFile.files[0];
            if (!file) {
                alert('Please select a valid database backup file (.sqlite, .db, or .json) first.');
                return;
            }

            const confirmRestore = confirm(
                'Are you sure you want to restore the database?\n\n' +
                'WARNING: This will permanently overwrite all current data (users, campaigns, leads, logs, settings) and log you out. This action cannot be undone.'
            );
            if (!confirmRestore) {
                return;
            }

            // Disable button and show spinner
            btnRestoreBackup.disabled = true;
            btnRestoreBackup.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring database...';

            const formData = new FormData();
            formData.append('backup_file', file);

            try {
                const res = await fetch('api/restore.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Database restored successfully! Reloading...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Restore failed: ' + (data.error || 'Unknown error occurred.'));
                    btnRestoreBackup.disabled = false;
                    btnRestoreBackup.innerHTML = '<i class="fas fa-file-upload"></i> Restore Backup';
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
                btnRestoreBackup.disabled = false;
                btnRestoreBackup.innerHTML = '<i class="fas fa-file-upload"></i> Restore Backup';
            }
        });
    }

    // ----------------------------------------------------
    // SYSTEM RESET & DEMO DATA HANDLERS
    // ----------------------------------------------------
    const btnLoadDemo = document.getElementById('btn-load-demo');
    if (btnLoadDemo) {
        btnLoadDemo.addEventListener('click', async () => {
            const confirmLoad = confirm(
                'Are you sure you want to load demo data?\n\n' +
                'WARNING: This will clear all existing campaigns, leads, logs, public chats, and notifications, and replace them with fresh test data. Your user account session will remain active.'
            );
            if (!confirmLoad) {
                return;
            }

            btnLoadDemo.disabled = true;
            btnLoadDemo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading demo...';

            try {
                const res = await fetch('api/load-demo.php', { method: 'POST' });
                const data = await res.json();

                if (data.success) {
                    showToast('Demo data loaded successfully! Reloading...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Failed to load demo data: ' + (data.error || 'Unknown error.'));
                    btnLoadDemo.disabled = false;
                    btnLoadDemo.innerHTML = '<i class="fas fa-magic"></i> Load Demo Data';
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
                btnLoadDemo.disabled = false;
                btnLoadDemo.innerHTML = '<i class="fas fa-magic"></i> Load Demo Data';
            }
        });
    }

    const btnResetApp = document.getElementById('btn-reset-app');
    if (btnResetApp) {
        btnResetApp.addEventListener('click', async () => {
            const confirmReset = confirm(
                'Are you sure you want to reset the application?\n\n' +
                'WARNING: This will completely delete the database and wipe all campaigns, leads, chat history, notifications, and logs. All custom settings will be reset to defaults and you will be logged out. This action is permanent and cannot be undone.'
            );
            if (!confirmReset) {
                return;
            }

            btnResetApp.disabled = true;
            btnResetApp.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting system...';

            try {
                const res = await fetch('api/reset-app.php', { method: 'POST' });
                const data = await res.json();

                if (data.success) {
                    showToast('Application reset successfully! Redirecting...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Reset failed: ' + (data.error || 'Unknown error.'));
                    btnResetApp.disabled = false;
                    btnResetApp.innerHTML = '<i class="fas fa-trash-alt"></i> Reset Application';
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
                btnResetApp.disabled = false;
                btnResetApp.innerHTML = '<i class="fas fa-trash-alt"></i> Reset Application';
            }
        });
    }

    // ----------------------------------------------------
    // USER MANAGEMENT
    // ----------------------------------------------------
    // USER ADMINISTRATION & SYSTEM LOGS
    // ----------------------------------------------------
    let pendingDeleteUserId = null;

    async function loadUsers() {
        const tbody = document.getElementById('admin-users-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="14" style="text-align:center; padding:20px; color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading users...</td></tr>';
        
        try {
            const res = await fetch('api/users.php');
            const data = await res.json();
            if (data.success) {
                tbody.innerHTML = '';
                if (data.users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="14" style="text-align:center; padding:20px; color:var(--text-muted);">No users found.</td></tr>';
                    return;
                }

                data.users.forEach(u => {
                    const isSelf = state.currentUser && (u.id === state.currentUser.id);
                    const tr = document.createElement('tr');
                    if (isSelf) tr.classList.add('self-row');

                    const campaignsUsage = u.campaign_usage || 0;
                    const campaignsLimit = u.campaign_limit == -1 ? 'Unlimited' : u.campaign_limit;
                    const campaignsRemaining = u.campaign_limit == -1 ? 'unlimited' : `${u.campaign_limit - campaignsUsage} left`;

                    const leadsUsage = u.lead_usage || 0;
                    const leadsLimit = u.lead_limit == -1 ? 'Unlimited' : u.lead_limit;
                    const leadsRemaining = u.lead_limit == -1 ? 'unlimited' : `${u.lead_limit - leadsUsage} left`;

                    const llmUsage = u.llm_usage || 0;
                    const llmLimit = u.llm_limit == -1 ? 'Unlimited' : (u.llm_limit !== undefined ? u.llm_limit : 100);
                    const llmRemaining = u.llm_limit == -1 ? 'unlimited' : `${llmLimit - llmUsage} left`;

                    const emailUsage = u.email_usage || 0;
                    const emailLimit = u.email_limit == -1 ? 'Unlimited' : (u.email_limit !== undefined ? u.email_limit : 100);
                    const emailRemaining = u.email_limit == -1 ? 'unlimited' : `${emailLimit - emailUsage} left`;

                    const whatsappUsage = u.whatsapp_usage || 0;
                    const whatsappLimit = u.whatsapp_limit == -1 ? 'Unlimited' : (u.whatsapp_limit !== undefined ? u.whatsapp_limit : 100);
                    const whatsappRemaining = u.whatsapp_limit == -1 ? 'unlimited' : `${whatsappLimit - whatsappUsage} left`;
                    const smsUsage = u.sms_usage || 0;
                    const smsLimit = u.sms_limit == -1 ? 'Unlimited' : (u.sms_limit !== undefined ? u.sms_limit : 100);
                    const smsRemaining = u.sms_limit == -1 ? 'unlimited' : `${smsLimit - smsUsage} left`;

                    const expiryText = u.plan_expires_at ? safeParseDate(u.plan_expires_at).toLocaleString() : 'Never';
                    const isExpired = u.plan_expires_at ? (Date.now() > safeParseDate(u.plan_expires_at).getTime()) : false;
                    const expiryColor = isExpired ? 'var(--accent-error)' : 'var(--text-primary)';
                    const expiryWeight = isExpired ? '600' : '400';

                    const hasCrossedLimit = (u.campaign_limit !== -1 && campaignsUsage >= u.campaign_limit) ||
                                            (u.lead_limit !== -1 && leadsUsage >= u.lead_limit) ||
                                            (u.llm_limit !== -1 && llmUsage >= u.llm_limit) ||
                                            (u.email_limit !== -1 && emailUsage >= u.email_limit) ||
                                            (u.whatsapp_limit !== -1 && whatsappUsage >= u.whatsapp_limit) ||
                                            (u.sms_limit !== -1 && smsUsage >= u.sms_limit);

                    tr.innerHTML = `
                        <td style="padding:12px 10px; font-weight:600;">${escapeHtml(u.username)} ${isSelf ? '<span style="font-size:10px; color:var(--accent-primary); font-weight:400;">(You)</span>' : ''}</td>
                        <td style="padding:12px 10px;">${escapeHtml(u.full_name || '—')}</td>
                        <td style="padding:12px 10px;">${escapeHtml(u.email || '—')}</td>
                        <td style="padding:12px 10px;">${escapeHtml(u.mobile || u.whatsapp_number || '—')}</td>
                        <td style="padding:12px 10px;"><span class="user-role-badge role-${u.role.toLowerCase()}">${u.role}</span></td>
                        <td style="padding:12px 10px; font-weight:600; color:var(--accent-primary);">${escapeHtml(u.plan_name || 'Default Plan')}</td>
                        <td style="padding:12px 10px; color:${expiryColor}; font-weight:${expiryWeight};">${expiryText}</td>
                        <td style="padding:12px 10px;">
                            ${campaignsUsage} / ${campaignsLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${campaignsRemaining})</span>
                        </td>
                        <td style="padding:12px 10px;">
                            ${leadsUsage} / ${leadsLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${leadsRemaining})</span>
                        </td>
                        <td style="padding:12px 10px;">
                            ${llmUsage} / ${llmLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${llmRemaining})</span>
                        </td>
                        <td style="padding:12px 10px;">
                            ${emailUsage} / ${emailLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${emailRemaining})</span>
                        </td>
                        <td style="padding:12px 10px;">
                            ${whatsappUsage} / ${whatsappLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${whatsappRemaining})</span>
                        </td>
                        <td style="padding:12px 10px;">
                            ${smsUsage} / ${smsLimit}
                            <br><span style="font-size:10px; color:var(--text-secondary); font-weight:500;">(${smsRemaining})</span>
                        </td>
                        <td style="padding:12px 10px; text-align:right;">
                            <div style="display:inline-flex; gap:6px;">
                                <button class="user-action-btn reset-plan-btn" data-id="${u.id}" title="Reset/Renew Plan" style="border-color:var(--accent-primary); color:var(--accent-primary);"><i class="fas fa-history"></i></button>
                                ${hasCrossedLimit ? `<button class="user-action-btn reset-usage-btn" data-id="${u.id}" title="Reset Usage" style="border-color:var(--accent-warning); color:var(--accent-warning);"><i class="fas fa-undo"></i></button>` : ''}
                                <button class="user-action-btn edit-btn" data-id="${u.id}" title="Edit User"><i class="fas fa-edit"></i></button>
                                ${isSelf ? '' : `<button class="user-action-btn delete-btn" data-id="${u.id}" title="Delete User"><i class="fas fa-trash-alt"></i></button>`}
                            </div>
                        </td>
                    `;

                    // Bind edit listener
                    tr.querySelector('.edit-btn').addEventListener('click', () => {
                        openEditUserModal(u);
                    });

                    // Bind reset usage listener
                    const resetBtn = tr.querySelector('.reset-usage-btn');
                    if (resetBtn) {
                        resetBtn.addEventListener('click', async () => {
                            if (confirm(`Are you sure you want to reset all service usage for user '${u.username}'?`)) {
                                try {
                                    const res = await fetch('api/reset-usage.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ user_id: u.id })
                                    });
                                    const data = await res.json();
                                    if (data.success) {
                                        showToast('User usage reset successfully.');
                                        loadUsers();
                                    } else {
                                        showToast(data.error || 'Failed to reset usage.', 'error');
                                    }
                                } catch (e) {
                                    console.error(e);
                                    showToast('Network error while resetting usage.', 'error');
                                }
                            }
                        });
                    }

                    // Bind reset plan listener
                    const resetPlanBtn = tr.querySelector('.reset-plan-btn');
                    if (resetPlanBtn) {
                        resetPlanBtn.addEventListener('click', async () => {
                            if (confirm(`Are you sure you want to reset/renew the plan expiration for user '${u.username}'?`)) {
                                try {
                                    const res = await fetch('api/reset-plan.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ user_id: u.id })
                                    });
                                    const data = await res.json();
                                    if (data.success) {
                                        showToast('User plan reset/renewed successfully.');
                                        loadUsers();
                                    } else {
                                        showToast(data.error || 'Failed to reset plan.', 'error');
                                    }
                                } catch (e) {
                                    console.error(e);
                                    showToast('Network error resetting plan.', 'error');
                                }
                            }
                        });
                    }

                    // Bind delete listener if not self
                    const delBtn = tr.querySelector('.delete-btn');
                    if (delBtn) {
                        delBtn.addEventListener('click', () => {
                            openDeleteUserModal(u.id, u.username);
                        });
                    }

                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="14" style="text-align:center; padding:20px; color:var(--accent-error);">Failed to load users: ${data.error}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="14" style="text-align:center; padding:20px; color:var(--accent-error);">Network error loading users.</td></tr>`;
        }
    }

    async function loadActivityLogs() {
        const container = document.getElementById('admin-activity-logs');
        if (!container) return;

        container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading activities...</div>';

        try {
            const res = await fetch('api/activity.php');
            const data = await res.json();
            if (data.success) {
                container.innerHTML = '';
                if (data.logs.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic;">No user activities logged yet.</div>';
                    return;
                }

                data.logs.forEach(log => {
                    const time = safeParseDate(log.created_at).toLocaleString();
                    const item = document.createElement('div');
                    item.className = 'activity-log-item';
                    item.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="activity-log-user"><i class="fas fa-user-circle"></i> ${escapeHtml(log.username || 'System/Guest')}</span>
                            <span class="activity-log-time">${time}</span>
                        </div>
                        <div class="activity-log-action">${escapeHtml(log.action)}</div>
                        <div class="activity-log-desc">${escapeHtml(log.details)}</div>
                    `;
                    container.appendChild(item);
                });
            } else {
                container.innerHTML = `<div style="text-align:center; padding:20px; color:var(--accent-error);">Error loading logs: ${data.error}</div>`;
            }
        } catch (err) {
            container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--accent-error);">Failed to load activity logs.</div>';
        }
    }

    async function loadDashboardStats() {
        const usersEl = document.getElementById('stat-total-users');
        const campaignsEl = document.getElementById('stat-total-campaigns');
        const leadsEl = document.getElementById('stat-total-leads');
        if (!usersEl || !campaignsEl || !leadsEl) return;

        try {
            const res = await fetch('api/stats.php');
            const data = await res.json();
            if (data.success) {
                usersEl.textContent = data.stats.users;
                campaignsEl.textContent = data.stats.campaigns;
                leadsEl.textContent = data.stats.leads;
            }
        } catch (e) {
            console.error('Failed to load dashboard stats', e);
        }
    }

    async function loadDashboardActivityLogs() {
        const container = document.getElementById('dashboard-activity-logs');
        if (!container) return;

        container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading activities...</div>';

        try {
            const res = await fetch('api/activity.php');
            const data = await res.json();
            if (data.success) {
                container.innerHTML = '';
                if (data.logs.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic;">No user activities logged yet.</div>';
                    return;
                }

                const logsToShow = data.logs.slice(0, 25);
                logsToShow.forEach(log => {
                    const time = safeParseDate(log.created_at).toLocaleString();
                    const item = document.createElement('div');
                    item.className = 'activity-log-item';
                    item.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="activity-log-user"><i class="fas fa-user-circle"></i> ${escapeHtml(log.username || 'System/Guest')}</span>
                            <span class="activity-log-time">${time}</span>
                        </div>
                        <div class="activity-log-action">${escapeHtml(log.action)}</div>
                        <div class="activity-log-desc">${escapeHtml(log.details)}</div>
                    `;
                    container.appendChild(item);
                });
            } else {
                container.innerHTML = `<div style="text-align:center; padding:20px; color:var(--accent-error);">Error loading logs: ${data.error}</div>`;
            }
        } catch (err) {
            container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--accent-error);">Failed to load activity logs.</div>';
        }
    }

    async function loadDashboardPlans() {
        const tbody = document.getElementById('dashboard-plans-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:10px; color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        try {
            const res = await fetch('api/plans.php');
            const data = await res.json();
            if (data.success) {
                tbody.innerHTML = '';
                if (data.plans.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:10px; color:var(--text-muted);">No plans.</td></tr>';
                    return;
                }
                data.plans.forEach(p => {
                    const cLimit = p.campaign_limit == -1 ? '∞' : p.campaign_limit;
                    const lLimit = p.lead_limit == -1 ? '∞' : p.lead_limit;
                    const llmLimit = p.llm_limit == -1 ? '∞' : (p.llm_limit !== undefined ? p.llm_limit : 100);
                    const emailLimit = p.email_limit == -1 ? '∞' : (p.email_limit !== undefined ? p.email_limit : 100);
                    const whatsappLimit = p.whatsapp_limit == -1 ? '∞' : (p.whatsapp_limit !== undefined ? p.whatsapp_limit : 100);
                    const smsLimit = p.sms_limit == -1 ? '∞' : (p.sms_limit !== undefined ? p.sms_limit : 100);

                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border-color)';
                    tr.innerHTML = `
                        <td style="padding:8px 4px; font-weight:600; color:var(--text-primary);">${escapeHtml(p.name)}</td>
                        <td style="padding:8px 4px;">${cLimit}</td>
                        <td style="padding:8px 4px;">${lLimit}</td>
                        <td style="padding:8px 4px;">${llmLimit}</td>
                        <td style="padding:8px 4px;">${emailLimit}</td>
                        <td style="padding:8px 4px;">${whatsappLimit}</td>
                        <td style="padding:8px 4px;">${smsLimit}</td>
                        <td style="padding:8px 4px;"><span class="user-role-badge role-user" style="background:rgba(var(--accent-primary-rgb),0.1); color:var(--accent-primary); border:none; padding:2px 6px; font-size:10px;">${p.user_count}</span></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:10px; color:var(--accent-error);">Error</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:10px; color:var(--accent-error);">Failed</td></tr>`;
        }
    }

    // Modal declarations & close/opens
    const userModal = document.getElementById('user-modal');
    const userEditForm = document.getElementById('user-edit-form');
    const editUserId = document.getElementById('edit-user-id');
    const editUsername = document.getElementById('edit-username');
    const editPassword = document.getElementById('edit-password');
    const editPasswordHint = document.getElementById('edit-password-hint');
    const editFullname = document.getElementById('edit-fullname');
    const editEmail = document.getElementById('edit-email');
    const editMobile = document.getElementById('edit-mobile');
    const editWhatsapp = document.getElementById('edit-whatsapp');
    const editRole = document.getElementById('edit-role');
    const editUserPlan = document.getElementById('edit-user-plan');
    const userModalError = document.getElementById('user-modal-error');
    const userModalSaveBtn = document.getElementById('user-modal-save-btn');

    const adminAddUserBtn = document.getElementById('admin-add-user-btn');
    if (adminAddUserBtn) {
        adminAddUserBtn.addEventListener('click', () => {
            document.getElementById('user-modal-title').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
            editUserId.value = '';
            editUsername.value = '';
            editUsername.removeAttribute('readonly');
            editPassword.value = '';
            editPassword.setAttribute('required', 'required');
            if (editPasswordHint) editPasswordHint.style.display = 'none';
            editFullname.value = '';
            editEmail.value = '';
            editMobile.value = '';
            editWhatsapp.value = '';
            editRole.value = 'user';
            if (editUserPlan) {
                editUserPlan.value = state.plans && state.plans[0] ? state.plans[0].id : '';
            }
            userModalError.style.display = 'none';
            userModal.style.display = 'flex';
        });
    }

    function openEditUserModal(user) {
        document.getElementById('user-modal-title').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
        editUserId.value = user.id;
        editUsername.value = user.username;
        editUsername.setAttribute('readonly', 'readonly');
        editPassword.value = '';
        editPassword.removeAttribute('required');
        if (editPasswordHint) editPasswordHint.style.display = '';
        editFullname.value = user.full_name || '';
        editEmail.value = user.email || '';
        editMobile.value = user.mobile || '';
        editWhatsapp.value = user.whatsapp_number || '';
        editRole.value = user.role;
        if (editUserPlan) {
            editUserPlan.value = user.plan_id || '';
        }
        userModalError.style.display = 'none';
        userModal.style.display = 'flex';
    }

    if (userEditForm) {
        userEditForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            userModalError.style.display = 'none';
            userModalSaveBtn.disabled = true;
            userModalSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const id = editUserId.value;
            const isEdit = !!id;

            const payload = {
                username: editUsername.value.trim(),
                full_name: editFullname.value.trim(),
                email: editEmail.value.trim(),
                mobile: editMobile.value.trim(),
                whatsapp_number: editWhatsapp.value.trim(),
                role: editRole.value,
                plan_id: editUserPlan ? parseInt(editUserPlan.value) : null
            };

            if (editPassword.value) {
                payload.password = editPassword.value;
            }

            try {
                const url = isEdit ? `api/users.php?id=${id}` : 'api/users.php';
                const method = isEdit ? 'PUT' : 'POST';

                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(isEdit ? 'User updated successfully.' : 'User created successfully.');
                    userModal.style.display = 'none';
                    loadUsers();
                    loadActivityLogs();
                } else {
                    userModalError.textContent = data.error || 'Operation failed.';
                    userModalError.style.display = 'block';
                }
            } catch (err) {
                userModalError.textContent = 'Network error saving user details.';
                userModalError.style.display = 'block';
            } finally {
                userModalSaveBtn.disabled = false;
                userModalSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save User';
            }
        });
    }

    // Modal Close event assignments
    ['user-modal-close', 'user-modal-cancel-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            userModal.style.display = 'none';
        });
    });

    // Delete user logic
    function openDeleteUserModal(id, username) {
        pendingDeleteUserId = id;
        document.getElementById('delete-user-name').textContent = username;
        document.getElementById('delete-user-modal').style.display = 'flex';
    }

    ['delete-user-modal-close', 'delete-user-cancel-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            document.getElementById('delete-user-modal').style.display = 'none';
            pendingDeleteUserId = null;
        });
    });

    const deleteConfirmBtn = document.getElementById('delete-user-confirm-btn');
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', async () => {
            if (!pendingDeleteUserId) return;
            deleteConfirmBtn.disabled = true;
            deleteConfirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            try {
                const res = await fetch(`api/users.php?id=${pendingDeleteUserId}`, { method: 'DELETE' });
                const data = await res.json();
                document.getElementById('delete-user-modal').style.display = 'none';
                if (data.success) {
                    showToast('User deleted successfully.');
                    loadUsers();
                    loadActivityLogs();
                } else {
                    alert('Delete failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            } finally {
                deleteConfirmBtn.disabled = false;
                deleteConfirmBtn.innerHTML = '<i class="fas fa-trash"></i> Yes, Delete';
                pendingDeleteUserId = null;
            }
        });
    }

    // ----------------------------------------------------
    // MY PROFILE SELF-EDIT MODAL
    // ----------------------------------------------------
    const profileModal = document.getElementById('profile-modal');
    const profileEditForm = document.getElementById('profile-edit-form');
    const profileUsername = document.getElementById('profile-username');
    const profileFullname = document.getElementById('profile-fullname');
    const profileEmail = document.getElementById('profile-email');
    const profileMobile = document.getElementById('profile-mobile');
    const profileWhatsapp = document.getElementById('profile-whatsapp');
    const profilePassword = document.getElementById('profile-password');
    const profileModalError = document.getElementById('profile-modal-error');
    const profileModalSaveBtn = document.getElementById('profile-modal-save-btn');
    const profileBtn = document.getElementById('profile-btn');

    if (profileBtn) {
        profileBtn.addEventListener('click', async () => {
            profileModalError.style.display = 'none';
            profilePassword.value = '';
            
            try {
                const res = await fetch('api/profile.php');
                const data = await res.json();
                if (data.success) {
                    profileUsername.value = data.profile.username;
                    profileFullname.value = data.profile.full_name || '';
                    profileEmail.value = data.profile.email || '';
                    profileMobile.value = data.profile.mobile || '';
                    profileWhatsapp.value = data.profile.whatsapp_number || '';
                    
                    profileModal.style.display = 'flex';
                } else {
                    alert('Failed to load profile details: ' + data.error);
                }
            } catch (err) {
                alert('Failed to fetch profile details.');
            }
        });
    }

    ['profile-modal-close', 'profile-modal-cancel-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            profileModal.style.display = 'none';
        });
    });

    if (profileEditForm) {
        profileEditForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            profileModalError.style.display = 'none';
            profileModalSaveBtn.disabled = true;
            profileModalSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const payload = {
                username: profileUsername.value.trim(),
                full_name: profileFullname.value.trim(),
                email: profileEmail.value.trim(),
                mobile: profileMobile.value.trim(),
                whatsapp_number: profileWhatsapp.value.trim()
            };

            if (profilePassword.value) {
                payload.password = profilePassword.value;
            }

            try {
                const res = await fetch('api/profile.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Profile updated successfully!');
                    profileModal.style.display = 'none';
                    state.currentUser.username = payload.username;
                    elements.userBadgeName.textContent = payload.username;
                    elements.userAvatarLetter.textContent = payload.username.charAt(0).toUpperCase();
                } else {
                    profileModalError.textContent = data.error || 'Failed to update profile.';
                    profileModalError.style.display = 'block';
                }
            } catch (err) {
                profileModalError.textContent = 'Network error saving profile.';
                profileModalError.style.display = 'block';
            } finally {
                profileModalSaveBtn.disabled = false;
                profileModalSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        });
    }

    // ----------------------------------------------------
    // MANUAL LEAD ADDITION FLOW
    // ----------------------------------------------------
    const manualLeadModal = document.getElementById('manual-lead-modal');
    const manualLeadForm = document.getElementById('manual-lead-form');
    const manualLeadCampaign = document.getElementById('manual-lead-campaign');
    const manualLeadCompany = document.getElementById('manual-lead-company');
    const manualLeadContact = document.getElementById('manual-lead-contact');
    const manualLeadEmail = document.getElementById('manual-lead-email');
    const manualLeadWhatsapp = document.getElementById('manual-lead-whatsapp');
    const manualLeadMobile = document.getElementById('manual-lead-mobile');
    const manualLeadPostalAddress = document.getElementById('manual-lead-postal-address');
    const manualLeadSource = document.getElementById('manual-lead-source');
    const manualLeadIndustry = document.getElementById('manual-lead-industry');
    const manualLeadScore = document.getElementById('manual-lead-score');
    const manualLeadStatus = document.getElementById('manual-lead-status');
    const manualLeadDesc = document.getElementById('manual-lead-desc');
    const manualLeadReasoning = document.getElementById('manual-lead-reasoning');
    const manualLeadError = document.getElementById('manual-lead-error');
    const manualLeadSaveBtn = document.getElementById('manual-lead-save-btn');
    const addManualLeadBtn = document.getElementById('add-manual-lead-btn');
    // Draft textarea refs
    const manualLeadEmailDraft = document.getElementById('manual-lead-email-draft');
    const manualLeadWhatsappDraft = document.getElementById('manual-lead-whatsapp-draft');
    const manualLeadSmsDraft = document.getElementById('manual-lead-sms-draft');
    const manualLeadCallsDraft = document.getElementById('manual-lead-calls-draft');

    // Draft tab switching for modal
    function setupDraftModalTabs() {
        const tabs = document.querySelectorAll('.draft-modal-tab');
        const textareas = {
            email: manualLeadEmailDraft,
            whatsapp: manualLeadWhatsappDraft,
            sms: manualLeadSmsDraft,
            calls: manualLeadCallsDraft
        };
        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                tabs.forEach(t => {
                    t.style.background = 'var(--bg-primary)';
                    t.style.color = 'var(--text-secondary)';
                    t.style.borderColor = 'var(--border-color)';
                });
                btn.style.background = 'rgba(99,102,241,0.15)';
                btn.style.color = 'var(--accent-primary)';
                btn.style.borderColor = 'var(--accent-primary)';
                const tab = btn.getAttribute('data-tab');
                Object.values(textareas).forEach(ta => { if (ta) ta.style.display = 'none'; });
                if (textareas[tab]) textareas[tab].style.display = '';
            });
        });
    }
    setupDraftModalTabs();

    // Collapsible drafts section toggle
    const draftsToggle = document.getElementById('manual-lead-drafts-toggle');
    const draftsBody = document.getElementById('manual-lead-drafts-body');
    const draftsChevron = document.getElementById('manual-lead-drafts-chevron');
    let draftsOpen = false;
    if (draftsToggle && draftsBody) {
        draftsToggle.addEventListener('click', () => {
            draftsOpen = !draftsOpen;
            draftsBody.style.display = draftsOpen ? 'flex' : 'none';
            if (draftsChevron) draftsChevron.style.transform = draftsOpen ? 'rotate(180deg)' : '';
        });
    }

    if (addManualLeadBtn) {
        addManualLeadBtn.addEventListener('click', () => {
            // Populate selector option list contextually
            manualLeadCampaign.innerHTML = '<option value="">None (General CRM Lead)</option>';
            state.campaigns.forEach(c => {
                manualLeadCampaign.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
            });
            manualLeadCampaign.value = state.activeCampaignId || '';
            
            if (elements.manualLeadId) elements.manualLeadId.value = '';
            const modalHeaderTitle = document.querySelector('#manual-lead-modal .modal-header h3');
            if (modalHeaderTitle) modalHeaderTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add Lead Manually';
            if (manualLeadSaveBtn) manualLeadSaveBtn.innerHTML = '<i class="fas fa-plus"></i> Add Lead';

            manualLeadCompany.value = '';
            manualLeadContact.value = '';
            manualLeadEmail.value = '';
            manualLeadWhatsapp.value = '';
            manualLeadMobile.value = '';
            if (manualLeadPostalAddress) manualLeadPostalAddress.value = '';
            manualLeadSource.value = 'manual';
            manualLeadIndustry.value = '';
            manualLeadScore.value = 'MEDIUM';
            if (manualLeadStatus) manualLeadStatus.value = '';
            manualLeadDesc.value = '';
            manualLeadReasoning.value = 'Manually added';
            if (manualLeadEmailDraft) manualLeadEmailDraft.value = '';
            if (manualLeadWhatsappDraft) manualLeadWhatsappDraft.value = '';
            if (manualLeadSmsDraft) manualLeadSmsDraft.value = '';
            if (manualLeadCallsDraft) manualLeadCallsDraft.value = '';
            manualLeadError.style.display = 'none';
            
            manualLeadModal.style.display = 'flex';
        });
    }

    ['manual-lead-modal-close', 'manual-lead-cancel-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            manualLeadModal.style.display = 'none';
        });
    });

    if (manualLeadForm) {
        manualLeadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            manualLeadError.style.display = 'none';
            manualLeadSaveBtn.disabled = true;
            manualLeadSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const isEdit = !!(elements.manualLeadId && elements.manualLeadId.value);
            const payload = {
                action: isEdit ? 'edit_manual' : 'add_manual',
                campaign_id: manualLeadCampaign.value || '',
                company_name: manualLeadCompany.value.trim(),
                contact_name: manualLeadContact.value.trim(),
                email: manualLeadEmail.value.trim(),
                whatsapp_number: manualLeadWhatsapp.value.trim(),
                mobile: manualLeadMobile.value.trim(),
                postal_address: manualLeadPostalAddress ? manualLeadPostalAddress.value.trim() : '',
                source: manualLeadSource.value.trim(),
                industry: manualLeadIndustry.value.trim(),
                score: manualLeadScore.value,
                status: manualLeadStatus ? manualLeadStatus.value : '',
                description: manualLeadDesc.value.trim(),
                reasoning: manualLeadReasoning.value.trim(),
                email_draft: manualLeadEmailDraft ? manualLeadEmailDraft.value.trim() : '',
                whatsapp_draft: manualLeadWhatsappDraft ? manualLeadWhatsappDraft.value.trim() : '',
                sms_draft: manualLeadSmsDraft ? manualLeadSmsDraft.value.trim() : '',
                calls_draft: manualLeadCallsDraft ? manualLeadCallsDraft.value.trim() : ''
            };
            if (isEdit) {
                payload.lead_id = parseInt(elements.manualLeadId.value);
                // Include owner_user_id if admin selects an owner
                const ownerSelect = document.getElementById('manual-lead-owner');
                if (ownerSelect && ownerSelect.value !== '') {
                    payload.owner_user_id = ownerSelect.value;
                }
            }

            try {
                const res = await fetch('api/leads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(isEdit ? 'Lead updated successfully.' : 'Manual lead added successfully.');
                    manualLeadModal.style.display = 'none';
                    loadLeadsCrmData();
                    if (typeof loadContactsDirectory === 'function') {
                        loadContactsDirectory();
                    }
                } else {
                    manualLeadError.textContent = data.error || 'Failed to save lead.';
                    manualLeadError.style.display = 'block';
                }
            } catch (err) {
                manualLeadError.textContent = 'Network error saving manual lead details.';
                manualLeadError.style.display = 'block';
            } finally {
                manualLeadSaveBtn.disabled = false;
                manualLeadSaveBtn.innerHTML = isEdit ? '<i class="fas fa-save"></i> Save Changes' : '<i class="fas fa-plus"></i> Add Lead';
            }
        });
    }

    // ----------------------------------------------------
    // SCOPED LEAD LOADING AND SELECTORS
    // ----------------------------------------------------
    async function loadLeadsCrmData() {
        const filterCampaignSelect = document.getElementById('crm-campaign-filter');
        if (!filterCampaignSelect) return;
        const filterCampaignId = filterCampaignSelect.value;
        try {
            const url = filterCampaignId ? `api/leads.php?campaign_id=${filterCampaignId}` : `api/leads.php`;
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                renderCrmBoard(data.leads);
            }
        } catch (err) {
            console.error('Failed to load leads data', err);
        }
    }

    const crmCampaignFilter = document.getElementById('crm-campaign-filter');
    if (crmCampaignFilter) {
        crmCampaignFilter.addEventListener('change', () => {
            const selectId = crmCampaignFilter.value;
            if (selectId === 'unsaved_scraper') {
                state.activeCampaignId = null;
                state.activeCampaign = null;
                renderScrapedLeadsList();
            } else if (selectId) {
                state.activeCampaignId = parseInt(selectId);
                const campObj = state.campaigns.find(c => c.id == state.activeCampaignId);
                if (campObj) {
                    state.activeCampaign = campObj;
                }
                loadLeadsCrmData();
            } else {
                state.activeCampaignId = null;
                state.activeCampaign = null;
                loadLeadsCrmData();
            }
        });
    }

    // Patch selectCampaign to update dropdown context selection
    const _origSelectCampaign = selectCampaign;
    selectCampaign = function(id) {
        _origSelectCampaign(id);
        const filterCampaignSelect = document.getElementById('crm-campaign-filter');
        if (filterCampaignSelect) {
            // Re-populate campaigns in case it changed
            filterCampaignSelect.innerHTML = '<option value="">All Campaigns &amp; Manual Leads</option>';
            filterCampaignSelect.innerHTML += '<option value="unsaved_scraper">Latest Scraper Results (Unsaved)</option>';
            state.campaigns.forEach(c => {
                filterCampaignSelect.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
            });
            filterCampaignSelect.value = id || '';
        }
    };

    // Plans Management logic
    const planModal = document.getElementById('plan-modal');
    const planModalForm = document.getElementById('plan-modal-form');
    const planModalId = document.getElementById('plan-modal-id');
    const planModalName = document.getElementById('plan-modal-name');
    const planModalCampaigns = document.getElementById('plan-modal-campaigns');
    const planModalLeads = document.getElementById('plan-modal-leads');
    const planModalLlm = document.getElementById('plan-modal-llm');
    const planModalEmail = document.getElementById('plan-modal-email');
    const planModalWhatsapp = document.getElementById('plan-modal-whatsapp');
    const planModalSms = document.getElementById('plan-modal-sms');
    const planModalError = document.getElementById('plan-modal-error');
    const planModalSaveBtn = document.getElementById('plan-modal-save-btn');
    const planModalTitle = document.getElementById('plan-modal-title');
    const planModalDuration = document.getElementById('plan-modal-duration');

    async function loadPlans() {
        const tbody = document.getElementById('admin-plans-tbody');
        const select = document.getElementById('edit-user-plan');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:15px; color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading plans...</td></tr>';

        try {
            const res = await fetch('api/plans.php');
            const data = await res.json();
            if (data.success) {
                state.plans = data.plans;
                tbody.innerHTML = '';
                
                // Populate dropdown
                if (select) {
                    select.innerHTML = '';
                    data.plans.forEach(p => {
                        select.innerHTML += `<option value="${p.id}">${escapeHtml(p.name)} (${p.campaign_limit == -1 ? '∞' : p.campaign_limit} campaigns / ${p.lead_limit == -1 ? '∞' : p.lead_limit} leads)</option>`;
                    });
                }

                if (data.plans.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:15px; color:var(--text-muted);">No plans found.</td></tr>';
                    return;
                }

                data.plans.forEach(p => {
                    const isDefault = p.name === 'Default Plan';
                    const tr = document.createElement('tr');
                    
                    const cLimit = p.campaign_limit == -1 ? 'Unlimited' : p.campaign_limit;
                    const lLimit = p.lead_limit == -1 ? 'Unlimited' : p.lead_limit;
                    const llmLimit = p.llm_limit == -1 ? 'Unlimited' : (p.llm_limit !== undefined ? p.llm_limit : 100);
                    const emailLimit = p.email_limit == -1 ? 'Unlimited' : (p.email_limit !== undefined ? p.email_limit : 100);
                    const whatsappLimit = p.whatsapp_limit == -1 ? 'Unlimited' : (p.whatsapp_limit !== undefined ? p.whatsapp_limit : 100);
                    const smsLimit = p.sms_limit == -1 ? 'Unlimited' : (p.sms_limit !== undefined ? p.sms_limit : 100);

                    tr.innerHTML = `
                        <td style="padding:10px; font-weight:600;">${escapeHtml(p.name)} ${isDefault ? '<span style="font-size:10px; color:var(--accent-primary); font-weight:400;">(System Default)</span>' : ''}</td>
                        <td style="padding:10px;">${cLimit}</td>
                        <td style="padding:10px;">${lLimit}</td>
                        <td style="padding:10px;">${llmLimit}</td>
                        <td style="padding:10px;">${emailLimit}</td>
                        <td style="padding:10px;">${whatsappLimit}</td>
                        <td style="padding:10px;">${smsLimit}</td>
                        <td style="padding:10px; font-weight:600; color:var(--accent-primary);">${escapeHtml(p.duration || '1 Month')}</td>
                        <td style="padding:10px;"><span class="user-role-badge role-user" style="background:rgba(var(--accent-primary-rgb),0.1); color:var(--accent-primary); border:none; padding:2px 8px;">${p.user_count} users</span></td>
                        <td style="padding:10px; text-align:right;">
                            <div style="display:inline-flex; gap:6px;">
                                <button class="user-action-btn edit-plan-btn" data-id="${p.id}" title="Edit Plan"><i class="fas fa-edit"></i></button>
                                ${isDefault ? '' : `<button class="user-action-btn delete-plan-btn" data-id="${p.id}" title="Delete Plan" style="border-color:var(--accent-error); color:var(--accent-error);"><i class="fas fa-trash-alt"></i></button>`}
                            </div>
                        </td>
                    `;

                    // Bind edit plan listener
                    tr.querySelector('.edit-plan-btn').addEventListener('click', () => {
                        openEditPlanModal(p);
                    });

                    // Bind delete plan listener
                    const delPlanBtn = tr.querySelector('.delete-plan-btn');
                    if (delPlanBtn) {
                        delPlanBtn.addEventListener('click', () => {
                            deletePlan(p.id, p.name);
                        });
                    }

                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:15px; color:var(--accent-error);">Failed to load plans: ${data.error}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:15px; color:var(--accent-error);">Network error loading plans.</td></tr>`;
        }
    }

    const adminAddPlanBtn = document.getElementById('admin-add-plan-btn');
    if (adminAddPlanBtn) {
        adminAddPlanBtn.addEventListener('click', () => {
            planModalTitle.innerHTML = '<i class="fas fa-tags"></i> Create New Plan';
            planModalId.value = '';
            planModalName.value = '';
            planModalCampaigns.value = '10';
            planModalLeads.value = '50';
            planModalLlm.value = '100';
            planModalEmail.value = '100';
            planModalWhatsapp.value = '100';
            planModalSms.value = '100';
            if (planModalDuration) planModalDuration.value = '1 Month';
            planModalError.style.display = 'none';
            planModal.style.display = 'flex';
        });
    }

    function openEditPlanModal(plan) {
        planModalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Usage Plan';
        planModalId.value = plan.id;
        planModalName.value = plan.name;
        planModalCampaigns.value = plan.campaign_limit;
        planModalLeads.value = plan.lead_limit;
        planModalLlm.value = plan.llm_limit !== undefined ? plan.llm_limit : 100;
        planModalEmail.value = plan.email_limit !== undefined ? plan.email_limit : 100;
        planModalWhatsapp.value = plan.whatsapp_limit !== undefined ? plan.whatsapp_limit : 100;
        planModalSms.value = plan.sms_limit !== undefined ? plan.sms_limit : 100;
        if (planModalDuration) planModalDuration.value = plan.duration || '1 Month';
        planModalError.style.display = 'none';
        planModal.style.display = 'flex';
    }

    ['plan-modal-close', 'plan-modal-cancel-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            planModal.style.display = 'none';
        });
    });

    if (planModalForm) {
        planModalForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            planModalError.style.display = 'none';
            planModalSaveBtn.disabled = true;
            planModalSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const id = planModalId.value;
            const isEdit = !!id;

            const payload = {
                name: planModalName.value.trim(),
                campaign_limit: parseInt(planModalCampaigns.value),
                lead_limit: parseInt(planModalLeads.value),
                llm_limit: parseInt(planModalLlm.value),
                email_limit: parseInt(planModalEmail.value),
                whatsapp_limit: parseInt(planModalWhatsapp.value),
                sms_limit: parseInt(planModalSms.value),
                duration: planModalDuration ? planModalDuration.value.trim() : '1 Month'
            };
            if (isEdit) {
                payload.id = parseInt(id);
            }

            try {
                const res = await fetch('api/plans.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(isEdit ? 'Plan updated successfully.' : 'Plan created successfully.');
                    planModal.style.display = 'none';
                    loadPlans();
                    loadUsers();
                } else {
                    planModalError.textContent = data.error || 'Failed to save plan.';
                    planModalError.style.display = 'block';
                }
            } catch (err) {
                planModalError.textContent = 'Network error saving plan.';
                planModalError.style.display = 'block';
            } finally {
                planModalSaveBtn.disabled = false;
                planModalSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save Plan';
            }
        });
    }

    async function deletePlan(id, name) {
        if (!confirm(`Are you sure you want to delete the plan "${name}"? Any users currently assigned to this plan will be automatically reassigned to the Default Plan.`)) {
            return;
        }

        try {
            const res = await fetch(`api/plans.php?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) {
                showToast('Plan deleted and users reassigned to Default Plan.');
                loadPlans();
                loadUsers();
            } else {
                alert('Failed to delete plan: ' + data.error);
            }
        } catch (err) {
            console.error('Delete plan failed', err);
        }
    }

    // Global Modal Backdrop closing
    window.addEventListener('click', (e) => {
        if (e.target === userModal) userModal.style.display = 'none';
        if (e.target === document.getElementById('delete-user-modal')) document.getElementById('delete-user-modal').style.display = 'none';
        if (e.target === profileModal) profileModal.style.display = 'none';
        if (e.target === manualLeadModal) manualLeadModal.style.display = 'none';
        if (e.target === planModal) planModal.style.display = 'none';
    });

    // ----------------------------------------------------
    // INITS & SETTINGS OVERRIDES
    // ----------------------------------------------------
    initTheme();
    checkAuthStatus().then(() => {
        fetch('api/settings.php')
            .then(r => r.json())
            .then(d => { if (d.success && d.settings.app_name) applyAppName(d.settings.app_name); })
            .catch(() => {});
    });

    elements.settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(elements.settingsForm);
        const payload = {};
        formData.forEach((value, key) => { payload[key] = value; });

        // Handle checkbox values manually since unchecked checkboxes are omitted from FormData
        const chatCheckbox = document.getElementById('enable_public_chat');
        if (chatCheckbox) {
            payload['enable_public_chat'] = chatCheckbox.checked ? '1' : '0';
        }
        const notifCheckbox = document.getElementById('enable_public_notifications');
        if (notifCheckbox) {
            payload['enable_public_notifications'] = notifCheckbox.checked ? '1' : '0';
        }
        const geminiCheckbox = document.getElementById('gemini_active');
        if (geminiCheckbox) {
            payload['gemini_active'] = geminiCheckbox.checked ? '1' : '0';
        }
        const lmStudioCheckbox = document.getElementById('lm_studio_active');
        if (lmStudioCheckbox) {
            payload['lm_studio_active'] = lmStudioCheckbox.checked ? '1' : '0';
        }
        const ollamaCheckbox = document.getElementById('ollama_active');
        if (ollamaCheckbox) {
            payload['ollama_active'] = ollamaCheckbox.checked ? '1' : '0';
        }
        const openRouterCheckbox = document.getElementById('openrouter_active');
        if (openRouterCheckbox) {
            payload['openrouter_active'] = openRouterCheckbox.checked ? '1' : '0';
        }
        const openAiCompatCheckbox = document.getElementById('openai_compat_active');
        if (openAiCompatCheckbox) {
            payload['openai_compat_active'] = openAiCompatCheckbox.checked ? '1' : '0';
        }

        try {
            const res = await fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                showToast('Configuration saved!');
                elements.settingsModal.style.display = 'none';
                state.settings = { ...state.settings, ...payload };
                if (payload.app_name) applyAppName(payload.app_name);
                updateChatSectionVisibility();
                loadUsageAndProviders();
            } else {
                alert('Error: ' + (data.error || 'Save failed.'));
            }
        } catch (err) {
            alert('Request failed: ' + err.message);
        }
    });

    // ----------------------------------------------------
    // CONTACTS DIRECTORY
    // ----------------------------------------------------
    let activeModalContact = null;
    let activeModalContactTab = 'email'; // email, whatsapp

    async function loadContactsDirectory() {
        const tbody = document.getElementById('contacts-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px; color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading contacts...</td></tr>';

        try {
            const res = await fetch('api/leads.php');
            const data = await res.json();
            if (data.success) {
                state.contacts = data.leads;
                renderContactsTable();
            } else {
                tbody.innerHTML = `<tr><td colspan="12" style="text-align:center; padding:20px; color:var(--accent-error);">Failed to load contacts: ${data.error}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px; color:var(--accent-error);">Network error loading contacts.</td></tr>';
        }
    }

    function renderContactsTable() {
        const tbody = document.getElementById('contacts-tbody');
        if (!tbody || !state.contacts) return;

        const searchText = (document.getElementById('contacts-search-input')?.value || '').toLowerCase().trim();
        const filterScore = document.getElementById('contacts-filter-score')?.value || 'ALL';
        const filterStatus = document.getElementById('contacts-filter-status')?.value || 'ALL';

        tbody.innerHTML = '';

        const filtered = state.contacts.filter(c => {
            // Search text filter
            if (searchText) {
                const name = (c.contact_name || '').toLowerCase();
                const company = (c.company_name || '').toLowerCase();
                const industry = (c.industry || '').toLowerCase();
                const email = (c.email || '').toLowerCase();
                const owner = (c.owner_username || '').toLowerCase();
                const campaign = (c.campaign_title || '').toLowerCase();
                const mobile = (c.mobile || '').toLowerCase();
                const source = (c.source || '').toLowerCase();

                if (!name.includes(searchText) && 
                    !company.includes(searchText) && 
                    !industry.includes(searchText) && 
                    !email.includes(searchText) && 
                    !owner.includes(searchText) && 
                    !campaign.includes(searchText) &&
                    !mobile.includes(searchText) &&
                    !source.includes(searchText)) {
                    return false;
                }
            }

            // Score filter
            if (filterScore !== 'ALL' && c.score !== filterScore) {
                return false;
            }

            // Status filter
            if (filterStatus !== 'ALL' && c.status !== filterStatus) {
                return false;
            }

            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center; padding:20px; color:var(--text-muted);">No contacts found matching the filters.</td></tr>';
            return;
        }

        filtered.forEach(c => {
            const scoreClass = `score-${(c.score || 'MEDIUM').toLowerCase()}`;
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.style.borderBottom = '1px solid var(--border-color)';
            tr.classList.add('contact-row');

            tr.innerHTML = `
                <td style="padding:12px 10px; font-weight:600;">${escapeHtml(c.contact_name || '—')}</td>
                <td style="padding:12px 10px;">${escapeHtml(c.company_name)}</td>
                <td style="padding:12px 10px;">${escapeHtml(c.industry || '—')}</td>
                <td style="padding:12px 10px;">${escapeHtml(c.email || '—')}</td>
                <td style="padding:12px 10px;">${escapeHtml(c.whatsapp || '—')}</td>
                <td style="padding:12px 10px;">${escapeHtml(c.mobile || '—')}</td>
                <td style="padding:12px 10px;"><span class="lead-score-badge ${scoreClass}">${c.score || 'MEDIUM'}</span></td>
                <td style="padding:12px 10px;"><span class="user-role-badge role-user" style="text-transform:uppercase; font-size:10px; background:rgba(255,255,255,0.05); color:var(--text-primary); border:none; padding:2px 8px;">${c.status || 'GENERATED'}</span></td>
                <td style="padding:12px 10px;">${escapeHtml(c.source || 'agent')}</td>
                <td style="padding:12px 10px; color:var(--text-secondary); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(c.campaign_title || 'Manual Lead')}</td>
                <td style="padding:12px 10px; font-weight:500;">${escapeHtml(c.owner_username || 'System')}</td>
                <td style="padding:10px 12px; text-align:right; white-space:nowrap;">
                    <div style="display:inline-flex; align-items:center; gap:3px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:10px; padding:4px 5px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                        <button class="user-action-btn view-details-btn" title="View Details" style="
                            display:inline-flex; align-items:center; justify-content:center;
                            width:30px; height:30px; padding:0; border-radius:7px; border:none; cursor:pointer;
                            background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.12));
                            color:var(--accent-primary); transition:all 0.18s ease;
                        " onmouseover="this.style.background='linear-gradient(135deg,rgba(99,102,241,0.25),rgba(139,92,246,0.25))';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(99,102,241,0.3)'" onmouseout="this.style.background='linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.12))';this.style.transform='none';this.style.boxShadow='none'">
                            <i class="fas fa-eye" style="font-size:12px;"></i>
                        </button>
                        ${canEditLeadMetadata(c) ? `
                            <div style="width:1px; height:18px; background:var(--border-color); opacity:0.5;"></div>
                            <button class="user-action-btn contacts-edit-btn" data-id="${c.id}" title="Edit Contact" style="
                                display:inline-flex; align-items:center; justify-content:center;
                                width:30px; height:30px; padding:0; border-radius:7px; border:none; cursor:pointer;
                                background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(5,150,105,0.12));
                                color:#10b981; transition:all 0.18s ease;
                            " onmouseover="this.style.background='linear-gradient(135deg,rgba(16,185,129,0.25),rgba(5,150,105,0.25))';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(16,185,129,0.3)'" onmouseout="this.style.background='linear-gradient(135deg,rgba(16,185,129,0.12),rgba(5,150,105,0.12))';this.style.transform='none';this.style.boxShadow='none'">
                                <i class="fas fa-pen-to-square" style="font-size:12px;"></i>
                            </button>
                            <div style="width:1px; height:18px; background:var(--border-color); opacity:0.5;"></div>
                            <button class="user-action-btn contacts-delete-btn" data-id="${c.id}" title="Delete Contact" style="
                                display:inline-flex; align-items:center; justify-content:center;
                                width:30px; height:30px; padding:0; border-radius:7px; border:none; cursor:pointer;
                                background:linear-gradient(135deg,rgba(239,68,68,0.1),rgba(220,38,38,0.1));
                                color:#ef4444; transition:all 0.18s ease;
                            " onmouseover="this.style.background='linear-gradient(135deg,rgba(239,68,68,0.25),rgba(220,38,38,0.25))';this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(239,68,68,0.3)'" onmouseout="this.style.background='linear-gradient(135deg,rgba(239,68,68,0.1),rgba(220,38,38,0.1))';this.style.transform='none';this.style.boxShadow='none'">
                                <i class="fas fa-trash-can" style="font-size:12px;"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;

            // Row click triggers details modal
            tr.addEventListener('click', (e) => {
                if (e.target.closest('.user-action-btn')) return;
                openContactDetailsModal(c);
            });

            tr.querySelector('.view-details-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                openContactDetailsModal(c);
            });

            const editBtn = tr.querySelector('.contacts-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openEditContactModal(c);
                });
            }

            const deleteBtn = tr.querySelector('.contacts-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (!confirm(`Delete contact "${c.company_name}"? This action cannot be undone.`)) return;
                    try {
                        const res = await fetch(`api/leads.php?id=${c.id}`, { method: 'DELETE' });
                        const data = await res.json();
                        if (data.success) {
                            showToast('Contact deleted.');
                            loadContactsDirectory();
                            loadLeadsCrmData();
                        } else {
                            alert('Delete failed: ' + (data.error || 'Unknown error'));
                        }
                    } catch (err) {
                        alert('Network error deleting contact.');
                    }
                });
            }

            tbody.appendChild(tr);
        });
    }

    function openContactDetailsModal(contact) {
        activeModalContact = contact;
        activeModalContactTab = 'email';

        document.getElementById('contact-modal-company').textContent = contact.company_name;
        document.getElementById('contact-modal-name').textContent = contact.contact_name || '—';
        document.getElementById('contact-modal-industry').textContent = contact.industry || '—';
        document.getElementById('contact-modal-email').textContent = contact.email || '—';
        document.getElementById('contact-modal-whatsapp').textContent = contact.whatsapp || '—';
        document.getElementById('contact-modal-mobile').textContent = contact.mobile || '—';
        document.getElementById('contact-modal-source').textContent = contact.source || 'agent';
        document.getElementById('contact-modal-campaign').textContent = contact.campaign_title || 'Manual Lead';
        document.getElementById('contact-modal-owner').textContent = contact.owner_username || 'System';
        const contactModalPostalAddress = document.getElementById('contact-modal-postal-address');
        if (contactModalPostalAddress) {
            contactModalPostalAddress.textContent = contact.postal_address || '—';
        }
        
        const descContainer = document.getElementById('contact-modal-desc-container');
        const descEl = document.getElementById('contact-modal-description');
        if (descEl && descContainer) {
            if (contact.description && contact.description.trim() !== '') {
                descEl.textContent = contact.description;
                descContainer.style.display = 'flex';
            } else {
                descContainer.style.display = 'none';
            }
        }

        document.getElementById('contact-modal-reasoning').textContent = contact.reasoning || 'No qualification report generated.';

        const scoreBadge = document.getElementById('contact-modal-score');
        const score = contact.score || 'MEDIUM';
        scoreBadge.textContent = `${score} FIT`;
        scoreBadge.className = `lead-score-badge score-${score.toLowerCase()}`;

        document.getElementById('contact-details-modal').style.display = 'flex';
    }

    // Bind event listeners for Contacts Tab
    const contactsSearchInput = document.getElementById('contacts-search-input');
    if (contactsSearchInput) {
        contactsSearchInput.addEventListener('input', renderContactsTable);
    }
    const contactsFilterScore = document.getElementById('contacts-filter-score');
    if (contactsFilterScore) {
        contactsFilterScore.addEventListener('change', renderContactsTable);
    }
    const contactsFilterStatus = document.getElementById('contacts-filter-status');
    if (contactsFilterStatus) {
        contactsFilterStatus.addEventListener('change', renderContactsTable);
    }

    // Modal close handlers
    ['contact-modal-close', 'contact-modal-close-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => {
            document.getElementById('contact-details-modal').style.display = 'none';
            activeModalContact = null;
        });
    });

    // Modal Tab click handlers and buttons removed

    const tabBtnContacts = document.getElementById('tab-btn-contacts');
    if (tabBtnContacts) {
        tabBtnContacts.addEventListener('click', () => switchTab('contacts'));
    }

    // ----------------------------------------------------
    // ADD CONTACT BUTTON IN CONTACTS TAB
    // ----------------------------------------------------
    const contactsAddBtn = document.getElementById('contacts-add-btn');
    if (contactsAddBtn) {
        contactsAddBtn.addEventListener('click', () => {
            // Re-use the manual lead modal (cleared for new entry)
            manualLeadCampaign.innerHTML = '<option value="">None (General CRM Lead)</option>';
            state.campaigns.forEach(c => {
                manualLeadCampaign.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
            });
            manualLeadCampaign.value = '';

            if (elements.manualLeadId) elements.manualLeadId.value = '';
            const modalHeaderTitle = document.querySelector('#manual-lead-modal .modal-header h3');
            if (modalHeaderTitle) modalHeaderTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New Contact';
            if (manualLeadSaveBtn) manualLeadSaveBtn.innerHTML = '<i class="fas fa-plus"></i> Add Contact';

            manualLeadCompany.value = '';
            manualLeadContact.value = '';
            manualLeadEmail.value = '';
            manualLeadWhatsapp.value = '';
            manualLeadMobile.value = '';
            manualLeadSource.value = 'manual';
            manualLeadIndustry.value = '';
            manualLeadScore.value = 'MEDIUM';
            if (manualLeadStatus) manualLeadStatus.value = '';
            manualLeadDesc.value = '';
            manualLeadReasoning.value = 'Manually added';
            if (manualLeadPostalAddress) manualLeadPostalAddress.value = '';
            if (manualLeadEmailDraft) manualLeadEmailDraft.value = '';
            if (manualLeadWhatsappDraft) manualLeadWhatsappDraft.value = '';
            if (manualLeadSmsDraft) manualLeadSmsDraft.value = '';
            if (manualLeadCallsDraft) manualLeadCallsDraft.value = '';
            manualLeadError.style.display = 'none';
            // Collapse drafts section for new contact
            if (draftsBody) draftsBody.style.display = 'none';
            if (draftsChevron) draftsChevron.style.transform = '';
            draftsOpen = false;

            // Show owner group only for admins
            const ownerGroup = document.getElementById('manual-lead-owner-group');
            if (ownerGroup) {
                ownerGroup.style.display = (state.currentUser && state.currentUser.role === 'admin') ? '' : 'none';
                // Populate owner dropdown
                if (state.currentUser && state.currentUser.role === 'admin') {
                    const ownerSelect = document.getElementById('manual-lead-owner');
                    if (ownerSelect) {
                        ownerSelect.innerHTML = '<option value="">— Assign to Me (Admin) —</option>';
                        if (state._adminUsersList) {
                            state._adminUsersList.forEach(u => {
                                ownerSelect.innerHTML += `<option value="${u.id}">${escapeHtml(u.username)} (${u.role})</option>`;
                            });
                        }
                    }
                }
            }

            manualLeadModal.style.display = 'flex';
        });
    }

    // Helper to open the Edit modal for a contact from the Contacts Directory
    function openEditContactModal(contact) {
        manualLeadCampaign.innerHTML = '<option value="">None (General CRM Lead)</option>';
        state.campaigns.forEach(c => {
            manualLeadCampaign.innerHTML += `<option value="${c.id}">${escapeHtml(c.title)}</option>`;
        });
        manualLeadCampaign.value = contact.campaign_id || '';

        if (elements.manualLeadId) elements.manualLeadId.value = contact.id;

        const titleEl = manualLeadModal.querySelector('.modal-header h3');
        if (titleEl) titleEl.innerHTML = '<i class="fas fa-edit"></i> Edit CRM Contact';
        if (manualLeadSaveBtn) manualLeadSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';

        manualLeadCompany.value = contact.company_name || '';
        manualLeadContact.value = contact.contact_name || '';
        manualLeadEmail.value = contact.email || '';
        manualLeadWhatsapp.value = contact.whatsapp || '';
        manualLeadMobile.value = contact.mobile || '';
        manualLeadSource.value = contact.source || 'manual';
        manualLeadIndustry.value = contact.industry || '';
        manualLeadScore.value = contact.score || 'MEDIUM';
        // Populate lead status
        if (manualLeadStatus) manualLeadStatus.value = contact.status || '';
        manualLeadDesc.value = contact.description || '';
        manualLeadReasoning.value = contact.reasoning || '';
        if (manualLeadPostalAddress) manualLeadPostalAddress.value = contact.postal_address || '';
        // Populate draft fields
        if (manualLeadEmailDraft) manualLeadEmailDraft.value = contact.email_draft || '';
        if (manualLeadWhatsappDraft) manualLeadWhatsappDraft.value = contact.whatsapp_draft || '';
        if (manualLeadSmsDraft) manualLeadSmsDraft.value = contact.sms_draft || '';
        if (manualLeadCallsDraft) manualLeadCallsDraft.value = contact.calls_draft || '';
        manualLeadError.style.display = 'none';
        // Auto-open drafts section if any draft exists
        const hasDraft = (contact.email_draft || contact.whatsapp_draft || contact.sms_draft || contact.calls_draft);
        if (hasDraft && draftsBody) {
            draftsOpen = true;
            draftsBody.style.display = 'flex';
            if (draftsChevron) draftsChevron.style.transform = 'rotate(180deg)';
            // Reset to email tab
            const emailTab = document.getElementById('modal-draft-tab-email');
            if (emailTab) emailTab.click();
        } else if (draftsBody) {
            draftsOpen = false;
            draftsBody.style.display = 'none';
            if (draftsChevron) draftsChevron.style.transform = '';
        }

        // Show owner group for admins only and populate dropdown
        const ownerGroup = document.getElementById('manual-lead-owner-group');
        if (ownerGroup) {
            const isAdmin = state.currentUser && state.currentUser.role === 'admin';
            ownerGroup.style.display = isAdmin ? '' : 'none';
            if (isAdmin) {
                const ownerSelect = document.getElementById('manual-lead-owner');
                if (ownerSelect) {
                    ownerSelect.innerHTML = '<option value="">— Keep Current Owner —</option>';
                    if (state._adminUsersList) {
                        state._adminUsersList.forEach(u => {
                            const selected = String(u.id) === String(contact.owner_user_id || '') ? 'selected' : '';
                            ownerSelect.innerHTML += `<option value="${u.id}" ${selected}>${escapeHtml(u.username)} (${u.role})</option>`;
                        });
                    }
                }
            }
        }

        manualLeadModal.style.display = 'flex';
    }

    // ----------------------------------------------------
    // LOAD USERS LIST FOR ADMIN (shared state cache)
    // ----------------------------------------------------
    async function loadUsersListForAdmin() {
        try {
            const res = await fetch('api/users.php');
            const data = await res.json();
            if (data.success) {
                // Cache user list (exclude current admin from share targets but keep for ownership)
                state._adminUsersList = data.users.filter(u => u.role !== 'admin' || u.id !== state.currentUser?.id);
            }
        } catch (err) {
            console.warn('Failed to preload users list for admin features.');
        }
    }

    // ----------------------------------------------------
    // SHARE CAMPAIGN MODAL LOGIC
    // ----------------------------------------------------
    const shareCampaignBtn = document.getElementById('share-campaign-btn');
    const shareCampaignModal = document.getElementById('share-campaign-modal');
    const shareCampaignModalClose = document.getElementById('share-campaign-modal-close');
    const shareCampaignCancelBtn = document.getElementById('share-campaign-cancel-btn');
    const shareCampaignSaveBtn = document.getElementById('share-campaign-save-btn');
    const shareCampaignUserList = document.getElementById('share-campaign-user-list');
    const shareCampaignError = document.getElementById('share-campaign-error');

    if (shareCampaignBtn) {
        shareCampaignBtn.addEventListener('click', async () => {
            if (!state.activeCampaignId) return;

            // Make sure we have a users list
            if (!state._adminUsersList) {
                await loadUsersListForAdmin();
            }

            const currentShares = shareCampaignBtn._shares || [];

            // Build user checkbox list (only non-admin users can be shared with)
            if (shareCampaignUserList) {
                const usersToList = (state._adminUsersList || []).filter(u => u.role !== 'admin');
                if (usersToList.length === 0) {
                    shareCampaignUserList.innerHTML = '<p style="color:var(--text-secondary); font-size:13px;">No regular users found to share with.</p>';
                } else {
                    shareCampaignUserList.innerHTML = '';
                    usersToList.forEach(u => {
                        const isChecked = currentShares.includes(u.id) || currentShares.includes(String(u.id));
                        const item = document.createElement('label');
                        item.style.cssText = 'display:flex; align-items:center; gap:12px; cursor:pointer; padding:10px 14px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-primary); transition:background 0.2s;';
                        item.innerHTML = `
                            <input type="checkbox" name="share_user" value="${u.id}" ${isChecked ? 'checked' : ''} style="width:16px; height:16px; accent-color:var(--accent-primary);">
                            <span>
                                <strong style="font-size:13px;">${escapeHtml(u.username)}</strong>
                                ${u.full_name ? `<span style="color:var(--text-secondary); font-size:11px;"> — ${escapeHtml(u.full_name)}</span>` : ''}
                                <span style="font-size:10px; color:var(--text-muted); display:block;">${escapeHtml(u.email || 'No email')}</span>
                            </span>
                        `;
                        shareCampaignUserList.appendChild(item);
                    });
                }
            }

            if (shareCampaignError) shareCampaignError.style.display = 'none';
            if (shareCampaignModal) shareCampaignModal.style.display = 'flex';
        });
    }

    [shareCampaignModalClose, shareCampaignCancelBtn].forEach(el => {
        if (el) el.addEventListener('click', () => {
            if (shareCampaignModal) shareCampaignModal.style.display = 'none';
        });
    });

    if (shareCampaignSaveBtn) {
        shareCampaignSaveBtn.addEventListener('click', async () => {
            if (!state.activeCampaignId) return;

            const checkedBoxes = shareCampaignUserList ? shareCampaignUserList.querySelectorAll('input[name="share_user"]:checked') : [];
            const selectedUserIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

            shareCampaignSaveBtn.disabled = true;
            shareCampaignSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch('api/campaigns.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'share',
                        campaign_id: state.activeCampaignId,
                        user_ids: selectedUserIds
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Campaign shared with ${selectedUserIds.length} user(s).`);
                    if (shareCampaignModal) shareCampaignModal.style.display = 'none';
                    // Update local share state
                    if (shareCampaignBtn) shareCampaignBtn._shares = selectedUserIds;
                } else {
                    if (shareCampaignError) {
                        shareCampaignError.textContent = data.error || 'Failed to save sharing.';
                        shareCampaignError.style.display = 'block';
                    }
                }
            } catch (err) {
                if (shareCampaignError) {
                    shareCampaignError.textContent = 'Network error. Please try again.';
                    shareCampaignError.style.display = 'block';
                }
            } finally {
                shareCampaignSaveBtn.disabled = false;
                shareCampaignSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save Sharing';
            }
        });
    }

    // --------------------------------------------------------
    // UNIFIED DASHBOARD HELPERS
    // --------------------------------------------------------

    function formatMentions(text) {
        return text.replace(/@([a-zA-Z0-9_-]+)/g, '<span style="color:var(--accent-primary); font-weight:700; background:rgba(99,102,241,0.1); padding:2px 6px; border-radius:4px;">@$1</span>');
    }

    function updateChatSectionVisibility() {
        const chatEnabled = (state.settings && state.settings.enable_public_chat === '1');
        const notificationsEnabled = (state.settings && state.settings.enable_public_notifications !== '0');

        const chatPane = document.getElementById('dashboard-chat-pane');
        const notificationsPane = document.getElementById('dashboard-notifications-pane');

        if (chatPane) {
            chatPane.style.display = chatEnabled ? 'flex' : 'none';
        }
        if (notificationsPane) {
            notificationsPane.style.display = notificationsEnabled ? 'flex' : 'none';
        }

        const placeholder = document.getElementById('chat-disabled-placeholder');
        const container = document.getElementById('chat-room-container');
        if (placeholder && container) {
            placeholder.style.display = chatEnabled ? 'none' : 'flex';
            container.style.display = chatEnabled ? 'flex' : 'none';
        }
    }

    async function loadDashboardQuotas() {
        const container = document.getElementById('dashboard-quota-container');
        const planBadge = document.getElementById('dashboard-plan-badge');
        if (!container) return;

        try {
            const res = await fetch('api/usage.php');
            const data = await res.json();
            if (data.success) {
                if (planBadge) planBadge.textContent = 'Plan: ' + data.plan_name;
                
                const expirySpan = document.getElementById('dashboard-plan-expiry');
                if (expirySpan) {
                    if (data.plan_expires_at) {
                        const date = safeParseDate(data.plan_expires_at);
                        expirySpan.textContent = 'Expires: ' + date.toLocaleString();
                        expirySpan.style.display = 'inline-block';
                        if (Date.now() > date.getTime()) {
                            expirySpan.style.background = 'rgba(239,68,68,0.15)';
                            expirySpan.style.color = 'var(--accent-error)';
                            expirySpan.style.borderColor = 'rgba(239,68,68,0.3)';
                            expirySpan.textContent = 'Plan Expired: ' + date.toLocaleString();
                        } else {
                            expirySpan.style.background = 'rgba(245,158,11,0.15)';
                            expirySpan.style.color = '#F59E0B';
                            expirySpan.style.borderColor = 'rgba(245,158,11,0.3)';
                        }
                    } else {
                        expirySpan.style.display = 'none';
                    }
                }
                
                container.innerHTML = '';
                const items = [
                    { key: 'llm', label: 'LLM AI Runs', icon: 'fas fa-brain', color: '#6366F1' },
                    { key: 'campaigns', label: 'Campaigns', icon: 'fas fa-bullhorn', color: '#06B6D4' },
                    { key: 'leads', label: 'Leads CRM', icon: 'fas fa-users-rectangle', color: '#10B981' },
                    { key: 'email', label: 'Emails Sent', icon: 'fas fa-envelope', color: '#F59E0B' },
                    { key: 'whatsapp', label: 'WhatsApp Sent', icon: 'fab fa-whatsapp', color: '#25D366' },
                    { key: 'sms', label: 'SMS Sent', icon: 'fas fa-sms', color: '#3B82F6' }
                ];

                items.forEach(item => {
                    const usage = data.usage[item.key] || { used: 0, limit: 100 };
                    const used = usage.used;
                    const limit = usage.limit;
                    const remaining = Math.max(0, limit - used);
                    const pct = Math.min(100, limit > 0 ? (used / limit) * 100 : 0);

                    const card = document.createElement('div');
                    card.style.cssText = 'background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--border-radius-sm); padding:16px; display:flex; flex-direction:column; gap:10px; box-shadow:var(--shadow-sm);';
                    card.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12px; font-weight:600; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                                <i class="${item.icon}" style="color:${item.color}; font-size:14px;"></i> ${item.label}
                            </span>
                            <span style="font-size:10px; color:var(--text-secondary); font-weight:600;">${pct.toFixed(0)}%</span>
                        </div>
                        <div style="background:var(--border-color); height:6px; border-radius:3px; overflow:hidden; position:relative;">
                            <div style="background:${item.color}; width:0%; height:100%; border-radius:3px; transition:width 0.8s ease-out;" class="quota-bar-fill" data-width="${pct}%"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--text-secondary);">
                            <span>${used} / ${limit} used</span>
                            <span style="font-weight:600; color:${remaining > 0 ? 'var(--text-primary)' : 'var(--accent-error)'};">${remaining} left</span>
                        </div>
                    `;
                    container.appendChild(card);
                });

                // Trigger bar load transition
                setTimeout(() => {
                    document.querySelectorAll('.quota-bar-fill').forEach(el => {
                        el.style.width = el.getAttribute('data-width');
                    });
                }, 100);
            }
        } catch (e) {
            console.error('Failed to load dashboard quotas', e);
        }
    }

    async function loadNotifications() {
        const container = document.getElementById('notifications-feed-list');
        if (!container) return;

        try {
            const res = await fetch('api/notifications.php');
            const data = await res.json();
            if (data.success) {
                container.innerHTML = '';
                if (data.notifications.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic;">No notifications published yet.</div>';
                    return;
                }

                data.notifications.forEach(notif => {
                    const time = safeParseDate(notif.created_at).toLocaleString();
                    const item = document.createElement('div');
                    item.style.cssText = 'background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--border-radius-sm); padding:14px; display:flex; flex-direction:column; gap:6px; box-shadow:var(--shadow-sm);';
                    
                    const roleBadge = notif.sender_role === 'admin' ? 
                        '<span style="font-size:9px; background:rgba(99,102,241,0.15); color:var(--accent-primary); padding:2px 6px; border-radius:10px; font-weight:600; text-transform:uppercase;">Admin</span>' : '';

                    const isAdmin = state.currentUser && state.currentUser.role === 'admin';
                    const deleteBtn = isAdmin ? `
                        <button class="delete-notification-btn" data-id="${notif.id}" title="Remove Notification" style="border:none; background:transparent; color:var(--accent-error); cursor:pointer; font-size:11px; padding:2px 6px; border-radius:4px; display:inline-flex; align-items:center; justify-content:center; transition: all 0.2s ease;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    ` : '';

                    item.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <span style="font-weight:700; font-size:13px; color:var(--text-primary);">${escapeHtml(notif.title)}</span>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:10px; color:var(--text-muted); white-space:nowrap;">${time}</span>
                                ${deleteBtn}
                            </div>
                        </div>
                        <div style="font-size:12px; color:var(--text-secondary); line-height:1.4; white-space:pre-line;">${formatMentions(escapeHtml(notif.message))}</div>
                        <div style="display:flex; align-items:center; gap:6px; font-size:10px; color:var(--text-muted); margin-top:4px; border-top:1px solid var(--border-color); padding-top:6px;">
                            <i class="fas fa-user-circle"></i> Published by: <strong>${escapeHtml(notif.sender_username || 'System')}</strong>
                            ${roleBadge}
                        </div>
                    `;

                    const delBtnEl = item.querySelector('.delete-notification-btn');
                    if (delBtnEl) {
                        delBtnEl.addEventListener('click', async (e) => {
                            e.stopPropagation();
                            if (!confirm('Are you sure you want to remove this notification?')) return;
                            try {
                                const deleteRes = await fetch(`api/notifications.php?id=${notif.id}`, {
                                    method: 'DELETE'
                                });
                                const deleteData = await deleteRes.json();
                                if (deleteData.success) {
                                    showToast('Notification removed successfully.');
                                    loadNotifications();
                                } else {
                                    alert('Error: ' + (deleteData.error || 'Failed to remove notification'));
                                }
                            } catch (err) {
                                alert('Request failed: ' + err.message);
                            }
                        });
                    }

                    container.appendChild(item);
                });
            }
        } catch (e) {
            console.error('Failed to load notifications', e);
        }
    }

    async function loadChatMessages() {
        const box = document.getElementById('chat-messages-box');
        if (!box) return;

        const chatEnabled = (state.settings && state.settings.enable_public_chat === '1');
        if (!chatEnabled) {
            updateChatSectionVisibility();
            return;
        }

        try {
            const res = await fetch('api/chats.php');
            const data = await res.json();
            if (data.success) {
                state.settings.enable_public_chat = data.chat_enabled ? '1' : '0';
                updateChatSectionVisibility();

                if (!data.chat_enabled) return;

                const isAtBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 60;

                box.innerHTML = '';
                if (data.messages.length === 0) {
                    box.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted); font-style:italic;">No messages yet. Start the conversation!</div>';
                    return;
                }

                data.messages.forEach(msg => {
                    const time = safeParseDate(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const isMe = state.currentUser && parseInt(msg.user_id) === parseInt(state.currentUser.id);
                    
                    const bubble = document.createElement('div');
                    bubble.style.cssText = `display:flex; flex-direction:column; gap:2px; max-width:80%; margin-bottom:8px; ${isMe ? 'align-self:flex-end; align-items:flex-end;' : 'align-self:flex-start; align-items:flex-start;'}`;

                    const userColor = msg.role === 'admin' ? 'var(--accent-primary)' : 'var(--accent-secondary)';
                    const bubbleBg = isMe ? 'var(--accent-primary-gradient)' : 'var(--bg-card)';
                    const bubbleColor = isMe ? '#ffffff' : 'var(--text-primary)';
                    const bubbleBorder = isMe ? 'none' : '1px solid var(--border-color)';
                    const roleBadge = msg.role === 'admin' ? '<span style="font-size:8px; background:rgba(99,102,241,0.2); color:var(--accent-primary); padding:1px 4px; border-radius:4px; font-weight:700; margin-left:4px;">Admin</span>' : '';

                    bubble.innerHTML = `
                        <div style="font-size:10px; color:var(--text-muted); display:flex; align-items:center; margin-bottom:1px;">
                            <strong style="color:${isMe ? 'var(--text-primary)' : userColor};">${escapeHtml(msg.username)}</strong>
                            ${roleBadge}
                            <span style="font-size:9px; margin-left:6px; color:var(--text-muted);">${time}</span>
                        </div>
                        <div style="background:${bubbleBg}; color:${bubbleColor}; border:${bubbleBorder}; padding:8px 12px; border-radius:12px; font-size:12px; word-break:break-word; line-height:1.4; box-shadow:var(--shadow-sm); border-top-${isMe ? 'right' : 'left'}-radius:2px;">
                            ${escapeHtml(msg.message)}
                        </div>
                    `;
                    box.appendChild(bubble);
                });

                if (isAtBottom || box.scrollTop === 0) {
                    box.scrollTop = box.scrollHeight;
                }
            }
        } catch (e) {
            console.error('Failed to load chat messages', e);
        }
    }

    // Publish Notification Submit
    const publishNotifForm = document.getElementById('publish-notification-form');
    if (publishNotifForm) {
        publishNotifForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const titleEl = document.getElementById('notification-title');
            const messageEl = document.getElementById('notification-message');
            if (!titleEl || !messageEl) return;

            const title = titleEl.value.trim();
            const message = messageEl.value.trim();
            if (!title || !message) return;

            try {
                const res = await fetch('api/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title, message })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Notification published successfully!');
                    titleEl.value = '';
                    messageEl.value = '';
                    loadNotifications();
                    
                    if (data.email_mentions && data.email_mentions.length > 0) {
                        const count = data.email_mentions.length;
                        const details = data.email_mentions.map(m => `@${m.username} (${m.status})`).join(', ');
                        showToast(`Sent ${count} mention email(s): ${details}`);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to publish notification.'));
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        });
    }

    // Chat Send message Submit
    const chatSendForm = document.getElementById('chat-send-form');
    if (chatSendForm) {
        chatSendForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const inputEl = document.getElementById('chat-input');
            if (!inputEl) return;

            const message = inputEl.value.trim();
            if (!message) return;

            try {
                const res = await fetch('api/chats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message })
                });
                const data = await res.json();
                if (data.success) {
                    inputEl.value = '';
                    loadChatMessages();
                } else {
                    alert('Error: ' + (data.error || 'Failed to send chat message.'));
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        });
    }

    // Manual Refresh buttons
    const refreshNotificationsBtn = document.getElementById('refresh-notifications-btn');
    if (refreshNotificationsBtn) {
        refreshNotificationsBtn.addEventListener('click', () => {
            loadNotifications();
            showToast('Notifications refreshed.');
        });
    }

    const refreshChatBtn = document.getElementById('refresh-chat-btn');
    if (refreshChatBtn) {
        refreshChatBtn.addEventListener('click', () => {
            loadChatMessages();
            showToast('Chat messages refreshed.');
        });
    }

    // ----------------------------------------------------
    // OUTREACH WORKSTATION LOGIC
    // ----------------------------------------------------
    state.outreachLeads = [];
    state.activeOutreachLead = null;
    state.activeOutreachChannel = 'email'; // 'email', 'whatsapp', 'sms'

    const outreachCampaignSelect = document.getElementById('outreach-campaign-select');
    const outreachLlmSelect = document.getElementById('outreach-llm-select');
    const outreachContactsList = document.getElementById('outreach-contacts-list');
    const outreachContactsCount = document.getElementById('outreach-contacts-count');
    const outreachEmptyState = document.getElementById('outreach-empty-state');
    const outreachWorkspaceContent = document.getElementById('outreach-workspace-content');
    
    const outreachTextarea = document.getElementById('outreach-pane-textarea');
    const outreachGenerateBtn = document.getElementById('outreach-generate-btn');
    const outreachSaveBtn = document.getElementById('outreach-save-btn');
    const outreachCopyBtn = document.getElementById('outreach-copy-btn');
    const outreachSendBtn = document.getElementById('outreach-send-btn');

    const outreachChannelBtns = {
        email: document.getElementById('outreach-pane-tab-email'),
        whatsapp: document.getElementById('outreach-pane-tab-whatsapp'),
        sms: document.getElementById('outreach-pane-tab-sms'),
        calls: document.getElementById('outreach-pane-tab-calls')
    };

    // -------------------------------------------------------
    // LANGUAGE PICKER — All Google Gemma 4 supported languages
    // -------------------------------------------------------
    const GEMMA_LANGUAGES = [
        { code: 'af', name: 'Afrikaans' },
        { code: 'sq', name: 'Albanian' },
        { code: 'am', name: 'Amharic' },
        { code: 'ar', name: 'Arabic' },
        { code: 'hy', name: 'Armenian' },
        { code: 'as', name: 'Assamese' },
        { code: 'az', name: 'Azerbaijani' },
        { code: 'eu', name: 'Basque' },
        { code: 'be', name: 'Belarusian' },
        { code: 'bn', name: 'Bengali' },
        { code: 'bs', name: 'Bosnian' },
        { code: 'bg', name: 'Bulgarian' },
        { code: 'ca', name: 'Catalan' },
        { code: 'ceb', name: 'Cebuano' },
        { code: 'zh-CN', name: 'Chinese (Simplified)' },
        { code: 'zh-TW', name: 'Chinese (Traditional)' },
        { code: 'hr', name: 'Croatian' },
        { code: 'cs', name: 'Czech' },
        { code: 'da', name: 'Danish' },
        { code: 'nl', name: 'Dutch' },
        { code: 'en', name: 'English' },
        { code: 'eo', name: 'Esperanto' },
        { code: 'et', name: 'Estonian' },
        { code: 'fi', name: 'Finnish' },
        { code: 'fr', name: 'French' },
        { code: 'gl', name: 'Galician' },
        { code: 'ka', name: 'Georgian' },
        { code: 'de', name: 'German' },
        { code: 'el', name: 'Greek' },
        { code: 'gu', name: 'Gujarati' },
        { code: 'ht', name: 'Haitian Creole' },
        { code: 'ha', name: 'Hausa' },
        { code: 'he', name: 'Hebrew' },
        { code: 'hi', name: 'Hindi' },
        { code: 'hu', name: 'Hungarian' },
        { code: 'is', name: 'Icelandic' },
        { code: 'ig', name: 'Igbo' },
        { code: 'id', name: 'Indonesian' },
        { code: 'ga', name: 'Irish' },
        { code: 'it', name: 'Italian' },
        { code: 'ja', name: 'Japanese' },
        { code: 'jv', name: 'Javanese' },
        { code: 'kn', name: 'Kannada' },
        { code: 'kk', name: 'Kazakh' },
        { code: 'km', name: 'Khmer' },
        { code: 'ko', name: 'Korean' },
        { code: 'ku', name: 'Kurdish' },
        { code: 'ky', name: 'Kyrgyz' },
        { code: 'lo', name: 'Lao' },
        { code: 'lv', name: 'Latvian' },
        { code: 'lt', name: 'Lithuanian' },
        { code: 'lb', name: 'Luxembourgish' },
        { code: 'mk', name: 'Macedonian' },
        { code: 'mg', name: 'Malagasy' },
        { code: 'ms', name: 'Malay' },
        { code: 'ml', name: 'Malayalam' },
        { code: 'mt', name: 'Maltese' },
        { code: 'mi', name: 'Maori' },
        { code: 'mr', name: 'Marathi' },
        { code: 'mn', name: 'Mongolian' },
        { code: 'my', name: 'Myanmar (Burmese)' },
        { code: 'ne', name: 'Nepali' },
        { code: 'no', name: 'Norwegian' },
        { code: 'or', name: 'Odia (Oriya)' },
        { code: 'ps', name: 'Pashto' },
        { code: 'fa', name: 'Persian' },
        { code: 'pl', name: 'Polish' },
        { code: 'pt', name: 'Portuguese' },
        { code: 'pa', name: 'Punjabi' },
        { code: 'ro', name: 'Romanian' },
        { code: 'ru', name: 'Russian' },
        { code: 'sm', name: 'Samoan' },
        { code: 'sr', name: 'Serbian' },
        { code: 'sn', name: 'Shona' },
        { code: 'sd', name: 'Sindhi' },
        { code: 'si', name: 'Sinhala' },
        { code: 'sk', name: 'Slovak' },
        { code: 'sl', name: 'Slovenian' },
        { code: 'so', name: 'Somali' },
        { code: 'es', name: 'Spanish' },
        { code: 'su', name: 'Sundanese' },
        { code: 'sw', name: 'Swahili' },
        { code: 'sv', name: 'Swedish' },
        { code: 'tl', name: 'Tagalog (Filipino)' },
        { code: 'tg', name: 'Tajik' },
        { code: 'ta', name: 'Tamil' },
        { code: 'tt', name: 'Tatar' },
        { code: 'te', name: 'Telugu' },
        { code: 'th', name: 'Thai' },
        { code: 'tr', name: 'Turkish' },
        { code: 'tk', name: 'Turkmen' },
        { code: 'uk', name: 'Ukrainian' },
        { code: 'ur', name: 'Urdu' },
        { code: 'ug', name: 'Uyghur' },
        { code: 'uz', name: 'Uzbek' },
        { code: 'vi', name: 'Vietnamese' },
        { code: 'cy', name: 'Welsh' },
        { code: 'xh', name: 'Xhosa' },
        { code: 'yi', name: 'Yiddish' },
        { code: 'yo', name: 'Yoruba' },
        { code: 'zu', name: 'Zulu' }
    ];

    (function initLanguagePicker() {
        const display = document.getElementById('outreach-language-display');
        const label   = document.getElementById('outreach-language-label');
        const hidden  = document.getElementById('outreach-language-value');
        const dropdown= document.getElementById('outreach-language-dropdown');
        const search  = document.getElementById('outreach-language-search');
        const list    = document.getElementById('outreach-language-list');
        const chevron = document.getElementById('outreach-language-chevron');
        if (!display || !dropdown || !list) return;

        let isOpen = false;

        function renderList(filter) {
            list.innerHTML = '';
            const q = (filter || '').toLowerCase();
            const matched = GEMMA_LANGUAGES.filter(l => l.name.toLowerCase().includes(q) || l.code.toLowerCase().includes(q));
            if (!matched.length) {
                list.innerHTML = '<div style="padding:10px; font-size:12px; color:var(--text-muted); text-align:center;">No languages found</div>';
                return;
            }
            matched.forEach(lang => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:7px 10px; border-radius:5px; cursor:pointer; font-size:12px; color:var(--text-primary); transition:background 0.15s; display:flex; align-items:center; gap:8px;';
                item.setAttribute('data-lang-name', lang.name);
                const isSelected = hidden.value === lang.name;
                if (isSelected) {
                    item.style.background = 'rgba(99,102,241,0.15)';
                    item.style.color = 'var(--accent-primary)';
                    item.style.fontWeight = '600';
                }
                item.innerHTML = `<span style="font-size:10px; background:var(--bg-primary); border:1px solid var(--border-color); padding:1px 5px; border-radius:3px; font-family:monospace; color:var(--text-muted); flex-shrink:0;">${lang.code}</span><span>${lang.name}</span>`;
                item.addEventListener('mouseenter', () => { if (hidden.value !== lang.name) item.style.background = 'var(--bg-primary)'; });
                item.addEventListener('mouseleave', () => { if (hidden.value !== lang.name) item.style.background = ''; });
                item.addEventListener('click', () => {
                    hidden.value = lang.name;
                    label.innerHTML = `<i class="fas fa-flag" style="margin-right:5px; opacity:0.6;"></i>${lang.name}`;
                    closeDropdown();
                });
                list.appendChild(item);
            });
        }

        function openDropdown() {
            isOpen = true;
            dropdown.style.display = 'flex';
            chevron.style.transform = 'rotate(180deg)';
            display.style.borderColor = 'var(--accent-primary)';
            renderList('');
            setTimeout(() => { if (search) { search.value = ''; search.focus(); } }, 50);
        }

        function closeDropdown() {
            isOpen = false;
            dropdown.style.display = 'none';
            chevron.style.transform = '';
            display.style.borderColor = '';
        }

        display.addEventListener('click', (e) => { e.stopPropagation(); isOpen ? closeDropdown() : openDropdown(); });

        if (search) {
            search.addEventListener('input', () => renderList(search.value));
            search.addEventListener('keydown', e => { if (e.key === 'Escape') closeDropdown(); });
        }

        document.addEventListener('click', (e) => {
            if (isOpen && !document.getElementById('outreach-language-picker').contains(e.target)) {
                closeDropdown();
            }
        });

        // Init list
        renderList('');
    })();

    (function initCampaignLanguagePicker() {
        const display = document.getElementById('campaign-language-display');
        const label   = document.getElementById('campaign-language-label');
        const hidden  = document.getElementById('campaign_language');
        const dropdown= document.getElementById('campaign-language-dropdown');
        const search  = document.getElementById('campaign-language-search');
        const list    = document.getElementById('campaign-language-list');
        const chevron = document.getElementById('campaign-language-chevron');
        if (!display || !dropdown || !list) return;

        let isOpen = false;

        function renderList(filter) {
            list.innerHTML = '';
            const q = (filter || '').toLowerCase();
            const matched = GEMMA_LANGUAGES.filter(l => l.name.toLowerCase().includes(q) || l.code.toLowerCase().includes(q));
            if (!matched.length) {
                list.innerHTML = '<div style="padding:10px; font-size:12px; color:var(--text-muted); text-align:center;">No languages found</div>';
                return;
            }
            matched.forEach(lang => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:7px 10px; border-radius:5px; cursor:pointer; font-size:12px; color:var(--text-primary); transition:background 0.15s; display:flex; align-items:center; gap:8px;';
                item.setAttribute('data-lang-name', lang.name);
                const isSelected = hidden.value === lang.name;
                if (isSelected) {
                    item.style.background = 'rgba(99,102,241,0.15)';
                    item.style.color = 'var(--accent-primary)';
                    item.style.fontWeight = '600';
                }
                item.innerHTML = `<span style="font-size:10px; background:var(--bg-primary); border:1px solid var(--border-color); padding:1px 5px; border-radius:3px; font-family:monospace; color:var(--text-muted); flex-shrink:0;">${lang.code}</span><span>${lang.name}</span>`;
                item.addEventListener('mouseenter', () => { if (hidden.value !== lang.name) item.style.background = 'var(--bg-primary)'; });
                item.addEventListener('mouseleave', () => { if (hidden.value !== lang.name) item.style.background = ''; });
                item.addEventListener('click', () => {
                    hidden.value = lang.name;
                    label.innerHTML = `<i class="fas fa-flag" style="margin-right:5px; opacity:0.6;"></i>${lang.name}`;
                    hidden.dispatchEvent(new Event('change'));
                    closeDropdown();
                });
                list.appendChild(item);
            });
        }

        function openDropdown() {
            isOpen = true;
            dropdown.style.display = 'flex';
            chevron.style.transform = 'rotate(180deg)';
            display.style.borderColor = 'var(--accent-primary)';
            renderList('');
            setTimeout(() => { if (search) { search.value = ''; search.focus(); } }, 50);
        }

        function closeDropdown() {
            isOpen = false;
            dropdown.style.display = 'none';
            chevron.style.transform = '';
            display.style.borderColor = '';
        }

        display.addEventListener('click', (e) => { e.stopPropagation(); isOpen ? closeDropdown() : openDropdown(); });

        if (search) {
            search.addEventListener('input', () => renderList(search.value));
            search.addEventListener('keydown', e => { if (e.key === 'Escape') closeDropdown(); });
        }

        document.addEventListener('click', (e) => {
            if (isOpen && !document.getElementById('campaign-language-picker').contains(e.target)) {
                closeDropdown();
            }
        });

        window.updateCampaignLanguage = function(val) {
            hidden.value = val;
            label.innerHTML = `<i class="fas fa-flag" style="margin-right:5px; opacity:0.6;"></i>${val}`;
        };

        // Init list
        renderList('');
    })();


    // Load campaign select dropdown options and contacts
    async function loadOutreachTab() {
        if (!outreachCampaignSelect) return;
        try {
            const res = await fetch('api/campaigns.php');
            const data = await res.json();
            if (data.success) {
                state.campaigns = data.campaigns;
                
                const prev = outreachCampaignSelect.value;
                outreachCampaignSelect.innerHTML = '<option value="">— Select Campaign —</option>';
                data.campaigns.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.title;
                    outreachCampaignSelect.appendChild(opt);
                });

                if (prev && [...outreachCampaignSelect.options].some(o => o.value === prev)) {
                    outreachCampaignSelect.value = prev;
                } else if (state.activeCampaignId && [...outreachCampaignSelect.options].some(o => o.value == state.activeCampaignId)) {
                    outreachCampaignSelect.value = state.activeCampaignId;
                }
                
                outreachCampaignSelect.dispatchEvent(new Event('change'));
            }
        } catch (err) {
            console.error('Failed to load campaigns for outreach tab', err);
        }
    }

    if (outreachCampaignSelect) {
        outreachCampaignSelect.addEventListener('change', async () => {
            const campaignId = outreachCampaignSelect.value;
            if (!campaignId) {
                state.outreachLeads = [];
                renderOutreachLeadsList();
                return;
            }
            try {
                const res = await fetch(`api/leads.php?campaign_id=${campaignId}`);
                const data = await res.json();
                if (data.success) {
                    state.outreachLeads = data.leads;
                    renderOutreachLeadsList();
                } else {
                    showToast('Failed to load campaign leads.');
                }
            } catch (err) {
                console.error('Error fetching outreach campaign leads', err);
                showToast('Error loading contacts.');
            }
        });
    }

    function renderOutreachLeadsList() {
        if (!outreachContactsList) return;
        outreachContactsList.innerHTML = '';
        const leads = state.outreachLeads || [];
        if (outreachContactsCount) {
            outreachContactsCount.textContent = leads.length;
        }

        if (leads.length === 0) {
            outreachContactsList.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">No contacts found in this campaign.</div>';
            selectOutreachLead(null);
            return;
        }

        leads.forEach(lead => {
            const card = document.createElement('div');
            card.style.cursor = 'pointer';
            card.style.padding = '12px';
            card.style.borderRadius = '6px';
            card.style.border = '1px solid var(--border-color)';
            card.style.background = (state.activeOutreachLead && state.activeOutreachLead.id == lead.id) 
                ? 'rgba(99, 102, 241, 0.15)' 
                : 'var(--bg-secondary)';
            card.style.transition = 'all 0.2s ease';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.gap = '4px';
            card.className = 'outreach-lead-card';
            card.setAttribute('data-id', lead.id);

            const scoreClass = `score-${(lead.score || 'MEDIUM').toLowerCase()}`;
            const leadStatus = (lead.status || 'GENERATED').toUpperCase();

            let statusClass = 'created';
            if (leadStatus === 'GENERATED') statusClass = 'created';
            else if (leadStatus === 'QUALIFIED') statusClass = 'running';
            else if (leadStatus === 'OUTREACHED') statusClass = 'running';
            else if (leadStatus === 'CLOSED') statusClass = 'completed';
            else if (leadStatus === 'LOST') statusClass = 'failed';

            const hasDrafts = !!(lead.email_draft || lead.whatsapp_draft || lead.sms_draft || lead.calls_draft);

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                    <span style="font-weight:600; font-size:13px; color:var(--text-primary); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:180px;">${escapeHtml(lead.company_name)}</span>
                    <span class="lead-score-badge ${scoreClass}" style="flex-shrink:0;">${lead.score || 'MEDIUM'}</span>
                </div>
                <div style="font-size:11px; color:var(--text-secondary); display:flex; justify-content:space-between; align-items:center;">
                    <span>${escapeHtml(lead.contact_name || 'No contact')}</span>
                    <span class="campaign-badge badge-${statusClass}" style="font-size:9px; padding:1px 4px;">${leadStatus}</span>
                </div>
                ${hasDrafts ? `<div style="font-size:10px; color:var(--accent-primary); margin-top:2px;"><i class="fas fa-check-circle"></i> Drafts ready</div>` : ''}
            `;

            card.addEventListener('click', () => {
                selectOutreachLead(lead);
                document.querySelectorAll('.outreach-lead-card').forEach(el => {
                    el.style.background = 'var(--bg-secondary)';
                });
                card.style.background = 'rgba(99, 102, 241, 0.15)';
            });

            outreachContactsList.appendChild(card);
        });

        // Maintain or select first
        if (leads.length > 0) {
            const stillExists = state.activeOutreachLead && leads.some(l => l.id == state.activeOutreachLead.id);
            if (stillExists) {
                const currentLead = leads.find(l => l.id == state.activeOutreachLead.id);
                selectOutreachLead(currentLead);
                const activeCard = outreachContactsList.querySelector(`.outreach-lead-card[data-id="${currentLead.id}"]`);
                if (activeCard) activeCard.style.background = 'rgba(99, 102, 241, 0.15)';
            } else {
                selectOutreachLead(leads[0]);
                const firstCard = outreachContactsList.querySelector('.outreach-lead-card');
                if (firstCard) firstCard.style.background = 'rgba(99, 102, 241, 0.15)';
            }
        } else {
            selectOutreachLead(null);
        }
    }

    function selectOutreachLead(lead) {
        state.activeOutreachLead = lead;
        if (!lead) {
            if (outreachEmptyState) outreachEmptyState.classList.remove('hidden');
            if (outreachWorkspaceContent) outreachWorkspaceContent.classList.add('hidden');
            return;
        }

        if (outreachEmptyState) outreachEmptyState.classList.add('hidden');
        if (outreachWorkspaceContent) outreachWorkspaceContent.classList.remove('hidden');

        // Populate details
        const elCompany = document.getElementById('outreach-contact-company');
        const elName = document.getElementById('outreach-contact-name');
        const elIndustry = document.getElementById('outreach-contact-industry');
        const elEmail = document.getElementById('outreach-contact-email');
        const elWhatsapp = document.getElementById('outreach-contact-whatsapp');
        const elMobile = document.getElementById('outreach-contact-mobile');
        const elSource = document.getElementById('outreach-contact-source');
        const elAddress = document.getElementById('outreach-contact-address');

        if (elCompany) elCompany.textContent = lead.company_name || 'N/A';
        if (elName) elName.textContent = lead.contact_name || 'N/A';
        if (elIndustry) elIndustry.textContent = lead.industry || 'N/A';
        if (elEmail) elEmail.textContent = lead.email || 'N/A';
        if (elWhatsapp) elWhatsapp.textContent = lead.whatsapp || 'N/A';
        if (elMobile) elMobile.textContent = lead.mobile || 'N/A';
        if (elSource) elSource.textContent = lead.source || 'N/A';
        if (elAddress) elAddress.textContent = lead.postal_address || 'N/A';

        const scoreBadge = document.getElementById('outreach-contact-score');
        if (scoreBadge) {
            scoreBadge.textContent = (lead.score || 'MEDIUM') + ' FIT';
            scoreBadge.className = 'lead-score-badge score-' + (lead.score || 'MEDIUM').toLowerCase();
        }

        // Populate & wire status dropdown
        const statusSel = document.getElementById('outreach-lead-status-select');
        if (statusSel) {
            statusSel.value = lead.status || 'GENERATED';
            // Remove old listener by cloning
            const newStatusSel = statusSel.cloneNode(true);
            statusSel.parentNode.replaceChild(newStatusSel, statusSel);
            newStatusSel.value = lead.status || 'GENERATED';
            newStatusSel.addEventListener('change', async () => {
                const newStatus = newStatusSel.value;
                try {
                    const res = await fetch('api/leads.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ lead_id: lead.id, status: newStatus })
                    });
                    const data = await res.json();
                    if (data.success) {
                        lead.status = newStatus;
                        const found = state.outreachLeads.find(l => l.id == lead.id);
                        if (found) found.status = newStatus;
                        showToast(`Lead status updated to ${newStatus}`);
                    } else {
                        showToast('Failed to update status: ' + (data.error || ''));
                    }
                } catch (err) {
                    showToast('Error updating status.');
                }
            });
        }

        const descContainer = document.getElementById('outreach-contact-desc-container');
        const descEl = document.getElementById('outreach-contact-desc');
        if (descContainer && descEl) {
            if (lead.description && lead.description.trim() !== '') {
                descEl.textContent = lead.description;
                descContainer.style.display = 'block';
            } else {
                descEl.textContent = '';
                descContainer.style.display = 'none';
            }
        }

        renderOutreachChannelText();
    }

    function renderOutreachChannelText() {
        const lead = state.activeOutreachLead;
        if (!lead || !outreachTextarea) return;

        if (state.activeOutreachChannel === 'email') {
            outreachTextarea.value = lead.email_draft || '';
        } else if (state.activeOutreachChannel === 'whatsapp') {
            outreachTextarea.value = lead.whatsapp_draft || '';
        } else if (state.activeOutreachChannel === 'sms') {
            outreachTextarea.value = lead.sms_draft || '';
        } else if (state.activeOutreachChannel === 'calls') {
            outreachTextarea.value = lead.calls_draft || '';
        }
    }

    if (outreachTextarea) {
        outreachTextarea.addEventListener('input', () => {
            const lead = state.activeOutreachLead;
            if (!lead) return;
            if (state.activeOutreachChannel === 'email') {
                lead.email_draft = outreachTextarea.value;
            } else if (state.activeOutreachChannel === 'whatsapp') {
                lead.whatsapp_draft = outreachTextarea.value;
            } else if (state.activeOutreachChannel === 'sms') {
                lead.sms_draft = outreachTextarea.value;
            } else if (state.activeOutreachChannel === 'calls') {
                lead.calls_draft = outreachTextarea.value;
            }
            const found = state.outreachLeads.find(l => l.id == lead.id);
            if (found) {
                found[state.activeOutreachChannel + '_draft'] = outreachTextarea.value;
            }
        });
    }

    function selectOutreachChannel(channel) {
        state.activeOutreachChannel = channel;
        Object.values(outreachChannelBtns).forEach(btn => {
            if (btn) btn.classList.remove('active');
        });
        if (outreachChannelBtns[channel]) {
            outreachChannelBtns[channel].classList.add('active');
        }
        renderOutreachChannelText();
    }

    if (outreachChannelBtns.email) outreachChannelBtns.email.addEventListener('click', () => selectOutreachChannel('email'));
    if (outreachChannelBtns.whatsapp) outreachChannelBtns.whatsapp.addEventListener('click', () => selectOutreachChannel('whatsapp'));
    if (outreachChannelBtns.sms) outreachChannelBtns.sms.addEventListener('click', () => selectOutreachChannel('sms'));
    if (outreachChannelBtns.calls) outreachChannelBtns.calls.addEventListener('click', () => selectOutreachChannel('calls'));

    // Outreach Generate AI Button
    if (outreachGenerateBtn) {
        outreachGenerateBtn.addEventListener('click', async () => {
            const lead = state.activeOutreachLead;
            if (!lead) return;

            const provider = outreachLlmSelect ? outreachLlmSelect.value : '';
            
            outreachGenerateBtn.disabled = true;
            const originalText = outreachGenerateBtn.innerHTML;
            outreachGenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            try {
                const selectedLanguage = document.getElementById('outreach-language-value');
                const res = await fetch('api/generate-outreach.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lead_id: lead.id,
                        llm_provider: provider,
                        language: selectedLanguage ? selectedLanguage.value : 'English'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    // Update local state immediately for instant UI response
                    lead.email_draft = data.email_draft;
                    lead.whatsapp_draft = data.whatsapp_draft;
                    lead.sms_draft = data.sms_draft;
                    lead.calls_draft = data.calls_draft;

                    // Sync list array
                    const found = state.outreachLeads.find(l => l.id == lead.id);
                    if (found) {
                        found.email_draft = data.email_draft;
                        found.whatsapp_draft = data.whatsapp_draft;
                        found.sms_draft = data.sms_draft;
                        found.calls_draft = data.calls_draft;
                    }

                    // Render updated draft content immediately
                    renderOutreachChannelText();
                    showToast('AI Outreach drafts generated successfully! Refreshing contacts...');

                    // Re-fetch leads from server to confirm data saved correctly
                    const campaignId = outreachCampaignSelect ? outreachCampaignSelect.value : null;
                    if (campaignId) {
                        try {
                            const refreshRes = await fetch(`api/leads.php?campaign_id=${campaignId}`);
                            const refreshData = await refreshRes.json();
                            if (refreshData.success) {
                                state.outreachLeads = refreshData.leads;
                                // Update active lead reference with fresh server data
                                const freshLead = refreshData.leads.find(l => l.id == lead.id);
                                if (freshLead) {
                                    state.activeOutreachLead = freshLead;
                                }
                                renderOutreachLeadsList();
                                renderOutreachChannelText();
                                showToast('AI Outreach drafts generated and saved successfully!');
                            }
                        } catch (refreshErr) {
                            console.warn('Could not refresh contacts after generation:', refreshErr);
                            showToast('Drafts generated — refresh the page to see all changes.');
                        }
                    } else {
                        showToast('AI Outreach drafts generated successfully!');
                    }
                } else {
                    alert('Error generating outreach: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Failed to generate AI outreach', err);
                alert('Request failed: ' + err.message);
            } finally {
                outreachGenerateBtn.disabled = false;
                outreachGenerateBtn.innerHTML = originalText;
            }
        });
    }

    // Outreach Save Button
    if (outreachSaveBtn) {
        outreachSaveBtn.addEventListener('click', async () => {
            const lead = state.activeOutreachLead;
            if (!lead) return;

            outreachSaveBtn.disabled = true;
            const originalText = outreachSaveBtn.innerHTML;
            outreachSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch('api/leads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_drafts',
                        lead_id: lead.id,
                        email_draft: lead.email_draft || '',
                        whatsapp_draft: lead.whatsapp_draft || '',
                        sms_draft: lead.sms_draft || '',
                        calls_draft: lead.calls_draft || ''
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Draft outreach saved successfully.');
                } else {
                    alert('Error saving draft: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Failed to save outreach drafts', err);
                alert('Request failed: ' + err.message);
            } finally {
                outreachSaveBtn.disabled = false;
                outreachSaveBtn.innerHTML = originalText;
            }
        });
    }

    // Outreach Copy Button
    if (outreachCopyBtn) {
        outreachCopyBtn.addEventListener('click', () => {
            if (!outreachTextarea) return;
            const text = outreachTextarea.value;
            if (!text) {
                showToast('Nothing to copy.');
                return;
            }

            navigator.clipboard.writeText(text)
                .then(() => showToast('Draft copied to clipboard.'))
                .catch(err => {
                    console.error('Failed to copy', err);
                    outreachTextarea.select();
                    document.execCommand('copy');
                    showToast('Draft copied to clipboard.');
                });
        });
    }

    // Outreach Send Button
    if (outreachSendBtn) {
        outreachSendBtn.addEventListener('click', async () => {
            const lead = state.activeOutreachLead;
            if (!lead) return;

            const type = state.activeOutreachChannel;

            outreachSendBtn.disabled = true;
            const originalText = outreachSendBtn.innerHTML;
            outreachSendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                // First save the current draft contents
                const saveRes = await fetch('api/leads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_drafts',
                        lead_id: lead.id,
                        email_draft: lead.email_draft || '',
                        whatsapp_draft: lead.whatsapp_draft || '',
                        sms_draft: lead.sms_draft || '',
                        calls_draft: lead.calls_draft || ''
                    })
                });
                const saveData = await saveRes.json();
                if (!saveData.success) {
                    throw new Error(saveData.error || 'Failed to save drafts before sending.');
                }

                // Call outreach delivery endpoint
                const sendRes = await fetch('api/outreach.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lead_id: lead.id,
                        type: type
                    })
                });
                const sendData = await sendRes.json();
                if (sendData.success) {
                    showToast(sendData.message || 'Outreach sent successfully.');
                    
                    // Mark lead status as OUTREACHED locally
                    lead.status = 'OUTREACHED';
                    const found = state.outreachLeads.find(l => l.id == lead.id);
                    if (found) found.status = 'OUTREACHED';
                    
                    // Re-render sidebar lists/badges to show outreached status
                    renderOutreachLeadsList();
                    
                    // Refresh CRM data if they switch back later
                    loadLeadsCrmData();
                } else {
                    alert('Error sending outreach: ' + (sendData.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Failed to send outreach', err);
                alert('Request failed: ' + err.message);
            } finally {
                outreachSendBtn.disabled = false;
                outreachSendBtn.innerHTML = originalText;
            }
        });
    }

    const tabBtnOutreach = document.getElementById('tab-btn-outreach');
    if (tabBtnOutreach) {
        tabBtnOutreach.addEventListener('click', () => switchTab('outreach'));
    }

    // ----------------------------------------------------
    // OUTREACH MINI PANEL LOGIC
    // ----------------------------------------------------
    const outreachMiniCampaignSelect = document.getElementById('outreach-mini-campaign-select');

    function populateOutreachMiniCampaignSelect() {
        const select = document.getElementById('outreach-mini-campaign-select');
        if (!select) return;
        
        const prev = select.value;
        select.innerHTML = '<option value="">— Select Campaign —</option>';
        state.campaigns.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.title;
            select.appendChild(opt);
        });

        if (prev && [...select.options].some(o => o.value === prev)) {
            select.value = prev;
        } else if (state.activeCampaignId && [...select.options].some(o => o.value == state.activeCampaignId)) {
            select.value = state.activeCampaignId;
        }
    }

    if (outreachMiniCampaignSelect) {
        outreachMiniCampaignSelect.addEventListener('change', async () => {
            const campaignId = outreachMiniCampaignSelect.value;
            const listContainer = document.getElementById('outreach-mini-contacts-list');
            const countSpan = document.getElementById('outreach-mini-count');
            if (!listContainer) return;

            if (!campaignId) {
                listContainer.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">Select a campaign above to load contacts.</div>';
                if (countSpan) countSpan.textContent = '0 Leads';
                return;
            }

            listContainer.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            try {
                const res = await fetch(`api/leads.php?campaign_id=${campaignId}`);
                const data = await res.json();
                if (data.success) {
                    const leads = data.leads || [];
                    if (countSpan) countSpan.textContent = leads.length + ' Leads';
                    
                    listContainer.innerHTML = '';
                    if (leads.length === 0) {
                        listContainer.innerHTML = '<div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">No contacts found in this campaign.</div>';
                        return;
                    }

                    leads.forEach(lead => {
                        const item = document.createElement('div');
                        item.style.cssText = 'background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--border-radius-sm); padding:10px 12px; display:flex; justify-content:space-between; align-items:center; gap:10px; box-shadow:var(--shadow-sm);';
                        
                        const scoreClass = `score-${(lead.score || 'MEDIUM').toLowerCase()}`;
                        let leadStatus = (lead.status || 'GENERATED').toUpperCase();
                        if (leadStatus === 'GENERATED') leadStatus = 'QUALIFIED';

                        let statusClass = 'created';
                        if (leadStatus === 'OUTREACHED') statusClass = 'running';
                        if (leadStatus === 'CLOSED') statusClass = 'completed';

                        item.innerHTML = `
                            <div style="display:flex; flex-direction:column; gap:2px; flex-grow:1; min-width:0;">
                                <span style="font-weight:600; font-size:12px; color:var(--text-primary); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:180px;">${escapeHtml(lead.company_name)}</span>
                                <div style="display:flex; align-items:center; gap:6px; font-size:10px; color:var(--text-secondary);">
                                    <span style="text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:100px;">${escapeHtml(lead.contact_name)}</span>
                                    <span class="lead-score-badge ${scoreClass}" style="font-size:8px; padding:1px 4px;">${lead.score || 'MEDIUM'}</span>
                                    <span class="campaign-badge badge-${statusClass}" style="font-size:8px; padding:1px 4px;">${leadStatus.toLowerCase()}</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:6px; flex-shrink:0;">
                                <button class="action-icon-button quick-send-email-btn" data-id="${lead.id}" title="Quick Send Email Outreach" style="color:var(--accent-success); width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:50%; border:1px solid var(--border-color); background:var(--bg-secondary);">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button class="action-icon-button open-workstation-btn" data-id="${lead.id}" title="Open in Outreach Workstation" style="color:var(--accent-primary); width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:50%; border:1px solid var(--border-color); background:var(--bg-secondary);">
                                    <i class="fas fa-external-link-alt"></i>
                                </button>
                            </div>
                        `;

                        // Quick Send Email button handler
                        const quickSendBtn = item.querySelector('.quick-send-email-btn');
                        if (quickSendBtn) {
                            quickSendBtn.addEventListener('click', async (e) => {
                                e.stopPropagation();
                                
                                quickSendBtn.disabled = true;
                                const origHtml = quickSendBtn.innerHTML;
                                quickSendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                                try {
                                    if (!lead.email_draft) {
                                        showToast('Generating AI outreach drafts first...');
                                        const genRes = await fetch('api/generate-outreach.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ lead_id: lead.id })
                                        });
                                        const genData = await genRes.json();
                                        if (genData.success) {
                                            lead.email_draft = genData.email_draft;
                                            lead.whatsapp_draft = genData.whatsapp_draft;
                                            lead.sms_draft = genData.sms_draft;
                                        } else {
                                            throw new Error(genData.error || 'Failed to generate drafts.');
                                        }
                                    }

                                    const saveRes = await fetch('api/leads.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            action: 'update_drafts',
                                            lead_id: lead.id,
                                            email_draft: lead.email_draft || '',
                                            whatsapp_draft: lead.whatsapp_draft || '',
                                            sms_draft: lead.sms_draft || ''
                                        })
                                    });
                                    const saveData = await saveRes.json();
                                    if (!saveData.success) {
                                        throw new Error(saveData.error || 'Failed to save drafts.');
                                    }

                                    const sendRes = await fetch('api/outreach.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            lead_id: lead.id,
                                            type: 'email'
                                        })
                                    });
                                    const sendData = await sendRes.json();
                                    if (sendData.success) {
                                        showToast(sendData.message || 'Outreach email sent successfully.');
                                        lead.status = 'OUTREACHED';
                                        
                                        outreachMiniCampaignSelect.dispatchEvent(new Event('change'));
                                        
                                        if (state.activeOutreachLead && state.activeOutreachLead.id == lead.id) {
                                            state.activeOutreachLead.status = 'OUTREACHED';
                                        }
                                        const found = state.outreachLeads.find(l => l.id == lead.id);
                                        if (found) found.status = 'OUTREACHED';
                                        
                                        loadLeadsCrmData();
                                    } else {
                                        alert('Error sending: ' + (sendData.error || 'Unknown error'));
                                    }
                                } catch (err) {
                                    console.error('Quick send failed', err);
                                    alert('Quick send failed: ' + err.message);
                                } finally {
                                    quickSendBtn.disabled = false;
                                    quickSendBtn.innerHTML = origHtml;
                                }
                            });
                        }

                        // Open in Workstation handler
                        const openWorkstationBtn = item.querySelector('.open-workstation-btn');
                        if (openWorkstationBtn) {
                            openWorkstationBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                
                                const outreachCampSelect = document.getElementById('outreach-campaign-select');
                                if (outreachCampSelect) {
                                    outreachCampSelect.value = campaignId;
                                }
                                state.activeCampaignId = parseInt(campaignId);
                                state.activeOutreachLead = lead;
                                
                                switchTab('outreach');
                            });
                        }

                        listContainer.appendChild(item);
                    });
                } else {
                    showToast('Failed to load contacts.');
                }
            } catch (err) {
                console.error('Error loading contacts for mini panel', err);
                showToast('Error loading contacts.');
            }
        });
    }

    // Export visibility function globally for toggle config uses
    window.updateChatSectionVisibility = updateChatSectionVisibility;

    // Plugins Panel Helpers
    function loadPluginsList() {
        const container = document.getElementById('plugins-list-container');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align:center; padding:20px; color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading plugins...
            </div>
        `;

        fetch('api/plugins.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = `
                        <div style="text-align:center; padding:20px; color:var(--accent-error);">
                            <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(data.error || 'Failed to load plugins')}
                        </div>
                    `;
                    return;
                }

                const plugins = data.plugins || [];
                if (plugins.length === 0) {
                    container.innerHTML = `
                        <div style="text-align:center; padding:20px; color:var(--text-muted);">
                            No plugins found in the <code>plugins/</code> directory.
                        </div>
                    `;
                    return;
                }

                let html = '';
                plugins.forEach(p => {
                    const statusClass = p.active ? 'active-badge' : 'inactive-badge';
                    const statusLabel = p.active ? 'Active' : 'Inactive';
                    const btnLabel = p.active ? 'Deactivate' : 'Activate';
                    const btnClass = p.active ? 'btn-deactivate' : 'btn-activate';
                    
                    const statusStyle = p.active 
                        ? 'background: hsla(142, 70%, 45%, 0.15); color: #2ecc71; border: 1px solid hsla(142, 70%, 45%, 0.25);'
                        : 'background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border-color);';
                    
                    const btnStyle = p.active
                        ? 'background: var(--accent-error); border-color: var(--accent-error);'
                        : 'background: var(--accent-primary); border-color: var(--accent-primary);';

                    html += `
                        <div class="plugin-card" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:16px;">
                            <div style="flex-grow:1; padding-right:16px;">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <h4 style="margin:0; font-size:14px; color:var(--text-primary); font-weight:600;">${escapeHtml(p.name)}</h4>
                                    <span style="font-size:10px; padding:2px 6px; border-radius:4px; font-weight:bold; ${statusStyle}">${statusLabel}</span>
                                </div>
                                <p style="margin:0 0 4px 0; font-size:11px; color:var(--text-secondary);">${escapeHtml(p.description)}</p>
                                <div style="font-size:10px; color:var(--text-muted); display:flex; gap:12px;">
                                    <span>Version: ${escapeHtml(p.version)}</span>
                                    <span>Author: ${escapeHtml(p.author)}</span>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn-primary toggle-plugin-btn ${btnClass}" data-plugin="${escapeHtml(p.id)}" data-action="${p.active ? 'deactivate' : 'activate'}" style="padding:6px 12px; font-size:11px; margin-top:0; ${btnStyle}">
                                    ${btnLabel}
                                </button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;

                // Wire up click handlers
                container.querySelectorAll('.toggle-plugin-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const pluginId = btn.dataset.plugin;
                        const action = btn.dataset.action;
                        togglePlugin(pluginId, action, btn);
                    });
                });
            })
            .catch(err => {
                container.innerHTML = `
                    <div style="text-align:center; padding:20px; color:var(--accent-error);">
                        <i class="fas fa-exclamation-triangle"></i> Failed to connect to server.
                    </div>
                `;
            });
    }

    function togglePlugin(pluginId, action, button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        fetch('api/plugins.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plugin: pluginId, action: action })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Operation successful.');
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                showToast(data.error || 'Failed to toggle plugin.', true);
                button.disabled = false;
                button.innerHTML = action === 'activate' ? 'Activate' : 'Deactivate';
            }
        })
        .catch(err => {
            showToast('Network error while toggling plugin.', true);
            button.disabled = false;
            button.innerHTML = action === 'activate' ? 'Activate' : 'Deactivate';
        });
    }

});

// Alias so old inline submit listener doesn't double-fire
// (The original settingsForm submit listener from line ~218 handles OLD call path; we override at bottom)
void 0;
