<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LocationPing extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'vehicle_id',
        'jobcard_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'recorded_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Always read/write `recorded_at` as UTC wall clock (DATETIME column, no MySQL TZ conversion).
     */
    protected function recordedAt(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?Carbon {
                if ($value === null || $value === '') {
                    return null;
                }

                return Carbon::parse($value, 'UTC');
            },
            set: function ($value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                $instant = $value instanceof CarbonInterface
                    ? $value->copy()
                    : Carbon::parse($value, 'UTC');

                return $instant->utc()->format('Y-m-d H:i:s');
            },
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Delete location pings older than the retention window for a user within a company.
     */
    public static function pruneStaleForUser(int $companyId, int $userId, int $retentionHours = 24): int
    {
        if ($companyId <= 0 || $userId <= 0 || $retentionHours < 1) {
            return 0;
        }

        return static::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('recorded_at', '<', now()->subHours($retentionHours))
            ->delete();
    }
}
