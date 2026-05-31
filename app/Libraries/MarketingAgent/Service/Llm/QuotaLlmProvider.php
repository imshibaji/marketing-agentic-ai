<?php
namespace MarketingAgent\Service\Llm;

use MarketingAgent\Service\DatabaseService;
use Exception;

class QuotaLlmProvider implements LlmProviderInterface {
    private LlmProviderInterface $inner;
    private DatabaseService $db;
    private ?int $userId;

    public function __construct(LlmProviderInterface $inner, DatabaseService $db, ?int $userId) {
        $this->inner = $inner;
        $this->db = $db;
        $this->userId = $userId;
    }

    public function generate(string $systemPrompt, string $userPrompt, float $temperature = 0.7): string {
        if ($this->userId !== null) {
            $user = $this->db->getUserById($this->userId);
            if ($user && $user['plan_llm'] !== -1) {
                if ($user['llm_usage'] >= $user['plan_llm']) {
                    throw new Exception("Your limits are over. Please contact your service provider.");
                }
            }
        }

        $result = $this->inner->generate($systemPrompt, $userPrompt, $temperature);

        if ($this->userId !== null) {
            $this->db->incrementLlmUsage($this->userId);
        }

        return $result;
    }
}
