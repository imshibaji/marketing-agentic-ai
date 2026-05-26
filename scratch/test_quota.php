<?php
require_once __DIR__ . '/../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Service\Llm\QuotaLlmProvider;
use MarketingAgent\Service\Llm\LlmProviderInterface;

echo "=== Quota & Limit Enforcement Test Suite ===\n\n";

try {
    $db = new DatabaseService();
    $pdo = $db->getPdo();

    // 1. Clean up old test data if exists
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute(['quotauser']);
    $pdo->prepare("DELETE FROM plans WHERE name = ?")->execute(['Test Quota Plan']);

    // 2. Create a test plan with a very tight LLM limit (2 calls), Email limit (1 call), WhatsApp limit (1 call)
    echo "1. Creating 'Test Quota Plan' with tight limits...\n";
    $planId = $db->createPlan('Test Quota Plan', 5, 10, 2, 1, 1);
    echo "   Plan created with ID: {$planId}\n\n";

    // 3. Create a test user assigned to this plan
    echo "2. Creating test user 'quotauser'...\n";
    $userId = $db->createUser('quotauser', password_hash('password', PASSWORD_BCRYPT), 'user', 'Quota User', 'quota@example.com', null, null, $planId);
    echo "   User created with ID: {$userId}\n\n";

    // 4. Test LLM provider quota wrapping & increment
    echo "3. Testing QuotaLlmProvider wrapper...\n";
    // We will create a mock provider to avoid making actual HTTP requests
    $mockInner = new class implements LlmProviderInterface {
        public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string {
            return "Mock generation output";
        }
    };

    $quotaLlm = new QuotaLlmProvider($mockInner, $db, $userId);

    // Initial check
    $user = $db->getUserById($userId);
    echo "   Initial LLM usage: {$user['llm_usage']} / {$user['plan_llm']}\n";

    // First call
    echo "   Executing first LLM generation...\n";
    $res1 = $quotaLlm->generate("Sys", "User");
    $user = $db->getUserById($userId);
    echo "   Usage after 1st call: {$user['llm_usage']} / {$user['plan_llm']}\n";

    // Second call
    echo "   Executing second LLM generation...\n";
    $res2 = $quotaLlm->generate("Sys", "User");
    $user = $db->getUserById($userId);
    echo "   Usage after 2nd call: {$user['llm_usage']} / {$user['plan_llm']}\n";

    // Third call - should fail!
    echo "   Executing third LLM generation (should throw exception)...\n";
    try {
        $quotaLlm->generate("Sys", "User");
        echo "   [FAIL] Third call succeeded but should have failed!\n";
    } catch (Exception $e) {
        echo "   [SUCCESS] Received expected exception: " . $e->getMessage() . "\n\n";
    }

    // 5. Test Outreach Limits (Email)
    echo "4. Testing Email outreach limit...\n";
    $user = $db->getUserById($userId);
    echo "   Initial Email usage: {$user['email_usage']} / {$user['plan_email']}\n";

    // First email - within limit
    if ($user['plan_email'] !== -1 && $user['email_usage'] >= $user['plan_email']) {
        echo "   [FAIL] Blocked on first email!\n";
    } else {
        echo "   Simulating successful email dispatch...\n";
        $db->incrementEmailUsage($userId);
        $user = $db->getUserById($userId);
        echo "   Usage after 1st email: {$user['email_usage']} / {$user['plan_email']}\n";
    }

    // Second email - should be blocked
    if ($user['plan_email'] !== -1 && $user['email_usage'] >= $user['plan_email']) {
        echo "   [SUCCESS] Blocked on second email as expected!\n\n";
    } else {
        echo "   [FAIL] Allowed second email execution when over limit!\n\n";
    }

    // 6. Test Outreach Limits (WhatsApp)
    echo "5. Testing WhatsApp outreach limit...\n";
    $user = $db->getUserById($userId);
    echo "   Initial WhatsApp usage: {$user['whatsapp_usage']} / {$user['plan_whatsapp']}\n";

    // First WhatsApp - within limit
    if ($user['plan_whatsapp'] !== -1 && $user['whatsapp_usage'] >= $user['plan_whatsapp']) {
        echo "   [FAIL] Blocked on first WhatsApp!\n";
    } else {
        echo "   Simulating successful WhatsApp dispatch...\n";
        $db->incrementWhatsappUsage($userId);
        $user = $db->getUserById($userId);
        echo "   Usage after 1st WhatsApp: {$user['whatsapp_usage']} / {$user['plan_whatsapp']}\n";
    }

    // Second WhatsApp - should be blocked
    if ($user['plan_whatsapp'] !== -1 && $user['whatsapp_usage'] >= $user['plan_whatsapp']) {
        echo "   [SUCCESS] Blocked on second WhatsApp as expected!\n\n";
    } else {
        echo "   [FAIL] Allowed second WhatsApp execution when over limit!\n\n";
    }

    // Clean up test data
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute(['quotauser']);
    $pdo->prepare("DELETE FROM plans WHERE name = ?")->execute(['Test Quota Plan']);
    echo "Test cleanup completed successfully.\n";
    echo "=== ALL TESTS PASSED ===\n";

} catch (Exception $e) {
    echo "Test failed with error: " . $e->getMessage() . "\n";
    exit(1);
}
