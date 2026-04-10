<?php

namespace App\Http\Controllers;

use App\Models\Jobcard;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    /**
     * Display a listing of time entries (timesheet view)
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $user = auth()->user();
        $isLimited = $user->isLimitedUser();

        $applyListFilters = function ($q) use ($request, $currentCompany, $user, $isLimited) {
            $q->where('company_id', $currentCompany->id);
            if ($isLimited) {
                $q->where('user_id', $user->id);
            } elseif ($request->filled('user_id')) {
                $q->where('user_id', $request->user_id);
            }
            if ($request->filled('jobcard_id')) {
                $q->where('jobcard_id', $request->jobcard_id);
            }
            if ($request->filled('start_date')) {
                $q->where('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $q->where('date', '<=', $request->end_date);
            }
            if ($request->has('is_billable')) {
                $q->where('is_billable', $request->boolean('is_billable'));
            }
        };

        $query = TimeEntry::with(['jobcard', 'user']);
        $applyListFilters($query);

        $timeEntries = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(50)
            ->withQueryString();

        $summaryBase = function (bool $billableOnly) use ($applyListFilters) {
            $q = TimeEntry::query();
            $applyListFilters($q);
            if ($billableOnly) {
                $q->where('is_billable', true);
            }

            return $q;
        };

        $summary = [
            'total_hours' => abs($summaryBase(false)->sum('duration_minutes')) / 60,
            'billable_hours' => abs($summaryBase(true)->sum('duration_minutes')) / 60,
            'total_amount' => abs($summaryBase(true)->get()->sum('total_amount')),
        ];

        $jobcardsQuery = Jobcard::where('company_id', $currentCompany->id);
        if ($isLimited) {
            $teamIds = $user->teams()->pluck('teams.id');
            $jobcardsQuery->where(function ($q) use ($user, $teamIds) {
                $q->where('assigned_to_user_id', $user->id);
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_team_id', $teamIds);
                }
            });
        }
        $jobcards = $jobcardsQuery->orderBy('job_number', 'desc')
            ->get(['id', 'job_number', 'title']);

        $users = $isLimited
            ? User::query()->whereKey($user->id)->get(['id', 'name'])
            : User::query()->staffSelectableForCompany($currentCompany->id)
                ->orderBy('name')
                ->get(['id', 'name']);

        $filters = $request->only(['jobcard_id', 'user_id', 'start_date', 'end_date', 'is_billable']);
        if ($isLimited) {
            $filters['user_id'] = $user->id;
        }

        return Inertia::render('time-entries/Index', [
            'timeEntries' => $timeEntries,
            'summary' => $summary,
            'filters' => $filters,
            'jobcards' => $jobcards,
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created time entry
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $cid = $currentCompany->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $jobcard = Jobcard::where('company_id', $cid)->findOrFail($validated['jobcard_id']);
        $this->authorize('view', $jobcard);

        // Calculate duration
        $durationMinutes = 0;
        if (isset($validated['start_time']) && isset($validated['end_time'])) {
            $start = \Carbon\Carbon::parse($validated['date'].' '.$validated['start_time']);
            $end = \Carbon\Carbon::parse($validated['date'].' '.$validated['end_time']);
            $durationMinutes = $start->diffInMinutes($end);
        } else {
            // Add hours and minutes together if both are provided
            if (isset($validated['duration_hours'])) {
                $durationMinutes += $validated['duration_hours'] * 60;
            }
            if (isset($validated['duration_minutes'])) {
                $durationMinutes += $validated['duration_minutes'];
            }
        }

        $timeEntry = TimeEntry::create([
            'company_id' => $currentCompany->id,
            'jobcard_id' => $validated['jobcard_id'],
            'user_id' => auth()->id(),
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'duration_minutes' => $durationMinutes,
            'hourly_rate' => $validated['hourly_rate'] ?? auth()->user()->hourly_rate ?? null,
            'is_billable' => $validated['is_billable'] ?? true,
            'description' => $validated['description'] ?? null,
            'status' => 'completed',
        ]);

        return redirect()->back()->with('success', 'Time entry created successfully');
    }

    /**
     * Update the specified time entry
     */
    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('update', $timeEntry);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Calculate duration
        $durationMinutes = 0;
        if (isset($validated['start_time']) && isset($validated['end_time'])) {
            $start = \Carbon\Carbon::parse($validated['date'].' '.$validated['start_time']);
            $end = \Carbon\Carbon::parse($validated['date'].' '.$validated['end_time']);
            $durationMinutes = $start->diffInMinutes($end);
        } else {
            // Add hours and minutes together if both are provided
            if (isset($validated['duration_hours'])) {
                $durationMinutes += $validated['duration_hours'] * 60;
            }
            if (isset($validated['duration_minutes'])) {
                $durationMinutes += $validated['duration_minutes'];
            }
            // If neither hours nor minutes are provided, keep the existing duration
            if ($durationMinutes === 0) {
                $durationMinutes = $timeEntry->duration_minutes;
            }
        }

        $timeEntry->update([
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'duration_minutes' => $durationMinutes,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'is_billable' => $validated['is_billable'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Time entry updated successfully');
    }

    /**
     * Remove the specified time entry
     */
    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return redirect()->back()->with('success', 'Time entry deleted successfully');
    }

    /**
     * Start timer for a jobcard
     */
    public function startTimer(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
            'description' => ['nullable', 'string', 'max:1000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
        ]);

        $jobcard = Jobcard::where('company_id', $cid)->findOrFail($validated['jobcard_id']);
        $this->authorize('view', $jobcard);

        // Check if there's already a running timer for this user and jobcard
        $runningEntry = TimeEntry::getRunningEntry(auth()->id(), $validated['jobcard_id']);
        if ($runningEntry) {
            return redirect()->back()->with('error', 'You already have a running timer for this jobcard');
        }

        $timeEntry = TimeEntry::create([
            'company_id' => $currentCompany->id,
            'jobcard_id' => $validated['jobcard_id'],
            'user_id' => auth()->id(),
            'date' => now()->toDateString(),
            'hourly_rate' => $validated['hourly_rate'] ?? auth()->user()->hourly_rate ?? null,
            'is_billable' => $validated['is_billable'] ?? true,
            'description' => $validated['description'] ?? null,
            'status' => 'running',
        ]);

        $timeEntry->startTimer();

        return redirect()->back()->with('success', 'Timer started');
    }

    /**
     * Stop timer for a jobcard
     */
    public function stopTimer(Request $request): RedirectResponse
    {
        $cid = auth()->user()->getCurrentCompany()->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
        ]);

        $timeEntry = TimeEntry::getRunningEntry(auth()->id(), $validated['jobcard_id']);

        if (! $timeEntry) {
            return redirect()->back()->with('error', 'No running timer found');
        }

        $timeEntry->stopTimer();

        return redirect()->back()->with('success', 'Timer stopped');
    }

    /**
     * Pause timer for a jobcard
     */
    public function pauseTimer(Request $request): RedirectResponse
    {
        $cid = auth()->user()->getCurrentCompany()->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
        ]);

        $timeEntry = TimeEntry::getRunningEntry(auth()->id(), $validated['jobcard_id']);

        if (! $timeEntry) {
            return redirect()->back()->with('error', 'No running timer found');
        }

        $timeEntry->pauseTimer();

        return redirect()->back()->with('success', 'Timer paused');
    }

    /**
     * Resume timer for a jobcard
     */
    public function resumeTimer(Request $request): RedirectResponse
    {
        $cid = auth()->user()->getCurrentCompany()->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
        ]);

        $timeEntry = TimeEntry::where('company_id', $cid)
            ->where('user_id', auth()->id())
            ->where('jobcard_id', $validated['jobcard_id'])
            ->where('status', 'paused')
            ->first();

        if (! $timeEntry) {
            return redirect()->back()->with('error', 'No paused timer found');
        }

        $timeEntry->resumeTimer();

        return redirect()->back()->with('success', 'Timer resumed');
    }

    /**
     * Convert time entries to billable line items for a jobcard
     */
    public function convertToLineItems(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $this->authorize('update', $jobcard);

        $cid = $jobcard->company_id;

        $validator = Validator::make($request->all(), [
            'time_entry_ids' => ['required', 'array'],
            'time_entry_ids.*' => [CompanyScopedRules::timeEntry($cid)],
        ]);
        $validator->after(CompanyScopedRules::afterTimeEntriesBelongToJobcard($jobcard->id, $cid));
        $validator->after(function (\Illuminate\Validation\Validator $v) use ($jobcard) {
            if (! auth()->user()->isLimitedUser()) {
                return;
            }
            $ids = $v->getData()['time_entry_ids'] ?? [];
            if (! is_array($ids)) {
                return;
            }
            foreach ($ids as $i => $id) {
                if ($id === null || $id === '') {
                    continue;
                }
                $ok = TimeEntry::query()
                    ->whereKey($id)
                    ->where('jobcard_id', $jobcard->id)
                    ->where('company_id', $jobcard->company_id)
                    ->where('user_id', auth()->id())
                    ->exists();
                if (! $ok) {
                    $v->errors()->add(
                        "time_entry_ids.{$i}",
                        'You may only convert your own time entries.'
                    );
                }
            }
        });
        $validated = $validator->validate();

        $timeEntries = TimeEntry::whereIn('id', $validated['time_entry_ids'])
            ->where('jobcard_id', $jobcard->id)
            ->where('is_billable', true)
            ->get();

        $sortOrder = $jobcard->lineItems()->max('sort_order') ?? -1;

        foreach ($timeEntries as $timeEntry) {
            $sortOrder++;

            // Create line item for time entry
            $jobcard->lineItems()->create([
                'product_id' => null,
                'description' => $timeEntry->description ?? "Time: {$timeEntry->formatted_duration}",
                'quantity' => $timeEntry->duration_hours,
                'unit_price' => $timeEntry->hourly_rate ?? 0,
                'total' => $timeEntry->total_amount,
                'sort_order' => $sortOrder,
            ]);

            // Mark time entry as converted (optional - you might want to add a flag)
            // $timeEntry->update(['converted_to_line_item' => true]);
        }

        // Recalculate jobcard totals
        $jobcard->calculateTotals();

        return redirect()->back()->with('success', count($timeEntries).' time entries converted to line items');
    }
}
