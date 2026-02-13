<?php

namespace App\Http\Controllers;

use App\Models\Jobcard;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        
        $query = TimeEntry::with(['jobcard', 'user'])
            ->where('company_id', $currentCompany->id);
        
        // Limited users can only see their own time entries
        if (auth()->user()->isLimitedUser()) {
            $query->where('user_id', auth()->id());
        }
        
        // Filter by jobcard
        if ($request->filled('jobcard_id')) {
            $query->where('jobcard_id', $request->jobcard_id);
        }
        
        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        
        // Filter by billable status
        if ($request->has('is_billable')) {
            $query->where('is_billable', $request->boolean('is_billable'));
        }
        
        $timeEntries = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(50)
            ->withQueryString();
        
        // Get summary statistics
        $summary = [
            'total_hours' => abs(TimeEntry::where('company_id', $currentCompany->id)
                ->when($request->filled('jobcard_id'), fn($q) => $q->where('jobcard_id', $request->jobcard_id))
                ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->filled('start_date'), fn($q) => $q->where('date', '>=', $request->start_date))
                ->when($request->filled('end_date'), fn($q) => $q->where('date', '<=', $request->end_date))
                ->sum('duration_minutes')) / 60,
            'billable_hours' => abs(TimeEntry::where('company_id', $currentCompany->id)
                ->where('is_billable', true)
                ->when($request->filled('jobcard_id'), fn($q) => $q->where('jobcard_id', $request->jobcard_id))
                ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->filled('start_date'), fn($q) => $q->where('date', '>=', $request->start_date))
                ->when($request->filled('end_date'), fn($q) => $q->where('date', '<=', $request->end_date))
                ->sum('duration_minutes')) / 60,
            'total_amount' => abs(TimeEntry::where('company_id', $currentCompany->id)
                ->where('is_billable', true)
                ->when($request->filled('jobcard_id'), fn($q) => $q->where('jobcard_id', $request->jobcard_id))
                ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->when($request->filled('start_date'), fn($q) => $q->where('date', '>=', $request->start_date))
                ->when($request->filled('end_date'), fn($q) => $q->where('date', '<=', $request->end_date))
                ->get()
                ->sum('total_amount')),
        ];
        
        // Get jobcards and users for filters
        $jobcards = Jobcard::where('company_id', $currentCompany->id)
            ->orderBy('job_number', 'desc')
            ->get(['id', 'job_number', 'title']);
        
        $users = \App\Models\User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        return Inertia::render('time-entries/Index', [
            'timeEntries' => $timeEntries,
            'summary' => $summary,
            'filters' => $request->only(['jobcard_id', 'user_id', 'start_date', 'end_date', 'is_billable']),
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
        
        $validated = $request->validate([
            'jobcard_id' => ['required', 'exists:jobcards,id'],
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
            $start = \Carbon\Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
            $end = \Carbon\Carbon::parse($validated['date'] . ' ' . $validated['end_time']);
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
            $start = \Carbon\Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
            $end = \Carbon\Carbon::parse($validated['date'] . ' ' . $validated['end_time']);
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
        // Limited users cannot delete time entries
        if (auth()->user()->isLimitedUser()) {
            abort(403, 'Your account does not have permission to delete time entries.');
        }

        $timeEntry->delete();
        
        return redirect()->back()->with('success', 'Time entry deleted successfully');
    }

    /**
     * Start timer for a jobcard
     */
    public function startTimer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'jobcard_id' => ['required', 'exists:jobcards,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['boolean'],
        ]);
        
        $currentCompany = auth()->user()->getCurrentCompany();
        
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
        $validated = $request->validate([
            'jobcard_id' => ['required', 'exists:jobcards,id'],
        ]);
        
        $timeEntry = TimeEntry::getRunningEntry(auth()->id(), $validated['jobcard_id']);
        
        if (!$timeEntry) {
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
        $validated = $request->validate([
            'jobcard_id' => ['required', 'exists:jobcards,id'],
        ]);
        
        $timeEntry = TimeEntry::getRunningEntry(auth()->id(), $validated['jobcard_id']);
        
        if (!$timeEntry) {
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
        $validated = $request->validate([
            'jobcard_id' => ['required', 'exists:jobcards,id'],
        ]);
        
        $timeEntry = TimeEntry::where('user_id', auth()->id())
            ->where('jobcard_id', $validated['jobcard_id'])
            ->where('status', 'paused')
            ->first();
        
        if (!$timeEntry) {
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
        $validated = $request->validate([
            'time_entry_ids' => ['required', 'array'],
            'time_entry_ids.*' => ['exists:time_entries,id'],
        ]);
        
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
        
        return redirect()->back()->with('success', count($timeEntries) . ' time entries converted to line items');
    }
}
