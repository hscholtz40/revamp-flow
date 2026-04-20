<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

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
        'recorded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
