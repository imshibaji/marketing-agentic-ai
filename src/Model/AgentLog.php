<?php
namespace MarketingAgent\Model;

class AgentLog extends BaseModel {
    protected static string $tableName = 'agent_logs';
    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $stmt = $this->pdo->prepare("INSERT INTO agent_logs (campaign_id, agent_name, action, log_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$campaignId, $agentName, $action, $logText]);
    }

    public function getLogs(int $campaignId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agent_logs WHERE campaign_id = ? ORDER BY id ASC");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll();
    }

    public function initializeSchema(): void {
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);
        $this->schema->createTableIfNotExists('agent_logs', "
            id {$pk},
            campaign_id INTEGER,
            agent_name {$vc} NOT NULL,
            action {$vc} NOT NULL,
            log_text TEXT NOT NULL,
            created_at {$dt}
        ");
    }
}
