<?php
namespace MarketingAgent\Model;

class AgentLog extends BaseModel {
    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $stmt = $this->pdo->prepare("INSERT INTO agent_logs (campaign_id, agent_name, action, log_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$campaignId, $agentName, $action, $logText]);
    }

    public function getLogs(int $campaignId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agent_logs WHERE campaign_id = ? ORDER BY id ASC");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll();
    }
}
