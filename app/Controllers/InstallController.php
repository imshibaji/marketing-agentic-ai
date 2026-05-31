<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use PDO;

class InstallController extends BaseController
{
    public function index()
    {
        $lockFile = ROOTPATH . 'database/install.lock';
        if (file_exists($lockFile)) {
            return view('install_locked');
        }

        // Run pre-install system checks
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.0.0', '>=');
        $sqliteOk = extension_loaded('pdo_sqlite');
        $mysqlOk  = extension_loaded('pdo_mysql');
        $pgsqlOk  = extension_loaded('pdo_pgsql');
        $sqlsrvOk = extension_loaded('pdo_sqlsrv');
        $ociOk    = extension_loaded('oci8') || extension_loaded('pdo_oci');
        $pdoOk = $sqliteOk || $mysqlOk || $pgsqlOk || $sqlsrvOk || $ociOk;
        $rootWritable = is_writable(ROOTPATH);
        $dbDirWritable = is_writable(ROOTPATH . 'database') || (!file_exists(ROOTPATH . 'database') && is_writable(ROOTPATH));

        $data = [
            'phpVersion' => $phpVersion,
            'phpOk' => $phpOk,
            'sqliteOk' => $sqliteOk,
            'mysqlOk' => $mysqlOk,
            'pgsqlOk' => $pgsqlOk,
            'sqlsrvOk' => $sqlsrvOk,
            'ociOk' => $ociOk,
            'pdoOk' => $pdoOk,
            'rootWritable' => $rootWritable,
            'dbDirWritable' => $dbDirWritable,
            'allChecksPassed' => ($phpOk && $pdoOk && $rootWritable && $dbDirWritable)
        ];

        return view('install', $data);
    }

