<?php

namespace App\Controllers\Api;

use MarketingAgent\Plugin\PluginManager;
use MarketingAgent\Service\AuthService;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use PDO;

class SystemController extends BaseApiController
{
    public function backup(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can perform database backups.', 403);
            }

            $pdo = $this->db->getPdo();
            // Try to fetch driver name from active database configuration if not set on PDO
            $dbDriver = \Config\Database::connect()->DBDriver;
            $driver = strtolower($dbDriver === 'mysqli' ? 'mysql' : ($dbDriver === 'sqlite3' ? 'sqlite' : $dbDriver));

            $format = $this->request->getGet('format');

            if ($driver === 'sqlite' && $format !== 'json') {
                // SQLite Binary File Backup
                $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');
                if ($sqliteFile === '') {
                    $sqliteFile = 'database/database.sqlite';
                }

                $projectRoot = ROOTPATH;
                if (strpos($sqliteFile, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $sqliteFile) === 1) {
                    $resolvedSqliteFile = $sqliteFile;
                } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
                    $root = $projectRoot;
                    $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
                } else {
                    $resolvedSqliteFile = $projectRoot . 'database/' . $sqliteFile;
                }

                if (!file_exists($resolvedSqliteFile)) {
                    throw new Exception("Database file not found: " . $resolvedSqliteFile);
                }

