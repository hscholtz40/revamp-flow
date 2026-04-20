<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            TimeEntry::query()
                ->where('company_id', $this->companyId($request))
                ->with(['user:id,name', 'jobcard:id,job_number,title'])
                ->orderByDesc('date')
                ->paginate(25)
        );
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'jobcard_id' => ['required', 'integer', 'exists:jobcards,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric'],
        ]);

        $entry = TimeEntry::create([
            ...$payload,
            'company_id' => $this->companyId($request),
            'user_id' => $request->user()->id,
            'status' => 'completed',
        ]);

        return response()->json($entry, 201);
    }

    public function update(Request $request, TimeEntry $timeEntry)
    {
        $this->assertCompanyRecord($request, (int) $timeEntry->company_id);
        $payload = $request->validate([
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric'],
        ]);
        $timeEntry->update($payload);

        return response()->json($timeEntry->fresh());
    }

    public function destroy(Request $request, TimeEntry $timeEntry)
    {
        $this->assertCompanyRecord($request, (int) $timeEntry->company_id);
        $timeEntry->delete();

        return response()->json(['message' => 'Time entry deleted']);
    }

    public function startTimer(Request $request)
    {
        $payload = $request->validate([
            'jobcard_id' => ['required', 'integer', 'exists:jobcards,id'],
            'description' => ['nullable', 'string'],
        ]);

        $entry = TimeEntry::create([
            'company_id' => $this->companyId($request),
            'user_id' => $request->user()->id,
            'jobcard_id' => $payload['jobcard_id'],
            'date' => now()->toDateString(),
            'start_time' => now()->format('H:i'),
            'status' => 'running',
            'description' => $payload['description'] ?? null,
        ]);

        return response()->json($entry, 201);
    }

    public function pauseTimer(Request $request)
    {
        $entry = TimeEntry::query()
            ->where('company_id', $this->companyId($request))
            ->where('user_id', $request->user()->id)
            ->where('status', 'running')
            ->latest('id')
            ->firstOrFail();

        $entry->update(['status' => 'paused']);

        return response()->json($entry->fresh());
    }

    public function resumeTimer(Request $request)
    {
        $entry = TimeEntry::query()
            ->where('company_id', $this->companyId($request))
            ->where('user_id', $request->user()->id)
            ->where('status', 'paused')
            ->latest('id')
            ->firstOrFail();

        $entry->update(['status' => 'running']);

        return response()->json($entry->fresh());
    }

    public function stopTimer(Request $request)
    {
        $entry = TimeEntry::query()
            ->where('company_id', $this->companyId($request))
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['running', 'paused'])
            ->latest('id')
            ->firstOrFail();

        $entry->update([
            'status' => 'completed',
            'end_time' => now()->format('H:i'),
        ]);

        return response()->json($entry->fresh());
    }

    private function companyId(Request $request): int
    {
        return (int) ($request->user()->getCurrentCompany()?->id ?? 0);
    }

    private function assertCompanyRecord(Request $request, int $recordCompanyId): void
    {
        abort_unless($recordCompanyId === $this->companyId($request), 404);
    }
}
