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

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $fullName = trim($input['full_name'] ?? '');
    $email    = trim($input['email'] ?? '');
    $mobile   = trim($input['mobile'] ?? '');
    $whatsapp = trim($input['whatsapp_number'] ?? '');
    $role     = 'user'; // Public registration is always user role; admins use the admin panel

    if (empty($username) || empty($password)) {
        throw new Exception("Username and password are required.");
    }

    $db = new DatabaseService();
    $auth = new AuthService($db);

    $res = $auth->register($username, $password, $role, $fullName, $email, $mobile, $whatsapp);
    if ($res['success']) {
        // Log activity
        $newUser = $db->getUserByUsername($username);
        if ($newUser) {
            $db->logActivity((int)$newUser['id'], 'REGISTER', "User registered account successfully");
        }
        echo json_encode(['success' => true, 'message' => $res['message']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $res['error']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
