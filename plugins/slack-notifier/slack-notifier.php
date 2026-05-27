<?php
/**
 * Plugin Name: Slack Notifier
 * Description: Logs lead qualifications as simulated Slack notifications and adds Slack Webhook configuration to settings.
 */

use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// 1. Register custom settings keys in the allowed whitelist
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'slack_webhook_url';
    return $keys;
});

// 2. Return masked slack webhook URL to prevent leaks in settings lists
HookManager::addFilter('admin_get_settings', function(array $settings): array {
    if (!empty($settings['slack_webhook_url'])) {
        $url = $settings['slack_webhook_url'];
        // Mask it if it looks like a real URL
        if (strlen($url) > 16) {
            $settings['slack_webhook_url_masked'] = substr($url, 0, 12) . '...' . substr($url, -8);
        } else {
            $settings['slack_webhook_url_masked'] = '***masked***';
        }
    } else {
        $settings['slack_webhook_url_masked'] = '';
    }
    return $settings;
});

// 3. Log simulated Slack actions on lead qualifications
HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    $db = PluginManager::getDatabaseService();
    if ($db) {
        $settings = $db->getSettings();
        $webhook = $settings['slack_webhook_url'] ?? 'https://hooks.slack.com/services/mock/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX';
        
        $companyName = $lead['company_name'] ?? 'Target Corp';
        $contactName = $lead['contact_name'] ?? 'Decision Maker';
        $score = $lead['score'] ?? 'MEDIUM';
        
        // Log action inside system activity logs
        $logText = "[Slack Notifier Plugin] Dynamic alert dispatched to Slack. Prospect: '{$companyName}' ({$contactName}) Qualified fit: {$score}. Webhook target: '{$webhook}'";
        $db->logAgentAction($campaignId, 'Slack SDR Notifier', 'SLACK_ALERT', $logText);
    }
});
