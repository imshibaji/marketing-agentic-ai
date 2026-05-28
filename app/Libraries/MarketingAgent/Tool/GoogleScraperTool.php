<?php
namespace MarketingAgent\Tool;

use Exception;
use DOMDocument;
use DOMXPath;

class GoogleScraperTool {
    private array $config;
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:123.0) Gecko/20100101 Firefox/123.0'
    ];

    /**
     * @param array $config Configuration array supporting:
     * - 'proxy': Optional proxy string (e.g. 'http://username:password@ip:port')
     * - 'serp_api_key': Optional API key for SerpApi (bypasses raw scrape blocks)
     * - 'serper_api_key': Optional API key for Serper.dev (highly reliable alternate)
     * - 'timeout': HTTP timeout in seconds
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'proxy' => null,
            'serp_api_key' => null,
            'serper_api_key' => null,
            'timeout' => 15,
        ], $config);
    }

    /**
     * Scrapes Google Web Search for organic results.
     * * @param string $query Search query string
     * @param int $numResults Preferred number of results
     * @return array List of parsed organic results (title, link, snippet)
     */
    public function searchWeb(string $query, int $numResults = 10): array {
        // If Serper.dev API is configured, use it for 100% reliability
        if (!empty($this->config['serper_api_key'])) {
            return $this->fetchViaSerper($query, 'search', $numResults);
        }

        // If SerpApi is configured, use it
        if (!empty($this->config['serp_api_key'])) {
            return $this->fetchViaSerpApi($query, 'search', $numResults);
        }

        // Fallback to direct raw HTML scraping
        return $this->scrapeOrganicHtml($query, $numResults);
    }

    /**
     * Scrapes Google local listings (businesses) for lead discovery.
     * Targets local business containers/packs.
     * * @param string $query Query (e.g. "Plumbers in Austin TX")
     * @param int $numResults Max local listings to parse
     * @return array List of local businesses (name, rating, phone, address, website)
     */
    public function searchLocal(string $query, int $numResults = 5): array {
        if (!empty($this->config['serper_api_key'])) {
            return $this->fetchViaSerper($query, 'places', $numResults);
        }

        if (!empty($this->config['serp_api_key'])) {
            return $this->fetchViaSerpApi($query, 'local', $numResults);
        }

        return $this->scrapeLocalHtml($query, $numResults);
    }

    /**
     * Directly parses Google Organic Search Results via HTML Scraping.
     */
    private function scrapeOrganicHtml(string $query, int $limit): array {
        $url = "https://www.google.com/search?q=" . urlencode($query) . "&num=" . ($limit + 5);
        $html = $this->fetchUrl($url);

        if (!$html) {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $results = [];
        // Google uses 'div.g' wrapper for organic web results
        $nodes = $xpath->query('//div[contains(@class, "g")]');

        foreach ($nodes as $node) {
            if (count($results) >= $limit) {
                break;
            }

            // Create contextual XPath for inside the current result node
            $subXpath = new DOMXPath($dom);
            
            // Extract Title - usually an h3 inside a anchor tag
            $titleNode = $xpath->query('.//h3', $node)->item(0);
            $title = $titleNode ? trim($titleNode->nodeValue) : '';

            // Extract Link - Anchor tag wrapping the title or adjacent
            $linkNode = $xpath->query('.//a[@href]', $node)->item(0);
            $link = '';
            if ($linkNode) {
                $rawUrl = $linkNode->getAttribute('href');
                // Standardize google redirections if any
                if (preg_match('/^\/url\?q=(.*?)&/', $rawUrl, $matches)) {
                    $link = urldecode($matches[1]);
                } elseif (strpos($rawUrl, 'http') === 0) {
                    $link = $rawUrl;
                }
            }

            // Exclude internal Google system links
            if (empty($link) || strpos($link, 'google.com') !== false || strpos($link, 'webcache.google') !== false) {
                continue;
            }

            // Extract Snippet/Description - typically found inside text block elements
            // Classes vary but are usually divs/spans under the heading structure
            $snippetNode = $xpath->query('.//div[contains(@class, "VwiC3b") or contains(@style, "webkit-line-clamp")]', $node)->item(0);
            if (!$snippetNode) {
                $snippetNode = $xpath->query('.//span[contains(@class, "aCOpbe")]', $node)->item(0);
            }
            $snippet = $snippetNode ? trim($snippetNode->nodeValue) : '';

            $results[] = [
                'title' => $title ?: 'No Title Found',
                'link' => $link,
                'snippet' => $snippet ?: 'No description snippet available.'
            ];
        }

        return $results;
    }

    /**
     * Directly parses Google Local Business Search Pack via HTML Scraping.
     */
    private function scrapeLocalHtml(string $query, int $limit): array {
        // Appending 'local' indicators to force local business pack viewport
        $url = "https://www.google.com/search?q=" . urlencode($query) . "&tbm=lcl";
        $html = $this->fetchUrl($url);

        if (!$html) {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $results = [];
        
        // Local listings block in HTML typically is held inside distinct visual elements
        // This targets standard Google local/maps list nodes
        $nodes = $xpath->query('//div[contains(@class, "Vkp9Pc") or contains(@class, "lcl-entry") or .//span[contains(@class, "OSrXXb")]]');

        foreach ($nodes as $node) {
            if (count($results) >= $limit) {
                break;
            }

            // Extract business name
            $nameNode = $xpath->query('.//div[contains(@class, "FCrY7c") or contains(@class, "dbg0pd")]//span', $node)->item(0);
            if (!$nameNode) {
                $nameNode = $xpath->query('.//span[contains(@class, "OSrXXb")]', $node)->item(0);
            }
            $name = $nameNode ? trim($nameNode->nodeValue) : '';

            if (empty($name)) {
                continue;
            }

            // Extract rating & review count
            $ratingNode = $xpath->query('.//span[contains(@class, "Yw7A8b") or contains(@class, "UR1Yzb")]', $node)->item(0);
            $rating = $ratingNode ? trim($ratingNode->nodeValue) : 'No Rating';

            // Extract Address, Phone, details (usually sequential spans inside wrapper blocks)
            $detailSpans = $xpath->query('.//div[contains(@class, "rllt__details")]//div', $node);
            $details = [];
            foreach ($detailSpans as $span) {
                $txt = trim($span->nodeValue);
                if (!empty($txt)) {
                    $details[] = preg_replace('/\s+/', ' ', $txt);
                }
            }

            $address = $details[1] ?? 'Address details unavailable';
            $phone = $details[2] ?? 'No Phone Listed';

            // Extract website if available
            $websiteNode = $xpath->query('.//a[contains(@class, "yYg3ee") or contains(@class, "ab_button")][contains(@href, "http")]', $node)->item(0);
            $website = $websiteNode ? $websiteNode->getAttribute('href') : '';

            // Guard redirects on local links
            if (!empty($website) && preg_match('/^\/url\?q=(.*?)&/', $website, $matches)) {
                $website = urldecode($matches[1]);
            }

            $results[] = [
                'company_name' => $name,
                'rating' => $rating,
                'postal_address' => $address,
                'phone' => $phone,
                'website' => $website ?: 'No Website Listed'
            ];
        }

        // If direct HTML scraper found nothing (e.g. captcha/markup change), return structured search context
        return $results;
    }

    /**
     * HTTP Client handling UA rotation, standard headers, and proxy configs
     */
    private function fetchUrl(string $url): string|false {
        $ch = curl_init();
        $randomAgent = $this->userAgents[array_rand($this->userAgents)];

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $randomAgent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Append proxy if configured
        if (!empty($this->config['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy']);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return false;
        }

        return $body;
    }

    /**
     * API integration: Serper.dev
     */
    private function fetchViaSerper(string $query, string $type, int $limit): array {
        $url = "https://google.serper.dev/" . $type;
        $payload = json_encode([
            'q' => $query,
            'num' => $limit
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-API-KEY: " . $this->config['serper_api_key'],
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        $results = [];

        if ($type === 'search' && !empty($data['organic'])) {
            foreach (array_slice($data['organic'], 0, $limit) as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? ''
                ];
            }
        } elseif ($type === 'places' && !empty($data['places'])) {
            foreach (array_slice($data['places'], 0, $limit) as $item) {
                $results[] = [
                    'company_name' => $item['title'] ?? '',
                    'rating' => isset($item['rating']) ? ($item['rating'] . " stars (" . ($item['ratingCount'] ?? 0) . " reviews)") : 'No Rating',
                    'postal_address' => $item['address'] ?? '',
                    'phone' => $item['phoneNumber'] ?? 'No Phone Listed',
                    'website' => $item['website'] ?? 'No Website Listed'
                ];
            }
        }

        return $results;
    }

    /**
     * API integration: SerpApi (serpapi.com)
     */
    private function fetchViaSerpApi(string $query, string $type, int $limit): array {
        $engine = ($type === 'local') ? 'google_maps' : 'google';
        $params = http_build_query([
            'engine' => $engine,
            'q' => $query,
            'api_key' => $this->config['serp_api_key'],
            'num' => $limit
        ]);

        $url = "https://serpapi.com/search.json?" . $params;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        $results = [];

        if ($type === 'search' && !empty($data['organic_results'])) {
            foreach (array_slice($data['organic_results'], 0, $limit) as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? ''
                ];
            }
        } elseif ($type === 'local' && !empty($data['local_results'])) {
            foreach (array_slice($data['local_results'], 0, $limit) as $item) {
                $results[] = [
                    'company_name' => $item['title'] ?? '',
                    'rating' => isset($item['rating']) ? ($item['rating'] . " stars (" . ($item['reviews'] ?? 0) . " reviews)") : 'No Rating',
                    'postal_address' => $item['address'] ?? '',
                    'phone' => $item['phone'] ?? 'No Phone Listed',
                    'website' => $item['website'] ?? 'No Website Listed'
                ];
            }
        }

        return $results;
    }
}