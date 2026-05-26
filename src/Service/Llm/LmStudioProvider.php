<?php
namespace MarketingAgent\Service\Llm;

use Exception;

class LmStudioProvider implements LlmProviderInterface {
    private string $baseUrl;
    private string $model;
    private string $apiKey;

    public function __construct(string $baseUrl = 'http://localhost:1234/v1', string $model = 'qwen2.5-7b-instruct', string $apiKey = '') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model ?: 'qwen2.5-7b-instruct';
        $this->apiKey = trim($apiKey);
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string {
        $url = "{$this->baseUrl}/chat/completions";

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $requestBody = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature
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
            throw new Exception("LM Studio Connection failed. Is LM Studio server running? Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("LM Studio API Error (HTTP {$httpCode}): " . $response);
        }

        $result = json_decode($response, true);
        $text = $result['choices'][0]['message']['content'] ?? null;

        if ($text === null) {
            throw new Exception("Failed to parse chat completions response from LM Studio. Response: " . $response);
        }

        return $text;
    }
}
