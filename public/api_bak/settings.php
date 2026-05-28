<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST is admin-only
        $user = AuthService::getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can update settings.']);
            exit;
        }

        // Read JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }

        // Whitelist settings keys to save
        $allowedKeys = [
            'app_name',
            'llm_provider',
            'gemini_api_key',
            'gemini_model',
            'lm_studio_url',
            'lm_studio_model',
            'ollama_url',
            'ollama_model',
            'lm_studio_api_key',
            'lm_studio_extra_model',
            'ollama_api_key',
            'ollama_extra_model',
            'smtp_host',
            'smtp_port',
            'smtp_user',
            'smtp_pass',
            'smtp_from_email',
            'smtp_from_name',
            'whatsapp_token',
            'whatsapp_phone_id',
            'sms_provider',
            'sms_twilio_account_sid',
            'sms_twilio_auth_token',
            'sms_twilio_from_number',
            'sms_custom_url',
            'sms_custom_method',
            'sms_custom_headers',
            'sms_custom_body',
            'enable_public_chat',
            'enable_public_notifications',
            'gemini_active',
            'lm_studio_active',
            'ollama_active',
        ];

        // Let plugins whitelist their own settings keys
        $allowedKeys = \MarketingAgent\Plugin\HookManager::applyFilters('admin_settings_allowed_keys', $allowedKeys);

        $settingsToSave = [];
        foreach ($allowedKeys as $key) {
            if (isset($input[$key])) {
                $settingsToSave[$key] = trim($input[$key]);
            }
        }

        $db->saveSettings($settingsToSave);
        echo json_encode(['success' => true, 'message' => 'System settings saved successfully.']);
    } else {
        // GET request — return settings
        // Non-admins and unauthenticated users can read non-sensitive public settings
        // Admins get all settings
        $user = AuthService::getCurrentUser();
        $settings = $db->getSettings();

        if (!$user || $user['role'] !== 'admin') {
            // Return only public safe settings
            $publicSettings = [
                'app_name' => $settings['app_name'] ?? 'Marketing AI Agent',
                'enable_public_chat' => $settings['enable_public_chat'] ?? '1',
                'enable_public_notifications' => $settings['enable_public_notifications'] ?? '1',
                'gemini_active' => $settings['gemini_active'] ?? '1',
                'lm_studio_active' => $settings['lm_studio_active'] ?? '1',
                'ollama_active' => $settings['ollama_active'] ?? '1'
            ];
            $publicSettings = \MarketingAgent\Plugin\HookManager::applyFilters('public_settings', $publicSettings, $settings);
            echo json_encode(['success' => true, 'settings' => $publicSettings]);
            exit;
        }

        // Admin: return full settings with masked secrets
        $settings = \MarketingAgent\Plugin\HookManager::applyFilters('admin_get_settings', $settings);
        if (!empty($settings['gemini_api_key'])) {
            $key = $settings['gemini_api_key'];
            $settings['gemini_api_key_masked'] = substr($key, 0, 4) . '...' . substr($key, -4);
        } else {
            $settings['gemini_api_key_masked'] = '';
        }

        if (!empty($settings['lm_studio_api_key'])) {
            $key = $settings['lm_studio_api_key'];
            $settings['lm_studio_api_key_masked'] = substr($key, 0, 4) . '...' . substr($key, -4);
        } else {
            $settings['lm_studio_api_key_masked'] = '';
        }

        if (!empty($settings['ollama_api_key'])) {
            $key = $settings['ollama_api_key'];
            $settings['ollama_api_key_masked'] = substr($key, 0, 4) . '...' . substr($key, -4);
        } else {
            $settings['ollama_api_key_masked'] = '';
        }
        // Mask the SMTP password
        if (!empty($settings['smtp_pass'])) {
            $settings['smtp_pass_masked'] = '********';
        } else {
            $settings['smtp_pass_masked'] = '';
        }
        // Mask the WhatsApp token
        if (!empty($settings['whatsapp_token'])) {
            $settings['whatsapp_token_masked'] = substr($settings['whatsapp_token'], 0, 4) . '...' . substr($settings['whatsapp_token'], -4);
        } else {
            $settings['whatsapp_token_masked'] = '';
        }

        if (!empty($settings['sms_twilio_auth_token'])) {
            $settings['sms_twilio_auth_token_masked'] = substr($settings['sms_twilio_auth_token'], 0, 4) . '...' . substr($settings['sms_twilio_auth_token'], -4);
        } else {
            $settings['sms_twilio_auth_token_masked'] = '';
        }

        echo json_encode(['success' => true, 'settings' => $settings]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
