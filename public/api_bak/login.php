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

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON request body.");
    }

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        throw new Exception("Username and password are required.");
    }

    $db = new DatabaseService();
    $auth = new AuthService($db);

    $res = $auth->login($username, $password);
    if ($res['success']) {
        // Log activity
        $db->logActivity((int)$res['user']['id'], 'LOGIN', "Logged in successfully via password");
        echo json_encode([
            'success' => true,
            'message' => $res['message'],
            'user' => $res['user']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $res['error']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
