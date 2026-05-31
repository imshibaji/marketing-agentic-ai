<?php
namespace MarketingAgent\Service;

use PDO;
use Exception;
use App\Models\UserModel;
use App\Models\SettingModel;
use App\Models\CampaignModel;
use App\Models\PlanModel;
use App\Models\UserActivityLogModel;
use App\Models\EmailOtpModel;
use App\Models\LeadModel;
use App\Models\AgentLogModel;
use App\Models\NotificationModel;
use App\Models\PublicChatModel;

class DatabaseService {
    private ?PDO $pdo = null;
    private string $driver;
    private array $dbConfig = [];
    private string $dbPath;

    private UserModel $userModel;
    private SettingModel $settingModel;
    private CampaignModel $campaignModel;
    private PlanModel $planModel;
    private UserActivityLogModel $userActivityLogModel;
    private EmailOtpModel $emailOtpModel;
    private LeadModel $leadModel;
    private AgentLogModel $agentLogModel;
    private NotificationModel $notificationModel;
    private PublicChatModel $publicChatModel;

    public function __construct() {
        $driver = strtolower(trim((string)getenv('DB_DRIVER') ?: 'sqlite'));
        
        if ($driver === 'sqlite') {
            $this->driver = 'sqlite';
            $sqliteFile = getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite';
            if (strpos($sqliteFile, '/') !== 0 && !preg_match('/^[A-Za-z]:\\\\/', $sqliteFile)) {
                $sqliteFile = ROOTPATH . $sqliteFile;
            }
            $dsn = "sqlite:" . $sqliteFile;
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT');
            $dbname = getenv('DB_DATABASE') ?: '';
            $username = getenv('DB_USERNAME') ?: '';
            $password = getenv('DB_PASSWORD') ?: '';

            if ($driver === 'mysql' || $driver === 'pdo_mysql') {
                $this->driver = 'mysql';
                $port = $port ?: '3306';
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            } elseif ($driver === 'pgsql' || $driver === 'postgresql') {
                $this->driver = 'pgsql';
                $port = $port ?: '5432';
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            } elseif ($driver === 'sqlsrv') {
                $this->driver = 'sqlsrv';
                $port = $port ?: '1433';
                $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
            } elseif ($driver === 'oracle') {
                $this->driver = 'oracle';
                $port = $port ?: '1521';
                $serviceName = getenv('DB_SERVICE_NAME') ?: $dbname;
                $dsn = "oci:dbname=//{$host}:{$port}/{$serviceName};charset=UTF8";
            } else {
                $this->driver = $driver;
                throw new Exception("Unsupported DB driver: " . $driver);
            }

            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
        }

        // Instantiate standard CodeIgniter Models
        $this->userModel = new UserModel();
        $this->settingModel = new SettingModel();
        $this->campaignModel = new CampaignModel();
        $this->planModel = new PlanModel();
        $this->userActivityLogModel = new UserActivityLogModel();
        $this->emailOtpModel = new EmailOtpModel();
        $this->leadModel = new LeadModel();
        $this->agentLogModel = new AgentLogModel();
        $this->notificationModel = new NotificationModel();
        $this->publicChatModel = new PublicChatModel();

        // Load active plugins before schema initialization
        \MarketingAgent\Plugin\PluginManager::initialize($this);

        $this->initializeSchema();
    }

