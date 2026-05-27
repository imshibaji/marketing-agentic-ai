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

    // Authorization check: Admin only
    $user = AuthService::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can reset the application.']);
        exit;
    }

    // Get database path
    $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');
    if ($sqliteFile === '') {
        $sqliteFile = 'database/database.sqlite';
    }

    // Resolve path
    $projectRoot = realpath(__DIR__ . '/../../');
    if (strpos($sqliteFile, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $sqliteFile) === 1) {
        $resolvedSqliteFile = $sqliteFile;
    } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
        $root = $projectRoot ?: __DIR__ . '/../../database';
        $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
    } else {
        $resolvedSqliteFile = __DIR__ . '/../../database/' . $sqliteFile;
    }

    // Close active database connection to release file locks
    $db = null;

    if (file_exists($resolvedSqliteFile)) {
        if (!unlink($resolvedSqliteFile)) {
            throw new Exception("Failed to delete database file.");
        }
    }

    // Instantiate DatabaseService to trigger schema recreation
    $db = new DatabaseService();
    $pdo = $db->getPdo();

    // Get Default Plan ID
    $stmt = $pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
    $planId = (int)$stmt->fetchColumn();
    if (!$planId) {
        $planId = $db->createPlan('Default Plan', 10, 50, 100, 100, 100, 100);
    }

    // Seed default users
    $users = [
        ['username' => 'admin', 'password' => 'admin', 'role' => 'admin', 'name' => 'Administrator', 'email' => 'admin@example.com'],
        ['username' => 'testadmin', 'password' => 'admin123', 'role' => 'admin', 'name' => 'Test Administrator', 'email' => 'testadmin@example.com'],
        ['username' => 'user', 'password' => 'user', 'role' => 'user', 'name' => 'Standard User', 'email' => 'user@example.com'],
        ['username' => 'charlie', 'password' => 'charlie', 'role' => 'user', 'name' => 'Charlie Green', 'email' => 'charlie@example.com'],
        ['username' => 'alice_updated', 'password' => 'alice', 'role' => 'admin', 'name' => 'Alice Green', 'email' => 'alice@example.com']
    ];

    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        $db->createUser($u['username'], $hash, $u['role'], $u['name'], $u['email'], null, null, $planId);
    }

    // Invalidate user session
    AuthService::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Application database reset successfully. You have been logged out.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
