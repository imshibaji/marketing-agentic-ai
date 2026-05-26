<?php
namespace MarketingAgent\Model;

class Setting extends BaseModel {
    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT * FROM settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function saveSettings(array $settings): void {
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        foreach ($settings as $key => $val) {
            $stmt->execute([$key, $val]);
        }
    }
}
