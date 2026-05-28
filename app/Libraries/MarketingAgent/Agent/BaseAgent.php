<?php
namespace MarketingAgent\Agent;

use MarketingAgent\Service\Llm\LlmProviderInterface;
use MarketingAgent\Service\DatabaseService;

abstract class BaseAgent implements AgentInterface {
    protected LlmProviderInterface $llm;
    protected DatabaseService $db;
    /** @var callable|null */
    protected $logCallback = null;

    public function __construct(LlmProviderInterface $llm, DatabaseService $db) {
        $this->llm = $llm;
        $this->db = $db;
    }

    /**
     * Sets a callback that is fired whenever the agent performs an action.
     * Useful for real-time Server-Sent Events (SSE) streaming.
     * 
     * @param callable $callback function(string $agentName, string $action, string $logText)
     */
    public function setLogCallback(callable $callback): void {
        $this->logCallback = $callback;
    }

    /**
     * Log an action to database and trigger SSE stream callback.
     */
    protected function log(int $campaignId, string $action, string $logText): void {
        // Save to SQLite
        $this->db->logAgentAction($campaignId, $this->getName(), $action, $logText);

        // Execute streaming callback if defined
        if ($this->logCallback !== null) {
            call_user_func($this->logCallback, $this->getName(), $action, $logText);
        }
    }
}
