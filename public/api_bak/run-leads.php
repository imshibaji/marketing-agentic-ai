<?php
// Set infinite execution time limit for long-running LLM generation
set_time_limit(0);

// Disable output buffering so we can stream data in real-time
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Agent\LeadAgent;

function sendSseEvent(string $event, array $data): void {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Auth check
$user = AuthService::getCurrentUser();
if (!$user) {
    sendSseEvent('error', ['message' => 'Unauthorized. Please login.']);
    exit;
}

$campaignId = $_GET['id'] ?? null;
$sourceUrl = $_GET['source_url'] ?? '';
$language = $_GET['language'] ?? '';

if (!$campaignId) {
    sendSseEvent('error', ['message' => 'Missing campaign ID']);
    exit;
}

// Ownership check
$dbCheck = new DatabaseService();
$campaignCheck = $dbCheck->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
if (!$campaignCheck) {
    sendSseEvent('error', ['message' => 'Forbidden. You do not have permission to access this campaign.']);
    exit;
}

// Plan limits check
$userDetails = $dbCheck->getUserById((int)$user['id']);
if ($user['role'] !== 'admin' && $userDetails['plan_leads'] !== -1) {
    $leadCount = $dbCheck->getUserLeadCount((int)$user['id']);
    if ($leadCount >= $userDetails['plan_leads']) {
        sendSseEvent('error', ['message' => "Plan limit reached. You can store at most {$userDetails['plan_leads']} leads in your CRM. Please contact an administrator."]);
        exit;
    }
}

// Get and update selected LLM provider
$llmProvider = $_GET['llm_provider'] ?? null;
if ($llmProvider) {
    $dbCheck->updateCampaignLlmProvider((int)$campaignId, $llmProvider);
} else {
    $llmProvider = $campaignCheck['llm_provider'] ?? 'gemini';
}

try {
    $db = new DatabaseService();
    $llm = LlmFactory::create($db, $llmProvider, (int)$user['id']);

    $leadAgent = new LeadAgent($llm, $db);

    // Stream logs in real-time
    $leadAgent->setLogCallback(function (string $agentName, string $action, string $logText) {
        sendSseEvent('log', [
            'agent' => $agentName,
            'action' => $action,
            'message' => $logText,
            'timestamp' => date('H:i:s')
        ]);
    });

    // Run qualification pipeline
    $leads = $leadAgent->generateAndQualifyLeads((int)$campaignId, $sourceUrl, $language);

    // Log activity
    $db->logActivity((int)$user['id'], 'RUN_SDR_FINDER', "Ran SDR Lead Finder on campaign '{$campaignCheck['title']}' (ID: {$campaignId}), found " . count($leads) . " leads.");

    // Complete SSE stream
    sendSseEvent('complete', [
        'message' => 'Leads generated and qualified successfully!',
        'leads' => $leads
    ]);

} catch (Exception $e) {
    sendSseEvent('error', [
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
