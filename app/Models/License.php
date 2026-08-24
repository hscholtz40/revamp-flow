<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    public const BILLING_CYCLE_MONTHLY = 'monthly';

    public const BILLING_CYCLE_ANNUAL = 'annual';

    public const PRICING_MODEL_PER_USER = 'per_user';

    public const PRICING_MODEL_FIXED = 'fixed';

    protected $fillable = [
        'company_id',
        'customer_id',
        'product_id',
        'source_query_id',
        'license_key',
        'url',
        'latitude',
        'longitude',
        'location_address',
        'version',
        'deployed_at',
        'limited_users',
        'standard_users',
        'monthly_credits',
        'status',
        'notes',
        'expires_at',
        'billing_cycle',
        'pricing_model',
        'price_standard_monthly',
        'price_limited_monthly',
        'price_standard_annual',
        'price_limited_annual',
        'fixed_amount_monthly',
        'fixed_amount_annual',
        'auto_email_invoice',
        'next_invoice_date',
        'last_invoiced_at',
    ];

    protected $casts = [
        'limited_users' => 'integer',
        'standard_users' => 'integer',
        'monthly_credits' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'deployed_at' => 'datetime',
        'expires_at' => 'datetime',
        'price_standard_monthly' => 'decimal:2',
        'price_limited_monthly' => 'decimal:2',
        'price_standard_annual' => 'decimal:2',
        'price_limited_annual' => 'decimal:2',
        'fixed_amount_monthly' => 'decimal:2',
        'fixed_amount_annual' => 'decimal:2',
        'auto_email_invoice' => 'boolean',
        'next_invoice_date' => 'date',
        'last_invoiced_at' => 'datetime',
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

    public function billingEnabled(): bool
    {
        return in_array($this->billing_cycle, [self::BILLING_CYCLE_MONTHLY, self::BILLING_CYCLE_ANNUAL], true)
            && in_array($this->pricing_model, [self::PRICING_MODEL_PER_USER, self::PRICING_MODEL_FIXED], true);
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceQuery(): BelongsTo
    {
        return $this->belongsTo(Query::class, 'source_query_id');
    }

    /**
     * Invoices generated for this license.
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'source', 'source_type', 'source_id');
    }
}
