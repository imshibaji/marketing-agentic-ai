<?php
/**
 * Marketing AI Agentic Automation - Application Installer
 */

// If app is already installed, reject installer access
$lockFile = __DIR__ . '/../database/install.lock';
if (file_exists($lockFile)) {
    if (isset($_GET['action'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Application already installed. Delete database/install.lock to reinstall.']);
        exit;
    }
    // Render already-installed page
    renderAlreadyInstalled();
    exit;
}

// ── BACKEND API ACTIONS ───────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if ($_GET['action'] === 'test_connection') {
        try {
            $driver = $input['driver'] ?? 'sqlite';
            testDbConnection($driver, $input);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'run_install') {
        try {
            $driver = $input['driver'] ?? 'sqlite';
            runApplicationInstaller($driver, $input);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// ── CONNECTION TEST LOGIC ─────────────────────────────────────────────
function testDbConnection(string $driver, array $config): void {
    if ($driver === 'sqlite') {
        $file = trim($config['sqlite_file'] ?? 'database/database.sqlite');
        if (empty($file)) {
            throw new \Exception("SQLite database file path cannot be empty.");
        }
        // Resolve absolute or relative path
        $projectRoot = realpath(__DIR__ . '/../');
        if (strpos($file, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $file) === 1) {
            $path = $file;
        } else {
            $path = $projectRoot . '/' . $file;
        }
        
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                throw new \Exception("Failed to create SQLite directory: '{$dir}'");
            }
        }
        if (!is_writable($dir)) {
            throw new \Exception("SQLite database directory is not writable: '{$dir}'");
        }
        
        $dsn = "sqlite:" . $path;
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    } else {
        $host = trim($config['host'] ?? '127.0.0.1');
        $port = trim($config['port'] ?? '3306');
        $dbname = trim($config['database'] ?? '');
        $username = trim($config['username'] ?? '');
        $password = trim($config['password'] ?? '');

        if (empty($dbname)) {
            throw new \Exception("Database name cannot be empty.");
        }

        if ($driver === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        } elseif ($driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        } else {
            throw new \Exception("Unsupported database driver: " . $driver);
        }

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_TIMEOUT => 5,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}

// ── INSTALL RUNNER LOGIC ──────────────────────────────────────────────
function runApplicationInstaller(string $driver, array $input): void {
    // 1. Validate inputs
    $adminUsername = trim($input['admin_username'] ?? '');
    $adminPassword = trim($input['admin_password'] ?? '');
    $adminEmail = trim($input['admin_email'] ?? '');
    $adminName = trim($input['admin_name'] ?? '');

    if (empty($adminUsername) || strlen($adminUsername) < 3) {
        throw new \Exception("Admin username must be at least 3 characters long.");
    }
    if (empty($adminPassword) || strlen($adminPassword) < 6) {
        throw new \Exception("Admin password must be at least 6 characters long.");
    }

    // Test connection first
    testDbConnection($driver, $input);

    // 2. Generate and write .env file
    $envContent = "# Database configuration\n";
    $envContent .= "DB_DRIVER={$driver}\n";
    if ($driver === 'sqlite') {
        $envContent .= "DB_SQLITE_FILE=" . ($input['sqlite_file'] ?? 'database/database.sqlite') . "\n";
    } else {
        $envContent .= "DB_HOST=" . ($input['host'] ?? '127.0.0.1') . "\n";
        $envContent .= "DB_PORT=" . ($input['port'] ?? '3306') . "\n";
        $envContent .= "DB_DATABASE=" . ($input['database'] ?? '') . "\n";
        $envContent .= "DB_USERNAME=" . ($input['username'] ?? '') . "\n";
        $envContent .= "DB_PASSWORD=" . ($input['password'] ?? '') . "\n";
        $envContent .= "DB_CHARSET=utf8mb4\n";
    }
    $envContent .= "\n# Google Gemini API Key\n";
    $envContent .= "GEMINI_API_KEY=" . trim($input['gemini_api_key'] ?? '') . "\n";

    $projectRoot = realpath(__DIR__ . '/../');
    $envFile = $projectRoot . '/.env';
    if (!@file_put_contents($envFile, $envContent)) {
        throw new \Exception("Failed to write .env file. Check folder write permissions.");
    }

    // Set env variables inside the current thread manually so that DatabaseService reads them
    putenv("DB_DRIVER={$driver}");
    if ($driver === 'sqlite') {
        putenv("DB_SQLITE_FILE=" . ($input['sqlite_file'] ?? 'database/database.sqlite'));
    } else {
        putenv("DB_HOST=" . ($input['host'] ?? '127.0.0.1'));
        putenv("DB_PORT=" . ($input['port'] ?? '3306'));
        putenv("DB_DATABASE=" . ($input['database'] ?? ''));
        putenv("DB_USERNAME=" . ($input['username'] ?? ''));
        putenv("DB_PASSWORD=" . ($input['password'] ?? ''));
        putenv("DB_CHARSET=utf8mb4");
    }
    putenv("GEMINI_API_KEY=" . trim($input['gemini_api_key'] ?? ''));

    // 3. Initialize schemas via DatabaseService
    require_once $projectRoot . '/autoload.php';
    
    // Instantiate DatabaseService. This creates/opens connection and calls initializeSchema()
    $db = new \MarketingAgent\Service\DatabaseService();
    $pdo = $db->getPdo();

    // 4. Create/overwrite Admin User
    // Delete existing admin records (if any) or user with same name to prevent constraint errors
    $pdo->prepare("DELETE FROM users WHERE username = ? OR role = 'admin'")->execute([$adminUsername]);

    // Create Premium Plan if missing
    $pdo->exec("DELETE FROM plans WHERE name = 'Premium Plan'");
    $stmt = $pdo->prepare("
        INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit, sms_limit)
        VALUES ('Premium Plan', -1, -1, -1, -1, -1, -1)
    ");
    $stmt->execute();
    $planId = (int)$pdo->lastInsertId();

    // Insert Admin user
    $passHash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $userModel = new \MarketingAgent\Model\User($pdo);
    $userModel->createUser($adminUsername, $passHash, 'admin', $adminName, $adminEmail, null, null, $planId);

    // 5. Save default settings
    $settings = [
        'app_name' => trim($input['app_name'] ?? 'Marketing AI Agent'),
        'gemini_api_key' => trim($input['gemini_api_key'] ?? ''),
        'gemini_active' => !empty(trim($input['gemini_api_key'] ?? '')) ? '1' : '0',
        'enable_public_chat' => '1',
        'enable_public_notifications' => '1'
    ];
    $db->saveSettings($settings);

    // 6. Write install lock file
    $lockPath = $projectRoot . '/database/install.lock';
    if (!@file_put_contents($lockPath, 'Installed on ' . date('c'))) {
        throw new \Exception("Installation completed, but failed to write database/install.lock. Lock file needs to be created manually.");
    }
}

// ── RENDER COMPONENT: ALREADY INSTALLED ────────────────────────────────
function renderAlreadyInstalled() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>App Already Installed</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: radial-gradient(ellipse at 20% 50%, hsla(265,80%,15%,0.95) 0%, hsla(220,30%,8%,0.98) 60%);
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                color: #f1f2f6;
            }
            .lock-card {
                background: rgba(22, 28, 45, 0.45);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 20px;
                padding: 40px;
                max-width: 460px;
                text-align: center;
                box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="lock-card">
            <i class="fas fa-lock" style="font-size: 48px; color: var(--accent-primary, #9b51e0); margin-bottom: 20px;"></i>
            <h2 style="margin: 0 0 10px 0;">Installation Locked</h2>
            <p style="color: #a4b0be; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
                The application is already installed and configured. To rerun the installation wizard, you must delete the lock file located at:
                <code style="display:block; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; margin:10px 0; font-family:monospace; color:#ff7675; font-size:12px;">database/install.lock</code>
            </p>
            <a href="index.php" style="display:inline-block; background:linear-gradient(135deg, #8e44ad, #2980b9); color:white; text-decoration:none; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; box-shadow: 0 8px 16px rgba(142,68,173,0.3);">
                Go to Application Log In
            </a>
        </div>
    </body>
    </html>
    <?php
}

// ── RENDER MAIN WEB INSTALLER VIEW ────────────────────────────────────
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.0.0', '>=');
$sqliteOk = extension_loaded('pdo_sqlite');
$mysqlOk = extension_loaded('pdo_mysql');
$pdoOk = $sqliteOk || $mysqlOk;
$rootWritable = is_writable(__DIR__ . '/../');
$dbDirWritable = is_writable(__DIR__ . '/../database') || (!file_exists(__DIR__ . '/../database') && is_writable(__DIR__ . '/../'));

$allChecksPassed = $phpOk && $pdoOk && $rootWritable && $dbDirWritable;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Installation Wizard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: radial-gradient(ellipse at 20% 50%, hsla(265,80%,15%,0.95) 0%, hsla(220,30%,8%,0.98) 60%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #f1f2f6;
        }

        .installer-card {
            width: 100%;
            max-width: 580px;
            background: rgba(22, 28, 45, 0.45);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .installer-header {
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.4), rgba(41, 128, 185, 0.2));
            padding: 32px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .logo-box {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #9b51e0, #2f80ed);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(155, 81, 224, 0.4);
        }

        .step-indicators {
            display: flex;
            justify-content: space-between;
            padding: 24px 32px 0;
            background: rgba(0, 0, 0, 0.15);
        }

        .step-dot {
            flex: 1;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: #747d8c;
            position: relative;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            color: #a4b0be;
            border-bottom-color: #9b51e0;
        }

        .step-dot.completed {
            color: #2ecc71;
            border-bottom-color: #2ecc71;
        }

        .installer-body {
            padding: 32px;
            flex-grow: 1;
        }

        .installer-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .installer-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .check-label {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .check-status.success {
            color: #2ecc71;
            font-size: 16px;
        }

        .check-status.error {
            color: #ff7675;
            font-size: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #a4b0be;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group input, .form-group select {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 10px 14px;
            color: #f1f2f6;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #9b51e0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn-row {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            gap: 16px;
        }

        .btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .btn-primary {
            background: linear-gradient(135deg, #9b51e0, #2f80ed);
            border: none;
            box-shadow: 0 4px 15px rgba(155, 81, 224, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #a75eed, #3d8cf5);
            box-shadow: 0 6px 18px rgba(155, 81, 224, 0.4);
        }

        .btn-primary:disabled {
            background: #57606f;
            box-shadow: none;
            cursor: not-allowed;
            color: #a4b0be;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .status-alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.5;
            display: none;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .status-alert.success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
            display: flex;
        }

        .status-alert.error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #ff7675;
            display: flex;
        }
    </style>
</head>
<body>
    <div class="installer-card">
        <div class="installer-header">
            <div class="logo-box">A</div>
            <h2 style="margin: 0 0 4px 0; font-size: 22px;">App Installation Wizard</h2>
            <span style="font-size: 12px; color: #a4b0be;">Configure environment database and administrator profiles</span>
        </div>

        <div class="step-indicators">
            <div class="step-dot active" id="dot-1">1. Checks</div>
            <div class="step-dot" id="dot-2">2. Database</div>
            <div class="step-dot" id="dot-3">3. Admin</div>
            <div class="step-dot" id="dot-4">4. Config</div>
            <div class="step-dot" id="dot-5">5. Finish</div>
        </div>

        <div class="installer-body">
            <!-- STEP 1: PREREQUISITES -->
            <div class="installer-step active" id="step-1">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;"><i class="fas fa-clipboard-check" style="color:#9b51e0; margin-right:6px;"></i> System Checks</h3>
                
                <div class="check-item">
                    <div class="check-label">
                        <i class="fas fa-microchip"></i>
                        <span>PHP Version (>= 8.0.0): <strong><?php echo htmlspecialchars($phpVersion); ?></strong></span>
                    </div>
                    <div class="check-status <?php echo $phpOk ? 'success' : 'error'; ?>">
                        <i class="fas <?php echo $phpOk ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                </div>

                <div class="check-item">
                    <div class="check-label">
                        <i class="fas fa-database"></i>
                        <span>PDO Driver (SQLite/MySQL): <strong><?php echo $pdoOk ? 'Available' : 'Missing'; ?></strong></span>
                    </div>
                    <div class="check-status <?php echo $pdoOk ? 'success' : 'error'; ?>">
                        <i class="fas <?php echo $pdoOk ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                </div>

                <div class="check-item">
                    <div class="check-label">
                        <i class="fas fa-folder-open"></i>
                        <span>Root Folder Write Permissions: <strong><?php echo $rootWritable ? 'Writable' : 'Blocked'; ?></strong></span>
                    </div>
                    <div class="check-status <?php echo $rootWritable ? 'success' : 'error'; ?>">
                        <i class="fas <?php echo $rootWritable ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                </div>

                <div class="check-item">
                    <div class="check-label">
                        <i class="fas fa-hdd"></i>
                        <span>Database Folder Write Permissions: <strong><?php echo $dbDirWritable ? 'Writable' : 'Blocked'; ?></strong></span>
                    </div>
                    <div class="check-status <?php echo $dbDirWritable ? 'success' : 'error'; ?>">
                        <i class="fas <?php echo $dbDirWritable ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                </div>

                <?php if (!$allChecksPassed): ?>
                    <div class="status-alert error" style="display:flex;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Some requirements are missing. Please fix file permissions or PHP modules to proceed.</span>
                    </div>
                <?php endif; ?>

                <div class="btn-row">
                    <span></span>
                    <button class="btn btn-primary" id="btn-next-1" <?php echo !$allChecksPassed ? 'disabled' : ''; ?>>
                        Next: Database <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: DATABASE CONFIG -->
            <div class="installer-step" id="step-2">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;"><i class="fas fa-server" style="color:#9b51e0; margin-right:6px;"></i> Configure Database Connection</h3>
                
                <div class="form-group">
                    <label for="driver"><i class="fas fa-hdd"></i> Database Driver</label>
                    <select id="driver" name="driver">
                        <?php if ($sqliteOk): ?><option value="sqlite">SQLite (File-based, recommended for simple setups)</option><?php endif; ?>
                        <?php if ($mysqlOk): ?><option value="mysql">MySQL / MariaDB (Server-based)</option><?php endif; ?>
                    </select>
                </div>

                <!-- SQLite Settings Group -->
                <div class="db-group db-sqlite">
                    <div class="form-group">
                        <label for="sqlite_file">SQLite File Path</label>
                        <input type="text" id="sqlite_file" name="sqlite_file" value="database/database.sqlite">
                        <small style="color:#a4b0be; font-size:11px; margin-top:2px;">Relative path starting from the project directory root.</small>
                    </div>
                </div>

                <!-- MySQL Settings Group -->
                <div class="db-group db-mysql" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="host">Database Host</label>
                            <input type="text" id="host" name="host" value="127.0.0.1">
                        </div>
                        <div class="form-group">
                            <label for="port">Port</label>
                            <input type="text" id="port" name="port" value="3306">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="database">Database Name</label>
                        <input type="text" id="database" name="database" value="marketing_ai">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="root">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Database password">
                        </div>
                    </div>
                </div>

                <div id="connection-alert" class="status-alert"></div>

                <div class="btn-row">
                    <button class="btn btn-secondary btn-prev" data-step="1">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-secondary" id="btn-test-conn">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <button class="btn btn-primary" id="btn-next-2" disabled>
                            Next: Administrator <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: ADMINISTRATOR DETAILS -->
            <div class="installer-step" id="step-3">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;"><i class="fas fa-user-shield" style="color:#9b51e0; margin-right:6px;"></i> Administrator Account</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_username"><i class="fas fa-user"></i> Username *</label>
                        <input type="text" id="admin_username" value="admin" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label for="admin_password"><i class="fas fa-lock"></i> Password * (min 6 chars)</label>
                        <input type="password" id="admin_password" placeholder="••••••••" required minlength="6">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_name"><i class="fas fa-id-card"></i> Full Name</label>
                        <input type="text" id="admin_name" value="Administrator">
                    </div>
                    <div class="form-group">
                        <label for="admin_email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="admin_email" value="admin@example.com">
                    </div>
                </div>

                <div class="btn-row">
                    <button class="btn btn-secondary btn-prev" data-step="2">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="btn-next-3">
                        Next: Settings <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 4: GLOBAL CONFIG -->
            <div class="installer-step" id="step-4">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;"><i class="fas fa-sliders-h" style="color:#9b51e0; margin-right:6px;"></i> Global Configuration</h3>
                
                <div class="form-group">
                    <label for="app_name"><i class="fas fa-paint-brush"></i> Application Name</label>
                    <input type="text" id="app_name" value="Marketing AI Agent">
                    <small style="color:#a4b0be; font-size:11px; margin-top:2px;">App title shown in login pages, dashboard headers, and alerts.</small>
                </div>

                <div class="form-group">
                    <label for="gemini_api_key"><i class="fas fa-robot"></i> Google Gemini API Key</label>
                    <input type="password" id="gemini_api_key" placeholder="AIzaSy... (Leave blank to add later)">
                    <small style="color:#a4b0be; font-size:11px; margin-top:2px;">Required for AI generation SDR pipelines. Get one from Google AI Studio.</small>
                </div>

                <div id="install-run-alert" class="status-alert"></div>

                <div class="btn-row">
                    <button class="btn btn-secondary btn-prev" data-step="3">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="btn-run-install">
                        <i class="fas fa-magic"></i> Run Installer
                    </button>
                </div>
            </div>

            <!-- STEP 5: SUCCESS & FINISH -->
            <div class="installer-step" id="step-5">
                <div style="text-align:center; padding: 20px 0;">
                    <i class="fas fa-check-circle" style="font-size: 64px; color:#2ecc71; margin-bottom:20px;"></i>
                    <h3 style="margin: 0 0 10px 0; font-size:20px;">Installation Complete!</h3>
                    <p style="color:#a4b0be; font-size:14px; line-height: 1.5; max-width:440px; margin: 0 auto 24px;">
                        The database tables have been created, standard plans configured, and your administrator account registered.
                    </p>
                    <a href="index.php" class="btn btn-primary" style="text-decoration:none; padding:12px 32px;">
                        Go to Log In Screen <i class="fas fa-sign-in-alt"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const steps = {
                1: document.getElementById('step-1'),
                2: document.getElementById('step-2'),
                3: document.getElementById('step-3'),
                4: document.getElementById('step-4'),
                5: document.getElementById('step-5')
            };

            const dots = {
                1: document.getElementById('dot-1'),
                2: document.getElementById('dot-2'),
                3: document.getElementById('dot-3'),
                4: document.getElementById('dot-4'),
                5: document.getElementById('dot-5')
            };

            let currentStep = 1;
            let connectionVerified = false;

            function goToStep(stepNum) {
                // Remove active classes
                Object.values(steps).forEach(s => s.classList.remove('active'));
                Object.values(dots).forEach(d => {
                    d.classList.remove('active');
                    d.classList.remove('completed');
                });

                // Set new step
                steps[stepNum].classList.add('active');
                
                // Indicators logic
                for (let i = 1; i <= 5; i++) {
                    if (i < stepNum) {
                        dots[i].classList.add('completed');
                    } else if (i === stepNum) {
                        dots[i].classList.add('active');
                    }
                }
                
                currentStep = stepNum;
            }

            // Step 1 navigation
            document.getElementById('btn-next-1').addEventListener('click', () => goToStep(2));

            // Prev buttons
            document.querySelectorAll('.btn-prev').forEach(btn => {
                btn.addEventListener('click', () => {
                    goToStep(parseInt(btn.dataset.step));
                });
            });

            // Driver switch fields display
            const driverSelect = document.getElementById('driver');
            driverSelect.addEventListener('change', () => {
                const driver = driverSelect.value;
                document.querySelectorAll('.db-group').forEach(el => el.style.display = 'none');
                document.querySelector('.db-' + driver).style.display = 'block';
                
                // Reset connection status on driver switch
                connectionVerified = false;
                document.getElementById('btn-next-2').disabled = true;
                const alert = document.getElementById('connection-alert');
                alert.style.display = 'none';
            });

            // Input listener to reset verification
            document.querySelectorAll('#step-2 input').forEach(input => {
                input.addEventListener('input', () => {
                    connectionVerified = false;
                    document.getElementById('btn-next-2').disabled = true;
                    document.getElementById('connection-alert').style.display = 'none';
                });
            });

            // Test Connection Action
            const testBtn = document.getElementById('btn-test-conn');
            testBtn.addEventListener('click', () => {
                testBtn.disabled = true;
                testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
                const alert = document.getElementById('connection-alert');
                alert.style.display = 'none';

                const driver = driverSelect.value;
                const payload = { driver };
                if (driver === 'sqlite') {
                    payload.sqlite_file = document.getElementById('sqlite_file').value;
                } else {
                    payload.host = document.getElementById('host').value;
                    payload.port = document.getElementById('port').value;
                    payload.database = document.getElementById('database').value;
                    payload.username = document.getElementById('username').value;
                    payload.password = document.getElementById('password').value;
                }

                fetch('install.php?action=test_connection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = '<i class="fas fa-plug"></i> Test Connection';
                    if (data.success) {
                        alert.className = 'status-alert success';
                        alert.innerHTML = '<i class="fas fa-check-circle"></i> Connection established successfully!';
                        alert.style.display = 'flex';
                        connectionVerified = true;
                        document.getElementById('btn-next-2').disabled = false;
                    } else {
                        alert.className = 'status-alert error';
                        alert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Connection failed.');
                        alert.style.display = 'flex';
                        connectionVerified = false;
                        document.getElementById('btn-next-2').disabled = true;
                    }
                })
                .catch(err => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = '<i class="fas fa-plug"></i> Test Connection';
                    alert.className = 'status-alert error';
                    alert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network request failed.';
                    alert.style.display = 'flex';
                    connectionVerified = false;
                    document.getElementById('btn-next-2').disabled = true;
                });
            });

            // Step 2 Next Navigation
            document.getElementById('btn-next-2').addEventListener('click', () => {
                if (connectionVerified) {
                    goToStep(3);
                }
            });

            // Step 3 Next Navigation
            document.getElementById('btn-next-3').addEventListener('click', () => {
                const username = document.getElementById('admin_username').value;
                const pass = document.getElementById('admin_password').value;
                if (username.length < 3 || pass.length < 6) {
                    alert('Please fulfill username (min 3 chars) and password (min 6 chars) requirements.');
                    return;
                }
                goToStep(4);
            });

            // Step 4 Run Installer Execution
            const runBtn = document.getElementById('btn-run-install');
            runBtn.addEventListener('click', () => {
                runBtn.disabled = true;
                runBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Installing...';
                const runAlert = document.getElementById('install-run-alert');
                runAlert.style.display = 'none';

                const driver = driverSelect.value;
                const payload = {
                    driver,
                    admin_username: document.getElementById('admin_username').value,
                    admin_password: document.getElementById('admin_password').value,
                    admin_name: document.getElementById('admin_name').value,
                    admin_email: document.getElementById('admin_email').value,
                    app_name: document.getElementById('app_name').value,
                    gemini_api_key: document.getElementById('gemini_api_key').value
                };

                if (driver === 'sqlite') {
                    payload.sqlite_file = document.getElementById('sqlite_file').value;
                } else {
                    payload.host = document.getElementById('host').value;
                    payload.port = document.getElementById('port').value;
                    payload.database = document.getElementById('database').value;
                    payload.username = document.getElementById('username').value;
                    payload.password = document.getElementById('password').value;
                }

                fetch('install.php?action=run_install', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    runBtn.disabled = false;
                    runBtn.innerHTML = '<i class="fas fa-magic"></i> Run Installer';
                    if (data.success) {
                        goToStep(5);
                    } else {
                        runAlert.className = 'status-alert error';
                        runAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Installation failed.');
                        runAlert.style.display = 'flex';
                    }
                })
                .catch(err => {
                    runBtn.disabled = false;
                    runBtn.innerHTML = '<i class="fas fa-magic"></i> Run Installer';
                    runAlert.className = 'status-alert error';
                    runAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network request failed.';
                    runAlert.style.display = 'flex';
                });
            });
        });
    </script>
</body>
</html>
