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
        'is_default_sales',
        'is_default_purchasing',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default_sales' => 'boolean',
        'is_default_purchasing' => 'boolean',
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
     * Scope to get the default sales tax rate.
     */
    public function scopeDefaultSales($query)
    {
        return $query->where('is_default_sales', true);
    }

    /**
     * Scope to get the default purchasing tax rate.
     */
    public function scopeDefaultPurchasing($query)
    {
        return $query->where('is_default_purchasing', true);
    }

    /**
     * Get the default sales tax rate for a company (used by invoices, quotes, jobcards).
     */
    public static function getDefaultSalesForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_sales', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the default purchasing tax rate for a company (used by purchase orders).
     */
    public static function getDefaultPurchasingForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_purchasing', true)
            ->where('is_active', true)
            ->first();
    }
}
