<?php
namespace MarketingAgent\Service;

use PDO;
use Exception;
use MarketingAgent\Model\User;
use MarketingAgent\Model\Setting;
use MarketingAgent\Model\Campaign;
use MarketingAgent\Model\Plan;
use MarketingAgent\Model\UserActivityLog;
use MarketingAgent\Model\EmailOtp;
use MarketingAgent\Model\Lead;
use MarketingAgent\Model\AgentLog;
use MarketingAgent\Model\Notification;
use MarketingAgent\Model\PublicChat;


class DatabaseService {
    private ?PDO $pdo = null;
    private string $driver;
    private array $dbConfig = [];
    private string $dbPath;

    private User $userModel;
    private Setting $settingModel;
    private Campaign $campaignModel;
    private Plan $planModel;
    private UserActivityLog $userActivityLogModel;
    private EmailOtp $emailOtpModel;
    private Lead $leadModel;
    private AgentLog $agentLogModel;
    private Notification $notificationModel;
    private PublicChat $publicChatModel;

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

        $this->userModel = new User($this->pdo);
        $this->settingModel = new Setting($this->pdo);
        $this->campaignModel = new Campaign($this->pdo);
        $this->planModel = new Plan($this->pdo);
        $this->userActivityLogModel = new UserActivityLog($this->pdo);
        $this->emailOtpModel = new EmailOtp($this->pdo);
        $this->leadModel = new Lead($this->pdo);
        $this->agentLogModel = new AgentLog($this->pdo);
        $this->notificationModel = new Notification($this->pdo);
        $this->publicChatModel = new PublicChat($this->pdo);

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

        // Let active plugins initialize custom schemas
        \MarketingAgent\Plugin\HookManager::doAction('db_initialize_schema', $this);
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    // ─── Settings Delegation ───────────────────────────────────────

    public function getSettings(): array {
        return $this->settingModel->getSettings();
    }

    public function saveSettings(array $settings): void {
        $this->settingModel->saveSettings($settings);
    }

    // ─── Agent Logs Delegation ─────────────────────────────────────

    public function logAgentAction(int $campaignId, string $agentName, string $action, string $logText): void {
        $this->agentLogModel->logAgentAction($campaignId, $agentName, $action, $logText);
    }

    public function getLogs(int $campaignId): array {
        return $this->agentLogModel->getLogs($campaignId);
    }

    // ─── Campaigns Delegation ──────────────────────────────────────

    public function createCampaign(string $title, string $description, string $audience, string $channel, string $crawlType = 'none', string $crawlTarget = '', string $language = 'English', ?int $userId = null): int {
        return $this->campaignModel->createCampaign($title, $description, $audience, $channel, $crawlType, $crawlTarget, $language, $userId);
    }

    public function updateCampaignStatus(int $id, string $status): void {
        $this->campaignModel->updateCampaignStatus($id, $status);
    }

    public function updateCampaignContent(int $id, string $content, string $status = 'COMPLETED'): void {
        $this->campaignModel->updateCampaignContent($id, $content, $status);
    }

    public function getCampaign(int $id, ?int $userId = null, string $role = 'user'): ?array {
        return $this->campaignModel->getCampaign($id, $userId, $role);
    }

    public function getCampaigns(?int $userId = null, string $role = 'user'): array {
        return $this->campaignModel->getCampaigns($userId, $role);
    }

    public function shareCampaign(int $campaignId, array $userIds): void {
        $this->campaignModel->shareCampaign($campaignId, $userIds);
    }

    public function getCampaignShares(int $campaignId): array {
        return $this->campaignModel->getCampaignShares($campaignId);
    }

    public function deleteCampaign(int $id): void {
        $this->campaignModel->deleteCampaign($id);
    }

    public function updateCampaignLlmProvider(int $id, string $provider): void {
        $this->campaignModel->updateCampaignLlmProvider($id, $provider);
    }

    // ─── User Management Delegation ────────────────────────────────

    public function getUsers(): array {
        return $this->userModel->getUsers();
    }

    public function getUserById(int $id): ?array {
        return $this->userModel->getUserById($id);
    }

    public function getUserByUsername(string $username): ?array {
        return $this->userModel->getUserByUsername($username);
    }

