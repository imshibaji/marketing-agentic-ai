<?php
/**
 * Diagnostic CLI script to test Lead Generation SDR Agent pipeline.
 * Run in terminal: php scratch/test_leads.php
 */

require_once __DIR__ . '/../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Agent\LeadAgent;

echo "==================================================\n";
echo "       Lead Generation & SDR Diagnostic          \n";
echo "==================================================\n\n";

try {
    $db = new DatabaseService();
    
    // Check if we have any campaigns, if not, create a mock campaign
    $campaigns = $db->getCampaigns();
    if (empty($campaigns)) {
        echo "No existing campaign found. Creating a test campaign...\n";
        $campaignId = $db->createCampaign(
            "Acme AutoScheduler",
            "An AI-powered social media post scheduling tool that analyzes engagement metrics and post drafts, automatically scheduling content to maximize organic reach.",
            "Independent content creators and social media marketing managers",
            "email"
        );
        echo "Created test campaign ID: {$campaignId}\n\n";
    } else {
        $campaignId = $campaigns[0]['id'];
        echo "Using existing campaign ID: {$campaignId} ('{$campaigns[0]['title']}')\n\n";
    }

    $llm = LlmFactory::create($db);
    $leadAgent = new LeadAgent($llm, $db);

    // Set callback to print logs in terminal
    $leadAgent->setLogCallback(function (string $agentName, string $action, string $logText) {
        echo "  [{$agentName}] -> [{$action}]: {$logText}\n";
    });

    echo "Running Lead Generation SDR pipeline...\n";
    $leads = $leadAgent->generateAndQualifyLeads($campaignId);

    echo "\nQualified Prospects Generated:\n";
    echo "--------------------------------------------------\n";
    foreach ($leads as $index => $lead) {
        echo ($index + 1) . ". Company: " . $lead['company_name'] . "\n";
        echo "   Contact: " . $lead['contact_name'] . " (" . $lead['email'] . ")\n";
        echo "   Industry: " . $lead['industry'] . "\n";
        echo "   Fit Score: " . $lead['score'] . "\n";
        echo "   Reasoning: " . $lead['reasoning'] . "\n";
        echo "   WhatsApp Pitch: " . substr(str_replace("\n", " ", $lead['whatsapp_draft']), 0, 75) . "...\n";
        echo "--------------------------------------------------\n";
    }

    echo "\nLead agent diagnostic completed successfully!\n";

} catch (Exception $e) {
    echo "\n[DIAGNOSTIC ERROR]: " . $e->getMessage() . "\n";
}
echo "==================================================\n";
