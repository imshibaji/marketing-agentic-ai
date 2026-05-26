<?php
namespace MarketingAgent\Service;

use PDO;
use Exception;

class DatabaseService {
    private ?PDO $pdo = null;
    private string $dbPath;

    public function __construct() {
        $dbDir = __DIR__ . '/../../database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $this->dbPath = $dbDir . '/database.sqlite';
        $this->connect();
        $this->initializeSchema();
    }

    private function connect(): void {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
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
            $this->pdo->exec("ALTER TABLE users ADD COLUMN llm_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN email_usage INTEGER NOT NULL DEFAULT 0");
        } catch (\PDOException $e) {}
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_usage INTEGER NOT NULL DEFAULT 0");
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Insert a default plan if not exists
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM plans WHERE name = ?");
            $stmt->execute(['Default Plan']);
            if ($stmt->fetchColumn() == 0) {
                $this->pdo->prepare("INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit) VALUES (?, ?, ?, ?, ?, ?)")
                     ->execute(['Default Plan', 10, 50, 100, 100, 100]);
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

        // Set default settings if not exists
        $defaults = [
            'app_name' => 'Antigravity Marketing',
            'llm_provider' => 'gemini',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-flash',
            'lm_studio_url' => 'http://localhost:1234/v1',
            'lm_studio_model' => 'qwen2.5-7b-instruct',
            'ollama_url' => 'http://localhost:11434/v1',
            'ollama_model' => 'llama3',
            'smtp_host' => 'mock',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from_email' => 'outreach@example.com',
            'smtp_from_name' => 'Antigravity Outreach',
            'whatsapp_token' => 'mock',
            'whatsapp_phone_id' => ''
        ];

        foreach ($defaults as $key => $val) {
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        }
    }

    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT * FROM settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function saveSettings(array $settings): void {
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        foreach ($settings as $key => $val) {
            $stmt->execute([$key, $val]);
        }
    }

    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $stmt = $this->pdo->prepare("INSERT INTO agent_logs (campaign_id, agent_name, action, log_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$campaignId, $agentName, $action, $logText]);
    }

    public function getLogs(int $campaignId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agent_logs WHERE campaign_id = ? ORDER BY id ASC");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll();
    }

