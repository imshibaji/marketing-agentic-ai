<?php
namespace MarketingAgent\Model;

class Setting extends BaseModel {
    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT * FROM settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function saveSettings(array $settings): void {
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO settings (`key`, value) VALUES (?, ?)");
        foreach ($settings as $key => $val) {
            $stmt->execute([$key, $val]);
        }
    }

    public function initializeSchema(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` TEXT PRIMARY KEY,
            value TEXT
        )");

        $defaults = [
            'app_name' => 'Marketing AI Agent',
            'llm_provider' => 'gemini',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-flash',
            'lm_studio_url' => 'http://localhost:1234/v1',
            'lm_studio_model' => 'qwen2.5-7b-instruct',
            'lm_studio_api_key' => '',
            'lm_studio_extra_model' => '',
            'ollama_url' => 'http://localhost:11434/v1',
            'ollama_model' => 'llama3',
            'ollama_api_key' => '',
            'ollama_extra_model' => '',
            'smtp_host' => 'mock',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from_email' => 'outreach@example.com',
            'smtp_from_name' => 'Mock Outreach',
            'whatsapp_token' => 'mock',
            'whatsapp_phone_id' => '',
            'sms_provider' => 'mock',
            'sms_twilio_account_sid' => '',
            'sms_twilio_auth_token' => '',
            'sms_twilio_from_number' => '',
            'sms_custom_url' => '',
            'sms_custom_method' => 'POST',
            'sms_custom_headers' => '',
            'sms_custom_body' => '{"to":"{to}", "message":"{message}"}',
            'enable_public_chat' => '1',
            'enable_public_notifications' => '1',
            'gemini_active' => '1',
            'lm_studio_active' => '1',
            'ollama_active' => '1'
        ];

        foreach ($defaults as $key => $val) {
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (`key`, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        }
    }
}
