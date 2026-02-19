<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'xero_account_id',
        'account_name',
        'account_number',
        'bank_name',
        'branch_code',
        'account_type',
        'currency',
        'opening_balance',
        'notes',
        'is_active',
        'is_default',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    /**
     * Get the company that owns this bank account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter active bank accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get the default bank account for a company.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default bank account for a company.
     */
    public static function getDefaultForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
