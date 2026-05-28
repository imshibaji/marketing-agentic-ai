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

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 60]);
            $response = $client->post($url, [
                'json' => $requestBody
            ]);
            $responseBody = (string)$response->getBody();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $msg = 'Unknown Gemini API error';
            if ($e->hasResponse()) {
                $errData = json_decode((string)$e->getResponse()->getBody(), true);
                $msg = $errData['error']['message'] ?? $msg;
            }
            throw new Exception("Gemini API Error: {$msg}");
        } catch (Exception $e) {
            throw new Exception("Gemini HTTP Error: " . $e->getMessage());
        }

        $result = json_decode($responseBody, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            throw new Exception("Failed to parse response content from Gemini API. Full response: " . $responseBody);
        }

        return $text;
    }
}