                $filename = 'marketing_suite_backup_' . date('Y-m-d_H-i-s') . '.sqlite';
                return $this->response->download($resolvedSqliteFile, null)->setFileName($filename);
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
                        $exportData['tables'][$table] = [];
                    }
                }

                $filename = 'marketing_suite_backup_' . date('Y-m-d_H-i-s') . '.json';
                $output = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                return $this->response->download($filename, $output);
            }
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function restore(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can restore the database.', 403);
            }

            $file = $this->request->getFile('backup_file');
            if (!$file || !$file->isValid()) {
                throw new Exception("No backup file uploaded or file is invalid.");
            }

            $tempName = $file->getTempName();

            // Check file signature to see if it is a SQLite binary file or a JSON file
            $fh = fopen($tempName, 'rb');
            if (!$fh) {
                throw new Exception("Could not read uploaded file.");
            }
            $signature = fread($fh, 15);
            fclose($fh);

            $pdo = $this->db->getPdo();
            $dbDriver = \Config\Database::connect()->DBDriver;
            $driver = strtolower($dbDriver === 'mysqli' ? 'mysql' : ($dbDriver === 'sqlite3' ? 'sqlite' : $dbDriver));

            if ($signature === "SQLite format 3") {
                // SQLite Binary Restore
                if ($driver !== 'sqlite') {
                    throw new Exception("Binary SQLite backups can only be restored when using the SQLite database driver.");
                }

                // Get database path
                $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');
                if ($sqliteFile === '') {
                    $sqliteFile = 'database/database.sqlite';
                }

                $projectRoot = ROOTPATH;
                if (strpos($sqliteFile, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $sqliteFile) === 1) {
                    $resolvedSqliteFile = $sqliteFile;
                } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
                    $root = $projectRoot;
                    $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
                } else {
                    $resolvedSqliteFile = $projectRoot . 'database/' . $sqliteFile;
                }

                // Create temporary backup of current database
                $tempBackupFile = $resolvedSqliteFile . '.bak';
                if (file_exists($resolvedSqliteFile)) {
                    if (!copy($resolvedSqliteFile, $tempBackupFile)) {
                        throw new Exception("Failed to create temporary database backup.");
                    }
                }

                // Copy the uploaded file to overwrite the database file
                if (!copy($tempName, $resolvedSqliteFile)) {
                    // Rollback backup if copy failed
                    if (file_exists($tempBackupFile)) {
                        copy($tempBackupFile, $resolvedSqliteFile);
                        unlink($tempBackupFile);
                    }
                    throw new Exception("Failed to overwrite active database file.");
                }

                // Verify integrity of the restored database
                try {
                    // We query the DB directly to test structure
                    $dbConnection = \Config\Database::connect();
                    $dbConnection->query("SELECT id, username, role FROM users LIMIT 1");
                    $dbConnection->query("SELECT `key`, `value` FROM settings LIMIT 1");
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
                $fileContent = file_get_contents($tempName);
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

            return $this->respondSuccess([], 'Database restored successfully. You have been logged out.');
        } catch (Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }

    public function resetApp(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can reset the application.', 403);
            }

            // Drop all tables driver-agnostically using DB Forge
            $dbConnection = \Config\Database::connect();
            $forge = \Config\Database::forge();
            $tables = $dbConnection->listTables();
            
            // Disable foreign key checks for dropping if supported
            $dbDriver = $dbConnection->DBDriver;
            $driver = strtolower($dbDriver === 'mysqli' ? 'mysql' : ($dbDriver === 'sqlite3' ? 'sqlite' : $dbDriver));
            
            if ($driver === 'mysql') {
                $dbConnection->query("SET foreign_key_checks = 0");
            }

            foreach ($tables as $table) {
                $forge->dropTable($table, true);
            }

            if ($driver === 'mysql') {
                $dbConnection->query("SET foreign_key_checks = 1");
            }

            // Run migrations to recreate the clean schemas
            $migrations = \Config\Services::migrations();
            $migrations->latest();

            // Re-instantiate DatabaseService to populate settings, etc.
            // Since initializeSchema is called in its constructor, it runs any plugin/setup logic
            $cleanDb = new \MarketingAgent\Service\DatabaseService();
            $pdo = $cleanDb->getPdo();

            // Get or create Default Plan ID
            $stmt = $pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
            $planId = (int)$stmt->fetchColumn();
            if (!$planId) {
                $planId = $cleanDb->createPlan('Default Plan', 10, 50, 100, 100, 100, 100);
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
                $cleanDb->createUser($u['username'], $hash, $u['role'], $u['name'], $u['email'], null, null, $planId);
            }

            // Invalidate user session
            AuthService::logout();

            return $this->respondSuccess([], 'Application database reset successfully. You have been logged out.');
        } catch (Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }

    public function loadDemo(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can load demo data.', 403);
            }

            $pdo = $this->db->getPdo();

            // 1. Wipe existing campaigns, leads, chats, notifications, logs
            $pdo->exec("DELETE FROM leads");
            $pdo->exec("DELETE FROM campaigns");
            $pdo->exec("DELETE FROM public_chats");
            $pdo->exec("DELETE FROM notifications");
            $pdo->exec("DELETE FROM agent_logs");
            $pdo->exec("DELETE FROM user_activity_logs");

            // 2. Fetch or create users to attach campaigns/leads to
            $usersToEnsure = [
                ['username' => 'admin', 'password' => 'admin', 'role' => 'admin', 'name' => 'Administrator', 'email' => 'admin@example.com'],
                ['username' => 'charlie', 'password' => 'charlie', 'role' => 'user', 'name' => 'Charlie Green', 'email' => 'charlie@example.com']
            ];

            // Find default plan
            $stmt = $pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
            $planId = (int)$stmt->fetchColumn();
            if (!$planId) {
                $planId = $this->db->createPlan('Default Plan', 10, 50, 100, 100, 100, 100);
            }

            $userIds = [];
            foreach ($usersToEnsure as $u) {
                $existing = $this->db->getUserByUsername($u['username']);
                if ($existing) {
                    $userIds[$u['username']] = (int)$existing['id'];
                } else {
                    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
                    $uid = $this->db->createUser($u['username'], $hash, $u['role'], $u['name'], $u['email'], null, null, $planId);
                    $userIds[$u['username']] = $uid;
                }
            }

            // 3. Seed Campaigns
            $campaign1 = $this->db->createCampaign(
                "Acme AutoScheduler",
                "An AI-powered social media post scheduling tool that analyzes engagement metrics and post drafts, automatically scheduling content to maximize organic reach.",
                "Independent content creators and social media marketing managers",
                "email",
                "none",
                "",
                "English",
                $userIds['admin']
            );

            $campaign2 = $this->db->createCampaign(
                "Marketing App Developer",
                "Bespoke mobile application development tailored specifically for modern digital marketing agencies and CRM systems integration.",
                "Agency owners and CTOs looking to build customized interactive dashboards",
                "whatsapp",
                "none",
                "",
                "English",
                $userIds['charlie']
            );

            // 4. Seed Chat Messages
            $chatStmt = $pdo->prepare("INSERT INTO public_chats (user_id, message, created_at) VALUES (?, ?, ?)");
            $chatMessages = [
                ['admin', 'Welcome to the Marketing AI Suite chatroom!', date('Y-m-d H:i:s', time() - 3600)],
                ['charlie', 'Hi team! Glad to be here. The new Outreach features look amazing.', date('Y-m-d H:i:s', time() - 1800)],
                ['admin', '@charlie Thanks! Let me know if you need any adjustments to the prompt generation.', date('Y-m-d H:i:s', time() - 900)]
            ];
            foreach ($chatMessages as $msg) {
                $chatStmt->execute([$userIds[$msg[0]], $msg[1], $msg[2]]);
            }

            // 5. Seed System Notifications
            $notifStmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message, created_at) VALUES (?, ?, ?, ?)");
            $notifications = [
                [$userIds['admin'], 'System Maintenance', 'Scheduled system upgrade completed. All services are running optimally.', date('Y-m-d H:i:s', time() - 7200)],
                [$userIds['admin'], 'New Feature Added', 'Outreach Mini Panel is now active on the dashboard. Use it for quick campaign actions!', date('Y-m-d H:i:s', time() - 3600)],
                [$userIds['admin'], 'Welcome Notification', 'Welcome to the platform! Please configure your SMTP settings to begin sending emails.', date('Y-m-d H:i:s', time() - 600)]
            ];
            foreach ($notifications as $n) {
                $notifStmt->execute([$n[0], $n[1], $n[2], $n[3]]);
            }

            // 6. Seed Leads
            $this->db->saveLead(
                $campaign1,
                "Globex Corporation",
                "Homer Simpson",
                "homer@globex.com",
                "+15550199",
                "Energy & Technology",
                "Needs to automate posting schedules for the corporate reactor division.",
                "85",
                "Fits our target profile of mid-sized energy providers seeking automated marketing workflows.",
                "Hi Homer,\n\nWe noticed Globex is looking to streamline post scheduling. Acme AutoScheduler can save you 10+ hours weekly.\n\nBest,\nAdmin",
                "Hi Homer, Acme AutoScheduler can automate Globex's social posting schedule. Interested in a quick demo?",
                $userIds['admin'],
                "agent",
                "+15550199",
                "Hi Homer, Acme AutoScheduler can automate Globex's social posting schedule. Reply to demo.",
                "100 Sector 7-G, Springfield"
            );

            $this->db->saveLead(
                $campaign2,
                "Initech Solutions",
                "Peter Gibbons",
                "peter@initech.com",
                "+15550123",
                "Consulting Services",
                "Wants to build a bespoke dashboard for client CRM visualization.",
                "95",
                "Excellent fit for custom agency dashboards.",
                "Hi Peter,\n\nWe build custom mobile apps for agencies. We would love to discuss custom visual dashboards for Initech.\n\nRegards,\nCharlie",
                "Hi Peter, Charlie here. We build custom dashboards for agency CRM systems. Let's talk!",
                $userIds['charlie'],
                "agent",
                "+15550123",
                "Hi Peter, Charlie here. We build custom CRM dashboards. Let's chat.",
                "4120 Freemont Ave, Austin, TX"
            );

            // Log admin activity
            $this->db->logActivity((int)$user['id'], 'LOAD_DEMO', "Loaded application demo data");

            return $this->respondSuccess([], 'Demo data loaded successfully!');
        } catch (Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }

    public function listPlugins(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can manage plugins.', 403);
            }

            $plugins = PluginManager::getInstalledPlugins();
            return $this->respondSuccess(['plugins' => $plugins]);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function managePlugin(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only administrators can manage plugins.', 403);
            }

            $input = $this->getJsonInput();
            $action = $input['action'] ?? '';
            $pluginId = $input['plugin'] ?? '';

            if (empty($action) || empty($pluginId)) {
                return $this->respondError('Missing action or plugin parameter.');
            }

            if ($action === 'activate') {
                $result = PluginManager::activatePlugin($pluginId);
                if ($result) {
                    return $this->respondSuccess([], "Plugin '{$pluginId}' activated successfully.");
                } else {
                    throw new Exception("Failed to activate plugin '{$pluginId}'.");
                }
            } elseif ($action === 'deactivate') {
                $result = PluginManager::deactivatePlugin($pluginId);
                if ($result) {
                    return $this->respondSuccess([], "Plugin '{$pluginId}' deactivated successfully.");
                } else {
                    throw new Exception("Failed to deactivate plugin '{$pluginId}'.");
                }
            }

            return $this->respondError("Invalid action. Must be 'activate' or 'deactivate'.");
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
}
