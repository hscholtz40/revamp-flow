<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
            ...$this->locationRules(),
        ]);

        $location = $this->extractLocation($payload);
        unset($payload['latitude'], $payload['longitude'], $payload['location_accuracy']);

        $durationMinutes = $this->calculateDurationMinutes(
            $payload['date'],
            $payload['start_time'],
            $payload['end_time'] ?? null,
            0
        );

        $entry = TimeEntry::create([
            ...$payload,
            ...$location,
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
            ...$this->locationRules(),
        ]);

        $location = $this->extractLocation($payload);
        unset($payload['latitude'], $payload['longitude'], $payload['location_accuracy']);

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

        $timeEntry->update([
            ...$payload,
            ...$location,
        ]);

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
            ...$this->locationRules(),
        ]);

        $location = $this->extractLocation($payload);

        $entry = TimeEntry::create([
            'company_id' => $this->companyId($request),
            'user_id' => $request->user()->id,
            'jobcard_id' => $payload['jobcard_id'],
            'date' => now()->toDateString(),
            'start_time' => now()->format('H:i'),
            'started_at' => now(),
            'status' => 'running',
            'description' => $payload['description'] ?? null,
            ...$location,
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
        $payload = $request->validate($this->locationRules());
        $location = $this->extractLocation($payload);

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

        if ($location !== []) {
            // Prefer stop location from mobile when provided; keep start location otherwise.
            $entry->update($location);
        }

        return response()->json($entry->fresh());
    }

    /**
     * @return array<string, list<string>>
     */
    private function locationRules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude?: float, longitude?: float, location_accuracy?: float}
     */
    private function extractLocation(array $payload): array
    {
        $location = [];

        if (array_key_exists('latitude', $payload) && $payload['latitude'] !== null) {
            $location['latitude'] = (float) $payload['latitude'];
        }

        if (array_key_exists('longitude', $payload) && $payload['longitude'] !== null) {
            $location['longitude'] = (float) $payload['longitude'];
        }

        if (array_key_exists('location_accuracy', $payload) && $payload['location_accuracy'] !== null) {
            $location['location_accuracy'] = (float) $payload['location_accuracy'];
        }

        // Require both coordinates when either is provided.
        if (isset($location['latitude']) xor isset($location['longitude'])) {
            throw ValidationException::withMessages([
                'latitude' => ['Both latitude and longitude are required when sending a location.'],
                'longitude' => ['Both latitude and longitude are required when sending a location.'],
            ]);
        }

        return $location;
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
