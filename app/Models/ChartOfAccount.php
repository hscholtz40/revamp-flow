<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'xero_account_id',
        'account_code',
        'account_name',
        'account_type',
        'parent_account_id',
        'description',
        'is_active',
        'is_default_sales',
        'is_default_purchasing',
        'is_default_rounding',
        'sort_order',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default_sales' => 'boolean',
        'is_default_purchasing' => 'boolean',
        'is_default_rounding' => 'boolean',
        'sort_order' => 'integer',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    /**
     * Get the company that owns this account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the parent account.
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_id');
    }

    /**
     * Get child accounts.
     */
    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_id');
    }

    /**
     * Scope to filter active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('account_code');
    }

    public function scopeDefaultSales($query)
    {
        return $query->where('is_default_sales', true);
    }

    public function scopeDefaultPurchasing($query)
    {
        return $query->where('is_default_purchasing', true);
    }

    public function scopeDefaultRounding($query)
    {
        return $query->where('is_default_rounding', true);
    }

    public static function getDefaultSalesForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_sales', true)
            ->where('is_active', true)
            ->first();
    }

    public static function getDefaultPurchasingForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_purchasing', true)
            ->where('is_active', true)
            ->first();
    }

    public static function getDefaultRoundingForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_rounding', true)
            ->where('is_active', true)
            ->first();
    }
}
