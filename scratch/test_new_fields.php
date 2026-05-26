<?php
require_once __DIR__ . '/../autoload.php';
use MarketingAgent\Service\DatabaseService;

try {
    $db = new DatabaseService();

    echo "=== Running Mobile Number and Data Source integration test ===\n";

    // 1. Create a dummy user and campaign
    $username = "tester_" . rand(1000, 9999);
    $userId = $db->createUser($username, password_hash("pass123", PASSWORD_BCRYPT), "user", "Tester User");
    $campaignId = $db->createCampaign("Test campaign", "desc", "audience", "email", "none", "", "English", $userId);

    echo "Created User ID: $userId, Campaign ID: $campaignId\n";

    // 2. Save a lead with mobile and source
    $companyName = "Acme Widgets Co";
    $contactName = "Alice Smith";
    $email = "alice@acmewidgets.com";
    $whatsapp = "+15550199";
    $mobile = "+15550288";
    $source = "linkedin";
    $industry = "Manufacturing";
    $desc = "Wants widgets";
    $score = "HIGH";
    $reasoning = "High fit match";
    $emailDraft = "Hello Alice...";
    $whatsappDraft = "Hi Alice...";

    $leadId = $db->saveLead(
        $campaignId,
        $companyName,
        $contactName,
        $email,
        $whatsapp,
        $industry,
        $desc,
        $score,
        $reasoning,
        $emailDraft,
        $whatsappDraft,
        $userId,
        $source,
        $mobile
    );

    echo "Saved manual lead with ID: $leadId\n";

    // 3. Fetch lead and verify fields
    $lead = $db->getLead($leadId);
    if (!$lead) {
        throw new Exception("Saved lead could not be fetched.");
    }

    echo "Fetched lead. Checking fields...\n";
    echo "  Company: " . $lead['company_name'] . "\n";
    echo "  Source: " . $lead['source'] . " (Expected: linkedin)\n";
    echo "  Mobile: " . $lead['mobile'] . " (Expected: +15550288)\n";

    if ($lead['source'] !== 'linkedin') {
        throw new Exception("Source field value mismatch: got '{$lead['source']}', expected 'linkedin'");
    }
    if ($lead['mobile'] !== '+15550288') {
        throw new Exception("Mobile field value mismatch: got '{$lead['mobile']}', expected '+15550288'");
    }

    // 4. Update lead
    echo "Updating lead...\n";
    $updatedMobile = "+919876543210";
    $updatedSource = "google-search";

    $db->updateLead(
        $leadId,
        $campaignId,
        $companyName,
        $contactName,
        $email,
        $whatsapp,
        $industry,
        $desc,
        $score,
        $reasoning,
        $emailDraft,
        $whatsappDraft,
        $updatedSource,
        $updatedMobile
    );

    // 5. Fetch and verify updated fields
    $updatedLead = $db->getLead($leadId);
    echo "Fetched updated lead. Checking updated fields...\n";
    echo "  Source: " . $updatedLead['source'] . " (Expected: google-search)\n";
    echo "  Mobile: " . $updatedLead['mobile'] . " (Expected: +919876543210)\n";

    if ($updatedLead['source'] !== 'google-search') {
        throw new Exception("Updated source mismatch: got '{$updatedLead['source']}', expected 'google-search'");
    }
    if ($updatedLead['mobile'] !== '+919876543210') {
        throw new Exception("Updated mobile mismatch: got '{$updatedLead['mobile']}', expected '+919876543210'");
    }

    echo "\n=== INTEGRATION TEST PASSED! ===\n";

} catch (Exception $e) {
    echo "\n=== TEST FAILED ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
