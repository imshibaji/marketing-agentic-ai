<?php
/**
 * CLI script to refresh and reset the SQLite database.
 * Run in terminal: php scratch/reset_db.php
 */

require_once __DIR__ . '/../autoload.php';

use MarketingAgent\Service\DatabaseService;

echo "==================================================\n";
echo "         Database Reset & Seed Script             \n";
echo "==================================================\n\n";

$dbFile = __DIR__ . '/../database/database.sqlite';

$driver = strtolower(trim((string)getenv('DB_DRIVER') ?: 'sqlite'));
if ($driver === 'mysql' || $driver === 'pdo_mysql') {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    
    echo "Resetting MySQL database '{$dbname}'...\n";
    $tempPdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $tempPdo->exec("SET foreign_key_checks = 0");
    $tables = ['plans', 'users', 'campaigns', 'campaign_shares', 'leads', 'agent_logs', 'user_activity_logs', 'email_otps', 'settings', 'notifications', 'public_chats'];
    foreach ($tables as $table) {
        $tempPdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "Dropped table: {$table}\n";
    }
    $tempPdo->exec("SET foreign_key_checks = 1");
    echo "MySQL database tables dropped successfully.\n\n";
} else {
    if (file_exists($dbFile)) {
        echo "Deleting existing database: {$dbFile}\n";
        unlink($dbFile);
    }
}

