<?php
namespace MarketingAgent\Model;

class UserActivityLog extends BaseModel {
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
}
