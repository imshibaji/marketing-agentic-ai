<?php
namespace MarketingAgent\Service\Llm;

use Exception;

class GeminiProvider implements LlmProviderInterface {
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-1.5-flash') {
        $this->apiKey = $apiKey;
        $this->model = $model ?: 'gemini-1.5-flash';
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string {
        if (empty($this->apiKey)) {
            throw new Exception("Gemini API key is not configured. Please add it in settings.");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $requestBody = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature
            ]
        ];

        if (!empty($systemPrompt)) {
            $requestBody['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? 'Unknown Gemini API error';
            throw new Exception("Gemini API Error (HTTP {$httpCode}): {$msg}");
        }

        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new Exception("Failed to parse response content from Gemini API. Full response: " . $response);
        }

        return $text;
    }
}
