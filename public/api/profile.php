<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $profile = $db->getUserById((int)$user['id']);
        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }
        unset($profile['password_hash']);
        echo json_encode(['success' => true, 'profile' => $profile]);
        exit;
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }

        $existing = $db->getUserById((int)$user['id']);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $username = isset($input['username']) ? trim($input['username']) : $existing['username'];
        $password = $input['password'] ?? null;
        $fullName = isset($input['full_name']) ? trim($input['full_name']) : $existing['full_name'];
        $email    = isset($input['email']) ? trim($input['email']) : $existing['email'];
        $mobile   = isset($input['mobile']) ? trim($input['mobile']) : $existing['mobile'];
        $whatsapp = isset($input['whatsapp_number']) ? trim($input['whatsapp_number']) : $existing['whatsapp_number'];

        // Validate
        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters.']);
            exit;
        }
        if ($password !== null && strlen($password) > 0 && strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
            exit;
        }

        // Duplicate username check
        if ($username !== $existing['username']) {
            $dup = $db->getUserByUsername($username);
            if ($dup) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Username already taken.']);
                exit;
            }
        }

        $newHash = ($password && strlen($password) >= 6)
                    ? password_hash($password, PASSWORD_BCRYPT)
                    : $existing['password_hash'];

        // Standard users cannot change their role or plan limits, so we preserve their existing ones
        $db->updateUser(
            (int)$user['id'],
            $username,
            $newHash,
            $existing['role'],
            $fullName,
            $email,
            $mobile,
            $whatsapp,
            (int)$existing['plan_id']
        );

        // Update session username if it changed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['username'] = $username;

        // Log activity
        $db->logActivity((int)$user['id'], 'UPDATE_PROFILE', "Updated own profile settings");

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
