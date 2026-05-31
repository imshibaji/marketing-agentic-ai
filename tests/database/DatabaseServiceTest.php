<?php

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use MarketingAgent\Service\DatabaseService;

/**
 * @internal
 */
final class DatabaseServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // Migrate the database before running tests
    protected $migrate = true;
    protected $namespace = 'App';

    private DatabaseService $dbService;

    protected function setUp(): void
    {
        parent::setUp();
        // Since DatabaseService construct runs initializeSchema/migrations,
        // it will also initialize plugins or migrations.
        // Let's construct it after CodeIgniter has run its own migrations.
        $this->dbService = new DatabaseService();
    }

    public function testDatabaseServiceInitialization(): void
    {
        $this->assertInstanceOf(DatabaseService::class, $this->dbService);
        $this->assertInstanceOf(\PDO::class, $this->dbService->getPdo());
    }

    public function testSettings(): void
    {
        $testSettings = [
            'test_key_1' => 'value_1',
            'test_key_2' => 'value_2',
        ];

        $this->dbService->saveSettings($testSettings);

        $settings = $this->dbService->getSettings();
        $this->assertArrayHasKey('test_key_1', $settings);
        $this->assertEquals('value_1', $settings['test_key_1']);
        $this->assertArrayHasKey('test_key_2', $settings);
        $this->assertEquals('value_2', $settings['test_key_2']);
    }

    public function testPlans(): void
    {
        // 1. Create a plan
        $planId = $this->dbService->createPlan('Test Business Plan', 20, 100, 200, 200, 200, 200, '3 Months');
        $this->assertGreaterThan(0, $planId);

        // 2. Get the plan
        $plan = $this->dbService->getPlan($planId);
        $this->assertNotNull($plan);
        $this->assertEquals('Test Business Plan', $plan['name']);
        $this->assertEquals(20, $plan['campaign_limit']);
        $this->assertEquals('3 Months', $plan['duration']);

        // 3. Update the plan
        $this->dbService->updatePlan($planId, 'Updated Business Plan', 25, 120, 250, 250, 250, 250, '6 Months');
        $updatedPlan = $this->dbService->getPlan($planId);
        $this->assertEquals('Updated Business Plan', $updatedPlan['name']);
        $this->assertEquals(25, $updatedPlan['campaign_limit']);
        $this->assertEquals('6 Months', $updatedPlan['duration']);

        // 4. Get all plans
        $plans = $this->dbService->getPlans();
        $this->assertNotEmpty($plans);
        $found = false;
        foreach ($plans as $p) {
            if ((int)$p['id'] === $planId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // 5. Delete the plan
        $this->dbService->deletePlan($planId);
        $deletedPlan = $this->dbService->getPlan($planId);
        $this->assertNull($deletedPlan);
    }

    public function testUsers(): void
    {
        // Setup a test plan
        $planId = $this->dbService->createPlan('User Test Plan', 10, 50, 100, 100, 100, 100, '1 Month');

        // 1. Create User
        $userId = $this->dbService->createUser(
            'test_agent_user',
            password_hash('password123', PASSWORD_BCRYPT),
            'user',
            'Test Agent',
            'agent@test.com',
            '1234567890',
            '0987654321',
            $planId
        );
        $this->assertGreaterThan(0, $userId);

        // 2. Get User by ID
        $user = $this->dbService->getUserById($userId);
        $this->assertNotNull($user);
        $this->assertEquals('test_agent_user', $user['username']);
        $this->assertEquals('Test Agent', $user['full_name']);
        $this->assertEquals('agent@test.com', $user['email']);
        $this->assertEquals('User Test Plan', $user['plan_name']);

        // 3. Get User by Username
        $userByUsername = $this->dbService->getUserByUsername('test_agent_user');
        $this->assertNotNull($userByUsername);
        $this->assertEquals($userId, (int)$userByUsername['id']);

        // 4. Get User by Email
        $userByEmail = $this->dbService->getUserByEmail('agent@test.com');
        $this->assertNotNull($userByEmail);
        $this->assertEquals($userId, (int)$userByEmail['id']);

        // 5. Update User
        $this->dbService->updateUser(
            $userId,
            'test_agent_user_updated',
            password_hash('newpassword123', PASSWORD_BCRYPT),
            'admin',
            'Test Agent Updated',
            'agent_new@test.com',
            '1111111111',
            '2222222222',
            $planId
        );
        $updatedUser = $this->dbService->getUserById($userId);
        $this->assertEquals('test_agent_user_updated', $updatedUser['username']);
        $this->assertEquals('Test Agent Updated', $updatedUser['full_name']);
        $this->assertEquals('admin', $updatedUser['role']);

        // 6. Reset Usages
        $this->dbService->incrementLlmUsage($userId);
        $this->dbService->incrementEmailUsage($userId);
        $this->dbService->incrementWhatsappUsage($userId);
        $this->dbService->incrementSmsUsage($userId);

        $userWithUsage = $this->dbService->getUserById($userId);
        $this->assertEquals(1, (int)$userWithUsage['llm_usage']);
        $this->assertEquals(1, (int)$userWithUsage['email_usage']);
        $this->assertEquals(1, (int)$userWithUsage['whatsapp_usage']);
        $this->assertEquals(1, (int)$userWithUsage['sms_usage']);

        $this->dbService->resetUserUsage($userId);
        $userReset = $this->dbService->getUserById($userId);
        $this->assertEquals(0, (int)$userReset['llm_usage']);
        $this->assertEquals(0, (int)$userReset['email_usage']);
        $this->assertEquals(0, (int)$userReset['whatsapp_usage']);
        $this->assertEquals(0, (int)$userReset['sms_usage']);

        // 7. Get Users List
        $usersList = $this->dbService->getUsers();
        $this->assertNotEmpty($usersList);

        // 8. Delete User
        $this->dbService->deleteUser($userId);
        $this->assertNull($this->dbService->getUserById($userId));
    }

    public function testCampaignsAndLeads(): void
    {
        $userId = $this->dbService->createUser('campaign_user', 'hash', 'user', 'Cam User', 'cam@test.com');
        $shareUserId = $this->dbService->createUser('share_user', 'hash', 'user', 'Share User', 'share@test.com');

        // 1. Create Campaign
        $campaignId = $this->dbService->createCampaign(
            'Test Campaign',
            'Cool campaign description',
            'Small businesses',
            'Email',
            'none',
            '',
            'English',
            $userId
        );
        $this->assertGreaterThan(0, $campaignId);

        // 2. Get Campaign
        $campaign = $this->dbService->getCampaign($campaignId, $userId);
        $this->assertNotNull($campaign);
        $this->assertEquals('Test Campaign', $campaign['title']);
        $this->assertEquals('CREATED', $campaign['status']);

        // 3. Update Campaign Status & Content
        $this->dbService->updateCampaignStatus($campaignId, 'RUNNING');
        $updated = $this->dbService->getCampaign($campaignId, $userId);
        $this->assertEquals('RUNNING', $updated['status']);

        $this->dbService->updateCampaignContent($campaignId, 'Qualified email content here', 'COMPLETED');
        $completed = $this->dbService->getCampaign($campaignId, $userId);
        $this->assertEquals('COMPLETED', $completed['status']);
        $this->assertEquals('Qualified email content here', $completed['final_content']);

        // 4. Campaign Shares
        $this->dbService->shareCampaign($campaignId, [$shareUserId]);
        $shares = $this->dbService->getCampaignShares($campaignId);
        $this->assertContains($shareUserId, $shares);

        // Campaign should be accessible by shared user
        $sharedCampaign = $this->dbService->getCampaign($campaignId, $shareUserId);
        $this->assertNotNull($sharedCampaign);

        // 5. Leads operations
        $leadId = $this->dbService->saveLead(
            $campaignId,
            'Acme Corp',
            'John Doe',
            'john@acme.com',
            '12345',
            'SaaS',
            'Potential customer',
            '9.5',
            'Strong interest in product',
            'Hello John...',
            'Hi John...',
            $userId,
            'agent',
            '555-5555',
            'Sms text',
            '123 Main St'
        );
        $this->assertGreaterThan(0, $leadId);

        $lead = $this->dbService->getLead($leadId);
        $this->assertNotNull($lead);
        $this->assertEquals('Acme Corp', $lead['company_name']);
        $this->assertEquals('GENERATED', $lead['status']);

        // Update lead status
        $this->dbService->updateLeadStatus($leadId, 'QUALIFIED');
        $leadUpdated = $this->dbService->getLead($leadId);
        $this->assertEquals('QUALIFIED', $leadUpdated['status']);

        // Update lead drafts
        $this->dbService->updateLeadDrafts($leadId, 'New Email Draft', 'New Whatsapp Draft', 'New SMS Draft', 'New Call Draft');
        $leadDrafts = $this->dbService->getLead($leadId);
        $this->assertEquals('New Email Draft', $leadDrafts['email_draft']);
        $this->assertEquals('New Whatsapp Draft', $leadDrafts['whatsapp_draft']);
        $this->assertEquals('New SMS Draft', $leadDrafts['sms_draft']);

        // Update lead general info
        $this->dbService->updateLead(
            $leadId,
            $campaignId,
            'Acme Corp Updated',
            'John Smith',
            'john.smith@acme.com',
            '12345-updated',
            'SaaS/IT',
            'B2B Customer',
            '9.8',
            'Updated reasoning',
            'Draft Email',
            'Draft WA',
            'manual',
            '555-6666',
            $userId,
            'Draft SMS',
            '456 Oak St',
            'QUALIFIED',
            'Draft Call'
        );
        $leadInfo = $this->dbService->getLead($leadId);
        $this->assertEquals('Acme Corp Updated', $leadInfo['company_name']);
        $this->assertEquals('John Smith', $leadInfo['contact_name']);
        $this->assertEquals('manual', $leadInfo['source']);

        // Get Leads List
        $leads = $this->dbService->getLeads($campaignId, $userId);
        $this->assertNotEmpty($leads);
        $this->assertEquals($leadId, (int)$leads[0]['id']);

        // Delete Lead
        $this->dbService->deleteLead($leadId);
        $this->assertNull($this->dbService->getLead($leadId));

        // Cleanup
        $this->dbService->deleteCampaign($campaignId);
        $this->assertNull($this->dbService->getCampaign($campaignId, $userId));

        $this->dbService->deleteUser($userId);
        $this->dbService->deleteUser($shareUserId);
    }

    public function testUserActivityLogs(): void
    {
        $userId = $this->dbService->createUser('logger_user', 'hash', 'user', 'Logger', 'logger@test.com');

        $this->dbService->logActivity($userId, 'LOGIN', 'User logged in successfully');
        $this->dbService->logActivity($userId, 'CAMPAIGN_CREATE', 'Created campaign #12');

        $logs = $this->dbService->getActivityLogs($userId);
        $this->assertCount(2, $logs);
        $this->assertEquals('CAMPAIGN_CREATE', $logs[0]['action']);
        $this->assertEquals('LOGIN', $logs[1]['action']);

        $this->dbService->deleteUser($userId);
    }

    public function testEmailOtps(): void
    {
        $email = 'otp_test@example.com';
        $otp = '567890';
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $this->dbService->createOtp($email, $otp, $expiresAt);

        // Verify valid OTP
        $verified = $this->dbService->verifyOtp($email, $otp);
        $this->assertTrue($verified);

        // Re-verifying same OTP should fail (since it gets marked as used)
        $reverify = $this->dbService->verifyOtp($email, $otp);
        $this->assertFalse($reverify);

        // Verify with non-existent email/otp should fail
        $fakeVerify = $this->dbService->verifyOtp('fake@test.com', '000000');
        $this->assertFalse($fakeVerify);
    }
}
