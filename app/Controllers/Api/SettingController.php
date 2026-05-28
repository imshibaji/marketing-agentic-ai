<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class SettingController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $settings = $this->db->getSettings();

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
            return $this->respondSuccess(['settings' => $publicSettings]);
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

        if (!empty($settings['openrouter_api_key'])) {
            $key = $settings['openrouter_api_key'];
            $settings['openrouter_api_key_masked'] = substr($key, 0, 4) . '...' . substr($key, -4);
        } else {
            $settings['openrouter_api_key_masked'] = '';
        }

        if (!empty($settings['openai_compat_api_key'])) {
            $key = $settings['openai_compat_api_key'];
            $settings['openai_compat_api_key_masked'] = substr($key, 0, 4) . '...' . substr($key, -4);
        } else {
            $settings['openai_compat_api_key_masked'] = '';
        }

        if (!empty($settings['smtp_pass'])) {
            $settings['smtp_pass_masked'] = '********';
        } else {
            $settings['smtp_pass_masked'] = '';
        }

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

        return $this->respondSuccess(['settings' => $settings]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->respondError('Forbidden. Only administrators can update settings.', 403);
        }

        $input = $this->getJsonInput();
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
            'openrouter_api_key',
            'openrouter_model',
            'openrouter_site_url',
            'openrouter_active',
            'openai_compat_url',
            'openai_compat_model',
            'openai_compat_api_key',
            'openai_compat_active',
        ];

        $allowedKeys = \MarketingAgent\Plugin\HookManager::applyFilters('admin_settings_allowed_keys', $allowedKeys);

        $settingsToSave = [];
        foreach ($allowedKeys as $key) {
            if (isset($input[$key])) {
                $val = trim($input[$key]);
                // If it is masked value and unchanged, do not overwrite original secret
                if (str_ends_with($key, '_api_key') || $key === 'whatsapp_token' || $key === 'sms_twilio_auth_token') {
                    if (str_ends_with($val, '...')) {
                        continue;
                    }
                }
                if ($key === 'smtp_pass' && $val === '********') {
                    continue;
                }
                if ($key === 'openrouter_api_key' && str_ends_with($val, '...')) {
                    continue;
                }
                if ($key === 'openai_compat_api_key' && str_ends_with($val, '...')) {
                    continue;
                }
                $settingsToSave[$key] = $val;
            }
        }

        $this->db->saveSettings($settingsToSave);
        return $this->respondSuccess([], 'System settings saved successfully.');
    }
}
