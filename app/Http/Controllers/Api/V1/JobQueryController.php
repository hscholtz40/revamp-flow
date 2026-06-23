<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\JobQueryNotActionableException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\LineGroup;
use App\Models\Note;
use App\Models\Query;
use App\Services\CustomerUpsertService;
use App\Services\JobQueryService;
use App\Services\RevampWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contractor-facing job queries for the mobile app. A "job" Query is dispatched
 * to a contractor's company; the contractor accepts or declines from the app and
 * the first accept wins.
 */
class JobQueryController extends Controller
{
    /**
     * List job queries dispatched to the authenticated contractor's company.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->getCurrentCompany();
        abort_if($company === null, 403, 'No active company for this user.');

        $status = $request->string('response')->toString(); // optional filter

        $jobs = Query::jobs()
            ->where('company_id', $company->id)
            ->when(
                in_array($status, [Query::RESPONSE_PENDING, Query::RESPONSE_ACCEPTED, Query::RESPONSE_DECLINED], true),
                fn ($q) => $q->where('response', $status)
            )
            ->orderByRaw("CASE WHEN response = ? THEN 0 ELSE 1 END", [Query::RESPONSE_PENDING])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Query $q) => $this->transform($q));

        return response()->json(['data' => $jobs]);
    }

    /**
     * Accept a job. First contractor to accept wins (atomic claim); the other
     * contractors' still-pending copies are expired.
     */
    public function accept(Request $request, Query $query, JobQueryService $service, RevampWebhookService $webhook): JsonResponse
    {
        $this->assertOwnedJob($request, $query);

        try {
            $accepted = $service->accept($query);
        } catch (JobQueryNotActionableException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $e->reason === JobQueryNotActionableException::REASON_TAKEN ? 409 : 422,
            );
        }

        // Push status update to Revamp (fire-and-forget).
        if ($query->external_source === 'revamp') {
            $webhook->notifyStatusUpdate($query);
        }

        return response()->json([
            'message' => 'Job accepted.',
            'data' => $this->transform($accepted),
        ]);
    }

    /**
     * Decline a job for this contractor only.
     */
    public function decline(Request $request, Query $query, JobQueryService $service, RevampWebhookService $webhook): JsonResponse
    {
        $this->assertOwnedJob($request, $query);

        try {
            $declined = $service->decline($query);
        } catch (JobQueryNotActionableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Push status update to Revamp (fire-and-forget).
        if ($query->external_source === 'revamp') {
            $webhook->notifyStatusUpdate($query);
        }

        return response()->json([
            'message' => 'Job declined.',
            'data' => $this->transform($declined),
        ]);
    }

    /**
     * Convert an accepted Revamp quote query into a jobcard.
     *
     * Finds or auto-creates a Customer from the query's client details,
     * then creates a Jobcard with line items sourced from the quote JSON.
     */
    public function convertToJobcard(Request $request, Query $query, CustomerUpsertService $customerUpsert): JsonResponse
    {
        $this->assertOwnedJob($request, $query);

        abort_unless($query->response === Query::RESPONSE_ACCEPTED, 400, 'This query has not been accepted yet.');
        abort_if(empty($query->quote_line_items), 400, 'This query has no quote line items to convert.');

        $company = $request->user()->getCurrentCompany();
        abort_if($company === null, 403, 'No active company.');

        // Check if already converted
        $existingJobcard = Jobcard::where('company_id', $company->id)
            ->where('source_type', 'query')
            ->where('source_id', $query->id)
            ->first();
        if ($existingJobcard) {
            return response()->json([
                'message' => 'This query has already been converted to a jobcard.',
                'data' => ['jobcard_id' => $existingJobcard->id, 'job_number' => $existingJobcard->job_number],
            ]);
        }

        // Find or create customer from the query's client details
        $clientEmail = $query->quote_client_email ?? $query->email;
        $clientName = trim($query->name . ' ' . $query->surname);

        $customer = null;
        if ($clientEmail) {
            $customer = Customer::where('company_id', $company->id)
                ->where('email', $clientEmail)
                ->first();
        }

        if (! $customer) {
            $customer = $customerUpsert->quickCreateForCompany([
                'name' => $clientName ?: 'Revamp Client',
                'email' => $clientEmail ?? '',
                'phone' => $query->quote_client_phone ?? $query->cell ?? null,
            ], $company->id);
        }

        $jobcard = DB::transaction(function () use ($query, $company, $customer) {
            $jobcard = Jobcard::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'email' => $query->quote_client_email ?? $query->email,
                'phone' => $query->quote_client_phone ?? $query->cell,
                'service_address' => $query->job_location,
                'job_number' => Jobcard::generateJobNumber($company->id),
                'title' => 'Quote from Revamp - ' . $query->external_quote_id,
                'description' => $query->description,
                'status' => 'new',
                'total' => $query->quote_total_amount ?? 0,
                'source_type' => 'query',
                'source_id' => $query->id,
            ]);

            $defaultGroup = LineGroup::createDefaultFor($jobcard);

            $lineItems = $query->quote_line_items ?? [];
            $sortOrder = 0;
            foreach ($lineItems as $item) {
                JobcardLineItem::create([
                    'jobcard_id' => $jobcard->id,
                    'line_group_id' => $defaultGroup->id,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['line_total'] ?? ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    'sort_order' => $sortOrder++,
                ]);
            }

            $jobcard->calculateTotals();

            // Extract only the "Notes: ..." portion from the bottom of the description
            $noteContent = null;
            if (preg_match('/\nNotes:\s*(.+)$/s', $query->description, $m)) {
                $noteContent = trim($m[1]);
            }

            if ($noteContent !== null) {
                $note = new Note([
                    'company_id' => $company->id,
                    'user_id' => $request->user()->id,
                    'subject' => 'Notes',
                    'description' => $noteContent,
                ]);
                $note->noteable()->associate($jobcard);
                $note->save();
            }

            return $jobcard;
        });

        return response()->json([
            'message' => 'Jobcard created from Revamp quote.',
            'data' => [
                'jobcard_id' => $jobcard->id,
                'job_number' => $jobcard->job_number,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ],
                'total' => $jobcard->total,
            ],
        ]);
    }

    /**
     * Ensure the query is a job belonging to the authenticated user's company.
     * (Whether it is still actionable is enforced atomically by JobQueryService.)
     */
    private function assertOwnedJob(Request $request, Query $query): void
    {
        $company = $request->user()->getCurrentCompany();

        abort_if($company === null || (int) $query->company_id !== (int) $company->id, 404);
        abort_unless($query->kind === Query::KIND_JOB, 404);
    }

    private function transform(Query $query): array
    {
        return [
            'id' => $query->id,
            'external_quote_id' => $query->external_quote_id,
            'client' => [
                'name' => trim($query->name.' '.$query->surname),
                'email' => $query->email,
                'cell' => $query->cell,
            ],
            'description' => $query->description,
            'job_location' => $query->job_location,
            'job_latitude' => $query->job_latitude,
            'job_longitude' => $query->job_longitude,
            'response' => $query->response,
            'responded_at' => $query->responded_at?->toIso8601String(),
            'created_at' => $query->created_at?->toIso8601String(),
        ];
    }
}
