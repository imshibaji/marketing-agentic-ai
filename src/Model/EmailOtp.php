<?php
namespace MarketingAgent\Model;

class EmailOtp extends BaseModel {
    protected static string $tableName = 'email_otps';
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
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dtNoDefault = $this->schema->datetimeDdl(false);
        $dt = $this->schema->datetimeDdl(true);
        $this->schema->createTableIfNotExists('email_otps', "
            id {$pk},
            email {$vc} NOT NULL,
            otp {$vc} NOT NULL,
            expires_at {$dtNoDefault} NOT NULL,
            used INTEGER DEFAULT 0,
            created_at {$dt}
        ");
    }
}
