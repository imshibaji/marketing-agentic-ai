<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db  = new DatabaseService();
    $user = AuthService::getCurrentUser();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    $pdo      = $db->getPdo();
    $userId   = (int)$user['id'];

    // ── Fetch user row with plan join ──────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT u.*, p.name AS plan_name,
               p.campaign_limit, p.lead_limit,
               p.llm_limit,     p.email_limit, p.whatsapp_limit, p.sms_limit
        FROM users u
        LEFT JOIN plans p ON u.plan_id = p.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    // ── Current usage counts ───────────────────────────────────────────────
    $llmUsed      = (int)($row['llm_usage']      ?? 0);
    $emailUsed    = (int)($row['email_usage']     ?? 0);
    $waUsed       = (int)($row['whatsapp_usage']  ?? 0);
    $smsUsed      = (int)($row['sms_usage']       ?? 0);
    $llmLimit     = (int)($row['llm_limit']       ?? 100);
    $emailLimit   = (int)($row['email_limit']     ?? 100);
    $waLimit      = (int)($row['whatsapp_limit']  ?? 100);
    $smsLimit     = (int)($row['sms_limit']       ?? 100);

    $campaignUsed = $db->getUserCampaignCount($userId);
    $leadUsed     = $db->getUserLeadCount($userId);
    $campaignLimit = (int)($row['campaign_limit'] ?? 10);
    $leadLimit     = (int)($row['lead_limit']     ?? 50);

    // ── Which LLM providers are activated (have valid config) ─────────────
    $settings = $db->getSettings();
    $activeProviders = [];

    // Gemini: activated when gemini_api_key is set AND gemini_active is '1'
    $geminiActive = ($settings['gemini_active'] ?? '1') === '1';
    if ($geminiActive && !empty($settings['gemini_api_key']) && $settings['gemini_api_key'] !== 'mock') {
        $model = $settings['gemini_model'] ?? 'gemini-1.5-flash';
        $activeProviders[] = [
            'id'    => 'gemini',
            'label' => 'Gemini ' . $model,
            'model' => $model,
            'icon'  => 'fa-google',
            'badge' => 'Cloud',
            'quota' => $llmLimit,
            'used'  => $llmUsed,
        ];
    }

    // LM Studio: activated when URL is set AND lm_studio_active is '1'
    $lmStudioActive = ($settings['lm_studio_active'] ?? '1') === '1';
    if ($lmStudioActive && !empty($settings['lm_studio_url'])) {
        $model = $settings['lm_studio_model'] ?? 'local';
        $activeProviders[] = [
            'id'    => 'lm_studio',
            'label' => 'LM Studio — ' . $model,
            'model' => $model,
            'icon'  => 'fa-server',
            'badge' => 'Local',
            'quota' => null, // local = unlimited
            'used'  => null,
        ];
        if (!empty($settings['lm_studio_extra_model'])) {
            $extraModel = $settings['lm_studio_extra_model'];
            $activeProviders[] = [
                'id'    => 'lm_studio_extra',
                'label' => 'LM Studio — ' . $extraModel,
                'model' => $extraModel,
                'icon'  => 'fa-server',
                'badge' => 'Local',
                'quota' => null,
                'used'  => null,
            ];
        }
    }

    // Ollama: activated when URL is set AND ollama_active is '1'
    $ollamaActive = ($settings['ollama_active'] ?? '1') === '1';
    if ($ollamaActive && !empty($settings['ollama_url'])) {
        $model = $settings['ollama_model'] ?? 'llama3';
        $activeProviders[] = [
            'id'    => 'ollama',
            'label' => 'Ollama — ' . $model,
            'model' => $model,
            'icon'  => 'fa-circle-nodes',
            'badge' => 'Local',
            'quota' => null,
            'used'  => null,
        ];
        if (!empty($settings['ollama_extra_model'])) {
            $extraModel = $settings['ollama_extra_model'];
            $activeProviders[] = [
                'id'    => 'ollama_extra',
                'label' => 'Ollama — ' . $extraModel,
                'model' => $extraModel,
                'icon'  => 'fa-circle-nodes',
                'badge' => 'Local',
                'quota' => null,
                'used'  => null,
            ];
        }
    }

    echo json_encode([
        'success'         => true,
        'plan_name'       => $row['plan_name'] ?? 'Default Plan',
        'active_providers'=> $activeProviders,
        'usage' => [
            'llm'       => ['used' => $llmUsed,      'limit' => $llmLimit],
            'email'     => ['used' => $emailUsed,     'limit' => $emailLimit],
            'whatsapp'  => ['used' => $waUsed,        'limit' => $waLimit],
            'sms'       => ['used' => $smsUsed,       'limit' => $smsLimit],
            'campaigns' => ['used' => $campaignUsed,  'limit' => $campaignLimit],
            'leads'     => ['used' => $leadUsed,      'limit' => $leadLimit],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
