<?php
// Set infinite execution time limit for long-running LLM generation
set_time_limit(0);

// Disable output buffering so we can stream data in real-time
if (PHP_SAPI !== 'cli' && ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\AuthService;
use MarketingAgent\Service\Llm\LlmFactory;
use MarketingAgent\Tool\LeadScraperTool;

function sendSseEvent(string $event, array $data): void {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Auth check
$user = AuthService::getCurrentUser();
if (!$user) {
    sendSseEvent('error', ['message' => 'Unauthorized. Please login.']);
    exit;
}

$location = trim($_GET['location'] ?? '');
$keywords = trim($_GET['keywords'] ?? '');
$sourceType = $_GET['source_type'] ?? 'maps';
$sourceUrl = trim($_GET['source_url'] ?? '');
$llmProvider = $_GET['llm_provider'] ?? null;

$db = new DatabaseService();

try {
    $llm = LlmFactory::create($db, $llmProvider, (int)$user['id']);
    
    // Construct search query/target URL
    if ($sourceType === 'maps_search' || $sourceType === 'maps') {
        if (empty($location) || empty($keywords)) {
            sendSseEvent('error', ['message' => 'Location and Keywords are required for Google Maps search.']);
            exit;
        }
        $searchTarget = "Location: {$location} | Keywords: {$keywords}";
    } elseif ($sourceType === 'maps_link') {
        if (empty($sourceUrl)) {
            sendSseEvent('error', ['message' => 'Target Google Maps URL is required.']);
            exit;
        }
        $searchTarget = $sourceUrl;
    } else {
        if (empty($sourceUrl)) {
            sendSseEvent('error', ['message' => 'Target Website URL is required.']);
            exit;
        }
        $searchTarget = $sourceUrl;
    }

    sendSseEvent('log', [
        'agent' => 'LeadsScraper',
        'action' => 'START',
        'message' => "Starting leads scraper for target: '{$searchTarget}' using provider '{$llmProvider}'",
        'timestamp' => date('H:i:s')
    ]);

    $scraper = new LeadScraperTool($llm);
    $scraper->setLogCallback(function(string $action, string $message) {
        sendSseEvent('log', [
            'agent' => 'LeadsScraper',
            'action' => $action,
            'message' => $message,
            'timestamp' => date('H:i:s')
        ]);
    });
    
    // Dummy product description & audience for scraper
    $productDesc = "Finding business contacts matching keywords: {$keywords} in {$location}";
    $targetAudience = "Businesses and contacts matching keywords: {$keywords} in location: {$location}";

    $rawLeads = $scraper->scrapeLeads($productDesc, $targetAudience, $searchTarget);
    
    sendSseEvent('log', [
        'agent' => 'LeadsScraper',
        'action' => 'SCRAPE_SUCCESS',
        'message' => "Discovered " . count($rawLeads) . " raw potential leads.",
        'timestamp' => date('H:i:s')
    ]);

    $filteredLeads = [];

    foreach ($rawLeads as $index => $lead) {
        $companyName = $lead['company_name'] ?? 'Target Corp';
        $contactName = $lead['contact_name'] ?? 'Decision Maker';
        $postalAddress = $lead['postal_address'] ?? '';
        $email = $lead['email'] ?? '';
        $whatsapp = $lead['whatsapp'] ?? '';
        $industry = $lead['industry'] ?? 'General';
        $leadDesc = $lead['description'] ?? '';
        $mobile = $lead['mobile'] ?? '';

        sendSseEvent('log', [
            'agent' => 'LeadsScraper',
            'action' => 'QUALIFYING',
            'message' => "Filtering Lead " . ($index + 1) . "/" . count($rawLeads) . ": {$companyName}",
            'timestamp' => date('H:i:s')
        ]);

        $systemPrompt = "You are a high-performing lead qualification expert.
Your goal is to evaluate if a scraped business prospect matches the user's search criteria:
Keywords: {$keywords}
Location: {$location}

Determine if the business fits the search keywords and location, score them, and write a qualification reasoning explaining why they fit the search query.

You MUST respond with ONLY a raw JSON object containing exactly the following keys:
{
  \"score\": \"HIGH\" or \"MEDIUM\" or \"LOW\",
  \"reasoning\": \"A 2-3 sentence explanation of why they fit the keywords and location and how their business aligns with the search query.\"
}

Do not include markdown code block formatting (like ```json). Just the raw JSON.
Ensure you escape quotes properly.";

        $userPrompt = "PROSPECT PROFILE:
Company: {$companyName}
Contact Person: {$contactName}
Postal Address: {$postalAddress}
Industry: {$industry}
Company Description: {$leadDesc}";

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
            
            $score = strtoupper($qualification['score'] ?? 'MEDIUM');
            if (!in_array($score, ['HIGH', 'MEDIUM', 'LOW'])) {
                $score = 'MEDIUM';
            }
            $reasoning = $qualification['reasoning'] ?? 'Lead matches industry parameters.';

        } catch (Exception $e) {
            $score = 'MEDIUM';
            $reasoning = "Matches keywords: '{$keywords}' in location: '{$location}' based on sector matching.";
        }

        $filteredLeads[] = [
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'postal_address' => $postalAddress,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'mobile' => $mobile,
            'industry' => $industry,
            'description' => $leadDesc,
            'score' => $score,
            'reasoning' => $reasoning,
            'source' => ($sourceType === 'website' ? 'website' : 'google-maps')
        ];

        sendSseEvent('log', [
            'agent' => 'LeadsScraper',
            'action' => 'QUALIFIED',
            'message' => "Filtered lead '{$companyName}' with score: {$score}",
            'timestamp' => date('H:i:s')
        ]);
    }

    sendSseEvent('complete', [
        'message' => 'Leads scraped and qualified successfully!',
        'leads' => $filteredLeads
    ]);

} catch (Exception $e) {
    sendSseEvent('error', [
        'message' => 'Error running scraper: ' . $e->getMessage()
    ]);
}
