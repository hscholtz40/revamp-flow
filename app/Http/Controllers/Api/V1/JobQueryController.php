<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Query;
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
     * Accept a job. First contractor to accept wins; siblings are auto-declined.
     */
    public function accept(Request $request, Query $query): JsonResponse
    {
        $this->assertActionable($request, $query);

        $alreadyTaken = Query::jobs()
            ->where('external_quote_id', $query->external_quote_id)
            ->where('response', Query::RESPONSE_ACCEPTED)
            ->exists();

        if ($alreadyTaken) {
            return response()->json([
                'message' => 'This job has already been accepted by another contractor.',
            ], 409);
        }

        DB::transaction(function () use ($query) {
            $query->update([
                'response' => Query::RESPONSE_ACCEPTED,
                'responded_at' => now(),
            ]);

            // First-accept-wins: decline the other contractors' copies of this job.
            Query::jobs()
                ->where('external_quote_id', $query->external_quote_id)
                ->where('id', '!=', $query->id)
                ->where('response', Query::RESPONSE_PENDING)
                ->update([
                    'response' => Query::RESPONSE_DECLINED,
                    'responded_at' => now(),
                    'status' => Query::STATUS_CLOSED,
                ]);
        });

        return response()->json([
            'message' => 'Job accepted.',
            'data' => $this->transform($query->fresh()),
        ]);
    }

    /**
     * Decline a job for this contractor only.
     */
    public function decline(Request $request, Query $query): JsonResponse
    {
        $this->assertActionable($request, $query);

        $query->update([
            'response' => Query::RESPONSE_DECLINED,
            'responded_at' => now(),
            'status' => Query::STATUS_CLOSED,
        ]);

        return response()->json([
            'message' => 'Job declined.',
            'data' => $this->transform($query->fresh()),
        ]);
    }

    /**
     * Ensure the query is an actionable, still-pending job for the user's company.
     */
    private function assertActionable(Request $request, Query $query): void
    {
        $company = $request->user()->getCurrentCompany();

        abort_if($company === null || (int) $query->company_id !== (int) $company->id, 404);
        abort_unless($query->kind === Query::KIND_JOB, 404);

        if ($query->response !== Query::RESPONSE_PENDING) {
            abort(422, 'This job has already been responded to.');
        }
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
