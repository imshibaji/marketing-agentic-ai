<?php
namespace MarketingAgent\Service\Llm;

use Exception;

class OllamaProvider implements LlmProviderInterface {
    private string $baseUrl;
    private string $model;
    private string $apiKey;

    public function __construct(string $baseUrl = 'http://localhost:11434', string $model = 'llama3', string $apiKey = '') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model ?: 'llama3';
        $this->apiKey = trim($apiKey);
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string {
        // We will support both the native /api/chat or the /v1 OpenAI compatible endpoints.
        // Let's use the native /api/chat as it is extremely standard for Ollama.
        $url = "{$this->baseUrl}/api/chat";

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $requestBody = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $temperature
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            $headers[] = "Authorization: Bearer {$this->apiKey}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Ollama Connection failed. Is Ollama service running? Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Ollama API Error (HTTP {$httpCode}): " . $response);
        }

        $result = json_decode($response, true);
        $text = $result['message']['content'] ?? null;

        if ($text === null) {
            throw new Exception("Failed to parse response content from Ollama API. Response: " . $response);
        }

        return $text;
    }
}
