<?php
namespace MarketingAgent\Agent;

use Exception;

class EditorAgent extends BaseAgent {
    public function getName(): string {
        return "Editorial Specialist";
    }

    /**
     * Reviews, refines, and polishes the copy draft.
     * 
     * @param int $campaignId
     * @param string $copyDraft
     * @param string $targetAudience
     * @param string $language
     * @return string Final polished campaign copy (Markdown)
     */
    public function editCopy(int $campaignId, string $copyDraft, string $targetAudience, string $language = 'English'): string {
        $this->log($campaignId, "STARTING_EDIT", "Analyzing draft copy for readability, grammar, and alignment with target audience in {$language}: '{$targetAudience}'");

        $systemPrompt = "You are a senior Editor and Brand Guardian. Your job is to review, polish, and optimize marketing copy. 

        IMPORTANT: The copy must be written and polished in {$language}. Keep the native grammar, flow, and expression natural to {$language}.

        Ensure that:
        1. Tone is highly professional yet engaging and suited for the target audience.
        2. Readability is outstanding (clear, concise sentences, no corporate jargon).
        3. Formatting is impeccable (correct markdown, clean lists, clear headers, easy-to-read layout).
        4. Strong Call to Action (CTA) stands out.
        5. Typographical or spelling errors are corrected.

        Output the final, edited copy in clean markdown format. Do not include editorial comments like 'I have edited the copy' or 'Here is the final draft'. Output only the finalized content directly.";

        $userPrompt = "Target Audience: {$targetAudience}

Raw Copy Draft:
{$copyDraft}";

        $this->log($campaignId, "EDITING_CONTENT", "Polishing draft, improving sentence flow, and formatting final version.");

        try {
            $editedCopy = $this->llm->generate($systemPrompt, $userPrompt, 0.4);
            $this->log($campaignId, "EDIT_COMPLETED", "Final campaign content reviewed and finalized (" . strlen($editedCopy) . " chars).");
            return $editedCopy;
        } catch (Exception $e) {
            $this->log($campaignId, "EDIT_FAILED", "Failed to edit copy: " . $e->getMessage());
            throw $e;
        }
    }
}
