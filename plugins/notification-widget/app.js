/**
 * Notification Center Widget Plugin - Client script
 */
(function() {
    function getRealElement(id) {
        const el = Document.prototype.getElementById.call(document, id);
        return (el && el instanceof HTMLElement) ? el : null;
    }

    let currentUser = null;
    let settings = {
        maxDisplay: 5,
        refreshRate: 15,
        soundEnabled: true
    };
    let allNotifications = [];
    let knownNotificationIds = new Set();
    let readNotificationIds = new Set();
    let pollIntervalId = null;
    let recentMessages = new Set();

    // Load active settings from server
    async function loadPluginSettings() {
        try {
            const res = await fetch('api/settings.php');
            const data = await res.json();
            if (data.success && data.settings) {
                settings.maxDisplay = parseInt(data.settings.notification_widget_max_display || '5', 10);
                settings.refreshRate = parseInt(data.settings.notification_widget_refresh_rate || '15', 10);
                settings.soundEnabled = data.settings.notification_widget_sound_enabled !== '0';
            }
        } catch (e) {
            console.error('[Notification Widget] Failed to load settings:', e);
        }
    }

    // Save read states to LocalStorage
    function saveReadState() {
        if (!currentUser) return;
        localStorage.setItem(
            `notif_widget_read_${currentUser.username}`, 
            JSON.stringify(Array.from(readNotificationIds))
        );
    }

    // Load read states from LocalStorage
    function loadReadState() {
        if (!currentUser) return;
        const saved = localStorage.getItem(`notif_widget_read_${currentUser.username}`);
        if (saved) {
            try {
                const ids = JSON.parse(saved);
                readNotificationIds = new Set(ids);
            } catch (e) {
                readNotificationIds = new Set();
            }
        } else {
            readNotificationIds = new Set();
        }
    }

    // Synthesize a pleasant chime notification sound using the Web Audio API
    function playChime() {
        if (!settings.soundEnabled) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gainNode = ctx.createGain();
            
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
            osc1.frequency.exponentialRampToValueAtTime(783.99, ctx.currentTime + 0.15); // G5
            
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(659.25, ctx.currentTime + 0.08); // E5
            osc2.frequency.exponentialRampToValueAtTime(1046.50, ctx.currentTime + 0.25); // C6
            
            gainNode.gain.setValueAtTime(0.08, ctx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            
            osc1.connect(gainNode);
            osc2.connect(gainNode);
            gainNode.connect(ctx.destination);
            
            osc1.start();
            osc1.stop(ctx.currentTime + 0.4);
            
            osc2.start(ctx.currentTime + 0.08);
            osc2.stop(ctx.currentTime + 0.4);
        } catch (e) {
            console.error('[Notification Widget] Audio chime error:', e);
        }
    }

    // Ensure container and styles exist
    function ensureToastContainer() {
        injectStyles();
        let toastContainer = getRealElement('notif-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'notif-toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 10110;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            `;
            document.body.appendChild(toastContainer);
        }
        return toastContainer;
    }

    // Global Hub initialization
    async function initNotificationHub() {
        injectStyles();
        ensureToastContainer();
        
        // Fetch current user
        try {
            const authRes = await fetch('api/auth-status.php');
            const authData = await authRes.json();
            if (authData.success) {
                currentUser = authData.user;
                loadReadState();
            }
        } catch (e) {
            console.error('[Notification Widget] Auth check failed:', e);
        }
    }

    // Show sliding toast alert for new notifications
    function showToastAlert(notification) {
        const toastContainer = ensureToastContainer();
        if (!toastContainer) return;

        if (notification.message) {
            const msgKey = notification.message.trim().toLowerCase();
            recentMessages.add(msgKey);
            setTimeout(() => {
                recentMessages.delete(msgKey);
            }, 30000);
        }

        const toast = document.createElement('div');
        toast.className = 'notif-toast-item';
        toast.style.cssText = `
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-sm);
            padding: 16px;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-width: 320px;
            animation: slideInNotif 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            position: relative;
            pointer-events: auto;
            border-left: 4px solid var(--accent-primary);
        `;

        // Customize left border depending on priority or mention
        const isMention = currentUser && notification.message.includes(`@${currentUser.username}`);
        if (isMention) {
            toast.style.borderLeftColor = 'var(--accent-primary)'; // Violet/Purple
        } else if (notification.title.toLowerCase().includes('lead') || notification.title.toLowerCase().includes('high-value')) {
            toast.style.borderLeftColor = 'var(--accent-success)'; // Green
        } else if (notification.title.toLowerCase().includes('alert') || notification.title.toLowerCase().includes('warning') || notification.title.toLowerCase().includes('limit')) {
            toast.style.borderLeftColor = 'var(--accent-warning)'; // Amber
        }

        toast.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                <span style="font-weight:700; font-size:13px; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                    <i class="fas ${isMention ? 'fa-at' : 'fa-bell'}" style="color:${isMention ? 'var(--accent-primary)' : 'var(--accent-secondary)'}"></i>
                    ${escapeHtml(notification.title)}
                </span>
                <button class="toast-close-btn" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px; display:inline-flex; align-items:center;"><i class="fas fa-times"></i></button>
            </div>
            <div style="font-size:11px; color:var(--text-secondary); line-height:1.4; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
                ${escapeHtml(notification.message)}
            </div>
        `;

        toast.querySelector('.toast-close-btn').addEventListener('click', () => {
            toast.style.animation = 'slideOutNotif 0.2s ease-in forwards';
            setTimeout(() => toast.remove(), 250);
        });

        toastContainer.appendChild(toast);
        playChime();

        // Auto dismiss after 6 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOutNotif 0.2s ease-in forwards';
                setTimeout(() => toast.remove(), 250);
            }
        }, 6000);
    }

    // Helper: Escape HTML string
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Helper: Format @mentions to be highlighted spans
    function formatMentionsText(text) {
        if (!text) return '';
        return text.replace(/@([a-zA-Z0-9_-]+)/g, (match, username) => {
            const isMe = currentUser && username === currentUser.username;
            return `<span style="font-weight:600; padding:2px 6px; border-radius:4px; font-size:11px; ${
                isMe 
                    ? 'background:rgba(var(--accent-primary-rgb), 0.15); color:var(--accent-primary); border: 1px solid rgba(var(--accent-primary-rgb), 0.3);' 
                    : 'background:var(--bg-primary); color:var(--text-secondary); border: 1px solid var(--border-color);'
            }">@${escapeHtml(username)}</span>`;
        });
    }

    // Render stats inside the widget
    function renderStats() {
        const totalCount = allNotifications.length;
        const unreadCount = allNotifications.filter(n => !readNotificationIds.has(n.id)).length;
        
        let mentionCount = 0;
        if (currentUser) {
            mentionCount = allNotifications.filter(n => n.message.includes(`@${currentUser.username}`)).length;
        }

        const totalBadge = getRealElement('widget-stat-total');
        const unreadBadge = getRealElement('widget-stat-unread');
        const mentionBadge = getRealElement('widget-stat-mentions');

        if (totalBadge) totalBadge.textContent = totalCount;
        if (unreadBadge) {
            unreadBadge.textContent = unreadCount;
            const card = unreadBadge.closest('.widget-stat-card');
            if (card) {
                if (unreadCount > 0) {
                    card.style.borderColor = 'rgba(var(--accent-primary-rgb), 0.4)';
                    unreadBadge.style.animation = 'pulse-slow 2s infinite';
                } else {
                    card.style.borderColor = 'var(--border-color)';
                    unreadBadge.style.animation = 'none';
                }
            }
        }
        if (mentionBadge) {
            mentionBadge.textContent = mentionCount;
            const card = mentionBadge.closest('.widget-stat-card');
            if (card && mentionCount > 0) {
                card.style.borderColor = 'rgba(139, 92, 246, 0.4)'; // violet
            } else if (card) {
                card.style.borderColor = 'var(--border-color)';
            }
        }
    }

    // Render the notifications list inside the widget
    function renderNotificationsList() {
        const listContainer = getRealElement('notif-widget-list');
        if (!listContainer) return;

        const filterValue = getRealElement('notif-filter')?.value || 'all';
        const searchValue = (getRealElement('notif-search')?.value || '').toLowerCase().trim();

        // 1. Apply filtering
        let filtered = allNotifications;

        if (filterValue === 'unread') {
            filtered = filtered.filter(n => !readNotificationIds.has(n.id));
        } else if (filterValue === 'mentions') {
            if (currentUser) {
                filtered = filtered.filter(n => n.message.includes(`@${currentUser.username}`));
            }
        } else if (filterValue === 'high') {
            filtered = filtered.filter(n => {
                const title = n.title.toLowerCase();
                const msg = n.message.toLowerCase();
                return title.includes('lead') || title.includes('high-value') || title.includes('alert') || title.includes('limit') || msg.includes('urgent') || msg.includes('critical');
            });
        }

        // 2. Apply search
        if (searchValue) {
            filtered = filtered.filter(n => 
                n.title.toLowerCase().includes(searchValue) || 
                n.message.toLowerCase().includes(searchValue)
            );
        }

        // 3. Render
        listContainer.innerHTML = '';
        
        if (filtered.length === 0) {
            listContainer.innerHTML = `
                <div style="text-align:center; padding:30px; color:var(--text-muted); font-size:12px; font-style:italic;">
                    No matching alerts found.
                </div>
            `;
            return;
        }

        // Apply slice based on max display count (only if not searching/filtering)
        let displayList = filtered;
        if (filterValue === 'all' && !searchValue) {
            displayList = filtered.slice(0, settings.maxDisplay);
        }

        displayList.forEach(notif => {
            const isRead = readNotificationIds.has(notif.id);
            const isMention = currentUser && notif.message.includes(`@${currentUser.username}`);
            
            const card = document.createElement('div');
            card.className = 'notif-widget-item';
            if (isRead) card.classList.add('notif-read');
            if (isMention) card.classList.add('notif-mention');

            card.style.cssText = `
                background: var(--bg-primary);
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius-sm);
                padding: 12px 14px;
                display: flex;
                flex-direction: column;
                gap: 6px;
                transition: all 0.25s ease;
                position: relative;
                border-left: 4px solid var(--border-color);
                cursor: pointer;
            `;

            // Style left border color depending on category/mentions
            if (isMention) {
                card.style.borderLeftColor = 'var(--accent-primary)'; // purple/violet
            } else if (notif.title.toLowerCase().includes('lead') || notif.title.toLowerCase().includes('high-value')) {
                card.style.borderLeftColor = 'var(--accent-success)'; // green
            } else if (notif.title.toLowerCase().includes('alert') || notif.title.toLowerCase().includes('warning') || notif.title.toLowerCase().includes('limit')) {
                card.style.borderLeftColor = 'var(--accent-warning)'; // amber
            }

            const checkButton = !isRead ? `
                <button class="notif-read-btn" data-id="${notif.id}" title="Mark as Read" style="background:transparent; border:none; color:var(--accent-success); cursor:pointer; font-size:12px; padding:4px; display:inline-flex; align-items:center; transition:transform 0.2s ease;">
                    <i class="fas fa-check"></i>
                </button>
            ` : `
                <span style="color:var(--text-muted); font-size:11px; font-style:italic;"><i class="fas fa-check-double"></i> Read</span>
            `;

            const relativeTime = getRelativeTime(notif.created_at);

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                    <span style="font-weight:700; font-size:13px; color:var(--text-primary); transition: color 0.25s ease; text-decoration: ${isRead ? 'line-through' : 'none'}; opacity: ${isRead ? '0.6' : '1'}">
                        ${escapeHtml(notif.title)}
                    </span>
                    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                        <span style="font-size:10px; color:var(--text-muted); white-space:nowrap;">${relativeTime}</span>
                        ${checkButton}
                    </div>
                </div>
                <div class="notif-msg" style="font-size:12px; color:var(--text-secondary); line-height:1.4; transition: opacity 0.25s ease; opacity: ${isRead ? '0.6' : '1'}">
                    ${formatMentionsText(notif.message)}
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:10px; color:var(--text-muted); margin-top:4px; border-top:1px solid var(--border-color); padding-top:6px; opacity: 0.8;">
                    <span><i class="fas fa-user-circle"></i> ${escapeHtml(notif.sender_username || 'System')}</span>
                    ${isMention ? '<span style="color:var(--accent-primary); font-weight:600;"><i class="fas fa-at"></i> Mentioned</span>' : ''}
                </div>
            `;

            // Hover micro-animation
            card.addEventListener('mouseenter', () => {
                if (!isRead) {
                    card.style.transform = 'translateY(-1px)';
                    card.style.boxShadow = 'var(--shadow-md)';
                }
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'none';
                card.style.boxShadow = 'none';
            });

            // Mark individual read
            const readBtn = card.querySelector('.notif-read-btn');
            if (readBtn) {
                readBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const notifId = parseInt(readBtn.getAttribute('data-id'), 10);
                    readNotificationIds.add(notifId);
                    saveReadState();
                    renderStats();
                    renderNotificationsList();
                });
            }

            listContainer.appendChild(card);
        });
    }

    // Helper: Human-readable relative time
    function getRelativeTime(dateStr) {
        try {
            const date = new Date(dateStr);
            const seconds = Math.floor((new Date() - date) / 1000);
            
            if (seconds < 5) return 'Just now';
            if (seconds < 60) return `${seconds}s ago`;
            
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `${minutes}m ago`;
            
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            
            return date.toLocaleDateString();
        } catch (e) {
            return dateStr;
        }
    }

    // Refresh notifications list from endpoint
    async function refreshNotifications(silent = false) {
        try {
            const res = await fetch('api/notifications.php');
            const data = await res.json();
            if (data.success && data.notifications) {
                allNotifications = data.notifications;
                
                // Track any *newly* arrived notifications
                let hasNew = false;
                let lastNewNotif = null;

                data.notifications.forEach(notif => {
                    if (!knownNotificationIds.has(notif.id)) {
                        knownNotificationIds.add(notif.id);
                        if (!silent) {
                            const msgKey = (notif.message || '').trim().toLowerCase();
                            if (!recentMessages.has(msgKey)) {
                                hasNew = true;
                                lastNewNotif = notif;
                            }
                        }
                    }
                });

                // Re-render
                renderStats();
                renderNotificationsList();

                // Trigger alert if a new notification is fetched
                if (hasNew && lastNewNotif) {
                    showToastAlert(lastNewNotif);
                }
            }
        } catch (e) {
            console.error('[Notification Widget] Failed to refresh notifications:', e);
        }
    }

    // Inject App Settings fields
    function injectSettingsFields() {
        const appPanel = getRealElement('app-panel');
        if (!appPanel) return;

        // Check if already injected
        if (getRealElement('notification_widget_max_display')) return;

        const container = document.createElement('div');
        container.style.cssText = `
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed var(--border-color);
        `;

        container.innerHTML = `
            <h4 style="margin: 0 0 12px; font-size: 13px; color: var(--accent-primary); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-bell"></i> Notification Widget Settings
            </h4>
            <div class="form-row">
                <div class="form-group">
                    <label for="notification_widget_max_display">Max Alerts to List</label>
                    <input type="number" id="notification_widget_max_display" name="notification_widget_max_display" min="1" max="50" placeholder="5" style="width:100%;">
                </div>
                <div class="form-group">
                    <label for="notification_widget_refresh_rate">Auto-Refresh Rate (seconds)</label>
                    <input type="number" id="notification_widget_refresh_rate" name="notification_widget_refresh_rate" min="5" max="300" placeholder="15" style="width:100%;">
                </div>
            </div>
            <div class="form-group">
                <label for="notification_widget_sound_enabled">Widget Sound Alerts</label>
                <select id="notification_widget_sound_enabled" name="notification_widget_sound_enabled" style="width:100%;">
                    <option value="1">Enabled (Chime audio notification)</option>
                    <option value="0">Disabled (Silent)</option>
                </select>
            </div>
        `;
        appPanel.appendChild(container);
    }

    // Injects HTML/CSS styles to head
    function injectStyles() {
        if (getRealElement('notif-widget-styles')) return;

        const style = document.createElement('style');
        style.id = 'notif-widget-styles';
        style.textContent = `
            /* Slide in toast alerts */
            @keyframes slideInNotif {
                from { transform: translateX(120%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutNotif {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(120%); opacity: 0; }
            }
            .notif-toast-item {
                transition: transform 0.2s ease, opacity 0.2s ease;
            }
            .widget-stat-card {
                background: var(--bg-primary);
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius-sm);
                padding: 10px 14px;
                text-align: center;
                transition: all 0.2s ease;
            }
            .widget-stat-card:hover {
                transform: scale(1.02);
            }
            .notif-widget-item.notif-read {
                border-left-color: var(--border-color) !important;
                background: rgba(var(--border-color), 0.05);
            }
            /* Sparkle/Pulse effect for mentions */
            .notif-widget-item.notif-mention {
                background: linear-gradient(135deg, rgba(var(--accent-primary-rgb), 0.03), rgba(139, 92, 246, 0.03));
            }
            #notif-widget-list::-webkit-scrollbar {
                width: 6px;
            }
            #notif-widget-list::-webkit-scrollbar-track {
                background: transparent;
            }
            #notif-widget-list::-webkit-scrollbar-thumb {
                background-color: var(--border-color);
                border-radius: 3px;
            }
        `;
        document.head.appendChild(style);
    }

    // Build and inject the dashboard widget
    async function injectDashboardWidget() {
        const dashboardTab = getRealElement('tab-admin-dashboard');
        if (!dashboardTab) return;

        // Check if already injected
        if (getRealElement('notification-center-widget')) return;

        // Setup local read states if not already done
        if (!currentUser) {
            await initNotificationHub();
        } else {
            loadReadState();
        }

        const widgetCard = document.createElement('div');
        widgetCard.id = 'notification-center-widget';
        widgetCard.className = 'panel-section card-box';
        widgetCard.style.cssText = `
            margin-bottom: 24px;
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
        `;

        widgetCard.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:12px; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                <h3 style="margin:0; font-size:16px; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-bell" style="color:var(--accent-primary); animation: pulse-slow 3s infinite;"></i> 
                    <span>Live Notification Hub</span>
                </h3>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <input type="text" id="notif-search" placeholder="Search alerts..." style="width:140px; padding:6px 10px; font-size:11px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none;">
                    
                    <select id="notif-filter" style="padding:6px 10px; font-size:11px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none;">
                        <option value="all">All Alerts</option>
                        <option value="unread">Unread</option>
                        <option value="mentions">Mentions (@)</option>
                        <option value="high">High Priority</option>
                    </select>

                    <button id="notif-mark-all-read" class="btn-secondary" style="padding:6px 10px; font-size:11px; margin:0; height:auto; display:flex; align-items:center; gap:4px;">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                    
                    <button id="notif-simulate-btn" class="btn-primary" style="padding:6px 10px; font-size:11px; margin:0; height:auto; display:flex; align-items:center; gap:4px; font-weight:500; box-shadow:none;">
                        <i class="fas fa-vial"></i> Simulate
                    </button>
                    
                    <button id="notif-simulate-mention-btn" class="btn-secondary" style="padding:6px 10px; font-size:11px; margin:0; height:auto; display:flex; align-items:center; gap:4px;">
                        <i class="fas fa-at"></i> Mention Me
                    </button>
                </div>
            </div>

            <!-- Stats strip -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin-bottom:16px;">
                <div class="widget-stat-card">
                    <div style="font-size:10px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Total Alerts</div>
                    <div id="widget-stat-total" style="font-size:18px; font-weight:700; color:var(--text-primary);">0</div>
                </div>
                <div class="widget-stat-card">
                    <div style="font-size:10px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">Unread</div>
                    <div id="widget-stat-unread" style="font-size:18px; font-weight:700; color:var(--accent-warning);">0</div>
                </div>
                <div class="widget-stat-card">
                    <div style="font-size:10px; text-transform:uppercase; color:var(--text-secondary); font-weight:600; letter-spacing:0.5px; margin-bottom:4px;">My Mentions</div>
                    <div id="widget-stat-mentions" style="font-size:18px; font-weight:700; color:var(--accent-primary);">0</div>
                </div>
            </div>

            <!-- Scrollable Alerts list -->
            <div id="notif-widget-list" style="max-height:220px; overflow-y:auto; display:flex; flex-direction:column; gap:8px; padding-right:4px;">
                <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">
                    <i class="fas fa-sync fa-spin"></i> Initializing notifications...
                </div>
            </div>
        `;

        // Position it: below stats grid
        const adminStats = getRealElement('admin-only-stats');
        const userStats = getRealElement('user-only-stats');
        const insertionTarget = userStats || adminStats;

        if (insertionTarget) {
            insertionTarget.parentNode.insertBefore(widgetCard, insertionTarget.nextSibling);
        } else {
            dashboardTab.appendChild(widgetCard);
        }

        // Add event listeners for widget controls
        getRealElement('notif-search').addEventListener('input', renderNotificationsList);
        getRealElement('notif-filter').addEventListener('change', renderNotificationsList);
        
        // Mark all read button
        getRealElement('notif-mark-all-read').addEventListener('click', () => {
            const visibleItems = allNotifications;
            if (visibleItems.length === 0) return;
            visibleItems.forEach(n => readNotificationIds.add(n.id));
            saveReadState();
            renderStats();
            renderNotificationsList();
            showToast('All notifications marked as read.');
        });

        // Simulate Alert Button
        getRealElement('notif-simulate-btn').addEventListener('click', async () => {
            const btn = getRealElement('notif-simulate-btn');
            btn.disabled = true;
            try {
                const response = await fetch('api/plugin-route.php?plugin=notification-widget&action=simulate', {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success && data.notification) {
                    // Prepend the new notification locally
                    allNotifications.unshift(data.notification);
                    knownNotificationIds.add(data.notification.id);
                    renderStats();
                    renderNotificationsList();
                    showToastAlert(data.notification);
                } else {
                    alert('Simulation failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('[Notification Widget] Simulation call failed:', err);
            } finally {
                btn.disabled = false;
            }
        });

        // Simulate @Mention Button
        getRealElement('notif-simulate-mention-btn').addEventListener('click', async () => {
            const btn = getRealElement('notif-simulate-mention-btn');
            btn.disabled = true;
            try {
                const response = await fetch('api/plugin-route.php?plugin=notification-widget&action=simulate&type=mention', {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success && data.notification) {
                    allNotifications.unshift(data.notification);
                    knownNotificationIds.add(data.notification.id);
                    renderStats();
                    renderNotificationsList();
                    showToastAlert(data.notification);
                } else {
                    alert('Simulation failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('[Notification Widget] Simulation call failed:', err);
            } finally {
                btn.disabled = false;
            }
        });

        // First initial pull - run silently so it doesn't play chime for legacy items
        await refreshNotifications(true);

        // Set up polling loop
        if (pollIntervalId) clearInterval(pollIntervalId);
        pollIntervalId = setInterval(() => {
            refreshNotifications(false);
        }, settings.refreshRate * 1000);
    }

    // Bootstrap hook execution
    if (window.AppHooks) {
        window.AppHooks.addFilter('show_toast', function(handled, message, type) {
            let title = '⚡ Performance Event';
            const lowerMessage = message.toLowerCase();
            const lowerType = (type || '').toLowerCase();
            
            if (lowerType === 'error' || lowerMessage.includes('fail') || lowerMessage.includes('error') || lowerMessage.includes('forbidden')) {
                title = '⚠️ Alert / Warning';
            } else if (lowerMessage.includes('lead') || lowerMessage.includes('prospect')) {
                title = '🔥 Lead Action';
            } else if (lowerMessage.includes('campaign')) {
                title = '🤖 Campaign Operation';
            } else if (lowerMessage.includes('outreach') || lowerMessage.includes('sent')) {
                title = '📧 Outreach Dispatched';
            } else if (lowerMessage.includes('plan') || lowerMessage.includes('usage') || lowerMessage.includes('reset') || lowerMessage.includes('renew')) {
                title = '⚙️ Limit / Usage Reset';
            } else if (lowerMessage.includes('database') || lowerMessage.includes('backup') || lowerMessage.includes('restore') || lowerMessage.includes('demo')) {
                title = '📦 System Maintenance';
            } else if (lowerMessage.includes('saved') || lowerMessage.includes('created') || lowerMessage.includes('updated')) {
                title = '💾 Save Performance';
            }

            showToastAlert({
                id: Date.now() + Math.random(),
                title: title,
                message: message,
                created_at: new Date().toISOString(),
                sender_username: currentUser ? currentUser.username : 'System'
            });
            return true;
        });

        window.AppHooks.addAction('dom_ready', async function() {
            // Initialize global elements (styles, container, user status)
            await initNotificationHub();

            // Load configuration
            await loadPluginSettings();

            // Inject app settings panel inputs
            injectSettingsFields();

            // Inject dashboard widget
            await injectDashboardWidget();
        });

        // Handle settings save configuration tab refresh
        window.AppHooks.addAction('tab_switched', async function(tabId) {
            // When user switches to dashboard, refresh notifications and settings
            if (tabId === 'tab-admin-dashboard' || tabId === 'tab-btn-dashboard') {
                await loadPluginSettings();
                await injectDashboardWidget();
                refreshNotifications(true);
            }
        });
    }
})();
