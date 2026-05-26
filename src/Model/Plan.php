<?php
namespace MarketingAgent\Model;

use Exception;

class Plan extends BaseModel {
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
}
