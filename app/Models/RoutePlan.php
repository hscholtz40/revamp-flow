<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class RoutePlan extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'vehicle_id',
        'route_date',
        'stops',
        'total_distance_m',
        'total_duration_s',
        'provider',
    ];

    protected $casts = [
        'route_date' => 'date',
        'stops' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
