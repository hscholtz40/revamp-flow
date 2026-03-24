<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

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
     * Mask a license key for display to non-administrators (first and last segment only).
     */
    public static function maskLicenseKey(string $key): string
    {
        $segments = explode('-', $key);
        if (count($segments) >= 5) {
            return $segments[0].'-****-****-****-'.$segments[4];
        }

        $len = strlen($key);
        if ($len > 10) {
            return substr($key, 0, 4).'••••••••'.substr($key, -4);
        }

        return '••••••••';
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
