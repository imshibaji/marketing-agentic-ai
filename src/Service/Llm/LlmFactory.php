<?php
namespace MarketingAgent\Service\Llm;

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use Exception;

class LlmFactory {
    public static function create(DatabaseService $db, ?string $overrideProvider = null, ?int $userId = null): LlmProviderInterface {
        $settings = $db->getSettings();
        $provider = $overrideProvider ?: ($settings['llm_provider'] ?? 'gemini');

        switch ($provider) {
            case 'gemini':
                $apiKey = $settings['gemini_api_key'] ?? '';
                $model = $settings['gemini_model'] ?? 'gemini-1.5-flash';
                $inner = new GeminiProvider($apiKey, $model);
                break;

            case 'lm_studio':
            case 'lm_studio_extra':
                $url = $settings['lm_studio_url'] ?? 'http://localhost:1234/v1';
                $model = ($provider === 'lm_studio_extra') ? ($settings['lm_studio_extra_model'] ?? '') : ($settings['lm_studio_model'] ?? 'qwen2.5-7b-instruct');
                $apiKey = $settings['lm_studio_api_key'] ?? '';
                $inner = new LmStudioProvider($url, $model, $apiKey);
                break;

            case 'ollama':
            case 'ollama_extra':
                $url = $settings['ollama_url'] ?? 'http://localhost:11434/v1';
                $model = ($provider === 'ollama_extra') ? ($settings['ollama_extra_model'] ?? '') : ($settings['ollama_model'] ?? 'llama3');
                $apiKey = $settings['ollama_api_key'] ?? '';
                $inner = new OllamaProvider($url, $model, $apiKey);
                break;

            default:
                throw new Exception("Unsupported LLM Provider: " . $provider);
        }

        if ($userId === null) {
            $currentUser = AuthService::getCurrentUser();
            if ($currentUser) {
                $userId = (int)$currentUser['id'];
            }
        }

        return new QuotaLlmProvider($inner, $db, $userId);
    }
}
