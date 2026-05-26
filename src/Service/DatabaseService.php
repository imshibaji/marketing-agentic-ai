<?php
namespace MarketingAgent\Service;

use PDO;
use Exception;
use MarketingAgent\Model\User;
use MarketingAgent\Model\Setting;
use MarketingAgent\Model\Campaign;
use MarketingAgent\Model\Plan;
use MarketingAgent\Model\UserActivityLog;
use MarketingAgent\Model\EmailOtp;
use MarketingAgent\Model\Lead;
use MarketingAgent\Model\AgentLog;

class DatabaseService {
    private ?PDO $pdo = null;
    private string $driver;
    private array $dbConfig = [];
    private string $dbPath;

    private User $userModel;
    private Setting $settingModel;
    private Campaign $campaignModel;
    private Plan $planModel;
    private UserActivityLog $userActivityLogModel;
    private EmailOtp $emailOtpModel;
    private Lead $leadModel;
    private AgentLog $agentLogModel;

    public function __construct() {
        $dbDir = __DIR__ . '/../../database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $this->driver = strtolower(trim((string)getenv('DB_DRIVER') ?: 'sqlite'));
        $this->loadDatabaseConfig($dbDir);
        $this->connect();
        $this->initializeSchema();

        $this->userModel = new User($this->pdo);
        $this->settingModel = new Setting($this->pdo);
        $this->campaignModel = new Campaign($this->pdo);
        $this->planModel = new Plan($this->pdo);
        $this->userActivityLogModel = new UserActivityLog($this->pdo);
        $this->emailOtpModel = new EmailOtp($this->pdo);
        $this->leadModel = new Lead($this->pdo);
        $this->agentLogModel = new AgentLog($this->pdo);
    }

    private function loadDatabaseConfig(string $dbDir): void {
        $projectRoot = realpath(__DIR__ . '/../../');
        $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');

        if ($sqliteFile === '') {
            $sqliteFile = 'database/database.sqlite';
        }

        if ($this->isAbsolutePath($sqliteFile)) {
            $resolvedSqliteFile = $sqliteFile;
        } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
            $root = $projectRoot ?: rtrim($dbDir, '/');
            $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
        } else {
            $resolvedSqliteFile = rtrim($dbDir, '/') . '/' . $sqliteFile;
        }

        $sqliteDir = dirname($resolvedSqliteFile);
        if (!is_dir($sqliteDir)) {
            mkdir($sqliteDir, 0777, true);
        }

        $this->dbConfig = [
            'host' => trim((string)getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => trim((string)getenv('DB_PORT') ?: ''),
            'database' => trim((string)getenv('DB_DATABASE') ?: ''),
            'username' => trim((string)getenv('DB_USERNAME') ?: ''),
            'password' => trim((string)getenv('DB_PASSWORD') ?: ''),
            'charset' => trim((string)getenv('DB_CHARSET') ?: 'utf8mb4'),
            'sslmode' => trim((string)getenv('DB_SSLMODE') ?: 'prefer'),
            'sqlite_file' => $resolvedSqliteFile,
            'service_name' => trim((string)getenv('DB_SERVICE_NAME') ?: ''),
        ];
    }

