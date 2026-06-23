<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\JobQueryNotActionableException;
use App\Http\Controllers\Controller;
use App\Models\Query;
use App\Services\JobQueryService;
use App\Services\RevampWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
