<?php
require_once __DIR__ . '/../autoload.php';
use MarketingAgent\Service\DatabaseService;

try {
    echo "1. Booting DatabaseService & Running schema initialization...\n";
    $db = new DatabaseService();
    $pdo = $db->getPdo();

    echo "2. Verifying duration column in plans table...\n";
    try {
        $pdo->query("SELECT duration FROM plans LIMIT 1");
        echo "Verified: duration column exists in plans table.\n\n";
    } catch (Exception $e) {
        throw new Exception("Column 'duration' not found or table plans does not exist: " . $e->getMessage());
    }

    echo "3. Verifying plan_expires_at column in users table...\n";
    try {
        $pdo->query("SELECT plan_expires_at FROM users LIMIT 1");
        echo "Verified: plan_expires_at column exists in users table.\n\n";
    } catch (Exception $e) {
        throw new Exception("Column 'plan_expires_at' not found or table users does not exist: " . $e->getMessage());
    }

    echo "4. Creating a new test plan with 23 Hours duration...\n";
    $testPlanId = $db->createPlan('Test Short Tier', 5, 20, 100, 100, 100, 100, '23 Hours');
    echo "Created plan ID: $testPlanId with duration '23 Hours'\n\n";

    echo "5. Creating a user assigned to 'Test Short Tier'...\n";
    $username = "expiryuser_" . rand(1000, 9999);
    $userId = $db->createUser($username, password_hash('password123', PASSWORD_BCRYPT), 'user', 'Expiry User', 'expiry@user.com', '12345', '12345', $testPlanId);
    echo "Created user ID: $userId\n";

    $userRow = $db->getUserById($userId);
    echo "User plan_expires_at calculated value: " . $userRow['plan_expires_at'] . "\n";
    
    $expectedMaxTs = time() + 23 * 3600;
    $calculatedTs = strtotime($userRow['plan_expires_at']);
    $diff = abs($expectedMaxTs - $calculatedTs);
    if ($diff > 10) { // allow a few seconds window
        throw new Exception("Calculated plan_expires_at is incorrect! Diff: $diff seconds. Expected: " . date('Y-m-d H:i:s', $expectedMaxTs) . ", calculated: " . $userRow['plan_expires_at']);
    }
    echo "Verified: plan_expires_at calculated correctly (~23 hours from now).\n\n";

    echo "6. Testing reset user usage limits...\n";
    // Artificially increment usage
    $db->incrementLlmUsage($userId);
    $db->incrementEmailUsage($userId);
    $userRowBeforeReset = $db->getUserById($userId);
    echo "Before reset: LLM usage = " . $userRowBeforeReset['llm_usage'] . ", Email usage = " . $userRowBeforeReset['email_usage'] . "\n";
    if ((int)$userRowBeforeReset['llm_usage'] !== 1 || (int)$userRowBeforeReset['email_usage'] !== 1) {
        throw new Exception("Usage increment failed!");
    }

    $db->resetUserUsage($userId);
    $userRowAfterReset = $db->getUserById($userId);
    echo "After reset: LLM usage = " . $userRowAfterReset['llm_usage'] . ", Email usage = " . $userRowAfterReset['email_usage'] . "\n";
    if ((int)$userRowAfterReset['llm_usage'] !== 0 || (int)$userRowAfterReset['email_usage'] !== 0) {
        throw new Exception("Usage reset failed!");
    }
    echo "Verified: resetUserUsage sets usage counters back to 0.\n\n";

    echo "7. Testing reset/renew plan expiration date...\n";
    // Set user's expiration date to past date
    $pastExpiry = date('Y-m-d H:i:s', time() - 3600);
    $pdo->prepare("UPDATE users SET plan_expires_at = ? WHERE id = ?")->execute([$pastExpiry, $userId]);
    $userRowBeforePlanReset = $db->getUserById($userId);
    echo "Before plan reset (forced past expiry): " . $userRowBeforePlanReset['plan_expires_at'] . "\n";
    
    $db->resetUserPlan($userId);
    $userRowAfterPlanReset = $db->getUserById($userId);
    echo "After plan reset (recalculated expiry): " . $userRowAfterPlanReset['plan_expires_at'] . "\n";
    
    $newCalculatedTs = strtotime($userRowAfterPlanReset['plan_expires_at']);
    $newDiff = abs($expectedMaxTs - $newCalculatedTs);
    if ($newDiff > 10) {
        throw new Exception("Calculated plan_expires_at after reset is incorrect! Diff: $newDiff seconds. Expected: " . date('Y-m-d H:i:s', $expectedMaxTs) . ", calculated: " . $userRowAfterPlanReset['plan_expires_at']);
    }
    echo "Verified: resetUserPlan successfully recalculated and renewed plan expiration.\n\n";

    // Clean up
    $db->deleteUser($userId);
    $db->deletePlan($testPlanId);
    echo "Cleanup successful.\n\n";

    echo "ALL RESET AND EXPIRY DB TESTS PASSED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "TEST FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
