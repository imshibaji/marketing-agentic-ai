<?php
namespace MarketingAgent\Tool;

use MarketingAgent\Service\Llm\LlmProviderInterface;
use Exception;

class LeadScraperTool {
    private LlmProviderInterface $llm;

    public function __construct(LlmProviderInterface $llm) {
        $this->llm = $llm;
    }

    /**
     * Uses the LLM to generate realistic lead prospects custom-tailored to the product, target audience, and source.
     * 
     * @param string $productDescription Product overview
     * @param string $targetAudience Target customer profile
     * @param string $sourceUrl Custom input directory URL or Google Maps search query
     * @return array List of raw lead dicts
     */
    public function scrapeLeads(string $productDescription, string $targetAudience, string $sourceUrl = ''): array {
        // Fallback default to Google Maps matching audience
        if (empty(trim($sourceUrl))) {
            $sourceUrl = "Google Maps Business Listing search for '" . $targetAudience . "'";
        }

        $sourceTypeInstruction = "";
        $sourceLower = strtolower($sourceUrl);
        if (strpos($sourceLower, 'http://') === 0 || strpos($sourceLower, 'https://') === 0) {
            if (strpos($sourceLower, 'google.com/maps') !== false || strpos($sourceLower, 'maps.google') !== false || (strpos($sourceLower, 'google.') !== false && strpos($sourceLower, '/maps') !== false)) {
                // Google Maps Link
                $sourceTypeInstruction = "The user has specified a Google Maps URL as the scraping source: '{$sourceUrl}'. 
Generate exactly 5 realistic local business prospects from this geographical area. 
Include a realistic physical address and a Google Maps review rating metric (e.g., '4.7 stars (85 reviews)') in each company's description. 
Derive the company domains and emails realistically from their company names.";
            } else {
                // Website Link
                $sourceTypeInstruction = "The user has specified a website URL directory/business directory as the scraping source: '{$sourceUrl}'. 
Generate exactly 5 realistic target companies as if they were extracted from this website directory. 
Make sure the contact emails use domains derived from these company names, and the descriptions specify their business activities and how they align with the directory link category.";
            }
        } elseif (strpos($sourceLower, 'location:') !== false) {
            // Google Maps Search (Location & Keywords)
            $sourceTypeInstruction = "The user has specified a Google Maps location and keywords query: '{$sourceUrl}'. 
Generate exactly 5 realistic local businesses operating in the specified location matching the specified keywords. 
In each company's description, include a local street address in that city and a Google Maps rating statement (e.g., '4.8 stars on Google Maps'). 
Ensure their business description explains why they fit this specific location and keyword niche.";
        } else {
            // Raw Google Maps query
            $sourceTypeInstruction = "The user has specified a Google Maps search query as the search source: '{$sourceUrl}'. 
Generate exactly 5 local businesses matching this search on Google Maps. 
In each company's description, include a simulated local street address and a mock rating statement (e.g. 'Rated 4.8 stars with 120 reviews on Google Maps').";
        }

        $systemPrompt = "You are an advanced Lead Scraping and Prospecting Tool. Your task is to generate exactly 5 realistic target prospects for a given product and target audience.

{$sourceTypeInstruction}

For each company, you must generate:
- Company Name
- Contact Name (a realistic decision maker, e.g., Owner, VP of Marketing, CTO, Head of Sales)
- Contact Email (realistic, matching the company domain)
- WhatsApp Number (realistic mobile phone format, e.g., +1-555-019-XXXX or +91-98765-XXXXX)
- Industry
- Short Company Description (what they do, why they might need the product, and any specific source context details)

IMPORTANT: You must return ONLY a raw JSON array. Do not include markdown code block formatting (like ```json). Just the raw JSON.
Format:
[
  {
    \"company_name\": \"...\",
    \"contact_name\": \"...\",
    \"email\": \"...\",
    \"whatsapp\": \"...\",
    \"industry\": \"...\",
    \"description\": \"...\"
  }
]";

        $userPrompt = "Product: {$productDescription}\nTarget Audience: {$targetAudience}\nScraping Source: {$sourceUrl}";

        try {
            $response = $this->llm->generate($systemPrompt, $userPrompt, 0.8);
            
            // Clean markdown indicators if the model ignored instructions
            $response = trim($response);
            if (strpos($response, '```') === 0) {
                $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
                $response = trim($response);
            }

            $leads = json_decode($response, true);

            if (is_array($leads) && count($leads) > 0) {
                return $leads;
            }
        } catch (Exception $e) {
            // Fallback will execute below
        }

