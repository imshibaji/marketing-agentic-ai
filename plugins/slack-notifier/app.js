/**
 * Slack Notifier Plugin - Frontend Script
 */
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
