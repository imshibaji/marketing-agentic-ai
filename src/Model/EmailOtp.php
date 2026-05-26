<?php
namespace MarketingAgent\Model;

class EmailOtp extends BaseModel {
    public function createOtp(string $email, string $otp, string $expiresAt): void {
        $stmt = $this->pdo->prepare("UPDATE email_otps SET used = 1 WHERE email = ?");
        $stmt->execute([$email]);

        $stmt = $this->pdo->prepare("INSERT INTO email_otps (email, otp, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $otp, $expiresAt]);
    }

    public function verifyOtp(string $email, string $otp): bool {
        $stmt = $this->pdo->prepare("SELECT * FROM email_otps WHERE email = ? AND otp = ? AND used = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $otp]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $this->pdo->prepare("UPDATE email_otps SET used = 1 WHERE id = ?");
            $stmt->execute([$row['id']]);
            if (strtotime($row['expires_at']) >= time()) {
                return true;
            }
        }
        return false;
    }

    public function initializeSchema(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            otp TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
