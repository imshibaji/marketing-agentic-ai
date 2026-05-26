<?php
namespace MarketingAgent\Model;

class PublicChat extends BaseModel {
    public function initializeSchema(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS public_chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }
}
