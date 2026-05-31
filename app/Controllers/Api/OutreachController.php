<?php

namespace App\Controllers\Api;

use MarketingAgent\Service\SmtpService;
use MarketingAgent\Service\WhatsAppService;
use MarketingAgent\Service\SmsService;
use MarketingAgent\Service\Llm\LlmFactory;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class OutreachController extends BaseApiController
{
    public function send(): ResponseInterface
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user) {
                return $this->respondError('Unauthorized. Please login.', 401);
            }

            $input = $this->getJsonInput();
            $leadId = $input['lead_id'] ?? null;
            $type = $input['type'] ?? ''; // email, whatsapp, or sms

            if (!$leadId || !in_array($type, ['email', 'whatsapp', 'sms'])) {
                return $this->respondError('Missing or invalid lead_id or type.');
            }

            // Fetch Lead
            $lead = $this->db->getLead((int)$leadId);
            if (!$lead) {
                return $this->respondError('Lead not found.');
            }

            // Verify permissions and set email subject
            $subject = "Business Outreach";
            if ($lead['campaign_id'] !== null) {
                $campaign = $this->db->getCampaign((int)$lead['campaign_id'], (int)$user['id'], $user['role']);
                if (!$campaign) {
                    return $this->respondError('Forbidden. You do not have permission to access this lead campaign.', 403);
                }
                $subject = "Outreach regarding " . $campaign['title'];
            } else {
                if ($user['role'] !== 'admin' && (int)$lead['user_id'] !== (int)$user['id']) {
                    return $this->respondError('Forbidden. Access denied to this lead.', 403);
                }
            }

            // Check and Enforce Quotas
            $userDetails = $this->db->getUserById((int)$user['id']);
            if ($this->isPlanExpired($userDetails)) {
                return $this->respondError("Your limits are over. Please contact your service provider.", 403);
            }
            if ($userDetails) {
                if ($type === 'email') {
                    if ($userDetails['plan_email'] !== -1 && $userDetails['email_usage'] >= $userDetails['plan_email']) {
                        return $this->respondError("Your limits are over. Please contact your service provider.", 403);
                    }
                } else if ($type === 'whatsapp') {
                    if ($userDetails['plan_whatsapp'] !== -1 && $userDetails['whatsapp_usage'] >= $userDetails['plan_whatsapp']) {
                        return $this->respondError("Your limits are over. Please contact your service provider.", 403);
                    }
                } else if ($type === 'sms') {
                    if ($userDetails['plan_sms'] !== -1 && $userDetails['sms_usage'] >= $userDetails['plan_sms']) {
                        return $this->respondError("Your limits are over. Please contact your service provider.", 403);
                    }
                }
            }

            // Fetch Settings
            $settings = $this->db->getSettings();
            $statusText = '';

            if ($type === 'email') {
                $toEmail = $lead['email'];
                $body = $lead['email_draft'];

                if (empty($toEmail)) {
                    return $this->respondError('Recipient email is empty.');
                }

                $smtpHost = $settings['smtp_host'] ?? 'mock';
                $smtpPort = (int)($settings['smtp_port'] ?? 587);
                $smtpUser = $settings['smtp_user'] ?? '';
                $smtpPass = $settings['smtp_pass'] ?? '';
                $smtpFromEmail = $settings['smtp_from_email'] ?? 'outreach@example.com';
                $smtpFromName = $settings['smtp_from_name'] ?? 'Outreach Team';

                if ($smtpHost === 'mock' || empty($smtpHost)) {
                    $statusText = "[SIMULATED EMAIL SENT] to {$toEmail} via Mock SMTP Host.";
                } else {
                    SmtpService::send(
                        $smtpHost,
                        $smtpPort,
                        $smtpUser,
                        $smtpPass,
                        $smtpFromEmail,
                        $smtpFromName,
                        $toEmail,
                        $subject,
                        $body
                    );
                    $statusText = "Email outreach sent successfully to {$toEmail}!";
                }

                if ($userDetails) {
                    $this->db->incrementEmailUsage((int)$user['id']);
                }
            } else if ($type === 'whatsapp') {
                $toPhone = $lead['whatsapp'];
                $body = $lead['whatsapp_draft'];

                if (empty($toPhone)) {
                    return $this->respondError('Recipient WhatsApp number is empty.');
                }

                $waToken = $settings['whatsapp_token'] ?? 'mock';
                $waPhoneId = $settings['whatsapp_phone_id'] ?? '';

                if ($waToken === 'mock' || empty($waToken) || empty($waPhoneId)) {
                    $statusText = "[SIMULATED WHATSAPP SENT] to {$toPhone} via Mock WhatsApp Gate.";
                } else {
                    WhatsAppService::send($waToken, $waPhoneId, $toPhone, $body);
                    $statusText = "WhatsApp outreach sent successfully to {$toPhone}!";
                }

                if ($userDetails) {
                    $this->db->incrementWhatsappUsage((int)$user['id']);
                }
            } else if ($type === 'sms') {
                $toPhone = $lead['mobile'] ?? $lead['whatsapp'] ?? '';
                $body = $lead['sms_draft'] ?? '';

                if (empty($toPhone)) {
                    return $this->respondError('Recipient mobile/phone number is empty.');
                }
                if (empty($body)) {
                    return $this->respondError('SMS draft outreach message is empty.');
                }

                $smsService = new SmsService($this->db);
                $smsService->sendSms($toPhone, $body);

                if (!$smsService->isConfigured()) {
                    $statusText = "[SIMULATED SMS SENT] to {$toPhone} via Mock SMS Gateway.";
                } else {
                    $statusText = "SMS outreach sent successfully to {$toPhone}!";
                }

                if ($userDetails) {
                    $this->db->incrementSmsUsage((int)$user['id']);
                }
            }

            // Update lead status to OUTREACHED
            $this->db->updateLeadStatus((int)$leadId, 'OUTREACHED');

            // Log activity
            $this->db->logActivity((int)$user['id'], 'SEND_OUTREACH', "Sent " . strtoupper($type) . " outreach to lead '" . $lead['company_name'] . "'");

            return $this->respondSuccess(['message' => $statusText]);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }

    public function generate(): ResponseInterface
    {
        // Allow LLM calls to run without PHP execution time limit
        set_time_limit(0);
        @ini_set('max_execution_time', '0');

        try {
            $user = $this->getCurrentUser();
            if (!$user) {
                return $this->respondError('Unauthorized. Please login.', 401);
            }

            $input = $this->getJsonInput();
            $leadId = $input['lead_id'] ?? null;
            $llmProvider = $input['llm_provider'] ?? null;
            $language = $input['language'] ?? '';

            if (!$leadId) {
                return $this->respondError('Missing lead_id parameter.');
            }

            // Fetch Lead
            $lead = $this->db->getLead((int)$leadId);
            if (!$lead) {
                return $this->respondError('Lead not found.');
            }

            // Check Permissions
            $campaignId = $lead['campaign_id'];
            if (!$campaignId) {
                return $this->respondError('This contact is not associated with any campaign.');
            }

            $campaign = $this->db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
            if (!$campaign) {
                return $this->respondError('Forbidden. Access denied to this lead/campaign.', 403);
            }

            // Get product and audience details
            $productDesc = $campaign['product_description'] ?? '';
            $audience = $campaign['target_audience'] ?? '';
            $outreachLanguage = empty(trim($language)) ? ($campaign['language'] ?? 'English') : $language;

            // Contact Details
            $companyName = $lead['company_name'] ?? 'Target Corp';
            $contactName = $lead['contact_name'] ?? 'Decision Maker';
            $industry = $lead['industry'] ?? 'General';
            $postalAddress = $lead['postal_address'] ?? 'N/A';
            $leadDesc = $lead['description'] ?? 'N/A';

            $userDetails = $this->db->getUserById((int)$user['id']);
            if ($this->isPlanExpired($userDetails)) {
                return $this->respondError("Your limits are over. Please contact your service provider.", 403);
            }
            if ($userDetails && $userDetails['plan_llm'] !== -1 && $userDetails['llm_usage'] >= $userDetails['plan_llm']) {
                return $this->respondError("Your limits are over. Please contact your service provider.", 403);
            }

            // Create LLM instance
            $selectedProvider = $llmProvider ?: ($campaign['llm_provider'] ?? 'gemini');
            $llm = LlmFactory::create($this->db, $selectedProvider, (int)$user['id']);

            $systemPrompt = "You are a high-performing Sales Development Representative (SDR) and outbound marketing expert. 
Your goal is to write personalized outreach drafts for a prospect across four channels: Email, WhatsApp, SMS, and Phone Call.

IMPORTANT: The outreach drafts (`email_draft`, `whatsapp_draft`, `sms_draft`, and `calls_draft`) must be written entirely in {$outreachLanguage}.

You will receive details about the product/service and target audience, along with the prospect's profile.

You MUST respond with ONLY a raw JSON object containing exactly the following keys:
{
  \"email_draft\": \"A personalized, short, compelling outbound sales email written in {$outreachLanguage}. It should have a catchy Subject: line, greet them by name, state the problem they likely face, introduce the product, and end with a soft call-to-action.\",
  \"whatsapp_draft\": \"A short, friendly, direct WhatsApp message written in {$outreachLanguage}. Use emojis, write conversationally, highlight a single key benefit, and ask a low-friction question like 'Would you be open to a 2-minute chat next week?'\",
  \"sms_draft\": \"A very short, punchy SMS outreach message written in {$outreachLanguage}. Must be strictly under 160 characters, direct, friendly, prompting a quick reply.\",
  \"calls_draft\": \"A concise, professional phone call script written in {$outreachLanguage}. Include: (1) Opening greeting and introduction, (2) Purpose of the call and value proposition, (3) 1-2 qualifying questions, (4) Handling a common objection, (5) Closing with a clear CTA (meeting/demo/callback). Keep it under 90 seconds when spoken.\"
}

Do not include markdown code block formatting (like ```json). Just the raw JSON.
Ensure you escape quotes properly.";

            $userPrompt = "PRODUCT/SERVICE TO SELL:
Description: {$productDesc}
Target Audience: {$audience}

PROSPECT PROFILE:
Company: {$companyName}
Contact Person: {$contactName}
Industry: {$industry}
Postal Address: {$postalAddress}
Description/Notes: {$leadDesc}";

            $response = $llm->generate($systemPrompt, $userPrompt, 0.7);

            // Clean LLM response — strip markdown code fences if present
            $response = trim($response);
            // Strip ```json ... ``` or ``` ... ``` wrappers
            $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
            $response = preg_replace('/\s*```\s*$/', '', $response);
            $response = trim($response);

            $data = json_decode($response, true);

            // If JSON decode failed, try regex extraction as fallback
            if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                $data = [];
                if (preg_match('/"email_draft"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $response, $m)) {
                    $data['email_draft'] = stripcslashes($m[1]);
                }
                if (preg_match('/"whatsapp_draft"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $response, $m)) {
                    $data['whatsapp_draft'] = stripcslashes($m[1]);
                }
                if (preg_match('/"sms_draft"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $response, $m)) {
                    $data['sms_draft'] = stripcslashes($m[1]);
                }
            }

            if (empty($data) || (!isset($data['email_draft']) && !isset($data['whatsapp_draft']) && !isset($data['sms_draft']) && !isset($data['calls_draft']))) {
                throw new Exception("Invalid JSON output structure returned by the AI Model. Raw response was: " . substr($response, 0, 500));
            }

            // Ensure draft values are always strings (never arrays)
            $emailDraft = is_array($data['email_draft'] ?? '') ? implode("\n", $data['email_draft']) : (string)($data['email_draft'] ?? '');
            $whatsappDraft = is_array($data['whatsapp_draft'] ?? '') ? implode("\n", $data['whatsapp_draft']) : (string)($data['whatsapp_draft'] ?? '');
            $smsDraft = is_array($data['sms_draft'] ?? '') ? implode("\n", $data['sms_draft']) : (string)($data['sms_draft'] ?? '');
            $callsDraft = is_array($data['calls_draft'] ?? '') ? implode("\n", $data['calls_draft']) : (string)($data['calls_draft'] ?? '');

            // Save generated drafts to the database
            $this->db->updateLeadDrafts((int)$leadId, $emailDraft, $whatsappDraft, $smsDraft, $callsDraft);

            // Log user activity
            $this->db->logActivity((int)$user['id'], 'GENERATE_AI_OUTREACH', "Generated AI outreach drafts for lead '{$companyName}' (ID: {$leadId})");

            return $this->respondSuccess([
                'email_draft' => $emailDraft,
                'whatsapp_draft' => $whatsappDraft,
                'sms_draft' => $smsDraft,
                'calls_draft' => $callsDraft
            ]);
        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
}
