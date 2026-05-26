<?php
/**
 * Diagnostic CLI script to test LLM connectivity (Gemini, LM Studio, Ollama)
 * Run in terminal: php scratch/test_llm.php
 */

require_once __DIR__ . '/../autoload.php';

use MarketingAgent\Service\DatabaseService;
use MarketingAgent\Service\Llm\LlmFactory;

echo "==================================================\n";
echo "           LLM Connectivity Diagnostic            \n";
echo "==================================================\n\n";

try {
    $db = new DatabaseService();
    $settings = $db->getSettings();

    $provider = $settings['llm_provider'] ?? 'gemini';
    echo "Active Provider: " . strtoupper($provider) . "\n";

    if ($provider === 'gemini') {
        echo "Model: " . ($settings['gemini_model'] ?? 'gemini-1.5-flash') . "\n";
        echo "API Key configured: " . (empty($settings['gemini_api_key']) ? "NO (Please configure in dashboard)" : "YES") . "\n";
    } elseif ($provider === 'lm_studio') {
        echo "Endpoint URL: " . ($settings['lm_studio_url'] ?? 'http://localhost:1234/v1') . "\n";
        echo "Model: " . ($settings['lm_studio_model'] ?? 'qwen2.5-7b-instruct') . "\n";
    } elseif ($provider === 'ollama') {
        echo "Endpoint URL: " . ($settings['ollama_url'] ?? 'http://localhost:11434') . "\n";
        echo "Model: " . ($settings['ollama_model'] ?? 'llama3') . "\n";
    }

    echo "\nSending test query 'Why is the sky blue?' to LLM...\n";
    echo "Waiting for completion response...\n";

    $llm = LlmFactory::create($db);
    $response = $llm->generate(
        "You are a helpful assistant. Keep your answer under 2 sentences.",
        "Why is the sky blue?"
    );

    echo "\n[LLM RESPONSE SUCCESS]:\n";
    echo "--------------------------------------------------\n";
    echo trim($response) . "\n";
    echo "--------------------------------------------------\n";
    echo "\nDiagnostic completed successfully!\n";

} catch (Exception $e) {
    echo "\n[DIAGNOSTIC ERROR]: " . $e->getMessage() . "\n";
    echo "Please ensure the LLM local server is running (if Ollama/LM Studio) or your Gemini API key is correct.\n";
}
echo "==================================================\n";
