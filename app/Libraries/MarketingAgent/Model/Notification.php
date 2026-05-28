<?php
namespace MarketingAgent\Model;

class Notification extends BaseModel {
    protected static string $tableName = 'notifications';
    public function initializeSchema(): void {
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);
        $this->schema->createTableIfNotExists('notifications', "
            id {$pk},
            sender_id INTEGER,
            title {$vc} NOT NULL,
            message TEXT NOT NULL,
            created_at {$dt}
        ");
    }
}
