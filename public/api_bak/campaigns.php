<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;

try {
    $db = new DatabaseService();
    $user = AuthService::getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Handle campaign sharing (Admin only)
        if (isset($input['action']) && $input['action'] === 'share') {
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden. Only admins can share campaigns.']);
                exit;
            }
            if (empty($input['campaign_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing campaign_id.']);
                exit;
            }
            $campaign = $db->getCampaign((int)$input['campaign_id'], (int)$user['id'], $user['role']);
            if (!$campaign) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Campaign not found.']);
                exit;
            }
            $userIds = array_filter(array_map('intval', $input['user_ids'] ?? []));
            $db->shareCampaign((int)$input['campaign_id'], $userIds);
            $db->logActivity((int)$user['id'], 'SHARE_CAMPAIGN', "Shared campaign '{$campaign['title']}' (ID: {$input['campaign_id']}) with " . count($userIds) . " user(s)");
            echo json_encode(['success' => true, 'message' => 'Campaign shared successfully.']);
            exit;
        }

        // Handle final content manual edit updates
        if (isset($input['action']) && $input['action'] === 'update_content') {
            if (empty($input['id']) || !isset($input['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields: id, content']);
                exit;
            }
            
            // Verify permission
            $campaign = $db->getCampaign((int)$input['id'], (int)$user['id'], $user['role']);
            if (!$campaign) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to modify this campaign.']);
                exit;
            }

            $db->updateCampaignContent((int)$input['id'], $input['content'], 'COMPLETED');
            echo json_encode([
                'success' => true,
                'message' => 'Campaign content updated successfully.'
            ]);
            exit;
        }

        if (empty($input['title']) || empty($input['product_description']) || empty($input['target_audience']) || empty($input['channel'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields: title, product_description, target_audience, channel']);
            exit;
        }

        // Plan limits check
        $userDetails = $db->getUserById((int)$user['id']);
        if ($user['role'] !== 'admin' && $userDetails['plan_campaigns'] !== -1) {
            $campaignCount = $db->getUserCampaignCount((int)$user['id']);
            if ($campaignCount >= $userDetails['plan_campaigns']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => "Plan limit reached. You can create at most {$userDetails['plan_campaigns']} campaigns. Please contact an administrator."]);
                exit;
            }
        }

        $crawlType = $input['crawl_type'] ?? 'none';
        $crawlTarget = $input['crawl_target'] ?? '';
        $language = $input['language'] ?? 'English';

        $campaignId = $db->createCampaign(
            trim($input['title']),
            trim($input['product_description']),
            trim($input['target_audience']),
            trim($input['channel']),
            trim($crawlType),
            trim($crawlTarget),
            trim($language),
            (int)$user['id']
        );

        // Log activity
        $db->logActivity((int)$user['id'], 'CREATE_CAMPAIGN', "Created campaign '" . trim($input['title']) . "' (ID: {$campaignId})");

        echo json_encode([
            'success' => true,
            'message' => 'Campaign created successfully.',
            'campaign_id' => $campaignId
        ]);

    } elseif ($method === 'DELETE') {
        $campaignId = $_GET['id'] ?? null;
        if (!$campaignId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing campaign ID']);
            exit;
        }

        // Verify DELETE is only allowed by campaign owner or admin
        $campaign = $db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
        if (!$campaign) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to delete this campaign.']);
            exit;
        }
        // Non-admin users can only delete campaigns they own (not shared ones)
        if ($user['role'] !== 'admin' && (int)$campaign['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. You cannot delete a campaign shared with you.']);
            exit;
        }

        $db->deleteCampaign((int)$campaignId);

        // Log activity
        $db->logActivity((int)$user['id'], 'DELETE_CAMPAIGN', "Deleted campaign '{$campaign['title']}' (ID: {$campaignId})");

        echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully.']);

    } else {
        // GET Request
        $campaignId = $_GET['id'] ?? null;

        if ($campaignId) {
            $campaign = $db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
            if (!$campaign) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Campaign not found or access denied']);
                exit;
            }

            // Fetch logs and leads too
            $logs = $db->getLogs((int)$campaignId);
            $leads = $db->getLeads((int)$campaignId);

            // Fetch shares for admin
            $shares = [];
            if ($user['role'] === 'admin') {
                $shares = $db->getCampaignShares((int)$campaignId);
            }

            echo json_encode([
                'success' => true,
                'campaign' => $campaign,
                'logs' => $logs,
                'leads' => $leads,
                'shares' => $shares
            ]);
        } else {
            $campaigns = $db->getCampaigns((int)$user['id'], $user['role']);
            echo json_encode([
                'success' => true,
                'campaigns' => $campaigns
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
