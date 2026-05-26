<?php
require_once __DIR__ . '/../autoload.php';
use MarketingAgent\Service\DatabaseService;

try {
    $db = new DatabaseService();

    echo "1. Checking plans list (should contain Default Plan)...\n";
    $plans = $db->getPlans();
    $defaultPlan = null;
    foreach ($plans as $p) {
        if ($p['name'] === 'Default Plan') {
            $defaultPlan = $p;
        }
    }
    if (!$defaultPlan) {
        throw new Exception("Default Plan not seeded!");
    }
    echo "Found Default Plan. Limits: " . $defaultPlan['campaign_limit'] . " campaigns, " . $defaultPlan['lead_limit'] . " leads.\n\n";

    echo "2. Creating a new test plan...\n";
    $testPlanId = $db->createPlan('Test Plan Tier', 2, 5);
    echo "Created plan ID: $testPlanId\n\n";

    echo "3. Creating user on 'Test Plan Tier'...\n";
    $username = "planuser_" . rand(1000, 9999);
    $userId = $db->createUser($username, password_hash('password123', PASSWORD_BCRYPT), 'user', 'Plan User', 'plan@user.com', '12345', '12345', $testPlanId);
    echo "Created user ID: $userId with username: $username\n\n";

    echo "4. Checking user plan limit enforcement (limit is 2 campaigns)...\n";
    $userRow = $db->getUserById($userId);
    if ((int)$userRow['plan_id'] !== $testPlanId) {
        throw new Exception("User plan_id not set correctly!");
    }
    if ((int)$userRow['plan_campaigns'] !== 2 || (int)$userRow['plan_leads'] !== 5) {
        throw new Exception("User inherited incorrect plan limits: " . print_r($userRow, true));
    }
    echo "Verified: user inherits campaigns limit = " . $userRow['plan_campaigns'] . " and leads limit = " . $userRow['plan_leads'] . "\n\n";

    echo "5. Verifying user plan deletion re-assignment...\n";
    // Delete Test Plan Tier. User should be reassigned to Default Plan (ID 1).
    $db->deletePlan($testPlanId);
    $userRowAfter = $db->getUserById($userId);
    if ((int)$userRowAfter['plan_id'] !== (int)$defaultPlan['id']) {
        throw new Exception("Re-assignment failed! User plan ID is: " . $userRowAfter['plan_id']);
    }
    echo "Verified: user plan ID reassigned to: " . $userRowAfter['plan_id'] . " (Default Plan)\n\n";

    // Cleanup user
    $db->deleteUser($userId);
    echo "Cleanup successful.\n\n";

    echo "ALL PLANS TESTS PASSED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "TEST FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
