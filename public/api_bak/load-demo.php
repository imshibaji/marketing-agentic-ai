<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed.");
    }

    $db = new DatabaseService();
    $pdo = $db->getPdo();

    // Authorization check: Admin only
    $user = AuthService::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can load demo data.']);
        exit;
    }

    // 1. Wipe existing campaigns, leads, chats, notifications, logs
    $pdo->exec("DELETE FROM leads");
    $pdo->exec("DELETE FROM campaigns");
    $pdo->exec("DELETE FROM public_chats");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM agent_logs");
    $pdo->exec("DELETE FROM user_activity_logs");

    // 2. Fetch or create users to attach campaigns/leads to
    $usersToEnsure = [
        ['username' => 'admin', 'password' => 'admin', 'role' => 'admin', 'name' => 'Administrator', 'email' => 'admin@example.com'],
        ['username' => 'charlie', 'password' => 'charlie', 'role' => 'user', 'name' => 'Charlie Green', 'email' => 'charlie@example.com']
    ];

    // Find default plan
    $stmt = $pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
    $planId = (int)$stmt->fetchColumn();
    if (!$planId) {
        $planId = $db->createPlan('Default Plan', 10, 50, 100, 100, 100, 100);
    }

    $userIds = [];
    foreach ($usersToEnsure as $u) {
        $existing = $db->getUserByUsername($u['username']);
        if ($existing) {
            $userIds[$u['username']] = (int)$existing['id'];
        } else {
            $hash = password_hash($u['password'], PASSWORD_BCRYPT);
            $uid = $db->createUser($u['username'], $hash, $u['role'], $u['name'], $u['email'], null, null, $planId);
            $userIds[$u['username']] = $uid;
        }
    }

    // 3. Seed Campaigns
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

    // 4. Seed Chat Messages
    $chatStmt = $pdo->prepare("INSERT INTO public_chats (user_id, message, created_at) VALUES (?, ?, ?)");
    $chatMessages = [
        ['admin', 'Welcome to the Marketing AI Suite chatroom!', date('Y-m-d H:i:s', time() - 3600)],
        ['charlie', 'Hi team! Glad to be here. The new Outreach features look amazing.', date('Y-m-d H:i:s', time() - 1800)],
        ['admin', '@charlie Thanks! Let me know if you need any adjustments to the prompt generation.', date('Y-m-d H:i:s', time() - 900)]
    ];
    foreach ($chatMessages as $msg) {
        $chatStmt->execute([$userIds[$msg[0]], $msg[1], $msg[2]]);
    }

    // 5. Seed System Notifications
    $notifStmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message, created_at) VALUES (?, ?, ?, ?)");
    $notifications = [
        [$userIds['admin'], 'System Maintenance', 'Scheduled system upgrade completed. All services are running optimally.', date('Y-m-d H:i:s', time() - 7200)],
        [$userIds['admin'], 'New Feature Added', 'Outreach Mini Panel is now active on the dashboard. Use it for quick campaign actions!', date('Y-m-d H:i:s', time() - 3600)],
        [$userIds['admin'], 'Welcome Notification', 'Welcome to the platform! Please configure your SMTP settings to begin sending emails.', date('Y-m-d H:i:s', time() - 600)]
    ];
    foreach ($notifications as $n) {
        $notifStmt->execute([$n[0], $n[1], $n[2], $n[3]]);
    }

    // 6. Seed Leads
    // Campaign 1 lead
    $db->saveLead(
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

    // Campaign 2 lead
    $db->saveLead(
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

    // Log admin activity
    $db->logActivity((int)$user['id'], 'LOAD_DEMO', "Loaded application demo data");

    echo json_encode([
        'success' => true,
        'message' => 'Demo data loaded successfully!'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
