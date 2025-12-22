<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    /**
     * Display a listing of backups.
     */
    public function index(Request $request): Response
    {
        $query = Backup::query()
            ->with(['user'])
            ->orderBy('created_at', 'desc');

        $backups = $query->paginate(20)->withQueryString();

        $schedules = BackupSchedule::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('backups/Index', [
            'backups' => $backups,
            'schedules' => $schedules,
            'stats' => [
                'total_backups' => Backup::count(),
                'total_size' => Backup::where('status', 'completed')->sum('file_size'),
                'last_backup' => Backup::where('status', 'completed')->latest('completed_at')->first()?->completed_at,
            ],
        ]);
    }

    /**
     * Create a new backup.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        
        try {
            $backup = $this->backupService->createBackup(
                null, // No company - system-wide backup
                auth()->id(),
                'manual',
                'local' // Only local storage supported
            );

            return redirect()->route('backups.index')
                ->with('success', 'Backup created successfully');
        } catch (\Exception $e) {
            return redirect()->route('backups.index')
                ->with('error', 'Failed to create backup: ' . $e->getMessage());
        }
    }

    /**
     * Download a backup.
     */
    public function download(Backup $backup)
    {
        $filePath = storage_path('app/' . $backup->file_path);
        
        if (!file_exists($filePath)) {
            \Log::error('Backup file not found', [
                'backup_id' => $backup->id,
                'file_path' => $filePath,
                'stored_path' => $backup->file_path,
            ]);
            abort(404, 'Backup file not found');
        }

        $fileName = $backup->file_name ?: basename($filePath);
        
        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Restore a backup.
     */
    public function restore(Backup $backup): RedirectResponse
    {
        if (!$backup->isCompleted()) {
            return redirect()->route('backups.index')
                ->with('error', 'Cannot restore an incomplete backup');
        }

        try {
            \Log::info('Starting backup restore', [
                'backup_id' => $backup->id,
                'backup_file' => $backup->file_path,
            ]);
            
            $this->backupService->restoreBackup($backup);

            \Log::info('Backup restore completed successfully', [
                'backup_id' => $backup->id,
            ]);

            return redirect()->route('backups.index')
                ->with('success', 'Backup restored successfully');
        } catch (\Exception $e) {
            \Log::error('Backup restore failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('backups.index')
                ->with('error', 'Failed to restore backup: ' . $e->getMessage());
        }
    }

    /**
     * Delete a backup.
     */
    public function destroy(Backup $backup): RedirectResponse
    {
        try {
            $this->backupService->deleteBackup($backup);

            return redirect()->route('backups.index')
                ->with('success', 'Backup deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('backups.index')
                ->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }

    /**
     * Store a backup schedule.
     */
    public function storeSchedule(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'timezone' => 'required|string|max:50',
            'day_of_week' => 'required_if:frequency,weekly|nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'required_if:frequency,monthly|nullable|integer|min:1|max:31',
            'retention_days' => 'required|integer|min:1|max:365',
        ]);

        $schedule = BackupSchedule::create([
            'company_id' => null, // System-wide schedule
            'name' => $request->name,
            'frequency' => $request->frequency,
            'time' => $request->time,
            'timezone' => $request->timezone,
            'day_of_week' => $request->day_of_week,
            'day_of_month' => $request->day_of_month,
            'storage_type' => 'local', // Only local storage supported
            'retention_days' => $request->retention_days,
            'is_active' => true,
        ]);

        $schedule->calculateNextRun();

        return redirect()->route('backups.index')
            ->with('success', 'Backup schedule created successfully');
    }

    /**
     * Update a backup schedule.
     */
    public function updateSchedule(Request $request, BackupSchedule $schedule): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'timezone' => 'required|string|max:50',
            'day_of_week' => 'required_if:frequency,weekly|nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'required_if:frequency,monthly|nullable|integer|min:1|max:31',
            'retention_days' => 'required|integer|min:1|max:365',
            'is_active' => 'boolean',
        ]);

        $schedule->update([
            'name' => $request->name,
            'frequency' => $request->frequency,
            'time' => $request->time,
            'timezone' => $request->timezone,
            'day_of_week' => $request->day_of_week,
            'day_of_month' => $request->day_of_month,
            'storage_type' => 'local', // Only local storage supported
            'retention_days' => $request->retention_days,
            'is_active' => $request->boolean('is_active', $schedule->is_active),
        ]);

        $schedule->calculateNextRun();

        return redirect()->route('backups.index')
            ->with('success', 'Backup schedule updated successfully');
    }

    /**
     * Delete a backup schedule.
     */
    public function destroySchedule(BackupSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('backups.index')
            ->with('success', 'Backup schedule deleted successfully');
    }
}
