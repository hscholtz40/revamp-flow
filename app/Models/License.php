<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'license_key',
        'url',
        'version',
        'deployed_at',
        'limited_users',
        'standard_users',
        'status',
        'notes',
        'expires_at',
    ];

    protected $casts = [
        'limited_users' => 'integer',
        'standard_users' => 'integer',
        'deployed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a unique license key.
     */
    public static function generateLicenseKey(): string
    {
        do {
            // Format: XXXX-XXXX-XXXX-XXXX-XXXX (25 chars + 4 dashes)
            $key = strtoupper(
                implode('-', str_split(Str::random(20), 4))
            );
        } while (static::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * Check if the license is currently valid.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the company that owns the license.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer this license is assigned to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
