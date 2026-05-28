<?php
namespace MarketingAgent\Agent;

interface AgentInterface {
    /**
     * Get the descriptive name of the agent.
     * 
     * @return string
     */
    public function getName(): string;
}
