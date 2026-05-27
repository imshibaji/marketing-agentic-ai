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
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

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

    // Check file signature to see if it is a SQLite binary file or a JSON file
    $fh = fopen($file['tmp_name'], 'rb');
    if (!$fh) {
        throw new Exception("Could not read uploaded file.");
    }
    $signature = fread($fh, 15);
    fclose($fh);

    if ($signature === "SQLite format 3") {
        // SQLite Binary Restore
        if ($driver !== 'sqlite') {
            throw new Exception("Binary SQLite backups can only be restored when using the SQLite database driver.");
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
        $pdo = null;
        
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
            $testPdo->query("SELECT id, username, role FROM users")->fetch();
            $testPdo->query("SELECT key, value FROM settings")->fetch();
            
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

    } else {
        // Universal JSON Restore
        $fileContent = file_get_contents($file['tmp_name']);
        $json = json_decode($fileContent, true);
        if (!$json || !isset($json['tables'])) {
            throw new Exception("Invalid backup file format. Expected a valid SQLite database or JSON backup file.");
        }

        $schema = new \MarketingAgent\Database\Schema($pdo);

        // Disable foreign keys / constraints temporarily where simple/supported
        if ($driver === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = OFF");
        } elseif ($driver === 'mysql') {
            $pdo->exec("SET foreign_key_checks = 0");
        }

        $pdo->beginTransaction();

        try {
            // Delete existing data in reverse dependency order
            $tables = array_keys($json['tables']);
            $order = ['campaign_shares', 'public_chats', 'notifications', 'settings', 'email_otps', 'user_activity_logs', 'agent_logs', 'leads', 'campaigns', 'users', 'plans'];
            
            // Custom/plugin tables deleted first
            usort($tables, function($a, $b) use ($order) {
                $idxA = array_search($a, $order);
                $idxB = array_search($b, $order);
                
                $posA = ($idxA === false) ? -1 : $idxA;
                $posB = ($idxB === false) ? -1 : $idxB;
                
                return $posB <=> $posA;
            });

            foreach ($tables as $table) {
                $qt = $schema->quoteTable($table);
                $pdo->exec("DELETE FROM {$qt}");
            }

            // Insert new data in forward dependency order
            usort($tables, function($a, $b) use ($order) {
                $idxA = array_search($a, $order);
                $idxB = array_search($b, $order);
                
                $posA = ($idxA === false) ? -1 : $idxA;
                $posB = ($idxB === false) ? -1 : $idxB;
                
                return $posA <=> $posB;
            });

            foreach ($tables as $table) {
                $rows = $json['tables'][$table];
                if (empty($rows)) {
                    continue;
                }

                $qt = $schema->quoteTable($table);
                
                $hasIdentity = ($table !== 'settings' && $table !== 'campaign_shares');
                if ($driver === 'sqlsrv' && $hasIdentity) {
                    $pdo->exec("SET IDENTITY_INSERT {$qt} ON");
                }

                $cols = array_keys($rows[0]);
                $qCols = implode(', ', array_map([$schema, 'quoteColumn'], $cols));
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                
                $insertSql = "INSERT INTO {$qt} ({$qCols}) VALUES ({$placeholders})";
                $stmt = $pdo->prepare($insertSql);

                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($cols as $col) {
                        $vals[] = $row[$col];
                    }
                    $stmt->execute($vals);
                }

                if ($driver === 'sqlsrv' && $hasIdentity) {
                    $pdo->exec("SET IDENTITY_INSERT {$qt} OFF");
                }

                if ($driver === 'pgsql' && $hasIdentity) {
                    try {
                        $pdo->exec("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1)) FROM {$qt}");
                    } catch (Exception $e) {}
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        } finally {
            // Re-enable foreign keys / constraints
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET foreign_key_checks = 1");
            }
        }
    }

    // Invalidate user session after successful restore
    AuthService::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Database restored successfully. You have been logged out.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
