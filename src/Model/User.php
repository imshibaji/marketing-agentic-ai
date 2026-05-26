<?php
namespace MarketingAgent\Model;

class User extends BaseModel {
    public function getUsers(): array {
        $stmt = $this->pdo->query(
            "SELECT u.id, u.username, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id,
                    u.llm_usage, u.email_usage, u.whatsapp_usage, u.sms_usage,
                    p.name AS plan_name, p.campaign_limit, p.lead_limit, p.llm_limit, p.email_limit, p.whatsapp_limit, p.sms_limit,
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
                    u.llm_usage, u.email_usage, u.whatsapp_usage, u.sms_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms
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
                    u.llm_usage, u.email_usage, u.whatsapp_usage, u.sms_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms
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
                    u.llm_usage, u.email_usage, u.whatsapp_usage, u.sms_usage,
                    p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads,
                    p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms
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

    public function incrementSmsUsage(int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE users SET sms_usage = sms_usage + 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function initializeSchema(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user', -- admin, user
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

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
    }
}
