<?php
namespace MarketingAgent\Agent;

use MarketingAgent\Tool\LeadScraperTool;
use Exception;

class LeadAgent extends BaseAgent {
    public function getName(): string {
        return "Lead Generation SDR";
    }

    /**
     * Finds, qualifies, and drafts personalized outreach for target leads.
     * 
     * @param int $campaignId
     * @param string $sourceUrl
     * @return array Array of qualified leads
     */
    public function generateAndQualifyLeads(int $campaignId, string $sourceUrl = '', string $language = ''): array {
        $this->log($campaignId, "START_LEADS_GEN", "Starting lead generation and qualification pipeline.");

        // 1. Fetch Campaign Info
        $campaign = $this->db->getCampaign($campaignId, null, 'admin');
        if (!$campaign) {
            throw new Exception("Campaign not found for ID: " . $campaignId);
        }

        $outreachLanguage = empty(trim($language)) ? ($campaign['language'] ?? 'English') : $language;

        $productDesc = $campaign['product_description'];
        $audience = $campaign['target_audience'];

        // 2. Clear old leads for this campaign
        $this->db->clearLeads($campaignId);

        // 3. Determine Scraping Source
        if (empty(trim($sourceUrl))) {
            if (!empty($campaign['crawl_target']) && ($campaign['crawl_type'] ?? 'none') !== 'none') {
                $sourceUrl = $campaign['crawl_target'];
            }
        }

        $scraper = new LeadScraperTool($this->llm);
        $sourceLogName = empty(trim($sourceUrl)) ? "Google Maps (Default)" : $sourceUrl;
        $this->log($campaignId, "SCRAPING_PROSPECTS", "Initiating lead scraping from source: '{$sourceLogName}' for audience: '{$audience}'");
        
        $rawLeads = $scraper->scrapeLeads($productDesc, $audience, $sourceUrl);
        $this->log($campaignId, "SCRAPE_SUCCESS", "Discovered " . count($rawLeads) . " potential lead targets.");

        $qualifiedLeads = [];

        // 4. Qualify & Personalize each Lead
        foreach ($rawLeads as $index => $lead) {
            $companyName = $lead['company_name'] ?? 'Target Corp';
            $contactName = $lead['contact_name'] ?? 'Decision Maker';
            $email = $lead['email'] ?? 'contact@domain.com';
            $whatsapp = $lead['whatsapp'] ?? '';
            $industry = $lead['industry'] ?? 'General';
            $leadDesc = $lead['description'] ?? '';

            $this->log($campaignId, "QUALIFYING_LEAD", "Qualifying Lead " . ($index + 1) . "/" . count($rawLeads) . ": {$companyName} ({$contactName})");

            $systemPrompt = "You are a high-performing Sales Development Representative (SDR) and outbound marketing expert. 
Your goal is to qualify a lead prospect against a product's Ideal Customer Profile (ICP), score them, and draft personalized outreach.

IMPORTANT: The outreach drafts (`email_draft` and `whatsapp_draft`) must be written entirely in {$outreachLanguage}. Keep the qualification reasoning in English.

You will receive details about the product and target audience, along with the prospect's profile.

You MUST respond with ONLY a raw JSON object containing exactly the following keys:
{
  \"score\": \"HIGH\" or \"MEDIUM\" or \"LOW\",
  \"reasoning\": \"A 2-3 sentence explanation of why they are scored this way and how the product fits their needs.\",
  \"email_draft\": \"A personalized, short, compelling outbound sales email written in {$outreachLanguage}. It should have a catchy Subject: line, greet them by name, state the problem they likely face, introduce the product, and end with a soft call-to-action.\",
  \"whatsapp_draft\": \"A short, friendly, direct WhatsApp message written in {$outreachLanguage}. Use emojis, write conversationally, highlight a single key benefit, and ask a low-friction question like 'Would you be open to a 2-minute chat next week?'\"
}

Do not include markdown code block formatting (like ```json). Just the raw JSON.
Ensure you escape quotes properly.";

            $userPrompt = "PRODUCT TO SELL:
Product Name/Description: {$productDesc}
Target ICP Audience: {$audience}

LEAD PROFILE:
Company: {$companyName}
Contact Person: {$contactName}
Industry: {$industry}
Company Description: {$leadDesc}";

            try {
                $response = $this->llm->generate($systemPrompt, $userPrompt, 0.7);
                
                // Clean response
                $response = trim($response);
                if (strpos($response, '```') === 0) {
                    $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
                    $response = trim($response);
                }

                $qualification = json_decode($response, true);
                
                if (!$qualification || !isset($qualification['score'])) {
                    throw new Exception("Invalid JSON output from LLM qualification.");
                }

                $score = strtoupper($qualification['score']);
                if (!in_array($score, ['HIGH', 'MEDIUM', 'LOW'])) {
                    $score = 'MEDIUM';
                }
                $reasoning = $qualification['reasoning'] ?? 'Lead fits basic industry parameters.';
                $emailDraft = $qualification['email_draft'] ?? '';
                $whatsappDraft = $qualification['whatsapp_draft'] ?? '';

            } catch (Exception $e) {
                // Fallback qualification in case of API issues
                $score = 'MEDIUM';
                $reasoning = "Lead belongs to {$industry} which aligns with our target segment. Good fit for initial cold testing.";
                $emailDraft = "Subject: Quick question regarding workflow efficiency at {$companyName}

Hi {$contactName},

I hope this email finds you well. 

I noticed {$companyName} focuses on {$industry} solutions. We recently launched a new marketing agent automation tool designed specifically to help businesses like yours. 

Would you be open to a brief 5-minute call this Thursday to see how we can assist {$companyName}?

Best regards,
Outreach Team";
                $whatsappDraft = "Hi {$contactName}! 👋 Hope your day is going well. I saw that {$companyName} is doing great work in {$industry}. We've built an AI system that helps teams automate content creation. Would you be open to a quick 2-minute chat about this? Let me know! 😊";
            }

            // Save to database
            $leadId = $this->db->saveLead(
                $campaignId,
                $companyName,
                $contactName,
                $email,
                $whatsapp,
                $industry,
                $leadDesc,
                $score,
                $reasoning,
                $emailDraft,
                $whatsappDraft
            );

            $this->log($campaignId, "LEAD_QUALIFIED", "Saved qualified lead '{$companyName}' with score: {$score}");

            $qualifiedLeads[] = [
                'id' => $leadId,
                'company_name' => $companyName,
                'contact_name' => $contactName,
                'email' => $email,
                'whatsapp' => $whatsapp,
                'industry' => $industry,
                'description' => $leadDesc,
                'score' => $score,
                'reasoning' => $reasoning,
                'email_draft' => $emailDraft,
                'whatsapp_draft' => $whatsappDraft,
                'status' => 'GENERATED'
            ];
        }

        $this->log($campaignId, "LEADS_PIPELINE_COMPLETE", "Successfully completed Lead Generation & Qualification workflow. " . count($qualifiedLeads) . " leads generated.");
        return $qualifiedLeads;
    }
}
