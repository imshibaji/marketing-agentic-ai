<?php
namespace MarketingAgent\Service\Llm;

interface LlmProviderInterface {
    /**
     * Generate content from the LLM model.
     * 
     * @param string $systemPrompt Instructions for the LLM behavior
     * @param string $userPrompt User input / task parameters
     * @param float $temperature Creativity parameter (0.0 to 1.0)
     * @return string Generated completion
     */
    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string;
}
