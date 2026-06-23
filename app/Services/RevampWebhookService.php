<?php

namespace App\Services;

use App\Models\Query;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes quote status updates back to Revamp when a contractor accepts or
 * declines a dispatched job query. Fire-and-forget; failures are logged
 * but never block the accept/decline flow.
 */
class RevampWebhookService
{
    /**
     * Notify Revamp that a job query was accepted or declined.
     */
    public function notifyStatusUpdate(Query $query): void
    {
        $baseUrl = rtrim((string) config('services.revamp.url'), '/');
        $apiKey = (string) config('services.revamp.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            Log::info('Revamp webhook not configured; skipping status push.', [
                'external_quote_id' => $query->external_quote_id,
            ]);

            return;
        }

        $externalQuoteId = $query->external_quote_id;

        // Gather all sibling queries for the per-recipient breakdown.
        $siblings = Query::jobs()
            ->where('external_quote_id', $externalQuoteId)
            ->with('company')
            ->get();

        $accepted = $siblings->firstWhere('response', Query::RESPONSE_ACCEPTED);

        $payload = [
            'external_quote_id' => $externalQuoteId,
            'status' => $accepted ? 'assigned' : $this->resolveOverallStatus($siblings),
            'recipients' => $siblings->map(fn (Query $q) => [
                'company_key' => $q->contractor_company_key,
                'company_name' => $q->company?->name,
                'email' => $q->company?->email,
                'phone' => $q->company?->phone,
                'response' => $q->response,
                'responded_at' => $q->responded_at?->toIso8601String(),
            ])->values(),
        ];

        if ($accepted) {
            $payload['contractor'] = [
                'company_key' => $accepted->contractor_company_key,
                'company_name' => $accepted->company?->name,
                'email' => $accepted->company?->email,
                'phone' => $accepted->company?->phone,
                'assigned_at' => $accepted->responded_at?->toIso8601String(),
            ];
        }

        try {
            Http::acceptJson()
                ->withHeaders(['X-Api-Key' => $apiKey])
                ->timeout(10)
                ->post("{$baseUrl}/webhooks/jco/quote-status", $payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to push quote status to Revamp.', [
                'external_quote_id' => $externalQuoteId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine the overall quote status from the sibling queries.
     */
    private function resolveOverallStatus($siblings): string
    {
        if ($siblings->contains(fn (Query $q) => $q->response === Query::RESPONSE_ACCEPTED)) {
            return 'assigned';
        }

        if ($siblings->every(fn (Query $q) => $q->response === Query::RESPONSE_DECLINED)) {
            return 'declined';
        }

        return 'pending';
    }
}