    private function initializeSchema(): void {
        // Automatically run migrations to guarantee standard database schema is up-to-date
        try {
            $migrations = \Config\Services::migrations();
            $migrations->latest();
        } catch (\Throwable $e) {
            if (function_exists('log_message')) {
                log_message('error', 'Migrations run failed: ' . $e->getMessage());
            } else {
                error_log('Migrations run failed: ' . $e->getMessage());
            }
        }

        // Run migrations/auto initializations
        // The table columns/definitions are managed directly by CI4 models & CodeIgniter migrations now.
        // Let's fire standard plugins hook to initialize custom schemas if needed
        \MarketingAgent\Plugin\HookManager::doAction('db_initialize_schema', $this);
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    // ─── Settings Delegation ───────────────────────────────────────

    public function getSettings(): array {
        $rows = $this->settingModel->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function saveSettings(array $settings): void {
        foreach ($settings as $key => $val) {
            $this->settingModel->save(['key' => $key, 'value' => $val]);
        }
    }

    // ─── Agent Logs Delegation ─────────────────────────────────────

    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $this->agentLogModel->insert([
            'campaign_id' => $campaignId,
            'agent_name' => $agentName,
            'action' => $action,
            'log_text' => $logText
        ]);
    }

    public function getLogs(int $campaignId): array {
        return $this->agentLogModel->where('campaign_id', $campaignId)->orderBy('id', 'ASC')->findAll();
    }

    // ─── Campaigns Delegation ──────────────────────────────────────

    public function createCampaign(string $title, string $description, string $audience, string $channel, string $crawlType = 'none', string $crawlTarget = '', string $language = 'English', ?int $userId = null): int {
        $this->campaignModel->insert([
            'title' => $title,
            'product_description' => $description,
            'target_audience' => $audience,
            'channel' => $channel,
            'crawl_type' => $crawlType,
            'crawl_target' => $crawlTarget,
            'language' => $language,
            'user_id' => $userId
        ]);
        return (int)$this->campaignModel->getInsertID();
    }

    public function updateCampaignStatus(int $id, string $status): void {
        $this->campaignModel->update($id, ['status' => $status]);
    }

    public function updateCampaignContent(int $id, string $content, string $status = 'COMPLETED'): void {
        $this->campaignModel->update($id, [
            'final_content' => $content,
            'status' => $status
        ]);
    }

    public function getCampaign(int $id, ?int $userId = null, string $role = 'user'): ?array {
        $db = \Config\Database::connect();
        $sharesTable = $db->prefixTable('campaign_shares');
        $builder = $this->campaignModel->builder();
        $builder->select('campaigns.*');
        if ($role !== 'admin' && $userId !== null) {
            $builder->groupStart()
                    ->where('campaigns.user_id', $userId)
                    ->orWhere("campaigns.id IN (SELECT campaign_id FROM {$sharesTable} WHERE user_id = {$userId})")
                    ->groupEnd();
        }
        $builder->where('campaigns.id', $id);
        return $builder->get()->getRowArray();
    }

    public function getCampaigns(?int $userId = null, string $role = 'user'): array {
        $db = \Config\Database::connect();
        $sharesTable = $db->prefixTable('campaign_shares');
        $builder = $this->campaignModel->builder();
        $builder->select('campaigns.*, u.username AS owner_name');
        $builder->join('users u', 'campaigns.user_id = u.id', 'left');
        if ($role !== 'admin' && $userId !== null) {
            $builder->groupStart()
                    ->where('campaigns.user_id', $userId)
                    ->orWhere("campaigns.id IN (SELECT campaign_id FROM {$sharesTable} WHERE user_id = {$userId})")
                    ->groupEnd();
        }
        $builder->orderBy('campaigns.id', 'DESC');
        return $builder->get()->getResultArray();
    }

    public function shareCampaign(int $campaignId, array $userIds): void {
        $db = \Config\Database::connect();
        $db->table('campaign_shares')->where('campaign_id', $campaignId)->delete();
        foreach ($userIds as $uid) {
            $db->table('campaign_shares')->ignore()->insert([
                'campaign_id' => $campaignId,
                'user_id' => (int)$uid
            ]);
        }
    }

    public function getCampaignShares(int $campaignId): array {
        $db = \Config\Database::connect();
        return array_column(
            $db->table('campaign_shares')->select('user_id')->where('campaign_id', $campaignId)->get()->getResultArray(),
            'user_id'
        );
    }

    public function deleteCampaign(int $id): void {
        $db = \Config\Database::connect();
        $db->table('campaign_shares')->where('campaign_id', $id)->delete();
        $this->clearLeads($id);
        $this->campaignModel->delete($id);
    }

    public function updateCampaignLlmProvider(int $id, string $provider): void {
        $this->campaignModel->update($id, ['llm_provider' => $provider]);
    }

    // ─── User Management Delegation ────────────────────────────────

    public function getUsers(): array {
        $db = \Config\Database::connect();
        $campaignsTable = $db->prefixTable('campaigns');
        $leadsTable = $db->prefixTable('leads');
        $builder = $db->table('users u');
        $builder->select('u.id, u.username, u.role, u.created_at, u.full_name, u.email, u.mobile, u.whatsapp_number, u.plan_id, u.plan_expires_at, u.llm_usage, u.email_usage, u.whatsapp_usage, u.sms_usage');
        $builder->select('p.name AS plan_name, p.campaign_limit, p.lead_limit, p.llm_limit, p.email_limit, p.whatsapp_limit, p.sms_limit, p.duration AS plan_duration');
        $builder->select("(SELECT COUNT(*) FROM {$campaignsTable} c WHERE c.user_id = u.id) AS campaign_usage");
        $builder->select("(SELECT COUNT(*) FROM {$leadsTable} l LEFT JOIN {$campaignsTable} c ON l.campaign_id = c.id WHERE l.user_id = u.id OR c.user_id = u.id) AS lead_usage");
        $builder->join('plans p', 'u.plan_id = p.id', 'left');
        $builder->orderBy('u.id', 'ASC');
        return $builder->get()->getResultArray();
    }

    public function getUserById(int $id): ?array {
        $builder = $this->userModel->builder();
        $builder->select('users.*, p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads, p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms, p.duration AS plan_duration');
        $builder->join('plans p', 'users.plan_id = p.id', 'left');
        $builder->where('users.id', $id);
        return $builder->get()->getRowArray();
    }

    public function getUserByUsername(string $username): ?array {
        $builder = $this->userModel->builder();
        $builder->select('users.*, p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads, p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms, p.duration AS plan_duration');
        $builder->join('plans p', 'users.plan_id = p.id', 'left');
        $builder->where('users.username', $username);
        return $builder->get()->getRowArray();
    }

    public function getUserByEmail(string $email): ?array {
        $builder = $this->userModel->builder();
        $builder->select('users.*, p.name AS plan_name, p.campaign_limit AS plan_campaigns, p.lead_limit AS plan_leads, p.llm_limit AS plan_llm, p.email_limit AS plan_email, p.whatsapp_limit AS plan_whatsapp, p.sms_limit AS plan_sms, p.duration AS plan_duration');
        $builder->join('plans p', 'users.plan_id = p.id', 'left');
        $builder->where('users.email', $email);
        return $builder->get()->getRowArray();
    }

    public function createUser(string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): int {
        if ($planId === null || $planId <= 0) {
            $defaultPlan = $this->planModel->where('name', 'Default Plan')->first();
            $planId = $defaultPlan ? (int)$defaultPlan['id'] : null;
        }

        $planExpiresAt = null;
        if ($planId) {
            $plan = $this->planModel->find($planId);
            $dur = $plan ? trim($plan['duration'] ?: '1 Month') : '1 Month';
            $ts = (stripos($dur, '+') !== 0 && stripos($dur, '-') !== 0) ? strtotime('+' . $dur) : strtotime($dur);
            $planExpiresAt = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s', strtotime('+1 Month'));
        }

        $this->userModel->insert([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'full_name' => $fullName,
            'email' => $email,
            'mobile' => $mobile,
            'whatsapp_number' => $whatsappNumber,
            'plan_id' => $planId,
            'plan_expires_at' => $planExpiresAt
        ]);
        return (int)$this->userModel->getInsertID();
    }

    public function updateUser(int $id, string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): void {
        if ($planId === null || $planId <= 0) {
            $defaultPlan = $this->planModel->where('name', 'Default Plan')->first();
            $planId = $defaultPlan ? (int)$defaultPlan['id'] : null;
        }

        $curr = $this->userModel->find($id);
        $currPlanId = $curr ? (int)$curr['plan_id'] : null;
        $planExpiresAt = $curr ? $curr['plan_expires_at'] : null;

        if ($currPlanId !== $planId || $planExpiresAt === null) {
            if ($planId) {
                $plan = $this->planModel->find($planId);
                $dur = $plan ? trim($plan['duration'] ?: '1 Month') : '1 Month';
                $ts = (stripos($dur, '+') !== 0 && stripos($dur, '-') !== 0) ? strtotime('+' . $dur) : strtotime($dur);
                $planExpiresAt = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s', strtotime('+1 Month'));
            } else {
                $planExpiresAt = null;
            }
        }

        $this->userModel->update($id, [
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'full_name' => $fullName,
            'email' => $email,
            'mobile' => $mobile,
            'whatsapp_number' => $whatsappNumber,
            'plan_id' => $planId,
            'plan_expires_at' => $planExpiresAt
        ]);
    }

    public function deleteUser(int $id): void {
        $this->campaignModel->where('user_id', $id)->set(['user_id' => null])->update();
        $this->userModel->delete($id);
    }

    public function getUserCampaignCount(int $userId): int {
        return $this->campaignModel->where('user_id', $userId)->countAllResults();
    }

    public function getUserLeadCount(int $userId): int {
        $builder = $this->leadModel->builder();
        $builder->join('campaigns c', 'leads.campaign_id = c.id', 'left');
        $builder->groupStart()
                ->where('leads.user_id', $userId)
                ->orWhere('c.user_id', $userId)
                ->groupEnd();
        return $builder->countAllResults();
    }

    public function incrementLlmUsage(int $userId): void {
        $db = \Config\Database::connect();
        $db->table('users')->where('id', $userId)->increment('llm_usage', 1);
    }

    public function incrementEmailUsage(int $userId): void {
        $db = \Config\Database::connect();
        $db->table('users')->where('id', $userId)->increment('email_usage', 1);
    }

    public function incrementPageUsage(int $userId): void {
        $this->incrementLlmUsage($userId);
    }

    public function incrementWhatsappUsage(int $userId): void {
        $db = \Config\Database::connect();
        $db->table('users')->where('id', $userId)->increment('whatsapp_usage', 1);
    }

    public function incrementSmsUsage(int $userId): void {
        $db = \Config\Database::connect();
        $db->table('users')->where('id', $userId)->increment('sms_usage', 1);
    }

    // ─── Leads Delegation ──────────────────────────────────────────

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
        ?string $postalAddress = null
    ): int {
        $this->leadModel->insert([
            'campaign_id' => $campaignId,
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'mobile' => $mobile,
            'industry' => $industry,
            'description' => $description,
            'score' => $score,
            'reasoning' => $reasoning,
            'email_draft' => $emailDraft,
            'whatsapp_draft' => $whatsappDraft,
            'sms_draft' => $smsDraft,
            'user_id' => $userId,
            'source' => $source,
            'postal_address' => $postalAddress
        ]);
        return (int)$this->leadModel->getInsertID();
    }