    public function createCampaign(string $title, string $description, string $audience, string $channel, string $crawlType = 'none', string $crawlTarget = '', string $language = 'English', ?int $userId = null): int {
        $stmt = $this->pdo->prepare("INSERT INTO campaigns (title, product_description, target_audience, channel, crawl_type, crawl_target, language, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'CREATED', ?)");
        $stmt->execute([$title, $description, $audience, $channel, $crawlType, $crawlTarget, $language, $userId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCampaignStatus(int $id, string $status): void {
        $stmt = $this->pdo->prepare("UPDATE campaigns SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    public function updateCampaignContent(int $id, string $content, string $status = 'COMPLETED'): void {
        $stmt = $this->pdo->prepare("UPDATE campaigns SET final_content = ?, status = ? WHERE id = ?");
        $stmt->execute([$content, $status, $id]);
    }

    public function getCampaign(int $id, ?int $userId = null, string $role = 'user'): ?array {
        if ($role === 'admin') {
            $stmt = $this->pdo->prepare("SELECT c.*, u.username as owner_username FROM campaigns c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ?");
            $stmt->execute([$id]);
        } else {
            // Allow access if the user owns it OR it is shared with them
            $stmt = $this->pdo->prepare("
                SELECT c.*, u.username as owner_username,
                       CASE WHEN c.user_id = ? THEN 0 ELSE 1 END as is_shared
                FROM campaigns c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.id = ? AND (
                    c.user_id = ?
                    OR EXISTS (SELECT 1 FROM campaign_shares cs WHERE cs.campaign_id = c.id AND cs.user_id = ?)
                )
            ");
            $stmt->execute([$userId, $id, $userId, $userId]);
        }
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getCampaigns(?int $userId = null, string $role = 'user'): array {
        if ($role === 'admin') {
            $stmt = $this->pdo->query("SELECT c.*, u.username as owner_username, 0 as is_shared FROM campaigns c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.id DESC");
            return $stmt->fetchAll();
        }
        // Return owned campaigns + shared campaigns for regular users
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.username as owner_username,
                   CASE WHEN c.user_id = ? THEN 0 ELSE 1 END as is_shared
            FROM campaigns c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.user_id = ?
               OR EXISTS (SELECT 1 FROM campaign_shares cs WHERE cs.campaign_id = c.id AND cs.user_id = ?)
            ORDER BY c.id DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function shareCampaign(int $campaignId, array $userIds): void {
        // Remove all existing shares for this campaign
        $stmt = $this->pdo->prepare("DELETE FROM campaign_shares WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        // Insert new shares
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO campaign_shares (campaign_id, user_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $stmt->execute([$campaignId, (int)$uid]);
        }
    }

    public function getCampaignShares(int $campaignId): array {
        $stmt = $this->pdo->prepare("SELECT user_id FROM campaign_shares WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    // ─── User Management ───────────────────────────────────────────

    public function getUsers(): array {
        $stmt = $this->pdo->query(
            "SELECT u.id, u.username, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id,
                    u.llm_usage, u.email_usage, u.whatsapp_usage,
                    p.name AS plan_name, p.campaign_limit, p.lead_limit, p.llm_limit, p.email_limit, p.whatsapp_limit,
                    (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) AS campaign_usage,
                    (SELECT COUNT(*) FROM leads l LEFT JOIN campaigns c ON l.campaign_id = c.id WHERE l.user_id = u.id OR c.user_id = u.id) AS lead_usage
             FROM users u
             LEFT JOIN plans p ON u.plan_id = p.id
             ORDER BY u.id ASC"
        );
        return $stmt->fetchAll();
    }

    public function getUserById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id,
                    u.llm_usage, u.email_usage, u.whatsapp_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp
             FROM users u
             LEFT JOIN plans p ON u.plan_id = p.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getUserByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id,
                    u.llm_usage, u.email_usage, u.whatsapp_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp
             FROM users u
             LEFT JOIN plans p ON u.plan_id = p.id
             WHERE u.username = ?"
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getUserByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id,
                    u.llm_usage, u.email_usage, u.whatsapp_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp
             FROM users u
             LEFT JOIN plans p ON u.plan_id = p.id
             WHERE u.email = ?"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUser(string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): int {
        if ($planId === null || $planId <= 0) {
            $stmt = $this->pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
            $planId = (int)$stmt->fetchColumn() ?: null;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash, role, full_name, email, mobile, whatsapp_number, plan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateUser(int $id, string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): void {
        if ($planId === null || $planId <= 0) {
            $stmt = $this->pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
            $planId = (int)$stmt->fetchColumn() ?: null;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE users SET username = ?, password_hash = ?, role = ?, full_name = ?, email = ?, mobile = ?, whatsapp_number = ?, plan_id = ? WHERE id = ?"
        );
        $stmt->execute([$username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId, $id]);
    }

    public function deleteUser(int $id): void {
        // Campaigns owned by this user become orphaned (user_id = NULL)
        $stmt = $this->pdo->prepare("UPDATE campaigns SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$id]);
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function deleteCampaign(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
    }

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
        ?string $mobile = null
    ): int {
        $stmt = $this->pdo->prepare("INSERT INTO leads 
            (campaign_id, company_name, contact_name, email, whatsapp, mobile, industry, description, score, reasoning, email_draft, whatsapp_draft, user_id, source) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $campaignId, $companyName, $contactName, $email, $whatsapp, $mobile, $industry, $description, $score, $reasoning, $emailDraft, $whatsappDraft, $userId, $source
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getLeads(?int $campaignId = null, ?int $userId = null, string $role = 'user'): array {
        if ($campaignId !== null) {
            $stmt = $this->pdo->prepare("SELECT l.*, c.title as campaign_title, u.username as owner_username FROM leads l LEFT JOIN campaigns c ON l.campaign_id = c.id LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id WHERE l.campaign_id = ? ORDER BY l.id DESC");
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll();
        }

        if ($role === 'admin') {
            $stmt = $this->pdo->query("
                SELECT l.*, c.title as campaign_title, u.username as owner_username 
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id
                ORDER BY l.id DESC
            ");
            return $stmt->fetchAll();
        }

        // Return leads owned by the user, from their campaigns, or from campaigns shared with them
        $stmt = $this->pdo->prepare("
            SELECT l.*, c.title as campaign_title, u.username as owner_username 
            FROM leads l 
            LEFT JOIN campaigns c ON l.campaign_id = c.id 
            LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id
            WHERE l.user_id = ?
               OR c.user_id = ?
               OR EXISTS (SELECT 1 FROM campaign_shares cs WHERE cs.campaign_id = l.campaign_id AND cs.user_id = ?)
            ORDER BY l.id DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getLead(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
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
        ?int $userId = null
    ): void {
        if ($userId !== null) {
            $stmt = $this->pdo->prepare("UPDATE leads SET 
                campaign_id = ?,
                company_name = ?,
                contact_name = ?,
                email = ?,
                whatsapp = ?,
                industry = ?,
                description = ?,
                score = ?,
                reasoning = ?,
                email_draft = ?,
                whatsapp_draft = ?,
                source = ?,
                mobile = ?,
                user_id = ?
                WHERE id = ?");
            $stmt->execute([
                $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, $source, $mobile, $userId, $id
            ]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE leads SET 
                campaign_id = ?,
                company_name = ?,
                contact_name = ?,
                email = ?,
                whatsapp = ?,
                industry = ?,
                description = ?,
                score = ?,
                reasoning = ?,
                email_draft = ?,
                whatsapp_draft = ?,
                source = ?,
                mobile = ?
                WHERE id = ?");
            $stmt->execute([
                $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, $source, $mobile, $id
            ]);
        }
    }

    public function updateLeadStatus(int $id, string $status): void {
        $stmt = $this->pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    public function updateLeadDrafts(int $id, string $emailDraft, string $whatsappDraft): void {
        $stmt = $this->pdo->prepare("UPDATE leads SET email_draft = ?, whatsapp_draft = ? WHERE id = ?");
        $stmt->execute([$emailDraft, $whatsappDraft, $id]);
    }

    public function deleteLead(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function clearLeads(int $campaignId): void {
        $stmt = $this->pdo->prepare("DELETE FROM leads WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
    }

    public function logActivity(?int $userId, string $action, string $details): void {
        $stmt = $this->pdo->prepare("INSERT INTO user_activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
    }

    public function getActivityLogs(?int $userId = null, string $role = 'user'): array {
        if ($role === 'admin') {
            $stmt = $this->pdo->query("SELECT l.*, u.username FROM user_activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC");
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->prepare("SELECT l.*, u.username FROM user_activity_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.user_id = ? ORDER BY l.id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getUserCampaignCount(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getUserLeadCount(int $userId): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM leads l 
            LEFT JOIN campaigns c ON l.campaign_id = c.id 
            WHERE l.user_id = ? OR c.user_id = ?
        ");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function createOtp(string $email, string $otp, string $expiresAt): void {
        $stmt = $this->pdo->prepare("UPDATE email_otps SET used = 1 WHERE email = ?");
        $stmt->execute([$email]);

        $stmt = $this->pdo->prepare("INSERT INTO email_otps (email, otp, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $otp, $expiresAt]);
    }

    public function verifyOtp(string $email, string $otp): bool {
        $stmt = $this->pdo->prepare("SELECT * FROM email_otps WHERE email = ? AND otp = ? AND used = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $otp]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $this->pdo->prepare("UPDATE email_otps SET used = 1 WHERE id = ?");
            $stmt->execute([$row['id']]);
            if (strtotime($row['expires_at']) >= time()) {
                return true;
            }
        }
        return false;
    }

    public function getPlans(): array {
        $stmt = $this->pdo->query("SELECT *, (SELECT COUNT(*) FROM users u WHERE u.plan_id = plans.id) AS user_count FROM plans ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getPlan(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createPlan(string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100): int {
        $stmt = $this->pdo->prepare("INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePlan(int $id, string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100): void {
        $stmt = $this->pdo->prepare("UPDATE plans SET name = ?, campaign_limit = ?, lead_limit = ?, llm_limit = ?, email_limit = ?, whatsapp_limit = ? WHERE id = ?");
        $stmt->execute([$name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $id]);
    }

    public function updateCampaignLlmProvider(int $id, string $provider): void {
        $stmt = $this->pdo->prepare("UPDATE campaigns SET llm_provider = ? WHERE id = ?");
        $stmt->execute([$provider, $id]);
    }

    public function incrementLlmUsage(int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE users SET llm_usage = llm_usage + 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function incrementEmailUsage(int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE users SET email_usage = email_usage + 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function incrementWhatsappUsage(int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE users SET whatsapp_usage = whatsapp_usage + 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function deletePlan(int $id): void {
        // Find default plan id
        $stmt = $this->pdo->query("SELECT id FROM plans WHERE name = 'Default Plan' LIMIT 1");
        $defaultPlanId = (int)$stmt->fetchColumn() ?: null;

        if ($defaultPlanId !== null && $id === $defaultPlanId) {
            throw new Exception("You cannot delete the Default Plan.");
        }

        // Reassign any users of this plan to default plan
        if ($defaultPlanId !== null) {
            $stmt = $this->pdo->prepare("UPDATE users SET plan_id = ? WHERE plan_id = ?");
            $stmt->execute([$defaultPlanId, $id]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([$id]);
    }
}
