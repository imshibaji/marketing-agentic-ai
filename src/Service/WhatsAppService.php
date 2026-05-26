<?php
namespace MarketingAgent\Service;

use Exception;

class WhatsAppService {
    /**
     * Sends a WhatsApp text message via Meta's Cloud API.
     */
    public static function send(
        string $token,
        string $phoneId,
        string $toPhone,
        string $messageText,
        string $version = 'v18.0'
    ): array {
        // Sanitize recipient phone number (must be digits only, start with country code, no + or spaces)
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($cleanPhone)) {
            throw new Exception("Invalid recipient phone number: '{$toPhone}'");
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageText
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("WhatsApp API Connection Error: " . $error);
        }

        $result = json_decode($response, true);
        if ($httpCode >= 400) {
            $errorMsg = $result['error']['message'] ?? 'Unknown API error';
            $errorType = $result['error']['type'] ?? 'OAuthException';
            throw new Exception("WhatsApp Cloud API error [Code {$httpCode}]: {$errorMsg} ({$errorType})");
        }

        return [
            'success' => true,
            'message_id' => $result['messages'][0]['id'] ?? 'mock_msg_id',
            'raw_response' => $result
        ];
    }
}
