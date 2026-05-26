<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();
    $user = AuthService::getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    $pdo = $db->getPdo();

    if ($user['role'] === 'admin') {
        // Global stats for admin
        $usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $campaignsCount = (int)$pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
        $leadsCount = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    } else {
        // Scoped stats for regular user
        $usersCount = 1;
        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
        $stmtC->execute([$user['id']]);
        $campaignsCount = (int)$stmtC->fetchColumn();

        $stmtL = $pdo->prepare("
            SELECT COUNT(*) FROM leads l 
            LEFT JOIN campaigns c ON l.campaign_id = c.id 
            WHERE l.user_id = ? OR c.user_id = ?
        ");
        $stmtL->execute([$user['id'], $user['id']]);
        $leadsCount = (int)$stmtL->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'users' => $usersCount,
            'campaigns' => $campaignsCount,
            'leads' => $leadsCount
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
