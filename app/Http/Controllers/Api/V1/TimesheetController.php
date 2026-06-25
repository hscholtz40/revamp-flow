<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $durationMinutes = $this->calculateDurationMinutes(
            $payload['date'],
            $payload['start_time'],
            $payload['end_time'] ?? null,
            0
        );

        $entry = TimeEntry::create([
            ...$payload,
            'company_id' => $this->companyId($request),
            'user_id' => $request->user()->id,
            'duration_minutes' => $durationMinutes,
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

        $effectiveDate = $payload['date'] ?? $timeEntry->date?->toDateString() ?? now()->toDateString();
        $effectiveStartTime = $payload['start_time'] ?? $timeEntry->start_time?->format('H:i');
        $effectiveEndTime = $payload['end_time'] ?? $timeEntry->end_time?->format('H:i');

        if ($effectiveStartTime !== null) {
            $payload['duration_minutes'] = $this->calculateDurationMinutes(
                $effectiveDate,
                $effectiveStartTime,
                $effectiveEndTime,
                (int) $timeEntry->duration_minutes
            );
        }

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
            'started_at' => now(),
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

        if ($entry->started_at) {
            $entry->pauseTimer();
        } else {
            $entry->update(['status' => 'paused']);
        }

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

        $entry->resumeTimer();

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

        if ($entry->status === 'running' && $entry->started_at) {
            $entry->stopTimer();
        } else {
            $entry->update([
                'status' => 'completed',
                'end_time' => now()->format('H:i'),
            ]);
        }

        return response()->json($entry->fresh());
    }

    private function calculateDurationMinutes(
        string $date,
        string $startTime,
        ?string $endTime,
        int $fallbackMinutes
    ): int {
        if ($endTime === null) {
            return $fallbackMinutes;
        }

        $start = Carbon::parse("{$date} {$startTime}");
        $end = Carbon::parse("{$date} {$endTime}");

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
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
