<?php
require_once __DIR__ . '/../autoload.php';
use MarketingAgent\Service\DatabaseService;

try {
    $db = new DatabaseService();

    echo "Fetching all leads (admin mode)...\n";
    $leads = $db->getLeads(null, null, 'admin');

    if (empty($leads)) {
        echo "No leads found in database. Let's create a campaign and save a lead.\n";
        $userId = $db->createUser("testuser_" . rand(1000, 9999), password_hash("password123", PASSWORD_BCRYPT), "user", "Test User");
        $campaignId = $db->createCampaign("Test Campaign", "Test desc", "Test audience", "email", "none", "", "English", $userId);
        $db->saveLead($campaignId, "Test Company", "John Doe", "john@example.com", "12345", "Tech", "A description", "HIGH", "Reasoning", "Email draft", "WhatsApp draft", $userId);
        
        $leads = $db->getLeads(null, null, 'admin');
    }

    echo "Found " . count($leads) . " lead(s).\n";
    foreach ($leads as $l) {
        echo "Lead: " . $l['company_name'] . " | Owner: " . ($l['owner_username'] ?? 'NULL') . "\n";
    }

    echo "\nTEST PASSED!\n";
} catch (Exception $e) {
    echo "TEST FAILED: " . $e->getMessage() . "\n";
}
