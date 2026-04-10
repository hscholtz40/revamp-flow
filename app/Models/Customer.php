<?php

namespace App\Models;

use App\Services\CustomerAccountBalanceCalculator;
use App\Traits\Auditable;
use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'terms',
        'vat_number',
        'account_code',
        'notes',
        'is_default_sales',
        'xero_contact_id',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'is_default_sales' => 'boolean',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function jobcards(): HasMany
    {
        return $this->hasMany(Jobcard::class);
    }

    public function smsActivities(): HasMany
    {
        return $this->hasMany(SMSActivity::class);
    }

    public function emailActivities(): HasMany
    {
        return $this->hasMany(EmailActivity::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function updateRequests(): HasMany
    {
        return $this->hasMany(CustomerUpdateRequest::class);
    }

    /**
     * JCO-only AR balance for this customer in a company (open invoice balances minus unapplied credit).
     *
     * @return array{outstanding_invoices: float, unapplied_credit: float, account_balance: float}
     */
    public function accountBalanceBreakdownForCompany(int $companyId): array
    {
        return CustomerAccountBalanceCalculator::forCustomersInCompany([$this->id], $companyId);
    }

    /**
     * Generate a unique account code based on customer name
     */
    public static function generateAccountCode(string $name, int $companyId): string
    {
        // Extract first 2 letters from name (uppercase, remove spaces and special chars)
        $cleanedName = preg_replace('/[^a-zA-Z]/', '', $name);

        if (strlen($cleanedName) === 0) {
            // Fallback if no letters found
            $prefix = 'CU';
        } elseif (strlen($cleanedName) === 1) {
            // If only one letter, duplicate it
            $prefix = strtoupper($cleanedName.$cleanedName);
        } else {
            // Take first 2 letters
            $prefix = strtoupper(substr($cleanedName, 0, 2));
        }

        // Find the next available number
        $number = 1;
        do {
            $accountCode = $prefix.str_pad($number, 2, '0', STR_PAD_LEFT);
            $exists = self::where('company_id', $companyId)
                ->where('account_code', $accountCode)
                ->exists();

            if (! $exists) {
                return $accountCode;
            }

            $number++;

            // Safety limit to prevent infinite loop
            if ($number > 9999) {
                // Fallback to timestamp-based code if we hit the limit
                return $prefix.time();
            }
        } while (true);
    }

    public static function getDefaultSalesForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_default_sales', true)
            ->first();
    }
}
