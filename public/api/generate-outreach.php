<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\Llm\LlmFactory;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed.");
    }

    // Check Authentication
    $user = AuthService::getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON request body.");
    }

    $leadId = $input['lead_id'] ?? null;
    $llmProvider = $input['llm_provider'] ?? null;
    $language = $input['language'] ?? '';

    if (!$leadId) {
        throw new Exception("Missing lead_id parameter.");
    }

    $db = new DatabaseService();

    // Fetch Lead
    $lead = $db->getLead((int)$leadId);
    if (!$lead) {
        throw new Exception("Lead not found.");
    }

    // Check Permissions
    $campaignId = $lead['campaign_id'];
    if (!$campaignId) {
        throw new Exception("This contact is not associated with any campaign.");
    }

    $campaign = $db->getCampaign((int)$campaignId, (int)$user['id'], $user['role']);
    if (!$campaign) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Access denied to this lead/campaign.']);
        exit;
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

    // Create LLM instance
    $selectedProvider = $llmProvider ?: ($campaign['llm_provider'] ?? 'gemini');
    $llm = LlmFactory::create($db, $selectedProvider, (int)$user['id']);

    $systemPrompt = "You are a high-performing Sales Development Representative (SDR) and outbound marketing expert. 
Your goal is to write personalized outreach drafts for a prospect across three channels: Email, WhatsApp, and SMS.

IMPORTANT: The outreach drafts (`email_draft`, `whatsapp_draft`, and `sms_draft`) must be written entirely in {$outreachLanguage}.

You will receive details about the product/service and target audience, along with the prospect's profile.

You MUST respond with ONLY a raw JSON object containing exactly the following keys:
{
  \"email_draft\": \"A personalized, short, compelling outbound sales email written in {$outreachLanguage}. It should have a catchy Subject: line, greet them by name, state the problem they likely face, introduce the product, and end with a soft call-to-action.\",
  \"whatsapp_draft\": \"A short, friendly, direct WhatsApp message written in {$outreachLanguage}. Use emojis, write conversationally, highlight a single key benefit, and ask a low-friction question like 'Would you be open to a 2-minute chat next week?'\",
  \"sms_draft\": \"A very short, punchy SMS outreach message written in {$outreachLanguage}. Must be strictly under 160 characters, direct, friendly, prompting a quick reply.\"
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

    // Clean LLM response
    $response = trim($response);
    if (strpos($response, '```') === 0) {
        $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
        $response = trim($response);
    }

    $data = json_decode($response, true);
    if (!$data || (!isset($data['email_draft']) && !isset($data['whatsapp_draft']) && !isset($data['sms_draft']))) {
        throw new Exception("Invalid JSON output structure returned by the AI Model. Raw response was: " . $response);
    }

    $emailDraft = $data['email_draft'] ?? '';
    $whatsappDraft = $data['whatsapp_draft'] ?? '';
    $smsDraft = $data['sms_draft'] ?? '';

    // Save generated drafts to the database
    $db->updateLeadDrafts((int)$leadId, $emailDraft, $whatsappDraft, $smsDraft);

    // Log user activity
    $db->logActivity((int)$user['id'], 'GENERATE_AI_OUTREACH', "Generated AI outreach drafts for lead '{$companyName}' (ID: {$leadId})");

    echo json_encode([
        'success' => true,
        'email_draft' => $emailDraft,
        'whatsapp_draft' => $whatsappDraft,
        'sms_draft' => $smsDraft
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
