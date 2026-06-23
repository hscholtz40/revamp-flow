<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Query;
use App\Services\ContractorMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * External quote integration (e.g. the Revamp quotes module). Authenticated by a
 * shared API key (query.api.auth). Revamp dispatches a quote's job location here;
 * JCO finds the nearest licensed contractors and creates a job Query for each.
 */
class QuoteIntegrationController extends Controller
{
    /**
     * Match the nearest licensed contractors to a job location and create a job
     * Query for each. Idempotent per external_quote_id.
     */
    public function dispatchQuote(Request $request, ContractorMatchingService $matcher): JsonResponse
    {
        $validated = $request->validate([
            'external_quote_id' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'client.name' => ['required', 'string', 'max:255'],
            'client.surname' => ['nullable', 'string', 'max:255'],
            'client.email' => ['required', 'email', 'max:255'],
            'client.cell' => ['required', 'string', 'max:50'],
            'job.description' => ['required', 'string', 'max:5000'],
            'job.address' => ['nullable', 'string', 'max:500'],
            'job.latitude' => ['required', 'numeric', 'between:-90,90'],
            'job.longitude' => ['required', 'numeric', 'between:-180,180'],
            'job.line_items' => ['nullable', 'array'],
            'job.line_items.*.group' => ['nullable', 'string', 'max:255'],
            'job.line_items.*.description' => ['required_with:job.line_items', 'string', 'max:1000'],
            'job.line_items.*.quantity' => ['required_with:job.line_items', 'numeric', 'min:0'],
            'job.line_items.*.unit_price' => ['required_with:job.line_items', 'numeric', 'min:0'],
            'job.line_items.*.line_total' => ['required_with:job.line_items', 'numeric', 'min:0'],
            'job.total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Idempotent: if this quote was already dispatched, return the existing matches.
        $existing = Query::jobs()->where('external_quote_id', $validated['external_quote_id'])->get();
        if ($existing->isNotEmpty()) {
            return response()->json([
                'external_quote_id' => $validated['external_quote_id'],
                'already_dispatched' => true,
                'matched' => $existing->load('company')->map(fn (Query $q) => [
                    'company_key' => $q->contractor_company_key,
                    'company_name' => $q->company?->name,
                    'email' => $q->company?->email,
                    'phone' => $q->company?->phone,
                    'response' => $q->response,
                ])->values(),
            ]);
        }

        $contractors = $matcher->nearest(
            (float) $validated['job']['latitude'],
            (float) $validated['job']['longitude'],
            (int) ($validated['limit'] ?? 3),
        );

        if ($contractors->isEmpty()) {
            return response()->json([
                'external_quote_id' => $validated['external_quote_id'],
                'matched' => [],
                'message' => 'No licensed contractors with a location are available near this job.',
            ], 200);
        }

        $matched = DB::transaction(function () use ($contractors, $validated) {
            return $contractors->map(function ($license) use ($validated) {
                Query::create([
                    'company_id' => $license->company_id,
                    'kind' => Query::KIND_JOB,
                    'external_source' => $validated['source'] ?? 'revamp',
                    'external_quote_id' => $validated['external_quote_id'],
                    'contractor_company_key' => $license->license_key,
                    'name' => $validated['client']['name'],
                    'surname' => $validated['client']['surname'] ?? '',
                    'email' => $validated['client']['email'],
                    'cell' => $validated['client']['cell'],
                    'description' => $validated['job']['description'],
                    'status' => Query::STATUS_OPEN,
                    'response' => Query::RESPONSE_PENDING,
                    'job_location' => $validated['job']['address'] ?? null,
                    'job_latitude' => $validated['job']['latitude'],
                    'job_longitude' => $validated['job']['longitude'],
                    'quote_line_items' => $validated['job']['line_items'] ?? null,
                    'quote_total_amount' => $validated['job']['total_amount'] ?? null,
                    'quote_client_email' => $validated['client']['email'],
                    'quote_client_phone' => $validated['client']['cell'],
                ]);

                return [
                    'company_key' => $license->license_key,
                    'company_name' => $license->company?->name,
                    'email' => $license->company?->email,
                    'phone' => $license->company?->phone,
                    'distance_km' => round((float) $license->distance_km, 2),
                ];
            })->values();
        });

        return response()->json([
            'external_quote_id' => $validated['external_quote_id'],
            'matched' => $matched,
        ], 201);
    }

    /**
     * Report the assignment status of a dispatched quote so the caller can pull
     * the winning contractor's company details once one has accepted.
     */
    public function status(string $externalQuoteId): JsonResponse
    {
        $jobs = Query::jobs()->where('external_quote_id', $externalQuoteId)->with('company')->get();

        if ($jobs->isEmpty()) {
            return response()->json(['message' => 'No dispatched job found for this quote.'], 404);
        }

        // Per-recipient breakdown so the caller can mirror the true audit trail
        // (pending / accepted / declined / expired) rather than guessing.
        $recipients = $jobs->map(fn (Query $q) => [
            'company_key' => $q->contractor_company_key,
            'company_name' => $q->company?->name,
            'email' => $q->company?->email,
            'phone' => $q->company?->phone,
            'response' => $q->response,
            'responded_at' => $q->responded_at?->toIso8601String(),
        ])->values();

        $accepted = $jobs->firstWhere('response', Query::RESPONSE_ACCEPTED);

        if ($accepted) {
            return response()->json([
                'external_quote_id' => $externalQuoteId,
                'status' => 'assigned',
                'contractor' => [
                    'company_key' => $accepted->contractor_company_key,
                    'company_name' => $accepted->company?->name,
                    'email' => $accepted->company?->email,
                    'phone' => $accepted->company?->phone,
                    'assigned_at' => $accepted->responded_at?->toIso8601String(),
                ],
                'contractors_notified' => $jobs->count(),
                'recipients' => $recipients,
            ]);
        }

        $allDeclined = $jobs->every(fn (Query $q) => $q->response === Query::RESPONSE_DECLINED);

        return response()->json([
            'external_quote_id' => $externalQuoteId,
            'status' => $allDeclined ? 'declined' : 'pending',
            'contractors_notified' => $jobs->count(),
            'recipients' => $recipients,
        ]);
    }
}
