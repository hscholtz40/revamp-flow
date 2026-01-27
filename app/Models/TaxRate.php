<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'xero_tax_rate_id',
        'name',
        'code',
        'rate',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the company that owns this tax rate.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter active tax rates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get the default tax rate for a company.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default tax rate for a company.
     */
    public static function getDefaultForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
