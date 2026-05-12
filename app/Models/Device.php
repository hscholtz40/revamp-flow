<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }

    public function updateLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}
