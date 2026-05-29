<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Agent\LeadAgent;
use MarketingAgent\Tool\LeadScraperTool;
use Exception;

class LeadController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $campaignId = $this->request->getGet('campaign_id');
        $leadId = $this->request->getGet('id');

        if ($leadId) {
            $lead = $this->db->getLead((int)$leadId);
            if (!$lead) {
                return $this->respondError('Lead not found', 404);
            }
            return $this->respondSuccess(['lead' => $lead]);
        }

        if ($campaignId !== null && $campaignId !== '') {
            $campaign = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
            if (!$campaign) {
                return $this->respondError('Forbidden. Access denied to this campaign.', 403);
            }
            $leads = $this->db->getLeads((int)$campaignId);
        } else {
            $leads = $this->db->getLeads(null, (int)$user['id'], $user['role']);
        }

        return $this->respondSuccess(['leads' => $leads]);
    }

    public function create(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $input = $this->getJsonInput();

        // Handle manual lead addition
        if (isset($input['action']) && $input['action'] === 'add_manual') {
            $campaignId = isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : null;
            $companyName = trim($input['company_name'] ?? '');
            
            if (empty($companyName)) {
                return $this->respondError('Company Name is required.');
            }

            if ($campaignId !== null) {
                $campaign = $this->db->getCampaign($campaignId, (int)$user['id'], $user['role']);
                if (!$campaign) {
                    return $this->respondError('Forbidden. Access denied to this campaign.', 403);
                }
            }

            $userDetails = $this->db->getUserById((int)$user['id']);
            if ($user['role'] !== 'admin' && $userDetails['plan_leads'] !== -1) {
                $leadCount = $this->db->getUserLeadCount((int)$user['id']);
                if ($leadCount >= $userDetails['plan_leads']) {
                    return $this->respondError("Plan limit reached. You can store at most {$userDetails['plan_leads']} leads in your CRM. Please contact an administrator.", 403);
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

            $leadId = $this->db->saveLead(
                $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, (int)$user['id'], $source, $mobile, $smsDraft, $postalAddress
            );

            $this->db->logActivity((int)$user['id'], 'CREATE_MANUAL_LEAD', "Manually added lead '{$companyName}' (ID: {$leadId})");
            return $this->respondSuccess(['lead_id' => $leadId], 'Lead manually added successfully.');
        }

        // Handle manual lead editing/updating
        if (isset($input['action']) && $input['action'] === 'edit_manual') {
            $leadId = isset($input['lead_id']) ? (int)$input['lead_id'] : null;
            if (!$leadId) {
                return $this->respondError('Missing lead ID.');
            }

            $lead = $this->db->getLead($leadId);
            if (!$lead) {
                return $this->respondError('Lead not found.', 404);
            }

            $campaign = null;
            if ($lead['campaign_id'] !== null) {
                $campaign = $this->db->getCampaign((int)$lead['campaign_id'], null, 'admin');
            }

            $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);
            if ($user['role'] !== 'admin' && !$isLeadOwner) {
                return $this->respondError('Forbidden. Access denied to modify this lead.', 403);
            }

            $campaignId = isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : null;
            $companyName = trim($input['company_name'] ?? '');
            
            if (empty($companyName)) {
                return $this->respondError('Company Name is required.');
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

            $newOwnerId = null;
            if ($user['role'] === 'admin' && isset($input['owner_user_id']) && $input['owner_user_id'] !== '') {
                $newOwnerId = (int)$input['owner_user_id'];
            }

            $this->db->updateLead(
                $leadId, $campaignId, $companyName, $contactName, $email, $whatsapp,
                $industry, $description, $score, $reasoning, $emailDraft,
                $whatsappDraft, $source, $mobile, $newOwnerId, $smsDraft, $postalAddress
            );

            $this->db->logActivity((int)$user['id'], 'UPDATE_LEAD', "Updated lead '{$companyName}' (ID: {$leadId})");
            return $this->respondSuccess([], 'Lead updated successfully.');
        }

        // Handle outreach draft manual edits
        if (isset($input['action']) && $input['action'] === 'update_drafts') {
            $leadId = $input['lead_id'] ?? null;
            $emailDraft = $input['email_draft'] ?? '';
            $whatsappDraft = $input['whatsapp_draft'] ?? '';
            $smsDraft = $input['sms_draft'] ?? '';

            if (!$leadId) {
                return $this->respondError('Missing lead_id');
            }

            $lead = $this->db->getLead((int)$leadId);
            if (!$lead) {
                return $this->respondError('Lead not found.', 404);
            }

            $campaign = null;
            if ($lead['campaign_id'] !== null) {
                $campaign = $this->db->getCampaign((int)$lead['campaign_id'], null, 'admin');
            }

            $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);
            if ($user['role'] !== 'admin' && !$isLeadOwner) {
                return $this->respondError('Forbidden. Access denied to update drafts.', 403);
            }

            $this->db->updateLeadDrafts((int)$leadId, $emailDraft, $whatsappDraft, $smsDraft);
            return $this->respondSuccess([], 'Lead outreach drafts updated successfully.');
        }

        // Update lead status
        $leadId = $input['lead_id'] ?? null;
        $status = $input['status'] ?? null;

        if (!$leadId || !$status) {
            return $this->respondError('Missing lead_id or status');
        }

        $lead = $this->db->getLead((int)$leadId);
        if (!$lead) {
            return $this->respondError('Lead not found.', 404);
        }

        $campaign = null;
        if ($lead['campaign_id'] !== null) {
            $campaign = $this->db->getCampaign((int)$lead['campaign_id'], null, 'admin');
        }

        $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);
        if ($user['role'] !== 'admin' && !$isLeadOwner) {
            return $this->respondError('Forbidden. Access denied to update status.', 403);
        }

        $this->db->updateLeadStatus((int)$leadId, strtoupper($status));
        return $this->respondSuccess([], 'Lead status updated successfully.');
    }

    public function delete(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $leadId = $this->request->getGet('id');

        if (!$leadId) {
            return $this->respondError('Missing lead ID');
        }

        $lead = $this->db->getLead((int)$leadId);
        if (!$lead) {
            return $this->respondError('Lead not found.', 404);
        }

        $campaign = null;
        if ($lead['campaign_id'] !== null) {
            $campaign = $this->db->getCampaign((int)$lead['campaign_id'], null, 'admin');
        }

        $isLeadOwner = (int)$lead['user_id'] === (int)$user['id'] || ($campaign && (int)$campaign['user_id'] === (int)$user['id']);
        if ($user['role'] !== 'admin' && !$isLeadOwner) {
            return $this->respondError('Forbidden. Access denied to delete this lead.', 403);
        }

        $this->db->deleteLead((int)$leadId);
        $this->db->logActivity((int)$user['id'], 'DELETE_LEAD', "Deleted lead '{$lead['company_name']}'");

        return $this->respondSuccess([], 'Lead deleted successfully.');
    }

    public function runLeads()
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
        $sourceUrl = $this->request->getGet('source_url') ?? '';
        $language = $this->request->getGet('language') ?? '';

        if (!$campaignId) {
            $sendSseEvent('error', ['message' => 'Missing campaign ID']);
            exit;
        }

        $campaignCheck = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
        if (!$campaignCheck) {
            $sendSseEvent('error', ['message' => 'Forbidden. You do not have permission to access this campaign.']);
            exit;
        }

        $userDetails = $this->db->getUserById((int)$user['id']);
        if ($user['role'] !== 'admin' && $userDetails['plan_leads'] !== -1) {
            $leadCount = $this->db->getUserLeadCount((int)$user['id']);
            if ($leadCount >= $userDetails['plan_leads']) {
                $sendSseEvent('error', ['message' => "Plan limit reached. You can store at most {$userDetails['plan_leads']} leads in your CRM. Please contact an administrator."]);
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
            $leadAgent = new LeadAgent($llm, $this->db);

            $leadAgent->setLogCallback(function (string $agentName, string $action, string $logText) use ($sendSseEvent) {
                $sendSseEvent('log', [
                    'agent' => $agentName,
                    'action' => $action,
                    'message' => $logText,
                    'timestamp' => date('H:i:s')
                ]);
            });

            $leads = $leadAgent->generateAndQualifyLeads((int)$campaignId, $sourceUrl, $language);
            $this->db->logActivity((int)$user['id'], 'RUN_SDR_FINDER', "Ran SDR Lead Finder on campaign '{$campaignCheck['title']}' (ID: {$campaignId}), found " . count($leads) . " leads.");

            $sendSseEvent('complete', [
                'message' => 'Leads generated and qualified successfully!',
                'leads' => $leads
            ]);

        } catch (Exception $e) {
            $sendSseEvent('error', [
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    public function runScraper()
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

        $sourceType = $this->request->getGet('source_type') ?? 'default';
        $location = $this->request->getGet('location') ?? '';
        $keywords = $this->request->getGet('keywords') ?? '';
        $sourceUrl = $this->request->getGet('source_url') ?? '';
        $llmProvider = $this->request->getGet('llm_provider') ?? 'gemini';
        $campaignId = $this->request->getGet('campaign_id');

        $campaign = null;
        if ($campaignId && is_numeric($campaignId)) {
            $campaign = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
        }

        $productDesc = $campaign ? $campaign['product_description'] : 'General Marketing Services';
        $audience = $campaign ? $campaign['target_audience'] : 'Business Owners';
        $outreachLanguage = $campaign ? ($campaign['language'] ?? 'English') : 'English';

        try {
            $llm = LlmFactory::create($this->db, $llmProvider, (int)$user['id']);
            $scraper = new LeadScraperTool($llm);

            $scraper->setLogCallback(function (string $action, string $message) use ($sendSseEvent) {
                $sendSseEvent('log', [
                    'agent' => 'LeadsScraper',
                    'action' => $action,
                    'message' => $message,
                    'timestamp' => date('H:i:s')
                ]);
            });

            // Target location formatting
            $target = $sourceUrl;
            if ($sourceType === 'default' || empty($target)) {
                $target = "Google Maps Business Listing search for '{$keywords}'";
                if (!empty($location)) {
                    $target .= " in {$location}";
                }
            }

            $leads = $scraper->scrapeLeads($productDesc, $audience, $target);

            $qualifiedLeads = [];
            foreach ($leads as $index => $lead) {
                $sendSseEvent('log', [
                    'agent' => 'LeadsScraper',
                    'action' => 'QUALIFYING_LEAD',
                    'message' => "Filtering & qualifying prospect " . ($index + 1) . "/" . count($leads) . ": " . ($lead['company_name'] ?? 'Target Corp'),
                    'timestamp' => date('H:i:s')
                ]);

                $companyName = $lead['company_name'] ?? 'Target Corp';
                $contactName = $lead['contact_name'] ?? 'Decision Maker';
                $postalAddress = $lead['postal_address'] ?? '';
                $industry = $lead['industry'] ?? 'General';
                $rawDesc = $lead['description'] ?? '';

                $systemPrompt = "You are a high-performing Sales Development Representative (SDR) and outbound marketing expert.
Your goal is to analyze a raw scraped business prospect profile, filter/summarize their description to highlight relevant requirements/notes, score their fit against our product's Ideal Customer Profile (ICP), and generate personalized outreach drafts.

IMPORTANT: The outreach drafts (`email_draft`, `whatsapp_draft`, and `sms_draft`) must be written entirely in {$outreachLanguage}. Keep the qualification reasoning and description summary in English.

You MUST respond with ONLY a raw JSON object containing exactly the following keys:
{
  \"description\": \"A filtered, professional 1-2 sentence summary of what this business does, their key requirements, or relevant notes regarding their fit for our product.\",
  \"score\": \"HIGH\" or \"MEDIUM\" or \"LOW\",
  \"reasoning\": \"A 2-3 sentence explanation (SDR AI Qualification Reasoning) of why they are scored this way and how the product fits their needs.\",
  \"email_draft\": \"A personalized, short outbound sales email written in {$outreachLanguage}. Catchy Subject: line, greet them, introduce our product, end with a soft call-to-action.\",
  \"whatsapp_draft\": \"A short, friendly WhatsApp message written in {$outreachLanguage}. Use emojis, convos style, highlight a key benefit, ask a low-friction question.\",
  \"sms_draft\": \"A punchy SMS outreach message written in {$outreachLanguage} under 160 characters.\"
}";

                $userPrompt = "PRODUCT TO SELL:
Product Description: {$productDesc}
Target ICP: {$audience}

PROSPECT PROFILE:
Company: {$companyName}
Contact Person: {$contactName}
Address: {$postalAddress}
Industry: {$industry}
Scraped Metadata/Description: {$rawDesc}";

                try {
                    $response = $llm->generate($systemPrompt, $userPrompt, 0.7);
                    $response = trim($response);
                    $firstBracket = strpos($response, '{');
                    $lastBracket = strrpos($response, '}');
                    if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
                        $jsonString = substr($response, $firstBracket, $lastBracket - $firstBracket + 1);
                        $qualification = json_decode($jsonString, true);
                    } else {
                        if (strpos($response, '```') === 0) {
                            $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
                            $response = trim($response);
                        }
                        $qualification = json_decode($response, true);
                    }

                    if (!$qualification || !isset($qualification['score'])) {
                        throw new Exception("Invalid JSON output from LLM.");
                    }

                    $lead['description'] = $qualification['description'] ?? $rawDesc;
                    $lead['score'] = strtoupper($qualification['score']);
                    $lead['reasoning'] = $qualification['reasoning'] ?? 'Fits basic industry parameters.';
                    $lead['email_draft'] = $qualification['email_draft'] ?? '';
                    $lead['whatsapp_draft'] = $qualification['whatsapp_draft'] ?? '';
                    $lead['sms_draft'] = $qualification['sms_draft'] ?? '';

                } catch (Exception $e) {
                    $lead['score'] = 'MEDIUM';
                    $lead['reasoning'] = "Lead belongs to {$industry} which aligns with our target segment. Good fit for initial cold testing.";
                    $lead['email_draft'] = "Subject: Quick question regarding workflow efficiency at {$companyName}\n\nHi {$contactName},\n\nWould you be open to a brief call?";
                    $lead['whatsapp_draft'] = "Hi {$contactName}! 👋 Hope your day is going well. Open to a quick chat about automating content creation?";
                    $lead['sms_draft'] = "Hi {$contactName}, open to a quick call about automating content creation? - Outreach Team";
                }

                $lead['campaign_id'] = $campaignId;
                $qualifiedLeads[] = $lead;
            }
            $leads = $qualifiedLeads;

            $this->db->logActivity((int)$user['id'], 'RUN_STANDALONE_SCRAPER', "Ran standalone scraper for target '{$target}', gathered " . count($leads) . " prospects.");

            $sendSseEvent('complete', [
                'message' => 'Standalone scraping completed successfully!',
                'leads' => $leads
            ]);

        } catch (Exception $e) {
            $sendSseEvent('error', [
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
