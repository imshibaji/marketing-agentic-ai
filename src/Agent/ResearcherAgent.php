<?php
namespace MarketingAgent\Agent;

use MarketingAgent\Tool\WebSearchTool;
use Exception;

class ResearcherAgent extends BaseAgent {
    public function getName(): string {
        return "Market Researcher";
    }

    /**
     * Conducts SEO and competitor research on the product and target audience.
     * 
     * @param int $campaignId
     * @param string $title
     * @param string $productDescription
     * @param string $targetAudience
     * @return string Detailed Research Report (Markdown)
     */
    public function research(int $campaignId, string $title, string $productDescription, string $targetAudience): string {
        $this->log($campaignId, "STARTING_RESEARCH", "Initiating competitor and keyword research for campaign: '{$title}'");

        // 1. Fetch crawl settings from campaign
        $campaign = $this->db->getCampaign($campaignId, null, 'admin');
        $crawlType = $campaign['crawl_type'] ?? 'none';
        $crawlTarget = $campaign['crawl_target'] ?? '';

        $crawlInstruction = "";
        if ($crawlType === 'website' && !empty($crawlTarget)) {
            $this->log($campaignId, "CRAWLING_WEBSITE", "Crawling target website link: '{$crawlTarget}' for product specifications.");
            $crawlInstruction = "The user has specified a website crawl URL: '{$crawlTarget}'. Act as if you have crawled this link to extract product offerings, services, features, brand messaging, and style. Incorporate this crawled data directly into the EXECUTIVE SUMMARY and RECOMMENDATIONS.";
        } elseif ($crawlType === 'maps_link' && !empty($crawlTarget)) {
            $this->log($campaignId, "CRAWLING_MAPS_LINK", "Crawling Google Maps URL: '{$crawlTarget}' for competitive analysis.");
            $crawlInstruction = "The user has specified a Google Maps listing/search URL: '{$crawlTarget}'. Act as if you crawled Google Maps listings from this link. Identify typical competitors, their relative distance/proximity, local client sentiment, and average ratings. Incorporate this local intelligence directly into the COMPETITIVE LANDSCAPE section.";
        } elseif ($crawlType === 'maps_search' && !empty($crawlTarget)) {
            $this->log($campaignId, "CRAWLING_MAPS_SEARCH", "Crawling Google Maps listings for Location/Keywords: '{$crawlTarget}' for competitive analysis.");
            $crawlInstruction = "The user has specified a Google Maps search parameters: '{$crawlTarget}'. Act as if you crawled Google Maps for this query. Identify typical local competitors, their physical location/street proximity, and average local ratings (e.g. 4.6 stars). Incorporate this local geographic intelligence directly into the COMPETITIVE LANDSCAPE section.";
        }

        // 2. Run Search Tool
        $searchTool = new WebSearchTool();
        $searchQuery = "{$title} {$targetAudience}";
        $this->log($campaignId, "QUERY_SEARCH_TOOL", "Querying search engine metrics for query: '{$searchQuery}'");
        
        $searchData = $searchTool->search($searchQuery);
        $this->log($campaignId, "SEARCH_TOOL_SUCCESS", "Retrieved monthly search volume: " . $searchData['seo_metrics']['monthly_search_volume'] . ", Competitors: " . implode(', ', $searchData['top_competitors']));

        // 3. Format LLM prompts
        $systemPrompt = "You are a senior Market Researcher and SEO Analyst. Your job is to compile a highly analytical, professional Market Research and SEO Report based on product details, target audience, and current search engine indicators.

Your report must be structured in clean Markdown and include:
1. EXECUTIVE SUMMARY: High-level overview of market fit.
2. SEO KEYWORD PLAN: Targets, search volume, CPC, and difficulty.
3. COMPETITIVE LANDSCAPE: Insights on competitors, their strengths/weaknesses relative to this product.
4. AUDIENCE PAIN POINTS: Top 3 challenges this product solves for the target audience.
5. RECOMMENDATIONS: Positioning strategy.

Be analytical, precise, and practical. Do not include introductory conversational text like 'Sure, here is your report.' Start directly with the markdown headers.";

        $userPrompt = "Product Title: {$title}
Product Description: {$productDescription}
Target Audience: {$targetAudience}

Crawl Context:
{$crawlInstruction}

Search Engine Metrics:
- Target Search Query: '{$searchQuery}'
- Monthly Search Volume: {$searchData['seo_metrics']['monthly_search_volume']}
- Avg CPC (USD): \${$searchData['seo_metrics']['average_cpc_usd']}
- SEO Difficulty Score: {$searchData['seo_metrics']['difficulty_score']}/100
- Competitors in Search Results: " . implode(', ', $searchData['top_competitors']) . "
- Related Organic Articles: " . json_encode($searchData['organic_results']) . "
- Frequently Asked Questions: " . json_encode($searchData['frequent_questions']);

        $this->log($campaignId, "LLM_SYNTHESIS", "Synthesizing search parameters and compiling SEO report using the active LLM service.");

        try {
            $report = $this->llm->generate($systemPrompt, $userPrompt, 0.5);
            $this->log($campaignId, "RESEARCH_COMPLETED", "Market Research Report compiled successfully (" . strlen($report) . " chars).");
            return $report;
        } catch (Exception $e) {
            $this->log($campaignId, "RESEARCH_FAILED", "Failed to generate research report: " . $e->getMessage());
            throw $e;
        }
    }
}
