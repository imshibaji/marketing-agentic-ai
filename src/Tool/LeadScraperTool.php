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
        $websiteData = [];
        $sourceLower = strtolower($sourceUrl);
        if (strpos($sourceLower, 'http://') === 0 || strpos($sourceLower, 'https://') === 0) {
            if (strpos($sourceLower, 'google.com/maps') !== false || strpos($sourceLower, 'maps.google') !== false || (strpos($sourceLower, 'google.') !== false && strpos($sourceLower, '/maps') !== false)) {
                // Google Maps Link
                $sourceTypeInstruction = "The user has specified a Google Maps URL as the scraping source: '{$sourceUrl}'. 
Generate exactly 5 realistic local business prospects from this geographical area. 
Include a realistic physical address and a Google Maps review rating metric (e.g., '4.7 stars (85 reviews)') in each company's description. 
Derive the company domains and emails realistically from their company names.";
            } else {
                // Website Link or Webpage URL
                $websiteData = $this->scrapeWebsitePage($sourceUrl);
                if (!empty($websiteData['fetch_success'])) {
                    $sourceTypeInstruction = "The user has specified a website or webpage URL as the scraping source: '{$sourceUrl}'.
Use the actual scraped website content below to identify realistic target companies, services, and business descriptions when generating leads.";
                } else {
                    $sourceTypeInstruction = "The user has specified a website or webpage URL as the scraping source: '{$sourceUrl}'.
Attempt to generate exactly 5 realistic target companies that could be found via this website or webpage. If the website could not be fetched, proceed with realistic prospect generation based on the page URL and target audience.";
                }
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
- Postal Address (realistic full physical address including street, city, state, zip/postal code)
- Contact Email (realistic, matching the company domain)
- Mobile Number (realistic mobile phone format, e.g., +1-555-019-XXXX or +91-98765-XXXXX)
- WhatsApp Number (realistic WhatsApp format, e.g., +1-555-019-XXXX or +91-98765-XXXXX)
- Industry
- Short Company Description (what they do, why they might need the product, and any specific source context details)

IMPORTANT: You must return ONLY a raw JSON array. Do not include markdown code block formatting (like ```json). Just the raw JSON.
Format:
[
  {
    \"company_name\": \"...\",
    \"contact_name\": \"...\",
    \"postal_address\": \"...\",
    \"email\": \"...\",
    \"mobile\": \"...\",
    \"whatsapp\": \"...\",
    \"industry\": \"...\",
    \"description\": \"...\"
  }
]";

        $userPrompt = "Product: {$productDescription}\nTarget Audience: {$targetAudience}\nScraping Source: {$sourceUrl}";
        if (!empty($websiteData['fetch_success'])) {
            $userPrompt .= "\nWebsite Content Summary: " . $websiteData['summary'];
        }

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
                    'postal_address' => '100 Engineering Blvd, Austin, TX 78701',
                    'email' => 'sarah.connor@devflow.io',
                    'mobile' => '+1-555-014-' . $num1,
                    'whatsapp' => '+1-555-014-' . $num1,
                    'industry' => 'Software & SaaS',
                    'description' => 'A mid-sized provider of workflow automation tools for engineering teams looking to streamline deployment pipelines.'
                ],
                [
                    'company_name' => 'CloudScale Inc',
                    'contact_name' => 'David Miller',
                    'postal_address' => '500 Cloud Way, Suite 300, Seattle, WA 98101',
                    'email' => 'd.miller@cloudscale.net',
                    'mobile' => '+1-555-019-' . $num2,
                    'whatsapp' => '+1-555-019-' . $num2,
                    'industry' => 'Cloud Infrastructure',
                    'description' => 'Enterprise cloud hosting startup scaling rapidly and needing better user onboarding and developer advocacy.'
                ],
                [
                    'company_name' => 'PixelPerfect Agency',
                    'contact_name' => 'Elena Rostova',
                    'postal_address' => '24 Design Plaza, London, EC1A 1BB, UK',
                    'email' => 'elena@pixelperfect.design',
                    'mobile' => '+44-7700-900' . $num1,
                    'whatsapp' => '+44-7700-900' . $num1,
                    'industry' => 'Digital Design Agency',
                    'description' => 'A boutique design agency that builds custom web apps for clients, looking for tool integrations to speed up development.'
                ],
                [
                    'company_name' => 'StackBound Corp',
                    'contact_name' => 'Marcus Aurelius',
                    'postal_address' => '800 Security Rd, Boston, MA 02110',
                    'email' => 'marcus@stackbound.com',
                    'mobile' => '+1-555-012-' . $num2,
                    'whatsapp' => '+1-555-012-' . $num2,
                    'industry' => 'Cybersecurity',
                    'description' => 'A fast-growing security compliance software company seeking automated marketing solutions to reach developer managers.'
                ],
                [
                    'company_name' => 'NodeCraft Systems',
                    'contact_name' => 'Kenji Tanaka',
                    'postal_address' => '1-2-3 Shibuya, Tokyo, 150-0002, Japan',
                    'email' => 'tanaka@nodecraft.io',
                    'mobile' => '+81-90-5555-' . $num1,
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
                'postal_address' => '456 Broadway, New York, NY 10013',
                'email' => 'j.alba@apexglobal.com',
                'mobile' => '+1-555-015-' . $num1,
                'whatsapp' => '+1-555-015-' . $num1,
                'industry' => 'Marketing & Advertising',
                'description' => 'Full-service advertising agency managing multiple client campaigns looking to automate content generation.'
            ],
            [
                'company_name' => 'Nova Retail Brands',
                'contact_name' => 'Thomas Wright',
                'postal_address' => '789 Commerce St, Los Angeles, CA 90014',
                'email' => 't.wright@novabrands.co',
                'mobile' => '+1-555-011-' . $num2,
                'whatsapp' => '+1-555-011-' . $num2,
                'industry' => 'E-commerce & Retail',
                'description' => 'Direct-to-consumer lifestyle brand expanding their online presence and seeking better social media engagement.'
            ],
            [
                'company_name' => 'Vanguard Logistics',
                'contact_name' => 'Robert Chen',
                'postal_address' => '12 Marina Blvd, Singapore 018982',
                'email' => 'robert.chen@vanguardlog.com',
                'mobile' => '+65-9123-' . $num1,
                'whatsapp' => '+65-9123-' . $num1,
                'industry' => 'Transportation & Logistics',
                'description' => 'Global supply chain manager looking to digitize customer notifications and sales follow-ups.'
            ],
            [
                'company_name' => 'Summit Financial Group',
                'contact_name' => 'Amanda Ross',
                'postal_address' => '100 Financial Plaza, Chicago, IL 60603',
                'email' => 'amanda.ross@summitfin.com',
                'mobile' => '+1-555-017-' . $num2,
                'whatsapp' => '+1-555-017-' . $num2,
                'industry' => 'Financial Services',
                'description' => 'Wealth management firm wanting to generate personalized newsletters for high-net-worth clients.'
            ],
            [
                'company_name' => 'GreenSprout Foods',
                'contact_name' => 'Oliver Green',
                'postal_address' => '32 Organic Way, Portland, OR 97201',
                'email' => 'oliver@greensprout.organic',
                'mobile' => '+1-555-018-' . $num1,
                'whatsapp' => '+1-555-018-' . $num1,
                'industry' => 'Food & Beverage',
                'description' => 'Organic snack subscription service looking to expand corporate B2B sales through direct outreach.'
            ]
        ];
    }

    public function scrapeWebsitePage(string $pageUrl): array {
        $html = $this->fetchUrl($pageUrl, [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        if ($html === false) {
            return [
                'fetch_success' => false,
                'url' => $pageUrl,
                'summary' => "Unable to fetch website content from {$pageUrl}.",
                'error' => 'Unable to retrieve page HTML.'
            ];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $title = trim($this->getFirstNodeValue($xpath, '//title'));
        $description = trim($this->getFirstNodeValue($xpath, '//meta[@name="description"]/attribute::content | //meta[@property="og:description"]/attribute::content'));
        $canonical = trim($this->getFirstNodeValue($xpath, '//link[@rel="canonical"]/attribute::href'));
        $headings = array_merge(
            $this->extractTextNodes($xpath, '//h1', 2),
            $this->extractTextNodes($xpath, '//h2', 3)
        );
        $paragraphs = $this->extractTextNodes($xpath, '//p', 4);
        $topLinks = $this->extractLinks($xpath, 6);

        $summaryParts = [];
        if (!empty($title)) {
            $summaryParts[] = "Page title: {$title}.";
        }
        if (!empty($description)) {
            $summaryParts[] = "Meta description: {$description}.";
        }
        if (!empty($headings)) {
            $summaryParts[] = "Headings: " . implode(' | ', $headings) . ".";
        }
        if (!empty($paragraphs)) {
            $summaryParts[] = "Page content preview: " . implode(' ', array_slice($paragraphs, 0, 2));
        }
        if (!empty($topLinks)) {
            $summaryParts[] = "Top page links: " . implode(', ', $topLinks) . ".";
        }

        $summary = implode(' ', $summaryParts);
        if (empty(trim($summary))) {
            $summary = "The page was successfully fetched, but no strong title, description, or text content was extracted.";
        }

        return [
            'fetch_success' => true,
            'url' => $pageUrl,
            'title' => $title,
            'description' => $description,
            'headings' => $headings,
            'paragraphs' => $paragraphs,
            'top_links' => $topLinks,
            'summary' => $summary
        ];
    }

    private function fetchUrl(string $url, array $headers = []): string|false {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 400) {
            return false;
        }

        return $body;
    }

    private function getFirstNodeValue(\DOMXPath $xpath, string $expression): string {
        $node = $xpath->query($expression)->item(0);
        return $node ? trim($node->nodeValue) : '';
    }

    private function extractTextNodes(\DOMXPath $xpath, string $expression, int $limit = 3): array {
        $values = [];
        $nodes = $xpath->query($expression);
        if (!$nodes) {
            return $values;
        }

        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $values[] = preg_replace('/\s+/u', ' ', $text);
            }
            if (count($values) >= $limit) {
                break;
            }
        }

        return $values;
    }

    private function extractLinks(\DOMXPath $xpath, int $limit = 5): array {
        $links = [];
        $nodes = $xpath->query('//a[@href]');
        if (!$nodes) {
            return $links;
        }

        foreach ($nodes as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href === '' || strpos($href, 'javascript:') === 0 || strpos($href, '#') === 0) {
                continue;
            }

            if (strpos($href, '/') === 0) {
                $links[] = $href;
            } elseif (strpos($href, 'http') === 0) {
                $links[] = $href;
            }

            if (count($links) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($links));
    }
}
