<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();
    $settings = $db->getSettings();
    $appName = $settings['app_name'] ?? 'Antigravity Marketing';

    $user = AuthService::getCurrentUser();

    echo json_encode([
        'success' => true,
        'loggedIn' => $user !== null,
        'logged_in' => $user !== null, // backwards compat alias
        'user' => $user,
        'app_name' => $appName
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
