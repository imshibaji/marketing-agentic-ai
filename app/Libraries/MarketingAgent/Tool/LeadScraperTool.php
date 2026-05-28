<?php
namespace MarketingAgent\Tool;

use MarketingAgent\Service\Llm\LlmProviderInterface;
use Exception;

class LeadScraperTool {
    private LlmProviderInterface $llm;
    private $_logCallback = null;

    public function __construct(LlmProviderInterface $llm) {
        $this->llm = $llm;
    }

    public function setLogCallback(?callable $callback): void {
        $this->_logCallback = $callback;
    }

    private function log(string $action, string $message): void {
        if ($this->_logCallback) {
            call_user_func($this->_logCallback, $action, $message);
        }
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

        $googleMapsLeads = [];
        if ($this->isGoogleMapsSource($sourceUrl)) {
            $this->log('INFO', "Live scraping triggered for Google Maps query/target: '{$sourceUrl}'");
            $googleMapsLeads = $this->scrapeGoogleMapsLeads($sourceUrl, $targetAudience);
        }

        if (!empty($googleMapsLeads)) {
            $this->log('SUCCESS', "Live scraped " . count($googleMapsLeads) . " business leads successfully from Google Maps.");
            return $googleMapsLeads;
        }

        if ($this->isGoogleMapsSource($sourceUrl)) {
            $this->log('WARNING', "Live Google Maps HTML scrape returned 0 results (possibly rate-limited or blocked by Google CAPTCHA). Falling back to LLM-based synthetic lead generation.");
        }

        if (strpos($sourceLower, 'http://') === 0 || strpos($sourceLower, 'https://') === 0) {
            if (strpos($sourceLower, 'google.com/maps') !== false || strpos($sourceLower, 'maps.google') !== false || (strpos($sourceLower, 'google.') !== false && strpos($sourceLower, '/maps') !== false)) {
                // Google Maps Link
                $sourceTypeInstruction = "The user has specified a Google Maps URL as the scraping source: '{$sourceUrl}'. 
Generate exactly 5 realistic local business prospects from this geographical area. 
Include a realistic physical address and a Google Maps review rating metric (e.g., '4.7 stars (85 reviews)') in each company's description. 
Derive the company domains and emails realistically from their company names.";
            } else {
                // Website Link or Webpage URL
                $this->log('INFO', "Scraping target website page: '{$sourceUrl}'");
                $websiteData = $this->scrapeWebsitePage($sourceUrl);
                if (!empty($websiteData['fetch_success'])) {
                    $this->log('INFO', "Website content fetched successfully. Analyzing title, headings, and description...");
                    $sourceTypeInstruction = "The user has specified a website or webpage URL as the scraping source: '{$sourceUrl}'.
Use the actual scraped website content below to identify realistic target companies, services, and business descriptions when generating leads.";
                } else {
                    $this->log('WARNING', "Failed to fetch website page '{$sourceUrl}' (" . ($websiteData['error'] ?? 'unknown error') . "). Generating target prospects using page URL and audience constraints.");
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
            $this->log('INFO', "Requesting LLM to generate qualified prospects using model context...");
            $response = $this->llm->generate($systemPrompt, $userPrompt, 0.8);
            
            // Clean markdown indicators if the model ignored instructions
            $response = trim($response);
            $firstBracket = strpos($response, '[');
            $lastBracket = strrpos($response, ']');
            if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
                $jsonString = substr($response, $firstBracket, $lastBracket - $firstBracket + 1);
                $leads = json_decode($jsonString, true);
            } else {
                if (strpos($response, '```') === 0) {
                    $response = preg_replace('/^```(?:json)?|```$/m', '', $response);
                    $response = trim($response);
                }
                $leads = json_decode($response, true);
            }

            if (is_array($leads) && count($leads) > 0) {
                $this->log('SUCCESS', "Successfully generated " . count($leads) . " qualified prospects via LLM.");
                return $leads;
            }
        } catch (Exception $e) {
            $this->log('WARNING', "LLM lead generation failed: " . $e->getMessage());
        }

        // Robust Fallback in case LLM is offline or output is invalid
        $this->log('WARNING', "No active LLM response or invalid format received. Using local static mock templates based on target audience.");
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

    private function isGoogleMapsSource(string $sourceUrl): bool {
        $sourceLower = strtolower($sourceUrl);

        if (strpos($sourceLower, 'google.com/maps') !== false || strpos($sourceLower, 'maps.google') !== false || strpos($sourceLower, '/maps') !== false) {
            return true;
        }

        if (strpos($sourceLower, 'location:') !== false || strpos($sourceLower, 'google maps') !== false || strpos($sourceLower, 'maps search') !== false) {
            return true;
        }

        return false;
    }

    private function scrapeGoogleMapsLeads(string $sourceUrl, string $targetAudience): array {
        $query = $this->buildGoogleMapsQuery($sourceUrl, $targetAudience);
        $serperKey = trim((string)getenv('SERPER_API_KEY'));
        $serpKey = trim((string)getenv('SERP_API_KEY'));

        if ($serperKey !== '') {
            $this->log('INFO', "Using configured Serper.dev API key for Google Maps scraping.");
        } elseif ($serpKey !== '') {
            $this->log('INFO', "Using configured SerpApi API key for Google Maps scraping.");
        } else {
            $this->log('WARNING', "No Serper.dev or SerpApi keys found in environment. Attempting direct HTML scraping (highly prone to Google Captcha blocks)...");
        }

        $googleScraper = new GoogleScraperTool([
            'serper_api_key' => $serperKey !== '' ? $serperKey : null,
            'serp_api_key' => $serpKey !== '' ? $serpKey : null,
        ]);

        $results = $googleScraper->searchLocal($query, 5);
        if (empty($results)) {
            $this->log('WARNING', "Google search returned zero local results.");
            return [];
        }

        $leads = [];
        foreach ($results as $result) {
            $companyName = trim((string)($result['company_name'] ?? ''));
            if ($companyName === '') {
                continue;
            }

            $website = trim((string)($result['website'] ?? ''));
            $address = trim((string)($result['postal_address'] ?? $result['address'] ?? ''));
            $phone = trim((string)($result['phone'] ?? $result['phone_number'] ?? $result['mobile'] ?? ''));
            $rating = trim((string)($result['rating'] ?? ''));

            $email = $this->buildLeadEmail($companyName, $website);
            $industry = $this->inferLeadIndustry($companyName, $query);
            $descriptionParts = ["Google Maps business listing matched for {$query}."];

            if ($address !== '') {
                $descriptionParts[] = "Address: {$address}.";
            }

            if ($rating !== '') {
                $descriptionParts[] = "Rating: {$rating}.";
            }

            if ($website !== '') {
                $descriptionParts[] = "Website: {$website}.";
            }

            $leads[] = [
                'company_name' => $companyName,
                'contact_name' => $this->buildLeadContactName($companyName),
                'postal_address' => $address,
                'email' => $email,
                'mobile' => $phone,
                'whatsapp' => $phone,
                'industry' => $industry,
                'description' => implode(' ', $descriptionParts)
            ];
        }

        return $leads;
    }

    private function buildGoogleMapsQuery(string $sourceUrl, string $targetAudience): string {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            return $targetAudience;
        }

        $sourceLower = strtolower($sourceUrl);
        if (strpos($sourceLower, 'location:') !== false) {
            return $sourceUrl;
        }

        if (strpos($sourceLower, 'google.com/maps') !== false || strpos($sourceLower, 'maps.google') !== false || strpos($sourceLower, '/maps') !== false) {
            return $sourceUrl;
        }

        return $sourceUrl;
    }

    private function buildLeadEmail(string $companyName, string $website): string {
        $domain = $this->extractDomain($website);
        if ($domain !== '') {
            return 'info@' . $domain;
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '', strtolower($companyName));
        if ($slug === '') {
            return 'info@business.example';
        }

        return 'info@' . $slug . '.com';
    }

    private function buildLeadContactName(string $companyName): string {
        $companyName = trim($companyName);
        if ($companyName === '') {
            return 'Business Owner';
        }

        return 'Business Owner';
    }

    private function inferLeadIndustry(string $companyName, string $query): string {
        $queryLower = strtolower($query);
        $keywords = [
            'plumber' => 'Plumbing',
            'doctor' => 'Healthcare',
            'lawyer' => 'Legal Services',
            'restaurant' => 'Food & Beverage',
            'salon' => 'Beauty & Wellness',
            'dentist' => 'Healthcare',
            'hotel' => 'Hospitality',
            'gym' => 'Fitness',
            'shoes' => 'Retail',
            'repair' => 'Repair & Maintenance',
            'marketing' => 'Marketing & Advertising',
            'software' => 'Software & SaaS'
        ];

        foreach ($keywords as $keyword => $industry) {
            if (strpos($queryLower, $keyword) !== false) {
                return $industry;
            }
        }

        return 'Local Business';
    }

    private function extractDomain(string $website): string {
        $website = trim($website);
        if ($website === '') {
            return '';
        }

        $parsed = parse_url($website);
        if (!isset($parsed['host'])) {
            return '';
        }

        $host = strtolower($parsed['host']);
        $host = ltrim($host, 'www.');
        return preg_replace('/^www\./i', '', $host);
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
        $client = new \GuzzleHttp\Client([
            'timeout' => 12,
            'allow_redirects' => true,
            'verify' => true
        ]);
        
        $options = [];
        if (!empty($headers)) {
            $parsedHeaders = [];
            foreach ($headers as $header) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $parsedHeaders[trim($parts[0])] = trim($parts[1]);
                }
            }
            $options['headers'] = $parsedHeaders;
        }
        
        try {
            $response = $client->get($url, $options);
            return (string)$response->getBody();
        } catch (\Exception $e) {
            return false;
        }
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
