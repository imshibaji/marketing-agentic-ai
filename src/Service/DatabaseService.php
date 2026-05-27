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
        $dbDir = __DIR__ . '/../../database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $this->driver = strtolower(trim((string)getenv('DB_DRIVER') ?: 'sqlite'));
        $this->loadDatabaseConfig($dbDir);
        $this->connect();

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

    private function loadDatabaseConfig(string $dbDir): void {
        $projectRoot = realpath(__DIR__ . '/../../');
        $sqliteFile = trim((string)getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite');

        if ($sqliteFile === '') {
            $sqliteFile = 'database/database.sqlite';
        }

        if ($this->isAbsolutePath($sqliteFile)) {
            $resolvedSqliteFile = $sqliteFile;
        } elseif (strpos($sqliteFile, 'database/') === 0 || strpos($sqliteFile, './') === 0 || strpos($sqliteFile, '../') === 0 || strpos($sqliteFile, '/') !== false) {
            $root = $projectRoot ?: rtrim($dbDir, '/');
            $resolvedSqliteFile = rtrim($root, '/') . '/' . ltrim($sqliteFile, '/');
        } else {
            $resolvedSqliteFile = rtrim($dbDir, '/') . '/' . $sqliteFile;
        }

        $sqliteDir = dirname($resolvedSqliteFile);
        if (!is_dir($sqliteDir)) {
            mkdir($sqliteDir, 0777, true);
        }

        $this->dbConfig = [
            'host' => trim((string)getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => trim((string)getenv('DB_PORT') ?: ''),
            'database' => trim((string)getenv('DB_DATABASE') ?: ''),
            'username' => trim((string)getenv('DB_USERNAME') ?: ''),
            'password' => trim((string)getenv('DB_PASSWORD') ?: ''),
            'charset' => trim((string)getenv('DB_CHARSET') ?: 'utf8mb4'),
            'sslmode' => trim((string)getenv('DB_SSLMODE') ?: 'prefer'),
            'sqlite_file' => $resolvedSqliteFile,
            'service_name' => trim((string)getenv('DB_SERVICE_NAME') ?: ''),
        ];
    }

    private function isAbsolutePath(string $path): bool {
        return strpos($path, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    private function connect(): void {
        try {
            switch ($this->driver) {
                case 'mysql':
                case 'pdo_mysql':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '3306';
                    $dbname = $this->dbConfig['database'];
                    $charset = $this->dbConfig['charset'];
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'pgsql':
                case 'postgresql':
                case 'postgres':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '5432';
                    $dbname = $this->dbConfig['database'];
                    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'sqlsrv':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '1433';
                    $dbname = $this->dbConfig['database'];
                    $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'oracle':
                    $host = $this->dbConfig['host'] ?: '127.0.0.1';
                    $port = $this->dbConfig['port'] ?: '1521';
                    $serviceName = $this->dbConfig['service_name'] ?: $this->dbConfig['database'];
                    $dsn = "oci:dbname=//{$host}:{$port}/{$serviceName};charset=UTF8";
                    $username = $this->dbConfig['username'];
                    $password = $this->dbConfig['password'];
                    break;

                case 'sqlite':
                default:
                    $this->dbPath = $this->dbConfig['sqlite_file'];
                    $dsn = "sqlite:" . $this->dbPath;
                    $username = null;
                    $password = null;
                    break;
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            if ($username === null) {
                $this->pdo = new PDO($dsn, null, null, $options);
            } else {
                $this->pdo = new PDO($dsn, $username, $password, $options);
            }

            // Driver-specific post-connect settings
            if (in_array($this->driver, ['mysql', 'pdo_mysql'])) {
                $this->pdo->exec("SET NAMES utf8mb4");
                $this->pdo->exec("SET foreign_key_checks = 0");
            } elseif (in_array($this->driver, ['pgsql', 'postgresql', 'postgres'])) {
                $this->pdo->exec("SET client_encoding TO 'UTF8'");
            }
        } catch (Exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function initializeSchema(): void {
        // Initialize in order of dependencies (foreign keys)
        $this->planModel->initializeSchema();
        $this->userModel->initializeSchema();
        $this->campaignModel->initializeSchema();
        $this->leadModel->initializeSchema();
        $this->agentLogModel->initializeSchema();
        $this->userActivityLogModel->initializeSchema();
        $this->emailOtpModel->initializeSchema();
        $this->settingModel->initializeSchema();
        $this->notificationModel->initializeSchema();
        $this->publicChatModel->initializeSchema();

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
