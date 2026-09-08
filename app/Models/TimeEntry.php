<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'jobcard_id',
        'user_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'hourly_rate',
        'is_billable',
        'description',
        'status',
        'started_at',
        'latitude',
        'longitude',
        'location_accuracy',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'hourly_rate' => 'decimal:2',
        'is_billable' => 'boolean',
        'status' => 'string',
        'started_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
    ];

    protected $appends = [
        'duration_hours',
        'formatted_duration',
        'total_amount',
        'formatted_total_amount',
        'formatted_time_range',
        'has_location',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        return round(abs($this->duration_minutes) / 60, 2);
    }

    /**
     * Get formatted duration (e.g., "2h 30m" or "1.5h")
     */
    public function getFormattedDurationAttribute(): string
    {
        $minutes = abs($this->duration_minutes);
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$remainingMinutes}m";
        }
    }

    /**
     * Calculate total amount (duration * hourly_rate)
     */
    public function getTotalAmountAttribute(): float
    {
        if (! $this->is_billable || ! $this->hourly_rate) {
            return 0;
        }

        return round((abs($this->duration_minutes) / 60) * $this->hourly_rate, 2);
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return 'R'.number_format($this->total_amount, 2);
    }

    /**
     * Get recorded start–end time range for display.
     */
    public function getFormattedTimeRangeAttribute(): string
    {
        $start = $this->start_time?->format('H:i');
        $end = $this->end_time?->format('H:i');

        if ($start && $end) {
            return "{$start} – {$end}";
        }

        if ($start) {
            return "{$start} –";
        }

        return '—';
    }

    public function getHasLocationAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Start timer
     */
    public function startTimer(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
        ]);
    }

    /**
     * Stop timer and calculate duration
     */
    public function stopTimer(): void
    {
        if (! $this->started_at) {
            return;
        }

        $duration = now()->diffInMinutes($this->started_at);

        $this->update([
            'status' => 'completed',
            'end_time' => now()->toTimeString(),
            'duration_minutes' => $this->duration_minutes + $duration,
            'started_at' => null,
        ]);
    }

    /**
     * Pause timer
     */
    public function pauseTimer(): void
    {
        if ($this->status !== 'running' || ! $this->started_at) {
            return;
        }

        $duration = now()->diffInMinutes($this->started_at);

        $this->update([
            'status' => 'paused',
            'duration_minutes' => $this->duration_minutes + $duration,
            'started_at' => null,
        ]);
    }

    /**
     * Resume timer
     */
    public function resumeTimer(): void
    {
        if ($this->status !== 'paused') {
            return;
        }

        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Get current running time entry for a user and jobcard
     */
    public static function getRunningEntry(int $userId, int $jobcardId): ?self
    {
        return static::where('user_id', $userId)
            ->where('jobcard_id', $jobcardId)
            ->where('status', 'running')
            ->first();
    }
}
