<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db  = new DatabaseService();
    $me  = AuthService::getCurrentUser();

    // All user management endpoints are admin-only
    if (!$me || $me['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Admin access required.']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    /* ----------------------------------------
       GET  /api/users.php          → list all users
       GET  /api/users.php?id=N     → single user
    ---------------------------------------- */
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $user = $db->getUserById((int)$id);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                exit;
            }
            unset($user['password_hash']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            $users = $db->getUsers();
            // Strip password hashes
            foreach ($users as &$u) unset($u['password_hash']);
            echo json_encode(['success' => true, 'users' => $users]);
        }
        exit;
    }

    /* ----------------------------------------
       POST /api/users.php  → create user (admin creates any role)
       body: { username, password, role }
    ---------------------------------------- */
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $role     = $input['role'] ?? 'user';
        $fullName = trim($input['full_name'] ?? '');
        $email    = trim($input['email'] ?? '');
        $mobile   = trim($input['mobile'] ?? '');
        $whatsapp = trim($input['whatsapp_number'] ?? '');
        $planId = isset($input['plan_id']) && $input['plan_id'] !== '' ? (int)$input['plan_id'] : null;

        if (strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters.']);
            exit;
        }
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
            exit;
        }
        if (!in_array($role, ['admin', 'user'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Role must be admin or user.']);
            exit;
        }

        // Check duplicate
        $existing = $db->getUserByUsername($username);
        if ($existing) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Username already exists.']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $userId = $db->createUser($username, $hash, $role, $fullName, $email, $mobile, $whatsapp, $planId);

        // Log activity
        $db->logActivity((int)$me['id'], 'CREATE_USER', "Admin created user '{$username}' with role '{$role}'");

        echo json_encode(['success' => true, 'message' => 'User created successfully.', 'user_id' => $userId]);
        exit;
    }

    /* ----------------------------------------
       PUT /api/users.php?id=N  → update user
       body: { username?, password?, role? }
    ---------------------------------------- */
    if ($method === 'PUT') {
        $id    = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing user ID.']);
            exit;
        }

        $existing = $db->getUserById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $username = isset($input['username']) ? trim($input['username']) : null;
        $password = $input['password'] ?? null;
        $role     = $input['role'] ?? null;
        $fullName = isset($input['full_name']) ? trim($input['full_name']) : $existing['full_name'];
        $email    = isset($input['email']) ? trim($input['email']) : $existing['email'];
        $mobile   = isset($input['mobile']) ? trim($input['mobile']) : $existing['mobile'];
        $whatsapp = isset($input['whatsapp_number']) ? trim($input['whatsapp_number']) : $existing['whatsapp_number'];
        $planId = isset($input['plan_id']) && $input['plan_id'] !== '' ? (int)$input['plan_id'] : $existing['plan_id'];

        // Validate
        if ($username !== null && strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters.']);
            exit;
        }
        if ($password !== null && strlen($password) > 0 && strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
            exit;
        }
        if ($role !== null && !in_array($role, ['admin', 'user'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Role must be admin or user.']);
            exit;
        }

        // Duplicate username check (ignore self)
        if ($username !== null && $username !== $existing['username']) {
            $dup = $db->getUserByUsername($username);
            if ($dup) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Username already taken.']);
                exit;
            }
        }

        $newUsername = $username ?? $existing['username'];
        $newHash     = ($password && strlen($password) >= 6)
                        ? password_hash($password, PASSWORD_BCRYPT)
                        : $existing['password_hash'];
        $newRole     = $role ?? $existing['role'];

        $db->updateUser($id, $newUsername, $newHash, $newRole, $fullName, $email, $mobile, $whatsapp, $planId);

        // Log activity
        $db->logActivity((int)$me['id'], 'UPDATE_USER', "Admin updated user '{$newUsername}' (ID: {$id})");

        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        exit;
    }

    /* ----------------------------------------
       DELETE /api/users.php?id=N  → delete user
    ---------------------------------------- */
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing user ID.']);
            exit;
        }

        // Prevent admin from deleting themselves
        if ($id === (int)$me['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You cannot delete your own account.']);
            exit;
        }

        $user = $db->getUserById($id);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $db->deleteUser($id);

        // Log activity
        $db->logActivity((int)$me['id'], 'DELETE_USER', "Admin deleted user '{$user['username']}' (ID: {$id})");

        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
