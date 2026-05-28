<?php
namespace MarketingAgent\Service\Llm;

use Exception;

/**
 * OpenAI-Compatible LLM Provider
 *
 * Connects to any API server that implements the OpenAI Chat Completions format:
 *   POST {baseUrl}/chat/completions
 *
 * Compatible with:
 *   - OpenAI          (https://api.openai.com/v1)
 *   - Azure OpenAI    (https://{resource}.openai.azure.com/openai/deployments/{deployment})
 *   - Groq            (https://api.groq.com/openai/v1)
 *   - Together AI     (https://api.together.xyz/v1)
 *   - Fireworks AI    (https://api.fireworks.ai/inference/v1)
 *   - Anyscale        (https://api.endpoints.anyscale.com/v1)
 *   - DeepSeek        (https://api.deepseek.com/v1)
 *   - Mistral AI      (https://api.mistral.ai/v1)
 *   - xAI Grok        (https://api.x.ai/v1)
 *   - vLLM, Text-Generation-WebUI, and any self-hosted OpenAI-compatible server
 */
class OpenAICompatibleProvider implements LlmProviderInterface {

    private string $baseUrl;
    private string $model;
    private string $apiKey;
    /** @var array<string,string> Additional HTTP headers */
    private array $extraHeaders;

    /**
     * @param string $baseUrl      Base API URL without trailing slash, e.g. "https://api.openai.com/v1"
     * @param string $model        Model identifier, e.g. "gpt-4o", "mixtral-8x7b-32768"
     * @param string $apiKey       Bearer token / API key (empty string for keyless local servers)
     * @param array  $extraHeaders Any additional headers as ['Header-Name: value', ...]
     */
    public function __construct(
        string $baseUrl      = 'https://api.openai.com/v1',
        string $model        = 'gpt-4o-mini',
        string $apiKey       = '',
        array  $extraHeaders = []
    ) {
        $this->baseUrl      = rtrim($baseUrl, '/');
        $this->model        = $model ?: 'gpt-4o-mini';
        $this->apiKey       = trim($apiKey);
        $this->extraHeaders = $extraHeaders;
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string
    {
        $url = $this->baseUrl . '/chat/completions';

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

        $headers = ['Content-Type: application/json'];

        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        // Merge any extra headers (e.g. for Azure: 'api-key: {key}')
        foreach ($this->extraHeaders as $h) {
            $headers[] = $h;
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
            throw new Exception(
                "OpenAI-Compatible provider connection failed (URL: {$url}). " .
                "cURL error: {$curlErr}"
            );
        }

        if ($httpCode === 401) {
            throw new Exception(
                "OpenAI-Compatible API Error (HTTP 401): Unauthorized. " .
                "Please check the API key in Settings → LLM."
            );
        }

        if ($httpCode === 404) {
            throw new Exception(
                "OpenAI-Compatible API Error (HTTP 404): Endpoint not found. " .
                "Please verify the Base URL is correct: {$this->baseUrl}"
            );
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $msg = $decoded['error']['message'] ?? $response;
            throw new Exception("OpenAI-Compatible API Error (HTTP {$httpCode}): {$msg}");
        }

        $result = json_decode($response, true);

        $text = $result['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new Exception(
                "Failed to parse OpenAI-Compatible response from {$this->baseUrl}. " .
                "Response body: " . $response
            );
        }

        return $text;
    }
}
