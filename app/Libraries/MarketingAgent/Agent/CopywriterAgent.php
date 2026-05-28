<?php
namespace MarketingAgent\Agent;

use Exception;

class CopywriterAgent extends BaseAgent {
    public function getName(): string {
        return "Creative Copywriter";
    }

    /**
     * Generates compelling marketing copy based on market research.
     * 
     * @param int $campaignId
     * @param string $channel (email, social, blog)
     * @param string $researchReport
     * @param string $productDescription
     * @param string $language
     * @return string Marketing copy draft (Markdown)
     */
    public function writeCopy(int $campaignId, string $channel, string $researchReport, string $productDescription, string $language = 'English'): string {
        $this->log($campaignId, "STARTING_COPYWRITE", "Reviewing market research report to draft '{$channel}' copy in {$language}.");

        $systemPrompt = "You are a world-class Direct Response Copywriter and Creative Director. Your job is to draft engaging, high-conversion marketing copy tailored to a specific channel based on a provided Market Research Report and product description.

IMPORTANT: You must write all output, subject lines, outline, and drafts in {$language}.

Your output must be in clean Markdown. Depending on the channel, follow these formats:

--- If Channel is 'email':
1. SUBJECT LINE OPTIONS: Provide 3 high-open-rate options.
2. PREHEADER TEXT: Provide 3 options.
3. EMAIL BODY: Write a compelling, personalized marketing email (AIDA framework: Attention, Interest, Desire, Action) with a clear Call to Action (CTA) button placeholder.

--- If Channel is 'social':
Write a 3-part social media campaign:
- Post 1 (LinkedIn style): Educational, thought-leadership, long-form, professional, with hashtags and emojis.
- Post 2 (Twitter/X/Facebook style): Short, punchy hook, bulleted value propositions, high urgency.
- Post 3 (Instagram/LinkedIn style): Conversational story-driven post focused on a user case study.

--- If Channel is 'blog':
1. TITLE OPTIONS: Provide 3 SEO-optimized title options.
2. BLOG OUTLINE: Detailed section-by-section outline (H2, H3) including key points to cover.
3. ARTICLE DRAFT: Write a fully completed, engaging blog post introduction (approx 200 words) and a summary conclusion.

Do not include introductory conversational text like 'Here is your copy.' Start directly with the markdown headers.";

        $userPrompt = "Selected Channel: {$channel}
Product Description: {$productDescription}

Market Research Report:
{$researchReport}";

        $this->log($campaignId, "DRAFTING_COPY", "Drafting marketing copy variations for the {$channel} channel using the active LLM.");

        try {
            $copyDraft = $this->llm->generate($systemPrompt, $userPrompt, 0.8);
            $this->log($campaignId, "COPY_DRAFTED", "Creative marketing copy draft generated successfully (" . strlen($copyDraft) . " chars).");
            return $copyDraft;
        } catch (Exception $e) {
            $this->log($campaignId, "COPYWRITE_FAILED", "Failed to generate copy draft: " . $e->getMessage());
            throw $e;
        }
    }
}
