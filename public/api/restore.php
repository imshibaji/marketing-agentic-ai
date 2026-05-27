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
        echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can restore the database.']);
        exit;
    }

    // Check if file is uploaded
    if (!isset($_FILES['backup_file'])) {
        throw new Exception("No backup file uploaded.");
    }

    $file = $_FILES['backup_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed with error code: " . $file['error']);
    }

    if ($file['size'] <= 0) {
        throw new Exception("Uploaded file is empty.");
    }

    // Validate SQLite signature (first 15 bytes must be "SQLite format 3")
    $fh = fopen($file['tmp_name'], 'rb');
    if (!$fh) {
        throw new Exception("Could not read uploaded file.");
    }
    $signature = fread($fh, 15);
    fclose($fh);

    if ($signature !== "SQLite format 3") {
        throw new Exception("Invalid SQLite database file format.");
    }

    // Get the database path
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

    // Create temporary backup of current database
    $tempBackupFile = $resolvedSqliteFile . '.bak';
    if (file_exists($resolvedSqliteFile)) {
        if (!copy($resolvedSqliteFile, $tempBackupFile)) {
            throw new Exception("Failed to create temporary database backup.");
        }
    }

    // Close database connection to release file lock
    $db = null;
    
    // Copy the uploaded file to overwrite the database file
    if (!copy($file['tmp_name'], $resolvedSqliteFile)) {
        // Rollback backup if copy failed
        if (file_exists($tempBackupFile)) {
            copy($tempBackupFile, $resolvedSqliteFile);
            unlink($tempBackupFile);
        }
        throw new Exception("Failed to overwrite active database file.");
    }

    // Verify integrity of the restored database
    try {
        $testDb = new DatabaseService();
        $testPdo = $testDb->getPdo();
        
        // Run a test query on key tables to ensure schema structure is valid
        $testPdo->query("SELECT id, username, role FROM users LIMIT 1");
        $testPdo->query("SELECT key, value FROM settings LIMIT 1");
        
        // Close test connection
        $testDb = null;
    } catch (Exception $e) {
        // Rollback corrupted database file
        if (file_exists($tempBackupFile)) {
            copy($tempBackupFile, $resolvedSqliteFile);
            unlink($tempBackupFile);
        }
        throw new Exception("Restored database verification failed (corrupt file or schema mismatch): " . $e->getMessage());
    }

    // Success: delete temp backup file
    if (file_exists($tempBackupFile)) {
        unlink($tempBackupFile);
    }

    // Invalidate user session
    AuthService::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Database restored successfully. You have been logged out.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