try {
    echo "Creating new database and initializing schemas...\n";
    $db = new DatabaseService();
    $pdo = $db->getPdo();
    echo "Schemas initialized successfully.\n\n";

    // 1. Get Plan ID
    $stmt = $pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
    $planId = (int)$stmt->fetchColumn();
    if (!$planId) {
        $planId = $db->createPlan('Default Plan', 10, 50, 100, 100, 100, 100);
    }
    echo "Using Default Plan ID: {$planId}\n";

    // 2. Seed Users
    echo "Seeding users...\n";
    $users = [
        ['username' => 'admin', 'password' => 'admin', 'role' => 'admin', 'name' => 'Administrator', 'email' => 'admin@example.com'],
        ['username' => 'testadmin', 'password' => 'admin123', 'role' => 'admin', 'name' => 'Test Administrator', 'email' => 'testadmin@example.com'],
        ['username' => 'user', 'password' => 'user', 'role' => 'user', 'name' => 'Standard User', 'email' => 'user@example.com'],
        ['username' => 'charlie', 'password' => 'charlie', 'role' => 'user', 'name' => 'Charlie Green', 'email' => 'charlie@example.com'],
        ['username' => 'alice_updated', 'password' => 'alice', 'role' => 'admin', 'name' => 'Alice Green', 'email' => 'alice@example.com']
    ];

    $userIds = [];
    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        $uid = $db->createUser($u['username'], $hash, $u['role'], $u['name'], $u['email'], null, null, $planId);
        $userIds[$u['username']] = $uid;
        echo " - Created {$u['role']} '{$u['username']}' with ID: {$uid} (password: '{$u['password']}')\n";
    }
    echo "\n";

    // 3. Seed Campaigns
    echo "Seeding campaigns...\n";
    $campaign1 = $db->createCampaign(
        "Acme AutoScheduler",
        "An AI-powered social media post scheduling tool that analyzes engagement metrics and post drafts, automatically scheduling content to maximize organic reach.",
        "Independent content creators and social media marketing managers",
        "email",
        "none",
        "",
        "English",
        $userIds['admin']
    );
    echo " - Created campaign 'Acme AutoScheduler' (ID: {$campaign1}) owned by admin\n";

    $campaign2 = $db->createCampaign(
        "Marketing App Developer",
        "Bespoke mobile application development tailored specifically for modern digital marketing agencies and CRM systems integration.",
        "Agency owners and CTOs looking to build customized interactive dashboards",
        "whatsapp",
        "none",
        "",
        "English",
        $userIds['charlie']
    );
    echo " - Created campaign 'Marketing App Developer' (ID: {$campaign2}) owned by charlie\n";
    echo "\n";

    // 4. Seed Chat Messages
    echo "Seeding chat messages...\n";
    $chatStmt = $pdo->prepare("INSERT INTO public_chats (user_id, message, created_at) VALUES (?, ?, ?)");
    $chatMessages = [
        ['admin', 'Welcome to the Marketing AI Suite chatroom!', date('Y-m-d H:i:s', time() - 3600)],
        ['charlie', 'Hi team! Glad to be here. The new Outreach features look amazing.', date('Y-m-d H:i:s', time() - 1800)],
        ['admin', '@charlie Thanks! Let me know if you need any adjustments to the prompt generation.', date('Y-m-d H:i:s', time() - 900)]
    ];

    foreach ($chatMessages as $msg) {
        $chatStmt->execute([$userIds[$msg[0]], $msg[1], $msg[2]]);
    }
    echo " - Seeded " . count($chatMessages) . " public chat messages.\n\n";

    // 5. Seed Notifications
    echo "Seeding system notifications...\n";
    $notifStmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message, created_at) VALUES (?, ?, ?, ?)");
    $notifications = [
        [$userIds['admin'], 'System Maintenance', 'Scheduled system upgrade completed. All services are running optimally.', date('Y-m-d H:i:s', time() - 7200)],
        [$userIds['admin'], 'New Feature Added', 'Outreach Mini Panel is now active on the dashboard. Use it for quick campaign actions!', date('Y-m-d H:i:s', time() - 3600)],
        [$userIds['admin'], 'Welcome Notification', 'Welcome to the platform! Please configure your SMTP settings to begin sending emails.', date('Y-m-d H:i:s', time() - 600)]
    ];

    foreach ($notifications as $n) {
        $notifStmt->execute([$n[0], $n[1], $n[2], $n[3]]);
    }
    echo " - Seeded " . count($notifications) . " system notifications.\n\n";

    // 6. Seed some Leads (for demo/development)
    echo "Seeding leads...\n";
    // Lead for campaign 1
    $lead1 = $db->saveLead(
        $campaign1,
        "Globex Corporation",
        "Homer Simpson",
        "homer@globex.com",
        "+15550199",
        "Energy & Technology",
        "Needs to automate posting schedules for the corporate reactor division.",
        "85",
        "Fits our target profile of mid-sized energy providers seeking automated marketing workflows.",
        "Hi Homer,\n\nWe noticed Globex is looking to streamline post scheduling. Acme AutoScheduler can save you 10+ hours weekly.\n\nBest,\nAdmin",
        "Hi Homer, Acme AutoScheduler can automate Globex's social posting schedule. Interested in a quick demo?",
        $userIds['admin'],
        "agent",
        "+15550199",
        "Hi Homer, Acme AutoScheduler can automate Globex's social posting schedule. Reply to demo.",
        "100 Sector 7-G, Springfield"
    );
    echo " - Created lead 'Globex Corporation' for campaign 1 (ID: {$lead1})\n";

    // Lead for campaign 2
    $lead2 = $db->saveLead(
        $campaign2,
        "Initech Solutions",
        "Peter Gibbons",
        "peter@initech.com",
        "+15550123",
        "Consulting Services",
        "Wants to build a bespoke dashboard for client CRM visualization.",
        "95",
        "Excellent fit for custom agency dashboards.",
        "Hi Peter,\n\nWe build custom mobile apps for agencies. We would love to discuss custom visual dashboards for Initech.\n\nRegards,\nCharlie",
        "Hi Peter, Charlie here. We build custom dashboards for agency CRM systems. Let's talk!",
        $userIds['charlie'],
        "agent",
        "+15550123",
        "Hi Peter, Charlie here. We build custom CRM dashboards. Let's chat.",
        "4120 Freemont Ave, Austin, TX"
    );
    echo " - Created lead 'Initech Solutions' for campaign 2 (ID: {$lead2})\n";
    echo "\n";

    echo "==================================================\n";
    echo "          DATABASE RESET SUCCESSFUL!              \n";
    echo "==================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR]: " . $e->getMessage() . "\n";
    exit(1);
}
