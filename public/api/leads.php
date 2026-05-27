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
        if (!$input) {
            throw new Exception("Invalid JSON request body.");
        }
        
        // Handle manual lead addition
        if (isset($input['action']) && $input['action'] === 'add_manual') {
            $campaignId = isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : null;
            $companyName = trim($input['company_name'] ?? '');
            
            if (empty($companyName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company Name is required.']);
                exit;
            }

            // Verify permission on campaign if provided
            if ($campaignId !== null) {
                $campaign = $db->getCampaign($campaignId, (int)$user['id'], $user['role']);
                if (!$campaign) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to this campaign.']);
                    exit;
                }
            }

            // Enforce plan limit for lead numbers
            $userDetails = $db->getUserById((int)$user['id']);
            if ($user['role'] !== 'admin' && $userDetails['plan_leads'] !== -1) {
                $leadCount = $db->getUserLeadCount((int)$user['id']);
                if ($leadCount >= $userDetails['plan_leads']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => "Plan limit reached. You can store at most {$userDetails['plan_leads']} leads in your CRM. Please contact an administrator."]);
                    exit;
                }
            }

            $contactName = trim($input['contact_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $whatsapp = trim($input['whatsapp_number'] ?? $input['whatsapp'] ?? '');
            $mobile = trim($input['mobile'] ?? '');
            $source = trim($input['source'] ?? 'manual');
            $industry = trim($input['industry'] ?? '');
            $description = trim($input['description'] ?? '');
            $score = trim($input['score'] ?? 'MEDIUM');
            $reasoning = trim($input['reasoning'] ?? 'Manually added');
            $emailDraft = trim($input['email_draft'] ?? '');
            $whatsappDraft = trim($input['whatsapp_draft'] ?? '');
            $smsDraft = trim($input['sms_draft'] ?? '');
            $postalAddress = trim($input['postal_address'] ?? '');

            $leadId = $db->saveLead(
                $campaignId,
                $companyName,
                $contactName,
                $email,
                $whatsapp,
                $industry,
                $description,
                $score,
                $reasoning,
                $emailDraft,
                $whatsappDraft,
                (int)$user['id'],
                $source,
                $mobile,
                $smsDraft,
                $postalAddress
            );

            // Log activity
            $db->logActivity((int)$user['id'], 'CREATE_MANUAL_LEAD', "Manually added lead '{$companyName}' (ID: {$leadId})");

            echo json_encode(['success' => true, 'message' => 'Lead manually added successfully.', 'lead_id' => $leadId]);
            exit;
        }

        // Handle manual lead editing/updating
        if (isset($input['action']) && $input['action'] === 'edit_manual') {
            $leadId = isset($input['lead_id']) ? (int)$input['lead_id'] : null;
            if (!$leadId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing lead ID.']);
                exit;
            }

            // Verify permission on existing lead
            $lead = $db->getLead($leadId);
            if (!$lead) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Lead not found.']);
                exit;
            }

            $campaign = null;
            if ($lead['campaign_id'] !== null) {
                $campaign = $db->getCampaign((int)$lead['campaign_id'], null, 'admin');
            }

            // Normal users cannot modify lead metadata on campaign/assigned leads unless they own the campaign.
            if ($user['role'] !== 'admin') {
                if ($lead['campaign_id'] !== null) {
                    if (!$campaign || (int)$campaign['user_id'] !== (int)$user['id']) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to modify lead data of this campaign.']);
                        exit;
                    }
                } else {
                    if ((int)$lead['user_id'] !== (int)$user['id']) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to modify this lead.']);
                        exit;
                    }
                }
            }

            $campaignId = isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : null;
            $companyName = trim($input['company_name'] ?? '');
            
            if (empty($companyName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Company Name is required.']);
                exit;
            }

            // Verify permission on new campaign if it's changing
            if ($campaignId !== null && ($lead['campaign_id'] === null || $campaignId !== (int)$lead['campaign_id'])) {
                $campaign = $db->getCampaign($campaignId, (int)$user['id'], $user['role']);
                if (!$campaign) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to this campaign.']);
                    exit;
                }
            }

            $contactName = trim($input['contact_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $whatsapp = trim($input['whatsapp_number'] ?? $input['whatsapp'] ?? '');
            $mobile = trim($input['mobile'] ?? '');
            $source = trim($input['source'] ?? 'manual');
            $industry = trim($input['industry'] ?? '');
            $description = trim($input['description'] ?? '');
            $score = trim($input['score'] ?? 'MEDIUM');
            $reasoning = trim($input['reasoning'] ?? 'Manually added');
            $emailDraft = trim($input['email_draft'] ?? '');
            $whatsappDraft = trim($input['whatsapp_draft'] ?? '');
            $smsDraft = trim($input['sms_draft'] ?? '');
            $postalAddress = trim($input['postal_address'] ?? '');

            // Admin can reassign lead owner; non-admin users cannot change ownership
            $newOwnerId = null;
            if ($user['role'] === 'admin' && isset($input['owner_user_id']) && $input['owner_user_id'] !== '') {
                $newOwnerId = (int)$input['owner_user_id'];
            }

            $db->updateLead(
                $leadId,
                $campaignId,
                $companyName,
                $contactName,
                $email,
                $whatsapp,
                $industry,
                $description,
                $score,
                $reasoning,
                $emailDraft,
                $whatsappDraft,
                $source,
                $mobile,
                $newOwnerId,
                $smsDraft,
                $postalAddress
            );

            // Log activity
            $db->logActivity((int)$user['id'], 'UPDATE_LEAD', "Updated lead '{$companyName}' (ID: {$leadId})");

            echo json_encode(['success' => true, 'message' => 'Lead updated successfully.']);
            exit;
        }

        // Handle outreach draft manual edits
        if (isset($input['action']) && $input['action'] === 'update_drafts') {
            $leadId = $input['lead_id'] ?? null;
            $emailDraft = $input['email_draft'] ?? '';
            $whatsappDraft = $input['whatsapp_draft'] ?? '';
            $smsDraft = $input['sms_draft'] ?? '';

            if (!$leadId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing lead_id']);
                exit;
            }

            // Verify permission
            $lead = $db->getLead((int)$leadId);
            if (!$lead) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Lead not found.']);
                exit;
            }

            $campaign = null;
            if ($lead['campaign_id'] !== null) {
                $campaign = $db->getCampaign((int)$lead['campaign_id'], null, 'admin');
            }

            // Lead owners (assignee or campaign owner) can update drafts.
            $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);

            if ($user['role'] !== 'admin' && !$isLeadOwner) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to update drafts for this lead.']);
                exit;
            }

            $db->updateLeadDrafts((int)$leadId, $emailDraft, $whatsappDraft, $smsDraft);
            echo json_encode(['success' => true, 'message' => 'Lead outreach drafts updated successfully.']);
            exit;
        }

        // Update lead status
        $leadId = $input['lead_id'] ?? null;
        $status = $input['status'] ?? null;

        if (!$leadId || !$status) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing lead_id or status']);
            exit;
        }

        $lead = $db->getLead((int)$leadId);
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lead not found.']);
            exit;
        }

        $campaign = null;
        if ($lead['campaign_id'] !== null) {
            $campaign = $db->getCampaign((int)$lead['campaign_id'], null, 'admin');
        }

        // Lead owners (assignee or campaign owner) can update status.
        $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);

        if ($user['role'] !== 'admin' && !$isLeadOwner) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to update status for this lead.']);
            exit;
        }

        $db->updateLeadStatus((int)$leadId, strtoupper($status));
        echo json_encode(['success' => true, 'message' => 'Lead status updated successfully.']);

    } elseif ($method === 'DELETE') {
        $leadId = $_GET['id'] ?? null;
        if (!$leadId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing lead ID']);
            exit;
        }

        $lead = $db->getLead((int)$leadId);
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lead not found.']);
            exit;
        }

        $campaign = null;
        if ($lead['campaign_id'] !== null) {
            $campaign = $db->getCampaign((int)$lead['campaign_id'], null, 'admin');
        }

        // Normal users cannot delete lead data of campaign unless they own the campaign.
        if ($user['role'] !== 'admin') {
            if ($lead['campaign_id'] !== null) {
                if (!$campaign || (int)$campaign['user_id'] !== (int)$user['id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to delete lead data of this campaign.']);
                    exit;
                }
            } else {
                if ((int)$lead['user_id'] !== (int)$user['id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to delete this lead.']);
                    exit;
                }
            }
        }

        $db->deleteLead((int)$leadId);

        // Log activity
        $db->logActivity((int)$user['id'], 'DELETE_LEAD', "Deleted lead '{$lead['company_name']}'");

        echo json_encode(['success' => true, 'message' => 'Lead deleted successfully.']);

    } else {
        // GET Request
        $campaignId = isset($_GET['campaign_id']) && $_GET['campaign_id'] !== '' ? (int)$_GET['campaign_id'] : null;

        if ($campaignId !== null) {
            $campaign = $db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
            if (!$campaign) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to this campaign.']);
                exit;
            }
            $leads = $db->getLeads((int)$campaignId);
        } else {
            // General fetch across all campaigns or manual leads
            $leads = $db->getLeads(null, (int)$user['id'], $user['role']);
        }

        echo json_encode([
            'success' => true,
            'leads' => $leads
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
