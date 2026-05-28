<?php
namespace MarketingAgent\Agent;

use MarketingAgent\Service\Llm\LlmProviderInterface;
use MarketingAgent\Service\DatabaseService;
use Exception;

class CoordinatorAgent extends BaseAgent {
    private ResearcherAgent $researcher;
    private CopywriterAgent $copywriter;
    private EditorAgent $editor;

    public function __construct(LlmProviderInterface $llm, DatabaseService $db) {
        parent::__construct($llm, $db);
        $this->researcher = new ResearcherAgent($llm, $db);
        $this->copywriter = new CopywriterAgent($llm, $db);
        $this->editor = new EditorAgent($llm, $db);
    }

    public function getName(): string {
        return "Campaign Coordinator";
    }

    /**
     * Set the logging callback for all sub-agents to enable integrated streaming.
     */
    public function setLogCallback(callable $callback): void {
        parent::setLogCallback($callback);
        $this->researcher->setLogCallback($callback);
        $this->copywriter->setLogCallback($callback);
        $this->editor->setLogCallback($callback);
    }

    /**
     * Executes the multi-agent marketing campaign generation pipeline.
     * 
     * @param int $campaignId
     * @return string Final polished content
     */
    public function runCampaign(int $campaignId): string {
        $this->log($campaignId, "START_PIPELINE", "Coordinator setting up campaign pipeline.");

        $campaign = $this->db->getCampaign($campaignId, null, 'admin');
        if (!$campaign) {
            $this->log($campaignId, "PIPELINE_ERROR", "Campaign record not found.");
            throw new Exception("Campaign not found.");
        }

        $this->db->updateCampaignStatus($campaignId, 'RUNNING');

        try {
            // Step 1: Research Agent
            $researchReport = $this->researcher->research(
                $campaignId,
                $campaign['title'],
                $campaign['product_description'],
                $campaign['target_audience']
            );

            $language = $campaign['language'] ?? 'English';

            // Step 2: Copywriter Agent
            $copyDraft = $this->copywriter->writeCopy(
                $campaignId,
                $campaign['channel'],
                $researchReport,
                $campaign['product_description'],
                $language
            );

            // Step 3: Editor Agent
            $finalContent = $this->editor->editCopy(
                $campaignId,
                $copyDraft,
                $campaign['target_audience'],
                $language
            );

            // Step 4: Finalize
            $this->db->updateCampaignContent($campaignId, $finalContent, 'COMPLETED');
            $this->log($campaignId, "PIPELINE_SUCCESS", "Campaign pipeline executed successfully. Final copy generated and saved.");

            return $finalContent;

        } catch (Exception $e) {
            $this->db->updateCampaignStatus($campaignId, 'FAILED');
            $this->log($campaignId, "PIPELINE_FAILED", "Workflow execution failed: " . $e->getMessage());
            throw $e;
        }
    }
}