    public function getLeads(?int $campaignId = null, ?int $userId = null, string $role = 'user'): array {
        $db = \Config\Database::connect();
        $sharesTable = $db->prefixTable('campaign_shares');
        $builder = $this->leadModel->builder();
        $builder->select('leads.*, c.title AS campaign_title, c.user_id AS campaign_owner_id');
        $builder->join('campaigns c', 'leads.campaign_id = c.id', 'left');
        
        if ($campaignId !== null && $campaignId > 0) {
            $builder->where('leads.campaign_id', $campaignId);
        }
        
        if ($role !== 'admin' && $userId !== null) {
            $builder->groupStart()
                    ->where('leads.user_id', $userId)
                    ->orWhere('c.user_id', $userId)
                    ->orWhere("c.id IN (SELECT campaign_id FROM {$sharesTable} WHERE user_id = {$userId})")
                    ->groupEnd();
        }
        
        $builder->orderBy('leads.id', 'DESC');
        return $builder->get()->getResultArray();
    }

    public function getLead(int $id): ?array {
        $builder = $this->leadModel->builder();
        $builder->select('leads.*, c.title AS campaign_title, c.user_id AS campaign_owner_id');
        $builder->join('campaigns c', 'leads.campaign_id = c.id', 'left');
        $builder->where('leads.id', $id);
        return $builder->get()->getRowArray();
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
        $data = [
            'campaign_id' => $campaignId,
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'mobile' => $mobile,
            'industry' => $industry,
            'description' => $description,
            'score' => $score,
            'reasoning' => $reasoning,
            'email_draft' => $emailDraft,
            'whatsapp_draft' => $whatsappDraft,
            'sms_draft' => $smsDraft,
            'source' => $source,
            'user_id' => $userId,
            'postal_address' => $postalAddress,
            'calls_draft' => $callsDraft
        ];
        if ($status !== null) {
            $data['status'] = $status;
        }
        $this->leadModel->update($id, $data);
    }

