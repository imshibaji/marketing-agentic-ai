<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Agent\CoordinatorAgent;
use Exception;

class CampaignController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $campaignId = $this->request->getGet('id');

        if ($campaignId) {
            $campaign = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
            if (!$campaign) {
                return $this->respondError('Campaign not found or access denied', 404);
            }

            $logs = $this->db->getLogs((int)$campaignId);
            $leads = $this->db->getLeads((int)$campaignId);

            $shares = [];
            if ($user['role'] === 'admin') {
                $shares = $this->db->getCampaignShares((int)$campaignId);
            }

            return $this->respondSuccess([
                'campaign' => $campaign,
                'logs' => $logs,
                'leads' => $leads,
                'shares' => $shares
            ]);
        }

        $campaigns = $this->db->getCampaigns((int)$user['id'], $user['role']);
        return $this->respondSuccess(['campaigns' => $campaigns]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $input = $this->getJsonInput();

        // Handle campaign sharing (Admin only)
        if (isset($input['action']) && $input['action'] === 'share') {
            if ($user['role'] !== 'admin') {
                return $this->respondError('Forbidden. Only admins can share campaigns.', 403);
            }
            if (empty($input['campaign_id'])) {
                return $this->respondError('Missing campaign_id.');
            }
            $campaign = $this->db->getCampaign((int)$input['campaign_id'], (int)$user['id'], $user['role']);
            if (!$campaign) {
                return $this->respondError('Campaign not found.', 404);
            }
            $userIds = array_filter(array_map('intval', $input['user_ids'] ?? []));
            $this->db->shareCampaign((int)$input['campaign_id'], $userIds);
            $this->db->logActivity((int)$user['id'], 'SHARE_CAMPAIGN', "Shared campaign '{$campaign['title']}' (ID: {$input['campaign_id']}) with " . count($userIds) . " user(s)");
            return $this->respondSuccess([], 'Campaign shared successfully.');
        }

        // Handle final content manual edit updates
        if (isset($input['action']) && $input['action'] === 'update_content') {
            if (empty($input['id']) || !isset($input['content'])) {
                return $this->respondError('Missing required fields: id, content');
            }
            
            $campaign = $this->db->getCampaign((int)$input['id'], (int)$user['id'], $user['role']);
            if (!$campaign) {
                return $this->respondError('Forbidden. You do not have permission to modify this campaign.', 403);
            }

            $this->db->updateCampaignContent((int)$input['id'], $input['content'], 'COMPLETED');
            return $this->respondSuccess([], 'Campaign content updated successfully.');
        }

        if (empty($input['title']) || empty($input['product_description']) || empty($input['target_audience']) || empty($input['channel'])) {
            return $this->respondError('Missing required fields: title, product_description, target_audience, channel');
        }

        // Plan limits check
        $userDetails = $this->db->getUserById((int)$user['id']);
        if ($this->isPlanExpired($userDetails)) {
            return $this->respondError("Plan expired. Your plan expired on {$userDetails['plan_expires_at']}. Please contact an administrator to renew.", 403);
        }
        if ($user['role'] !== 'admin' && $userDetails['plan_campaigns'] !== -1) {
            $campaignCount = $this->db->getUserCampaignCount((int)$user['id']);
            if ($campaignCount >= $userDetails['plan_campaigns']) {
                return $this->respondError("Plan limit reached. You can create at most {$userDetails['plan_campaigns']} campaigns. Please contact an administrator.", 403);
            }
        }

        $crawlType = $input['crawl_type'] ?? 'none';
        $crawlTarget = $input['crawl_target'] ?? '';
        $language = $input['language'] ?? 'English';

        $campaignId = $this->db->createCampaign(
            trim($input['title']),
            trim($input['product_description']),
            trim($input['target_audience']),
            trim($input['channel']),
            trim($crawlType),
            trim($crawlTarget),
            trim($language),
            (int)$user['id']
        );

        $this->db->logActivity((int)$user['id'], 'CREATE_CAMPAIGN', "Created campaign '" . trim($input['title']) . "' (ID: {$campaignId})");

        return $this->respondSuccess(['campaign_id' => $campaignId], 'Campaign created successfully.');
    }

    public function delete(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $campaignId = $this->request->getGet('id');

        if (!$campaignId) {
            return $this->respondError('Missing campaign ID');
        }

        $campaign = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
        if (!$campaign) {
            return $this->respondError('Forbidden. You do not have permission to delete this campaign.', 403);
        }

        if ($user['role'] !== 'admin' && (int)$campaign['user_id'] !== (int)$user['id']) {
            return $this->respondError('Forbidden. You cannot delete a campaign shared with you.', 403);
        }

        $this->db->deleteCampaign((int)$campaignId);
        $this->db->logActivity((int)$user['id'], 'DELETE_CAMPAIGN', "Deleted campaign '{$campaign['title']}' (ID: {$campaignId})");

        return $this->respondSuccess([], 'Campaign deleted successfully.');
    }

    public function run()
    {
        // Disable output buffering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        ob_implicit_flush(true);

        $response = service('response');
        $response->setHeader('Content-Type', 'text/event-stream');
        $response->setHeader('Cache-Control', 'no-cache');
        $response->setHeader('Connection', 'keep-alive');
        $response->setHeader('X-Accel-Buffering', 'no');
        $response->sendHeaders();

        $sendSseEvent = function(string $event, array $data): void {
            echo "event: {$event}\n";
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        };

        $user = $this->getCurrentUser();
        if (!$user) {
            $sendSseEvent('error', ['message' => 'Unauthorized. Please login.']);
            exit;
        }

        $campaignId = $this->request->getGet('id');
        if (!$campaignId) {
            $sendSseEvent('error', ['message' => 'Missing campaign ID']);
            exit;
        }

        $campaignCheck = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
        if (!$campaignCheck) {
            $sendSseEvent('error', ['message' => 'Forbidden. You do not have permission to run this campaign.']);
            exit;
        }

        $userDetails = $this->db->getUserById((int)$user['id']);
        if ($this->isPlanExpired($userDetails)) {
            $sendSseEvent('error', ['message' => "Plan expired. Your plan expired on {$userDetails['plan_expires_at']}. Please contact an administrator to renew."]);
            exit;
        }
        if ($userDetails && $userDetails['role'] !== 'admin' && $userDetails['plan_llm'] !== -1) {
            if ($userDetails['llm_usage'] >= $userDetails['plan_llm']) {
                $sendSseEvent('error', ['message' => "LLM/AI quota exceeded. You have used {$userDetails['llm_usage']} of {$userDetails['plan_llm']} allowed generations. Please upgrade your plan."]);
                exit;
            }
        }

        $llmProvider = $this->request->getGet('llm_provider');
        if ($llmProvider) {
            $this->db->updateCampaignLlmProvider((int)$campaignId, $llmProvider);
        } else {
            $llmProvider = $campaignCheck['llm_provider'] ?? 'gemini';
        }

        try {
            $llm = LlmFactory::create($this->db, $llmProvider, (int)$user['id']);
            $coordinator = new CoordinatorAgent($llm, $this->db);

            $coordinator->setLogCallback(function (string $agentName, string $action, string $logText) use ($sendSseEvent) {
                $sendSseEvent('log', [
                    'agent' => $agentName,
                    'action' => $action,
                    'message' => $logText,
                    'timestamp' => date('H:i:s')
                ]);
            });

            $finalContent = $coordinator->runCampaign((int)$campaignId);
            $this->db->logActivity((int)$user['id'], 'RUN_CAMPAIGN_GENERATOR', "Ran Campaign Generator for campaign '{$campaignCheck['title']}' (ID: {$campaignId})");

            $sendSseEvent('complete', [
                'message' => 'Campaign content generation completed successfully!',
                'final_content' => $finalContent
            ]);

        } catch (Exception $e) {
            $sendSseEvent('error', [
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