    public function action(): ResponseInterface
    {
        $lockFile = ROOTPATH . 'database/install.lock';
        if (file_exists($lockFile)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => 'Application already installed. Delete database/install.lock to reinstall.'
            ]);
        }

        $action = $this->request->getGet('action');
        $input = $this->request->getJSON(true) ?: $this->request->getPost();

        if ($action === 'test_connection') {
            try {
                $driver = $input['driver'] ?? 'sqlite';
                $this->testDbConnection($driver, $input);
                return $this->response->setJSON(['success' => true]);
            } catch (\Throwable $e) {
                return $this->response->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        if ($action === 'run_install') {
            try {
                $driver = $input['driver'] ?? 'sqlite';
                $this->runApplicationInstaller($driver, $input);
                return $this->response->setJSON(['success' => true]);
            } catch (\Throwable $e) {
                return $this->response->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        return $this->response->setStatusCode(400)->setJSON([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }

    private function testDbConnection(string $driver, array $config): void
    {
        if ($driver === 'sqlite') {
            $file = trim($config['sqlite_file'] ?? 'database/database.sqlite');
            if (empty($file)) {
                throw new Exception("SQLite database file path cannot be empty.");
            }
            
            $path = ROOTPATH . '/' . $file;
            if (strpos($file, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $file) === 1) {
                $path = $file;
            }

            $dir = dirname($path);
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0777, true)) {
                    throw new Exception("Failed to create SQLite directory: '{$dir}'");
                }
            }
            if (!is_writable($dir)) {
                throw new Exception("SQLite database directory is not writable: '{$dir}'");
            }
            
            $dsn = "sqlite:" . $path;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $host = trim($config['host'] ?? '127.0.0.1');
            $port = trim($config['port'] ?? '');
            $dbname = trim($config['database'] ?? '');
            $username = trim($config['username'] ?? '');
            $password = trim($config['password'] ?? '');

            if (empty($dbname)) {
                throw new Exception("Database name cannot be empty.");
            }

            if ($driver === 'mysql') {
                $port = $port ?: '3306';
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            } elseif ($driver === 'pgsql') {
                $port = $port ?: '5432';
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            } elseif ($driver === 'sqlsrv') {
                $port = $port ?: '1433';
                $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
            } elseif ($driver === 'oracle') {
                $port = $port ?: '1521';
                $serviceName = trim($config['service_name'] ?? $dbname);
                $dsn = "oci:dbname=//{$host}:{$port}/{$serviceName};charset=UTF8";
            } else {
                throw new Exception("Unsupported database driver: " . $driver);
            }

            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        }
    }

    private function runApplicationInstaller(string $driver, array $input): void
    {
        $adminUsername = trim($input['admin_username'] ?? '');
        $adminPassword = trim($input['admin_password'] ?? '');
        $adminEmail = trim($input['admin_email'] ?? '');
        $adminName = trim($input['admin_name'] ?? '');

        if (empty($adminUsername) || strlen($adminUsername) < 3) {
            throw new Exception("Admin username must be at least 3 characters long.");
        }
        if (empty($adminPassword) || strlen($adminPassword) < 6) {
            throw new Exception("Admin password must be at least 6 characters long.");
        }

        // Test connection first
        $this->testDbConnection($driver, $input);

        // Generate and write .env file
        $envContent = "# Database configuration\n";
        $envContent .= "DB_DRIVER={$driver}\n";
        if ($driver === 'sqlite') {
            $envContent .= "DB_SQLITE_FILE=" . ($input['sqlite_file'] ?? 'database/database.sqlite') . "\n";
        } else {
            $defaultPorts = ['mysql' => '3306','pgsql' => '5432','sqlsrv' => '1433','oracle' => '1521'];
            $envContent .= "DB_HOST=" . ($input['host'] ?? '127.0.0.1') . "\n";
            $envContent .= "DB_PORT=" . ($input['port'] ?? ($defaultPorts[$driver] ?? '3306')) . "\n";
            $envContent .= "DB_DATABASE=" . ($input['database'] ?? '') . "\n";
            $envContent .= "DB_USERNAME=" . ($input['username'] ?? '') . "\n";
            $envContent .= "DB_PASSWORD=" . ($input['password'] ?? '') . "\n";
            $envContent .= "DB_CHARSET=utf8mb4\n";
            if (!empty($input['service_name'])) {
                $envContent .= "DB_SERVICE_NAME=" . $input['service_name'] . "\n";
            }
        }
        $envContent .= "\n# Google Gemini API Key\n";
        $envContent .= "GEMINI_API_KEY=" . trim($input['gemini_api_key'] ?? '') . "\n";

        $envFile = ROOTPATH . '.env';
        if (!@file_put_contents($envFile, $envContent)) {
            throw new Exception("Failed to write .env file. Check folder write permissions.");
        }

        // Set env variables inside the current thread manually so that DatabaseService reads them
        putenv("DB_DRIVER={$driver}");
        if ($driver === 'sqlite') {
            putenv("DB_SQLITE_FILE=" . ($input['sqlite_file'] ?? 'database/database.sqlite'));
        } else {
            $defaultPorts = ['mysql' => '3306','pgsql' => '5432','sqlsrv' => '1433','oracle' => '1521'];
            putenv("DB_HOST=" . ($input['host'] ?? '127.0.0.1'));
            putenv("DB_PORT=" . ($input['port'] ?? ($defaultPorts[$driver] ?? '3306')));
            putenv("DB_DATABASE=" . ($input['database'] ?? ''));
            putenv("DB_USERNAME=" . ($input['username'] ?? ''));
            putenv("DB_PASSWORD=" . ($input['password'] ?? ''));
            putenv("DB_CHARSET=utf8mb4");
            if (!empty($input['service_name'])) {
                putenv("DB_SERVICE_NAME=" . $input['service_name']);
            }
        }
        putenv("GEMINI_API_KEY=" . trim($input['gemini_api_key'] ?? ''));

        // Refresh/reconnect database configuration in CodeIgniter dynamically
        $dbConfig = config('Database');
        $dbConfig->default['DBDriver'] = ($driver === 'sqlite') ? 'SQLite3' : ($driver === 'mysql' ? 'MySQLi' : ($driver === 'pgsql' ? 'Postgre' : ($driver === 'sqlsrv' ? 'SQLSRV' : 'OCI8')));
        if ($driver === 'sqlite') {
            $sqlitePath = $input['sqlite_file'] ?? 'database/database.sqlite';
            $dbConfig->default['database'] = (strpos($sqlitePath, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $sqlitePath) === 1) ? $sqlitePath : ROOTPATH . $sqlitePath;
        } else {
            $dbConfig->default['hostname'] = $input['host'] ?? '127.0.0.1';
            $dbConfig->default['database'] = $input['database'] ?? '';
            $dbConfig->default['username'] = $input['username'] ?? '';
            $dbConfig->default['password'] = $input['password'] ?? '';
            $dbConfig->default['port'] = $input['port'] ?? '';
        }

        // Initialize DatabaseService (triggers migrations run)
        $dbService = new \MarketingAgent\Service\DatabaseService();
        $pdo = $dbService->getPdo();

        // Create/overwrite Admin User
        $pdo->prepare("DELETE FROM users WHERE username = ? OR role = 'admin'")->execute([$adminUsername]);

        // Create Premium Plan
        $pdo->exec("DELETE FROM plans WHERE name = 'Premium Plan'");
        $stmt = $pdo->prepare("
            INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit, sms_limit)
            VALUES ('Premium Plan', -1, -1, -1, -1, -1, -1)
        ");
        $stmt->execute();
        $planId = (int)$pdo->lastInsertId();

        // Insert Admin user
        $passHash = password_hash($adminPassword, PASSWORD_BCRYPT);
        $dbService->createUser($adminUsername, $passHash, 'admin', $adminName, $adminEmail, null, null, $planId);

        // Save default settings
        $settings = [
            'app_name' => trim($input['app_name'] ?? 'Marketing AI Agent'),
            'gemini_api_key' => trim($input['gemini_api_key'] ?? ''),
            'gemini_active' => !empty(trim($input['gemini_api_key'] ?? '')) ? '1' : '0',
            'enable_public_chat' => '1',
            'enable_public_notifications' => '1'
        ];
        $dbService->saveSettings($settings);

        // Write install lock file
        $lockPath = ROOTPATH . 'database/install.lock';
        if (!@file_put_contents($lockPath, 'Installed on ' . date('c'))) {
            throw new Exception("Installation completed, but failed to write database/install.lock.");
        }
    }
}