    public function updateLeadStatus(int $id, string $status): void {
        $this->leadModel->update($id, ['status' => $status]);
    }

    public function updateLeadDrafts(int $id, string $emailDraft, string $whatsappDraft, ?string $smsDraft = null, ?string $callsDraft = null): void {
        $this->leadModel->update($id, [
            'email_draft' => $emailDraft,
            'whatsapp_draft' => $whatsappDraft,
            'sms_draft' => $smsDraft,
            'calls_draft' => $callsDraft
        ]);
    }

    public function deleteLead(int $id): void {
        $this->leadModel->delete($id);
    }

    public function clearLeads(int $campaignId): void {
        $this->leadModel->where('campaign_id', $campaignId)->delete();
    }

    // ─── User Activity Logs Delegation ─────────────────────────────

    public function logActivity(?int $userId, string $action, string $details): void {
        $this->userActivityLogModel->insert([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details
        ]);
        \MarketingAgent\Plugin\HookManager::doAction('activity_logged', $userId, $action, $details);
    }

    public function getActivityLogs(?int $userId = null, string $role = 'user'): array {
        $builder = $this->userActivityLogModel->builder();
        $builder->select('user_activity_logs.*, u.username');
        $builder->join('users u', 'user_activity_logs.user_id = u.id', 'left');
        if ($role !== 'admin' && $userId !== null) {
            $builder->where('user_activity_logs.user_id', $userId);
        }
        $builder->orderBy('user_activity_logs.id', 'DESC');
        return $builder->get()->getResultArray();
    }

