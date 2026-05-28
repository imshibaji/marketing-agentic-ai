<?php
namespace MarketingAgent\Service\Llm;

use Exception;

/**
 * OpenRouter LLM Provider
 *
 * Routes requests to 200+ models (GPT-4o, Claude, Mistral, LLaMA, etc.)
 * through the OpenRouter unified API at https://openrouter.ai/api/v1
 *
 * Docs: https://openrouter.ai/docs
 */
class OpenRouterProvider implements LlmProviderInterface {

    private const BASE_URL = 'https://openrouter.ai/api/v1';

    private string $apiKey;
    private string $model;
    private string $siteUrl;
    private string $siteName;

    /**
     * @param string $apiKey   Your OpenRouter API key (sk-or-...)
     * @param string $model    Model slug e.g. "openai/gpt-4o", "anthropic/claude-3-5-sonnet", "meta-llama/llama-3-70b-instruct"
     * @param string $siteUrl  Optional: your site URL for OpenRouter rankings
     * @param string $siteName Optional: your app name for OpenRouter rankings
     */
    public function __construct(
        string $apiKey,
        string $model      = 'openai/gpt-4o-mini',
        string $siteUrl    = '',
        string $siteName   = 'Marketing AI Agent'
    ) {
        $this->apiKey   = trim($apiKey);
        $this->model    = $model ?: 'openai/gpt-4o-mini';
        $this->siteUrl  = $siteUrl;
        $this->siteName = $siteName;
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('OpenRouter API key is not configured.');
        }

        $url = self::BASE_URL . '/chat/completions';

        $messages = [];
        if (!empty(trim($systemPrompt))) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $requestBody = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $temperature,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!empty($this->siteUrl)) {
            $headers[] = 'HTTP-Referer: ' . $this->siteUrl;
        }
        if (!empty($this->siteName)) {
            $headers[] = 'X-Title: ' . $this->siteName;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('OpenRouter connection failed: ' . $curlErr);
        }

        if ($httpCode === 401) {
            throw new Exception('OpenRouter API Error: Invalid or missing API key. Please check your OpenRouter API key in Settings.');
        }

        if ($httpCode === 402) {
            throw new Exception('OpenRouter API Error: Insufficient credits. Please top up your OpenRouter account.');
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $msg = $decoded['error']['message'] ?? $response;
            throw new Exception("OpenRouter API Error (HTTP {$httpCode}): {$msg}");
        }

        $result = json_decode($response, true);

        // Check for upstream error (OpenRouter may return 200 with an error body)
        if (isset($result['error'])) {
            $msg = $result['error']['message'] ?? json_encode($result['error']);
            throw new Exception("OpenRouter upstream error: {$msg}");
        }

        $text = $result['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new Exception('Failed to parse OpenRouter response: ' . $response);
        }

        return $text;
    }
}
