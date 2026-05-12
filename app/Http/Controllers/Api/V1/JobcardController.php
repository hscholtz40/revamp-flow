<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Jobcard;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class JobcardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->input('company_id') ?: $request->user()?->getCurrentCompany()?->id;
        $user = $request->user();

        $teamIds = $user?->teams()->where('teams.company_id', $companyId)->pluck('teams.id') ?? collect();

        $query = Jobcard::query()
            ->with(['customer:id,name', 'assignedUser:id,name', 'assignedTeam:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('assigned_to_user_id', $user?->id);
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_team_id', $teamIds);
                }
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(25));
    }

    public function show(Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $jobcard->load(['customer', 'assignedUser', 'assignedTeam', 'lineItems', 'timeEntries']);

        return response()->json($jobcard);
    }

    public function store(Request $request)
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:new,needs_scheduling,scheduled,dispatched,accepted,en_route,on_site,paused,waiting_for_parts,needs_follow_up,emergency,completed,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        $jobcard = Jobcard::create([
            ...$payload,
            'company_id' => $companyId,
            'job_number' => Jobcard::generateJobNumber($companyId),
            'status' => $payload['status'] ?? 'new',
        ]);

        return response()->json($jobcard, 201);
    }

    public function update(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
            'dispatch_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', 'nullable', 'in:low,normal,high,urgent'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        $jobcard->update($payload);

        return response()->json($jobcard->fresh());
    }

    public function destroy(Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $jobcard->delete();

        return response()->json(['message' => 'Jobcard deleted']);
    }

    public function updateStatus(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $payload = $request->validate([
            'status' => ['required', 'in:new,needs_scheduling,scheduled,dispatched,accepted,en_route,on_site,paused,waiting_for_parts,needs_follow_up,emergency,completed,cancelled'],
        ]);

        $jobcard->update($payload);

        return response()->json([
            'message' => 'Status updated',
            'jobcard' => $jobcard->fresh(),
        ]);
    }

    public function convertTimeEntries(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $entryIds = $request->validate([
            'time_entry_ids' => ['required', 'array', 'min:1'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
        ])['time_entry_ids'];

        $entries = TimeEntry::query()
            ->whereIn('id', $entryIds)
            ->where('jobcard_id', $jobcard->id)
            ->get();

        return response()->json([
            'message' => 'Time entries ready for conversion',
            'count' => $entries->count(),
            'entries' => $entries,
        ]);
    }

    private function assertCompanyScope(Jobcard $jobcard): void
    {
        $companyId = request()->input('company_id')
            ?? (int) (request()->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $jobcard->company_id === $companyId, 404);
    }
}
