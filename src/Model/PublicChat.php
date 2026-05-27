<?php
namespace MarketingAgent\Model;

class PublicChat extends BaseModel {
    protected static string $tableName = 'public_chats';
    public function initializeSchema(): void {
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);
        $this->schema->createTableIfNotExists('public_chats', "
            id {$pk},
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at {$dt}
        ");
    }
}