    public function getUserByEmail(string $email): ?array {
        return $this->userModel->getUserByEmail($email);
    }

    public function createUser(string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): int {
        return $this->userModel->createUser($username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId);
    }

    public function updateUser(int $id, string $username, string $passwordHash, string $role, ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null, ?int $planId = null): void {
        $this->userModel->updateUser($id, $username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, $planId);
    }

    public function deleteUser(int $id): void {
        $this->userModel->deleteUser($id);
    }

    public function getUserCampaignCount(int $userId): int {
        return $this->userModel->getUserCampaignCount($userId);
    }

    public function getUserLeadCount(int $userId): int {
        return $this->userModel->getUserLeadCount($userId);
    }

    public function incrementLlmUsage(int $userId): void {
        $this->userModel->incrementLlmUsage($userId);
    }

    public function incrementEmailUsage(int $userId): void {
        $this->userModel->incrementEmailUsage($userId);
    }

    public function incrementPageUsage(int $userId): void {
        // Compatibility for any callers of legacy incrementPageUsage
        $this->userModel->incrementLlmUsage($userId);
    }

    public function incrementWhatsappUsage(int $userId): void {
        $this->userModel->incrementWhatsappUsage($userId);
    }

    public function incrementSmsUsage(int $userId): void {
        $this->userModel->incrementSmsUsage($userId);
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
        return $this->leadModel->saveLead(
            $campaignId, $companyName, $contactName, $email, $whatsapp,
            $industry, $description, $score, $reasoning, $emailDraft,
            $whatsappDraft, $userId, $source, $mobile, $smsDraft, $postalAddress
        );
    }

    public function getLeads(?int $campaignId = null, ?int $userId = null, string $role = 'user'): array {
        return $this->leadModel->getLeads($campaignId, $userId, $role);
    }

    public function getLead(int $id): ?array {
        return $this->leadModel->getLead($id);
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
        ?string $postalAddress = null
    ): void {
        $this->leadModel->updateLead(
            $id, $campaignId, $companyName, $contactName, $email, $whatsapp,
            $industry, $description, $score, $reasoning, $emailDraft,
            $whatsappDraft, $source, $mobile, $userId, $smsDraft, $postalAddress
        );
    }

    public function updateLeadStatus(int $id, string $status): void {
        $this->leadModel->updateLeadStatus($id, $status);
    }

    public function updateLeadDrafts(int $id, string $emailDraft, string $whatsappDraft, ?string $smsDraft = null): void {
        $this->leadModel->updateLeadDrafts($id, $emailDraft, $whatsappDraft, $smsDraft);
    }

    public function deleteLead(int $id): void {
        $this->leadModel->deleteLead($id);
    }

    public function clearLeads(int $campaignId): void {
        $this->leadModel->clearLeads($campaignId);
    }

    // ─── User Activity Logs Delegation ─────────────────────────────

    public function logActivity(?int $userId, string $action, string $details): void {
        $this->userActivityLogModel->logActivity($userId, $action, $details);
    }

    public function getActivityLogs(?int $userId = null, string $role = 'user'): array {
        return $this->userActivityLogModel->getActivityLogs($userId, $role);
    }

    // ─── Email OTPs Delegation ─────────────────────────────────────

    public function createOtp(string $email, string $otp, string $expiresAt): void {
        $this->emailOtpModel->createOtp($email, $otp, $expiresAt);
    }

    public function verifyOtp(string $email, string $otp): bool {
        return $this->emailOtpModel->verifyOtp($email, $otp);
    }

    // ─── Plans Delegation ──────────────────────────────────────────

    public function getPlans(): array {
        return $this->planModel->getPlans();
    }

    public function getPlan(int $id): ?array {
        return $this->planModel->getPlan($id);
    }

    public function createPlan(string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): int {
        return $this->planModel->createPlan($name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
    }

    public function updatePlan(int $id, string $name, int $campaignLimit, int $leadLimit, int $llmLimit = 100, int $emailLimit = 100, int $whatsappLimit = 100, int $smsLimit = 100): void {
        $this->planModel->updatePlan($id, $name, $campaignLimit, $leadLimit, $llmLimit, $emailLimit, $whatsappLimit, $smsLimit);
    }

    public function deletePlan(int $id): void {
        $this->planModel->deletePlan($id);
    }
}
