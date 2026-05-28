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

// 3. Log and dispatch Slack actions on lead qualifications
HookManager::addAction('lead_qualified', function(int $leadId, int $campaignId, array $lead): void {
    $db = PluginManager::getDatabaseService();
    if ($db) {
        $settings = $db->getSettings();
        $webhook = $settings['slack_webhook_url'] ?? 'https://hooks.slack.com/services/mock/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX';
        
        $companyName = $lead['company_name'] ?? 'Target Corp';
        $contactName = $lead['contact_name'] ?? 'Decision Maker';
        $score = $lead['score'] ?? 'MEDIUM';
        
        $logText = "[Slack Notifier Plugin] Dynamic alert dispatched to Slack. Prospect: '{$companyName}' ({$contactName}) Qualified fit: {$score}. Webhook target: '{$webhook}'";
        
        // Prepare the Slack webhook payload
        $payload = [
            'text' => "🎯 *New Lead Qualified!*\n*Company:* {$companyName}\n*Contact:* " . ($contactName ?: 'N/A') . "\n*Fit Score:* `{$score}`",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🎯 New Lead Qualified!',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Company:*\n{$companyName}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Contact Name:*\n" . ($contactName ?: 'N/A')
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Fit Score:*\n`{$score}`"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Industry:*\n" . ($lead['industry'] ?? 'N/A')
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Reasoning:*\n" . ($lead['reasoning'] ?? 'N/A')
                    ]
                ]
            ]
        ];

        // Send HTTP POST request to Slack Webhook (if it is a real URL and not the default mock URL)
        if (!empty($settings['slack_webhook_url']) && strpos($settings['slack_webhook_url'], 'http') === 0 && strpos($settings['slack_webhook_url'], 'mock') === false) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 5]);
                $response = $client->post($settings['slack_webhook_url'], [
                    'json' => $payload
                ]);
                $httpCode = $response->getStatusCode();
                $result = (string)$response->getBody();
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    $logText .= " [Status: Dispatched Successfully]";
                } else {
                    $logText .= " [Status: Failed with HTTP code {$httpCode}. Response: {$result}]";
                }
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $httpCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'none';
                $result = $e->hasResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
                $logText .= " [Status: Failed with HTTP code {$httpCode}. Error: {$result}]";
            } catch (\Throwable $t) {
                $logText .= " [Status: Webhook Error: " . $t->getMessage() . "]";
            }
        }

        $db->logAgentAction($campaignId, 'Slack SDR Notifier', 'SLACK_ALERT', $logText);
    }
});
