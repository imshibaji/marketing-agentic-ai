<?php
namespace MarketingAgent\Model;

class Campaign extends BaseModel {
    protected static string $tableName = 'campaigns';
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
        $this->pdo->prepare("DELETE FROM " . $this->schema->quoteTable('campaign_shares') . " WHERE " . $this->schema->quoteColumn('campaign_id') . " = ?")->execute([$campaignId]);
        foreach ($userIds as $uid) {
            (new \MarketingAgent\Database\QueryBuilder($this->pdo, 'campaign_shares'))
                ->insertIgnore(['campaign_id' => $campaignId, 'user_id' => (int)$uid], ['campaign_id', 'user_id']);
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
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);

        $this->schema->createTableIfNotExists('campaigns', "
            id {$pk},
            title {$vc} NOT NULL,
            product_description TEXT NOT NULL,
            target_audience {$vc} NOT NULL,
            channel {$vc} NOT NULL,
            status {$vc} NOT NULL DEFAULT 'CREATED',
            final_content TEXT,
            crawl_type {$vc} DEFAULT 'none',
            crawl_target {$vc} DEFAULT '',
            language {$vc} DEFAULT 'English',
            user_id INTEGER,
            llm_provider {$vc} DEFAULT 'gemini',
            created_at {$dt}
        ");

        // campaign_shares junction table
        $this->schema->createTableIfNotExists('campaign_shares', "
            campaign_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL
        ");
        $this->schema->addUniqueIndexIfNotExists('campaign_shares', 'idx_campaign_shares_unique', ['campaign_id', 'user_id']);

        $this->schema->addColumnIfNotExists('campaigns', 'crawl_type', "{$vc} DEFAULT 'none'");
        $this->schema->addColumnIfNotExists('campaigns', 'crawl_target', "{$vc} DEFAULT ''");
        $this->schema->addColumnIfNotExists('campaigns', 'language', "{$vc} DEFAULT 'English'");
        $this->schema->addColumnIfNotExists('campaigns', 'user_id', 'INTEGER');
        $this->schema->addColumnIfNotExists('campaigns', 'llm_provider', "{$vc} DEFAULT 'gemini'");
    }
}
