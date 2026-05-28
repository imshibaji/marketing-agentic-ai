<?php
/**
 * Plugin Name: Notification Widget
 * Description: Adds a premium dashboard widget for monitoring system alerts, user mentions, and quick notification handling (marking as read/dismissing).
 */

use MarketingAgent\Plugin\HookManager;
use MarketingAgent\Plugin\PluginManager;

// 1. Whitelist the settings keys for the notification widget config
HookManager::addFilter('admin_settings_allowed_keys', function(array $keys): array {
    $keys[] = 'notification_widget_max_display';
    $keys[] = 'notification_widget_refresh_rate';
    $keys[] = 'notification_widget_sound_enabled';
    return $keys;
});

// 2. Set default values for the settings if they are not defined
HookManager::addFilter('admin_get_settings', function(array $settings): array {
    if (!isset($settings['notification_widget_max_display'])) {
        $settings['notification_widget_max_display'] = '5';
    }
    if (!isset($settings['notification_widget_refresh_rate'])) {
        $settings['notification_widget_refresh_rate'] = '15';
    }
    if (!isset($settings['notification_widget_sound_enabled'])) {
        $settings['notification_widget_sound_enabled'] = '1';
    }
    return $settings;
});

// 3. Register custom API endpoint to simulate mock notifications for demonstration/testing
// Triggered via GET/POST /api/plugin-route.php?plugin=notification-widget&action=simulate
HookManager::addAction('api_route_notification-widget_simulate', function(array $inputData): void {
    $db = PluginManager::getDatabaseService();
    if (!$db) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database service not available.']);
        exit;
    }

    $pdo = $db->getPdo();
    $session = \Config\Services::session();
    $user = $session->get('user');
    $currentUsername = $user['username'] ?? 'User';
    $currentUserId = $user['id'] ?? 1;

    // Optional override params
    $type = $inputData['type'] ?? 'random';
    
    if ($type === 'mention') {
        $title = "🔔 New User Mention Alert";
        $message = "Hey @{$currentUsername}, you were tagged in this alert. The AI Agent completed lead qualification for Campaign #1. Please confirm closing steps.";
    } else {
        $titles = [
            "🔥 High-Value Lead Qualified",
            "⚡ System Service Update",
            "🤖 Lead Scraping Finished",
            "📧 Outbound Campaign Dispatched",
            "⚠️ Usage Threshold Approaching"
        ];
        
        $messages = [
            "AI SDR agent successfully qualified 'Nexus Enterprises' (Fit Score: 98/100, Industry: SaaS). Ready for sales closing.",
            "Database maintenance completed successfully. All lead tables optimized in 18ms.",
            "Campaign 'Product Launch Q3' lead grabbing completed. 24 new qualified leads added to CRM.",
            "Bulk email outreach sequences dispatched for 50 leads in the campaign queue.",
            "Your email quota usage is at 82% of your Default Plan monthly limit."
        ];
        
        $randomIndex = array_rand($titles);
        $title = $titles[$randomIndex];
        $message = $messages[$randomIndex];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$currentUserId, $title, $message]);
        $notificationId = $pdo->lastInsertId();

        $db->logActivity($currentUserId, 'SIMULATED_NOTIFICATION', "Simulated notification '{$title}'");

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Simulated notification created successfully.',
            'notification_id' => $notificationId,
            'notification' => [
                'id' => $notificationId,
                'sender_id' => $currentUserId,
                'title' => $title,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
                'sender_username' => $currentUsername,
                'sender_role' => $user['role'] ?? 'user'
            ]
        ]);
        exit;
    } catch (\Throwable $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
});
