<?php
require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();
    
    // Authorization check: Admin only
    $user = AuthService::getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Only administrators can perform database backups.']);
        exit;
    }

    $pdo = $db->getPdo();
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : '';

    if ($driver === 'sqlite' && $format !== 'json') {
        // SQLite Binary File Backup
        $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');
        if ($sqliteFile === '') {
            $sqliteFile = 'database/database.sqlite';
        }

        // Resolve path (mirroring loadDatabaseConfig in DatabaseService)
        $projectRoot = realpath(__DIR__ . '/../../');
        if (strpos($sqliteFile, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $sqliteFile) === 1) {
            $resolvedSqliteFile = $sqliteFile;
        } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
            $root = $projectRoot ?: __DIR__ . '/../../database';
            $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
        } else {
            $resolvedSqliteFile = __DIR__ . '/../../database/' . $sqliteFile;
        }

        if (!file_exists($resolvedSqliteFile)) {
            throw new Exception("Database file not found: " . $resolvedSqliteFile);
        }

        // Clear output buffer to ensure clean file download
        if (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'marketing_suite_backup_' . date('Y-m-d_H-i-s') . '.sqlite';
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($resolvedSqliteFile));
        readfile($resolvedSqliteFile);
        exit;
    } else {
        // Universal JSON Backup
        $tables = ['plans', 'users', 'campaigns', 'leads', 'agent_logs', 'user_activity_logs', 'email_otps', 'settings', 'notifications', 'public_chats', 'campaign_shares'];
        $tables = \MarketingAgent\Plugin\HookManager::applyFilters('db_backup_tables', $tables);

        $schema = new \MarketingAgent\Database\Schema($pdo);
        $exportData = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'driver' => $driver,
            'tables' => []
        ];

        foreach ($tables as $table) {
            $qt = $schema->quoteTable($table);
            try {
                $stmt = $pdo->query("SELECT * FROM {$qt}");
                $exportData['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table might not exist or error out, skip
                $exportData['tables'][$table] = [];
            }
        }

        // Clear output buffer to ensure clean file download
        if (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'marketing_suite_backup_' . date('Y-m-d_H-i-s') . '.json';
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        $output = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        header('Content-Length: ' . strlen($output));
        echo $output;
        exit;
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
