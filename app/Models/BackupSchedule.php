<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    use ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'name',
        'frequency',
        'time',
        'timezone',
        'day_of_week',
        'day_of_month',
        'storage_type',
        'is_active',
        'retention_days',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'retention_days' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function calculateNextRun(): void
    {
        $timezone = $this->timezone ?: 'UTC';

        // Parse the time in the schedule's timezone
        $timeParts = explode(':', $this->time);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);

        // Get current time in the schedule's timezone
        $nowInTimezone = now($timezone);

        switch ($this->frequency) {
            case 'daily':
                $nextRun = $nowInTimezone->copy()->setTime($hour, $minute, 0);
                if ($nextRun->lte($nowInTimezone)) {
                    $nextRun->addDay();
                }
                break;

            case 'weekly':
                $dayOfWeek = $this->day_of_week ?: 'monday';
                $dayMap = [
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                    'thursday' => 4, 'friday' => 5, 'saturday' => 6,
                ];
                $targetDay = $dayMap[strtolower($dayOfWeek)] ?? 1;
                $nextRun = $nowInTimezone->copy()->next($targetDay)->setTime($hour, $minute, 0);
                break;

            case 'monthly':
                $dayOfMonth = $this->day_of_month ?: 1;
                $nextRun = $nowInTimezone->copy()->day($dayOfMonth)->setTime($hour, $minute, 0);
                if ($nextRun->lte($nowInTimezone)) {
                    $nextRun->addMonth();
                }
                break;

            default:
                $nextRun = $nowInTimezone->addDay();
        }

        // Convert to UTC for storage
        $this->next_run_at = $nextRun->utc();
        $this->save();
    }
}
