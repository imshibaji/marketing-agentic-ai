<?php
namespace MarketingAgent\Model;

use Exception;

class Plan extends BaseModel {
    protected static string $tableName = 'plans';

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

    public function createPlan(string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): int {
        $stmt = $this->pdo->prepare("INSERT INTO plans (name, campaign_limit, lead_limit, llm_limit, email_limit, whatsapp_limit, sms_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePlan(int $id, string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): void {
        $stmt = $this->pdo->prepare("UPDATE plans SET name = ?, campaign_limit = ?, lead_limit = ?, llm_limit = ?, email_limit = ?, whatsapp_limit = ?, sms_limit = ? WHERE id = ?");
        $stmt->execute([$name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit, $id]);
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

    public function initializeSchema(): void {
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);

        $this->schema->createTableIfNotExists('plans', "
            id {$pk},
            name {$vc} NOT NULL,
            campaign_limit INTEGER NOT NULL DEFAULT 10,
            lead_limit INTEGER NOT NULL DEFAULT 50,
            llm_limit INTEGER NOT NULL DEFAULT 100,
            email_limit INTEGER NOT NULL DEFAULT 100,
            whatsapp_limit INTEGER NOT NULL DEFAULT 100,
            sms_limit INTEGER NOT NULL DEFAULT 100,
            created_at {$dt}
        ");

        $this->schema->addUniqueIndexIfNotExists('plans', 'idx_plans_name_unique', ['name']);
        $this->schema->addColumnIfNotExists('plans', 'llm_limit', 'INTEGER NOT NULL DEFAULT 100');
        $this->schema->addColumnIfNotExists('plans', 'email_limit', 'INTEGER NOT NULL DEFAULT 100');
        $this->schema->addColumnIfNotExists('plans', 'whatsapp_limit', 'INTEGER NOT NULL DEFAULT 100');
        $this->schema->addColumnIfNotExists('plans', 'sms_limit', 'INTEGER NOT NULL DEFAULT 100');

        // Seed default plan
        $qb = $this->qb();
        if ($qb->where('name', '=', 'Default Plan')->count() === 0) {
            $this->qb()->reset()->insert([
                'name' => 'Default Plan',
                'campaign_limit' => 10,
                'lead_limit' => 50,
                'llm_limit' => 100,
                'email_limit' => 100,
                'whatsapp_limit' => 100,
                'sms_limit' => 100
            ]);
        }
    }
}
