<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use App\Models\User;
use App\Services\AuditService;

class AiAuditService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function redact(string $text): string
    {
        $text = preg_replace('/sk-[a-zA-Z0-9_\-]{16,}/', 'sk-***', $text ?? '') ?? '';
        $text = preg_replace('/\\b\\d{12,19}\\b/', '[redacted-number]', $text) ?? '';
        return mb_substr(trim($text), 0, 600);
    }

    public function record(
        User $user,
        string $feature,
        array $usage,
        int $latencyMs,
        int $statusCode,
        string $traceId,
        ?string $prompt = null,
        ?string $response = null,
    ): void {
        $companyId = $user->getCurrentCompany()?->id;
        AiUsage::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'feature' => $feature,
            'model' => (string) ($usage['model'] ?? ''),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'latency_ms' => max(0, $latencyMs),
            'status_code' => $statusCode,
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);

        $this->auditService->log(
            'ai_request',
            $user,
            null,
            null,
            "AI feature invoked: {$feature}",
            [
                'feature' => $feature,
                'trace_id' => $traceId,
                'status_code' => $statusCode,
                'usage' => $usage,
                'prompt_excerpt' => $prompt ? $this->redact($prompt) : null,
                'response_excerpt' => $response ? $this->redact($response) : null,
            ]
        );
    }
}