    // ─── Email OTPs Delegation ─────────────────────────────────────

    public function createOtp(string $email, string $otp, string $expiresAt): void {
        $this->emailOtpModel->insert([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'used' => 0
        ]);
    }

    public function verifyOtp(string $email, string $otp): bool {
        $row = $this->emailOtpModel->where('email', $email)
                                    ->where('otp', $otp)
                                    ->where('used', 0)
                                    ->where('expires_at >=', date('Y-m-d H:i:s'))
                                    ->first();
        if ($row) {
            $this->emailOtpModel->update($row['id'], ['used' => 1]);
            return true;
        }
        return false;
    }

    // ─── Plans Delegation ──────────────────────────────────────────

    public function getPlans(): array {
        return $this->planModel->orderBy('id', 'ASC')->findAll();
    }

    public function getPlan(int $id): ?array {
        return $this->planModel->find($id) ?: null;
    }

    public function createPlan(string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100, string $duration = '1 Month'): int {
        $this->planModel->insert([
            'name' => $name,
            'campaign_limit' => $campaignLimit,
            'lead_limit' => $leadLimit,
            'llm_limit' => $llmLimit,
            'email_limit' => $emailLimit,
            'whatsapp_limit' => $whatsappLimit,
            'sms_limit' => $smsLimit,
            'duration' => $duration
        ]);
        return (int)$this->planModel->getInsertID();
    }

    public function updatePlan(int $id, string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100, string $duration = '1 Month'): void {
        $this->planModel->update($id, [
            'name' => $name,
            'campaign_limit' => $campaignLimit,
            'lead_limit' => $leadLimit,
            'llm_limit' => $llmLimit,
            'email_limit' => $emailLimit,
            'whatsapp_limit' => $whatsappLimit,
            'sms_limit' => $smsLimit,
            'duration' => $duration
        ]);
    }

    public function deletePlan(int $id): void {
        $defaultPlan = $this->planModel->where('name', 'Default Plan')->first();
        $defaultPlanId = $defaultPlan ? (int)$defaultPlan['id'] : null;
        
        $this->userModel->where('plan_id', $id)->set(['plan_id' => $defaultPlanId])->update();
        $this->planModel->delete($id);
    }

    public function resetUserUsage(int $userId): void {
        $this->userModel->update($userId, [
            'llm_usage' => 0,
            'email_usage' => 0,
            'whatsapp_usage' => 0,
            'sms_usage' => 0
        ]);
    }

    public function resetUserPlan(int $userId): void {
        $user = $this->userModel->find($userId);
        $planId = $user ? $user['plan_id'] : null;
        $planExpiresAt = null;
        if ($planId) {
            $plan = $this->planModel->find($planId);
            $dur = $plan ? trim($plan['duration'] ?: '1 Month') : '1 Month';
            $ts = (stripos($dur, '+') !== 0 && stripos($dur, '-') !== 0) ? strtotime('+' . $dur) : strtotime($dur);
            $planExpiresAt = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s', strtotime('+1 Month'));
        }
        $this->userModel->update($userId, ['plan_expires_at' => $planExpiresAt]);
    }
}
