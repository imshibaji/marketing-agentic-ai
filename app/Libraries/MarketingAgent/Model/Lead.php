<?php
namespace MarketingAgent\Model;

class Lead extends BaseModel {
    protected static string $tableName = 'leads';
    public function saveLead(
        ?int $campaignId,
        string $companyName,
        ?string $contactName,
        ?string $email,
        ?string $whatsapp,
        ?string $industry,
        ?string $description,
        string $score,
        string $reasoning,
        string $emailDraft,
        string $whatsappDraft,
        ?int $userId = null,
        string $source = 'agent',
        ?string $mobile = null,
        ?string $smsDraft = null,
        ?string $postalAddress = null,
        ?string $callsDraft = null
    ): int {
        $stmt = $this->pdo->prepare("INSERT INTO leads 
            (campaign_id, company_name, contact_name, email, whatsapp, mobile, industry, description, score, reasoning, email_draft, whatsapp_draft, sms_draft, calls_draft, user_id, source, postal_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $campaignId, $companyName, $contactName, $email, $whatsapp, $mobile, $industry, $description, $score, $reasoning, $emailDraft, $whatsappDraft, $smsDraft, $callsDraft, $userId, $source, $postalAddress
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getLeads(?int $campaignId = null, ?int $userId = null, string $role = 'user'): array {
        if ($campaignId !== null) {
            $stmt = $this->pdo->prepare("SELECT l.*, c.title as campaign_title, c.user_id as campaign_owner_id, u.username as owner_username FROM leads l LEFT JOIN campaigns c ON l.campaign_id = c.id LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id WHERE l.campaign_id = ? ORDER BY l.id DESC");
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll();
        }

        if ($role === 'admin') {
            $stmt = $this->pdo->query("
                SELECT l.*, c.title as campaign_title, c.user_id as campaign_owner_id, u.username as owner_username 
                FROM leads l 
                LEFT JOIN campaigns c ON l.campaign_id = c.id 
                LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id
                ORDER BY l.id DESC
            ");
            return $stmt->fetchAll();
        }

        // Return leads owned by the user, from their campaigns, or from campaigns shared with them
        $stmt = $this->pdo->prepare("
            SELECT l.*, c.title as campaign_title, c.user_id as campaign_owner_id, u.username as owner_username 
            FROM leads l 
            LEFT JOIN campaigns c ON l.campaign_id = c.id 
            LEFT JOIN users u ON COALESCE(l.user_id, c.user_id) = u.id
            WHERE l.user_id = ?
               OR c.user_id = ?
               OR EXISTS (SELECT 1 FROM campaign_shares cs WHERE cs.campaign_id = l.campaign_id AND cs.user_id = ?)
            ORDER BY l.id DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getLead(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function updateLead(
        int $id,
        ?int $campaignId,
        string $companyName,
        ?string $contactName,
        ?string $email,
        ?string $whatsapp,
        ?string $industry,
        ?string $description,
        string $score,
        string $reasoning,
        string $emailDraft,
        string $whatsappDraft,
        string $source = 'manual',
        ?string $mobile = null,
        ?int $userId = null,
        ?string $smsDraft = null,
        ?string $postalAddress = null,
        ?string $status = null,
        ?string $callsDraft = null
    ): void {
        if ($userId !== null) {
            $stmt = $this->pdo->prepare("UPDATE leads SET 
                campaign_id = ?,
                company_name = ?,
                contact_name = ?,
                email = ?,
                whatsapp = ?,
                industry = ?,
                description = ?,
                score = ?,
                reasoning = ?,
                email_draft = ?,
                whatsapp_draft = ?,
                sms_draft = ?,
                calls_draft = ?,
                source = ?,
                mobile = ?,
                user_id = ?,
                postal_address = ?,
                status = COALESCE(?, status)
                WHERE id = ?");
            $stmt->execute([
                $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, $smsDraft, $callsDraft, $source, $mobile, $userId, $postalAddress, $status, $id
            ]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE leads SET 
                campaign_id = ?,
                company_name = ?,
                contact_name = ?,
                email = ?,
                whatsapp = ?,
                industry = ?,
                description = ?,
                score = ?,
                reasoning = ?,
                email_draft = ?,
                whatsapp_draft = ?,
                sms_draft = ?,
                calls_draft = ?,
                source = ?,
                mobile = ?,
                postal_address = ?,
                status = COALESCE(?, status)
                WHERE id = ?");
            $stmt->execute([
                $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, $smsDraft, $callsDraft, $source, $mobile, $postalAddress, $status, $id
            ]);
        }
    }

    public function updateLeadStatus(int $id, string $status): void {
        $stmt = $this->pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    public function updateLeadDrafts(int $id, string $emailDraft, string $whatsappDraft, ?string $smsDraft = null, ?string $callsDraft = null): void {
        $stmt = $this->pdo->prepare("UPDATE leads SET email_draft = ?, whatsapp_draft = ?, sms_draft = ?, calls_draft = ? WHERE id = ?");
        $stmt->execute([$emailDraft, $whatsappDraft, $smsDraft, $callsDraft, $id]);
    }

    public function deleteLead(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function clearLeads(int $campaignId): void {
        $stmt = $this->pdo->prepare("DELETE FROM leads WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
    }

    public function initializeSchema(): void {
        $pk = $this->schema->primaryKeyDdl();
        $vc = $this->schema->varcharDdl(255);
        $dt = $this->schema->datetimeDdl(true);

        $this->schema->createTableIfNotExists('leads', "
            id {$pk},
            campaign_id INTEGER,
            company_name {$vc} NOT NULL,
            contact_name {$vc},
            email {$vc},
            whatsapp {$vc},
            mobile {$vc},
            industry {$vc},
            description TEXT,
            score {$vc},
            reasoning TEXT,
            email_draft TEXT,
            whatsapp_draft TEXT,
            sms_draft TEXT,
            calls_draft TEXT,
            status {$vc} DEFAULT 'GENERATED',
            user_id INTEGER,
            source {$vc} DEFAULT 'agent',
            postal_address TEXT,
            created_at {$dt}
        ");

        $this->schema->addColumnIfNotExists('leads', 'user_id', 'INTEGER');
        $this->schema->addColumnIfNotExists('leads', 'source', "{$vc} DEFAULT 'agent'");
        $this->schema->addColumnIfNotExists('leads', 'mobile', 'TEXT');
        $this->schema->addColumnIfNotExists('leads', 'sms_draft', 'TEXT');
        $this->schema->addColumnIfNotExists('leads', 'calls_draft', 'TEXT');
        $this->schema->addColumnIfNotExists('leads', 'postal_address', 'TEXT');
    }
}
