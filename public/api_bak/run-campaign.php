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
header('X-Accel-Buffering: no'); // Prevent buffering on proxies/webservers
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Agent\CoordinatorAgent;

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
if (!$campaignId) {
    sendSseEvent('error', ['message' => 'Missing campaign ID']);
    exit;
}

// Ownership check
$dbCheck = new DatabaseService();
$campaignCheck = $dbCheck->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
if (!$campaignCheck) {
    sendSseEvent('error', ['message' => 'Forbidden. You do not have permission to run this campaign.']);
    exit;
}

// Quota check
$userDetails = $dbCheck->getUserById((int)$user['id']);
if ($userDetails && $userDetails['role'] !== 'admin' && $userDetails['plan_llm'] !== -1) {
    if ($userDetails['llm_usage'] >= $userDetails['plan_llm']) {
        sendSseEvent('error', ['message' => "LLM/AI quota exceeded. You have used {$userDetails['llm_usage']} of {$userDetails['plan_llm']} allowed generations. Please upgrade your plan."]);
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

    $coordinator = new CoordinatorAgent($llm, $db);

    // Set callback to stream logs back in real-time via SSE
    $coordinator->setLogCallback(function (string $agentName, string $action, string $logText) {
        sendSseEvent('log', [
            'agent' => $agentName,
            'action' => $action,
            'message' => $logText,
            'timestamp' => date('H:i:s')
        ]);
    });

    // Run pipeline
    $finalContent = $coordinator->runCampaign((int)$campaignId);

    // Log activity
    $db->logActivity((int)$user['id'], 'RUN_CAMPAIGN_GENERATOR', "Ran Campaign Generator for campaign '{$campaignCheck['title']}' (ID: {$campaignId})");

    // Stream the final result
    sendSseEvent('complete', [
        'message' => 'Campaign content generation completed successfully!',
        'final_content' => $finalContent
    ]);

} catch (Exception $e) {
    sendSseEvent('error', [
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