    private function isAbsolutePath(string $path): bool {
        return strpos($path, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    private function connect(): void {
        try {
            switch ($this->driver) {
                case 'mysql':
                case 'pdo_mysql':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '3306';
                    $dbname = $this->dbConfig['database'];
                    $charset = $this->dbConfig['charset'];
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'pgsql':
                case 'postgresql':
                case 'postgres':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '5432';
                    $dbname = $this->dbConfig['database'];
                    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'sqlsrv':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '1433';
                    $dbname = $this->dbConfig['database'];
                    $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'oracle':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '1521';
                    $serviceName = $this->dbConfig['service_name'] ?: $this->dbConfig['database'];
                    $dsn = "oci:dbname=//{$host}:{$port}/{$serviceName};charset=UTF8";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'sqlite':
                default:
                    $this->dbPath = $this->dbConfig['sqlite_file'];
                    $dsn = "sqlite:" . $this->dbPath;
                    $username = null;
                    $password = null;
                    break;
            }

            if ($username === null) {
                $this->pdo = new PDO($dsn);
            } else {
                $this->pdo = new PDO($dsn, $username, $password);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function initializeSchema(): void {
        // Users Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user', -- admin, user
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Settings Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");

        // Campaigns Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            product_description TEXT NOT NULL,
            target_audience TEXT NOT NULL,
            channel TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'CREATED', -- CREATED, RUNNING, COMPLETED, FAILED
            final_content TEXT,
            crawl_type TEXT DEFAULT 'none',
            crawl_target TEXT DEFAULT '',
            language TEXT DEFAULT 'English',
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // Run migrations for existing databases
        try {
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN crawl_type TEXT DEFAULT 'none'");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN crawl_target TEXT DEFAULT ''");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN language TEXT DEFAULT 'English'");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN user_id INTEGER");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN mobile TEXT");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_number TEXT");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN plan_campaigns INTEGER DEFAULT 10");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN plan_leads INTEGER DEFAULT 50");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN plan_id INTEGER");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE plans ADD COLUMN llm_limit INTEGER NOT NULL DEFAULT 100");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE plans ADD COLUMN email_limit INTEGER NOT NULL DEFAULT 100");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE plans ADD COLUMN whatsapp_limit INTEGER NOT NULL DEFAULT 100");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE plans ADD COLUMN sms_limit INTEGER NOT NULL DEFAULT 100");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN llm_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN email_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN sms_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN llm_provider TEXT DEFAULT 'gemini'");
        } catch (\PDOException $e) {}

        // Campaign Shares Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS campaign_shares (
            campaign_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (campaign_id, user_id),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Plans Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            campaign_limit INTEGER NOT NULL DEFAULT 10,
            lead_limit INTEGER NOT NULL DEFAULT 50,
            llm_limit INTEGER NOT NULL DEFAULT 100,
            email_limit INTEGER NOT NULL DEFAULT 100,
            whatsapp_limit INTEGER NOT NULL DEFAULT 100,
            sms_limit INTEGER NOT NULL DEFAULT 100,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert a default plan if not exists
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM plans WHERE name = ?");
            $stmt->execute(['Default Plan']);
            if ($stmt->fetchColumn() == 0) {
                $this->pdo->prepare("INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit, sms_limit) VALUES (?, ?, ?, ?, ?, ?, ?)")
                     ->execute(['Default Plan', 10, 50, 100, 100, 100, 100]);
                $defaultPlanId = (int)$this->pdo->lastInsertId();
                // Assign all existing users who have plan_id NULL to this default plan
                $this->pdo->prepare("UPDATE users SET plan_id = ? WHERE plan_id IS NULL")->execute([$defaultPlanId]);
            }
        } catch (\PDOException $e) {}

        // User Activity Logs Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // Email OTPs Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            otp TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Alter leads table to add columns for manual lead input & ownership
        try {
            $this->pdo->exec("ALTER TABLE leads ADD COLUMN user_id INTEGER");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE leads ADD COLUMN source TEXT DEFAULT 'agent'");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE leads ADD COLUMN mobile TEXT");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE leads ADD COLUMN sms_draft TEXT");
        } catch (\PDOException $e) {}

        // Leads Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            company_name TEXT NOT NULL,
            contact_name TEXT,
            email TEXT,
            whatsapp TEXT,
            mobile TEXT,
            industry TEXT,
            description TEXT,
            score TEXT, -- HIGH, MEDIUM, LOW
            reasoning TEXT,
            email_draft TEXT,
            whatsapp_draft TEXT,
            sms_draft TEXT,
            status TEXT DEFAULT 'GENERATED', -- GENERATED, QUALIFIED, OUTREACHED, CLOSED
            user_id INTEGER,
            source TEXT DEFAULT 'agent',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        )");

        // Agent Logs Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS agent_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            agent_name TEXT NOT NULL,
            action TEXT NOT NULL,
            log_text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        )");

        // Notifications Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // Public Chats Table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS public_chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Set default settings if not exists
        $defaults = [
            'app_name' => 'Antigravity Marketing',
            'llm_provider' => 'gemini',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-flash',
            'lm_studio_url' => 'http://localhost:1234/v1',
            'lm_studio_model' => 'qwen2.5-7b-instruct',
            'lm_studio_api_key' => '',
            'lm_studio_extra_model' => '',
            'ollama_url' => 'http://localhost:11434/v1',
            'ollama_model' => 'llama3',
            'ollama_api_key' => '',
            'ollama_extra_model' => '',
            'smtp_host' => 'mock',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from_email' => 'outreach@example.com',
            'smtp_from_name' => 'Antigravity Outreach',
            'whatsapp_token' => 'mock',
            'whatsapp_phone_id' => '',
            'sms_provider' => 'mock',
            'sms_twilio_account_sid' => '',
            'sms_twilio_auth_token' => '',
            'sms_twilio_from_number' => '',
            'sms_custom_url' => '',
            'sms_custom_method' => 'POST',
            'sms_custom_headers' => '',
            'sms_custom_body' => '{"to":"{to}", "message":"{message}"}',
            'enable_public_chat' => '1',
            'gemini_active' => '1',
            'lm_studio_active' => '1',
            'ollama_active' => '1'
        ];

        foreach ($defaults as $key => $val) {
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        }
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    // ─── Settings Delegation ───────────────────────────────────────

    public function getSettings(): array {
        return $this->settingModel->getSettings();
    }

    public function saveSettings(array $settings): void {
        $this->settingModel->saveSettings($settings);
    }

    // ─── Agent Logs Delegation ─────────────────────────────────────

    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $this->agentLogModel->logAgentAction($campaignId, $agentName, $action, $logText);
    }

    public function getLogs(int $campaignId): array {
        return $this->agentLogModel->getLogs($campaignId);
    }

    // ─── Campaigns Delegation ──────────────────────────────────────

    public function createCampaign(string $title, string $description, string $audience, string $channel, string $crawlType = 'none', string $crawlTarget = '', string $language = 'English', ?int $userId = null): int {
        return $this->campaignModel->createCampaign($title, $description, $audience, $channel, $crawlType, $crawlTarget, $language, $userId);
    }

    public function updateCampaignStatus(int $id, string $status): void {
        $this->campaignModel->updateCampaignStatus($id, $status);
    }

    public function updateCampaignContent(int $id, string $content, string $status = 'COMPLETED'): void {
        $this->campaignModel->updateCampaignContent($id, $content, $status);
    }

    public function getCampaign(int $id, ?int $userId = null, string $role = 'user'): ?array {
        return $this->campaignModel->getCampaign($id, $userId, $role);
    }

    public function getCampaigns(?int $userId = null, string $role = 'user'): array {
        return $this->campaignModel->getCampaigns($userId, $role);
    }

    public function shareCampaign(int $campaignId, array $userIds): void {
        $this->campaignModel->shareCampaign($campaignId, $userIds);
    }

    public function getCampaignShares(int $campaignId): array {
        return $this->campaignModel->getCampaignShares($campaignId);
    }

    public function deleteCampaign(int $id): void {
        $this->campaignModel->deleteCampaign($id);
    }

    public function updateCampaignLlmProvider(int $id, string $provider): void {
        $this->campaignModel->updateCampaignLlmProvider($id, $provider);
    }

    // ─── User Management Delegation ────────────────────────────────

    public function getUsers(): array {
        return $this->userModel->getUsers();
    }

    public function getUserById(int $id): ?array {
        return $this->userModel->getUserById($id);
    }

    public function getUserByUsername(string $username): ?array {
        return $this->userModel->getUserByUsername($username);
    }

    public function getUserByEmail(string $email): ?array {
        return $this->userModel->getUserByEmail($email);
    }

    public function createUser(string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): int {
        return $this->userModel->createUser($username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId);
    }

    public function updateUser(int $id, string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): void {
        $this->userModel->updateUser($id, $username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId);
    }

    public function deleteUser(int $id): void {
        $this->userModel->deleteUser($id);
    }

    public function getUserCampaignCount(int $userId): int {
        return $this->userModel->getUserCampaignCount($userId);
    }

    public function getUserLeadCount(int $userId): int {
        return $this->userModel->getUserLeadCount($userId);
    }

    public function incrementLlmUsage(int $userId): void {
        $this->userModel->incrementLlmUsage($userId);
    }

    public function incrementEmailUsage(int $userId): void {
        $this->userModel->incrementEmailUsage($userId);
    }

    public function incrementPageUsage(int $userId): void {
        // Compatibility for any callers of legacy incrementPageUsage
        $this->userModel->incrementLlmUsage($userId);
    }

    public function incrementWhatsappUsage(int $userId): void {
        $this->userModel->incrementWhatsappUsage($userId);
    }

    public function incrementSmsUsage(int $userId): void {
        $this->userModel->incrementSmsUsage($userId);
    }

    // ─── Leads Delegation ──────────────────────────────────────────

    public function saveLead(
        ?int $campaignId,
        string $companyName,
        ?string $contactName,
        ?string $email,
        ?string $whatsapp,
        ?string $industry,
        ?string $description,
        string $score,
        string $reasoning,
        string $emailDraft,
        string $whatsappDraft,
        ?int $userId = null,
        string $source = 'agent',
        ?string $mobile = null,
        ?string $smsDraft = null
    ): int {
        return $this->leadModel->saveLead(
            $campaignId, $companyName, $contactName, $email, $whatsapp,
            $industry, $description, $score, $reasoning, $emailDraft,
            $whatsappDraft, $userId, $source, $mobile, $smsDraft
        );
    }

    public function getLeads(?int $campaignId = null, ?int $userId = null, string $role = 'user'): array {
        return $this->leadModel->getLeads($campaignId, $userId, $role);
    }

    public function getLead(int $id): ?array {
        return $this->leadModel->getLead($id);
    }

    public function updateLead(
        int $id,
        ?int $campaignId,
        string $companyName,
        ?string $contactName,
        ?string $email,
        ?string $whatsapp,
        ?string $industry,
        ?string $description,
        string $score,
        string $reasoning,
        string $emailDraft,
        string $whatsappDraft,
        string $source = 'manual',
        ?string $mobile = null,
        ?int $userId = null,
        ?string $smsDraft = null
    ): void {
        $this->leadModel->updateLead(
            $id, $campaignId, $companyName, $contactName, $email, $whatsapp,
            $industry, $description, $score, $reasoning, $emailDraft,
            $whatsappDraft, $source, $mobile, $userId, $smsDraft
        );
    }

    public function updateLeadStatus(int $id, string $status): void {
        $this->leadModel->updateLeadStatus($id, $status);
    }

    public function updateLeadDrafts(int $id, string $emailDraft, string $whatsappDraft, ?string $smsDraft = null): void {
        $this->leadModel->updateLeadDrafts($id, $emailDraft, $whatsappDraft, $smsDraft);
    }

    public function deleteLead(int $id): void {
        $this->leadModel->deleteLead($id);
    }

    public function clearLeads(int $campaignId): void {
        $this->leadModel->clearLeads($campaignId);
    }

    // ─── User Activity Logs Delegation ─────────────────────────────

    public function logActivity(?int $userId, string $action, string $details): void {
        $this->userActivityLogModel->logActivity($userId, $action, $details);
    }

    public function getActivityLogs(?int $userId = null, string $role = 'user'): array {
        return $this->userActivityLogModel->getActivityLogs($userId, $role);
    }

    // ─── Email OTPs Delegation ─────────────────────────────────────

    public function createOtp(string $email, string $otp, string $expiresAt): void {
        $this->emailOtpModel->createOtp($email, $otp, $expiresAt);
    }

    public function verifyOtp(string $email, string $otp): bool {
        return $this->emailOtpModel->verifyOtp($email, $otp);
    }

    // ─── Plans Delegation ──────────────────────────────────────────

    public function getPlans(): array {
        return $this->planModel->getPlans();
    }

    public function getPlan(int $id): ?array {
        return $this->planModel->getPlan($id);
    }

    public function createPlan(string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): int {
        return $this->planModel->createPlan($name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
    }

    public function updatePlan(int $id, string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): void {
        $this->planModel->updatePlan($id, $name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
    }

    public function deletePlan(int $id): void {
        $this->planModel->deletePlan($id);
    }
}
