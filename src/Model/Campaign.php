<?php
namespace MarketingAgent\Model;

class Campaign extends BaseModel {
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

    public function deleteCampaign(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function updateCampaignLlmProvider(int $id, string $provider): void {
        $stmt = $this->pdo->prepare("UPDATE campaigns SET llm_provider = ? WHERE id = ?");
        $stmt->execute([$provider, $id]);
    }

    public function initializeSchema(): void {
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

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS campaign_shares (
            campaign_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (campaign_id, user_id),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

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
            $this->pdo->exec("ALTER TABLE campaigns ADD COLUMN llm_provider TEXT DEFAULT 'gemini'");
        } catch (\PDOException $e) {}
    }
}
