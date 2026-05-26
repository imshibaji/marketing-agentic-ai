<?php
namespace MarketingAgent\Service;

use Exception;

class SmsService {
    private DatabaseService $db;
    private array $settings;

    public function __construct(DatabaseService $db) {
        $this->db = $db;
        $this->settings = $db->getSettings();
    }

    public function getProvider(): string {
        return strtolower(trim($this->settings['sms_provider'] ?? 'mock')) ?: 'mock';
    }

    public function isConfigured(): bool {
        return $this->getProvider() !== 'mock';
    }

    public function sendSms(string $to, string $message): bool {
        $provider = $this->getProvider();
        if ($provider === 'twilio') {
            return $this->sendWithTwilio($to, $message);
        } elseif ($provider === 'custom') {
            return $this->sendWithCustom($to, $message);
        }

        // Mock or unsupported providers simply log and return true for local testing.
        return $this->mockSend($to, $message);
    }

    private function sendWithTwilio(string $to, string $message): bool {
        $accountSid = trim((string)($this->settings['sms_twilio_account_sid'] ?? ''));
        $authToken = trim((string)($this->settings['sms_twilio_auth_token'] ?? ''));
        $fromNumber = trim((string)($this->settings['sms_twilio_from_number'] ?? ''));

        if (empty($accountSid) || empty($authToken) || empty($fromNumber)) {
            throw new Exception('Twilio SMS provider is not fully configured. Please provide account SID, auth token, and sender number.');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $payload = http_build_query([
            'From' => $fromNumber,
            'To' => $to,
            'Body' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new Exception('Twilio SMS request failed: ' . ($error ?: $response));
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['sid'])) {
            throw new Exception('Unexpected Twilio response: ' . $response);
        }

        return true;
    }

    private function sendWithCustom(string $to, string $message): bool {
        $url = trim((string)($this->settings['sms_custom_url'] ?? ''));
        $method = strtoupper(trim((string)($this->settings['sms_custom_method'] ?? 'POST')));
        $headersStr = trim((string)($this->settings['sms_custom_headers'] ?? ''));
        $bodyTemplate = trim((string)($this->settings['sms_custom_body'] ?? ''));

        if (empty($url)) {
            throw new Exception('Custom SMS Provider is not configured. Custom API URL is required.');
        }

        $toEncoded = urlencode($to);
        $messageEncoded = urlencode($message);

        $url = str_replace('{to}', $toEncoded, $url);
        $url = str_replace('{message}', $messageEncoded, $url);

        $body = str_replace('{to}', $to, $bodyTemplate);
        $body = str_replace('{message}', $message, $body);

        $headers = [];
        if (!empty($headersStr)) {
            $lines = explode("\n", str_replace("\r", "", $headersStr));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $headers[] = $line;
                }
            }
        }

        $hasContentType = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType && ($method === 'POST' || $method === 'PUT') && !empty($body)) {
            if (is_array(json_decode($body, true))) {
                $headers[] = 'Content-Type: application/json';
            } else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if (($method === 'POST' || $method === 'PUT') && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new Exception('Custom SMS gateway request failed (HTTP ' . $status . '): ' . ($error ?: $response));
        }

        return true;
    }

    private function mockSend(string $to, string $message): bool {
        // In mock mode, do not send a real SMS. Developers can use this mode for testing.
        return true;
    }
}