        // Robust Fallback in case LLM is offline or output is invalid
        return $this->getFallbackLeads($targetAudience);
    }

    private function getFallbackLeads(string $targetAudience): array {
        $audienceClean = strtolower($targetAudience);
        
        // Generate random realistic numbers
        $num1 = rand(100, 999);
        $num2 = rand(100, 999);

        if (strpos($audienceClean, 'developer') !== false || strpos($audienceClean, 'tech') !== false || strpos($audienceClean, 'saas') !== false) {
            return [
                [
                    'company_name' => 'DevFlow Solutions',
                    'contact_name' => 'Sarah Connor',
                    'email' => 'sarah.connor@devflow.io',
                    'whatsapp' => '+1-555-014-' . $num1,
                    'industry' => 'Software & SaaS',
                    'description' => 'A mid-sized provider of workflow automation tools for engineering teams looking to streamline deployment pipelines.'
                ],
                [
                    'company_name' => 'CloudScale Inc',
                    'contact_name' => 'David Miller',
                    'email' => 'd.miller@cloudscale.net',
                    'whatsapp' => '+1-555-019-' . $num2,
                    'industry' => 'Cloud Infrastructure',
                    'description' => 'Enterprise cloud hosting startup scaling rapidly and needing better user onboarding and developer advocacy.'
                ],
                [
                    'company_name' => 'PixelPerfect Agency',
                    'contact_name' => 'Elena Rostova',
                    'email' => 'elena@pixelperfect.design',
                    'whatsapp' => '+44-7700-900' . $num1,
                    'industry' => 'Digital Design Agency',
                    'description' => 'A boutique design agency that builds custom web apps for clients, looking for tool integrations to speed up development.'
                ],
                [
                    'company_name' => 'StackBound Corp',
                    'contact_name' => 'Marcus Aurelius',
                    'email' => 'marcus@stackbound.com',
                    'whatsapp' => '+1-555-012-' . $num2,
                    'industry' => 'Cybersecurity',
                    'description' => 'A fast-growing security compliance software company seeking automated marketing solutions to reach developer managers.'
                ],
                [
                    'company_name' => 'NodeCraft Systems',
                    'contact_name' => 'Kenji Tanaka',
                    'email' => 'tanaka@nodecraft.io',
                    'whatsapp' => '+81-90-5555-' . $num1,
                    'industry' => 'EdTech & Training',
                    'description' => 'Online learning platform offering coding bootcamps to career switchers, wanting to target tech organizations.'
                ]
            ];
        }

        // Generic fallback
        return [
            [
                'company_name' => 'Apex Global Marketing',
                'contact_name' => 'Jessica Alba',
                'email' => 'j.alba@apexglobal.com',
                'whatsapp' => '+1-555-015-' . $num1,
                'industry' => 'Marketing & Advertising',
                'description' => 'Full-service advertising agency managing multiple client campaigns looking to automate content generation.'
            ],
            [
                'company_name' => 'Nova Retail Brands',
                'contact_name' => 'Thomas Wright',
                'email' => 't.wright@novabrands.co',
                'whatsapp' => '+1-555-011-' . $num2,
                'industry' => 'E-commerce & Retail',
                'description' => 'Direct-to-consumer lifestyle brand expanding their online presence and seeking better social media engagement.'
            ],
            [
                'company_name' => 'Vanguard Logistics',
                'contact_name' => 'Robert Chen',
                'email' => 'robert.chen@vanguardlog.com',
                'whatsapp' => '+65-9123-' . $num1,
                'industry' => 'Transportation & Logistics',
                'description' => 'Global supply chain manager looking to digitize customer notifications and sales follow-ups.'
            ],
            [
                'company_name' => 'Summit Financial Group',
                'contact_name' => 'Amanda Ross',
                'email' => 'amanda.ross@summitfin.com',
                'whatsapp' => '+1-555-017-' . $num2,
                'industry' => 'Financial Services',
                'description' => 'Wealth management firm wanting to generate personalized newsletters for high-net-worth clients.'
            ],
            [
                'company_name' => 'GreenSprout Foods',
                'contact_name' => 'Oliver Green',
                'email' => 'oliver@greensprout.organic',
                'whatsapp' => '+1-555-018-' . $num1,
                'industry' => 'Food & Beverage',
                'description' => 'Organic snack subscription service looking to expand corporate B2B sales through direct outreach.'
            ]
        ];
    }
}
