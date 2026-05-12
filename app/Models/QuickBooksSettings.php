<?php

namespace App\Models;

use App\Casts\AsEncryptedWithPlaintextFallback;
use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickBooksSettings extends Model
{
    use ScopedToCurrentCompanyRouteBinding;

    protected $table = 'quickbooks_settings';

    protected $fillable = [
        'company_id',
        'is_enabled',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'realm_id',
        'realm_name',
        'sync_customers',
        'sync_products',
        'sync_invoices',
        'sync_customers_to_quickbooks',
        'sync_customers_from_quickbooks',
        'sync_products_to_quickbooks',
        'sync_products_from_quickbooks',
        'sync_invoices_to_quickbooks',
        'sync_invoices_from_quickbooks',
        'sync_suppliers_to_quickbooks',
        'sync_suppliers_from_quickbooks',
        'sync_quotes_to_quickbooks',
        'sync_quotes_from_quickbooks',
        'sync_tax_rates_from_quickbooks',
        'sync_bank_accounts_from_quickbooks',
        'sync_chart_of_accounts_from_quickbooks',
        'sync_credit_notes_to_quickbooks',
        'sync_credit_notes_from_quickbooks',
        'sync_purchase_orders_to_quickbooks',
        'sync_purchase_orders_from_quickbooks',
    ];

    /**
     * Never serialize OAuth secrets to JSON / Inertia.
     *
     * @var list<string>
     */
    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'client_secret' => AsEncryptedWithPlaintextFallback::class,
        'access_token' => AsEncryptedWithPlaintextFallback::class,
        'refresh_token' => AsEncryptedWithPlaintextFallback::class,
        'is_enabled' => 'boolean',
        'token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'sync_customers' => 'boolean',
        'sync_products' => 'boolean',
        'sync_invoices' => 'boolean',
        'sync_customers_to_quickbooks' => 'boolean',
        'sync_customers_from_quickbooks' => 'boolean',
        'sync_products_to_quickbooks' => 'boolean',
        'sync_products_from_quickbooks' => 'boolean',
        'sync_invoices_to_quickbooks' => 'boolean',
        'sync_invoices_from_quickbooks' => 'boolean',
        'sync_suppliers_to_quickbooks' => 'boolean',
        'sync_suppliers_from_quickbooks' => 'boolean',
        'sync_quotes_to_quickbooks' => 'boolean',
        'sync_quotes_from_quickbooks' => 'boolean',
        'sync_tax_rates_from_quickbooks' => 'boolean',
        'sync_bank_accounts_from_quickbooks' => 'boolean',
        'sync_chart_of_accounts_from_quickbooks' => 'boolean',
        'sync_credit_notes_to_quickbooks' => 'boolean',
        'sync_credit_notes_from_quickbooks' => 'boolean',
        'sync_purchase_orders_to_quickbooks' => 'boolean',
        'sync_purchase_orders_from_quickbooks' => 'boolean',
    ];

    public static function getCurrent(): self
    {
        $user = auth()->user();
        if (! $user) {
            throw new \Exception('No authenticated user found');
        }

        $currentCompany = $user->getCurrentCompany();
        if (! $currentCompany) {
            throw new \Exception('No current company found for user');
        }

        $settings = static::where('company_id', $currentCompany->id)->first();

        if (! $settings) {
            $settings = static::create([
                'company_id' => $currentCompany->id,
                'is_enabled' => false,
                'sync_customers' => false,
                'sync_products' => false,
                'sync_invoices' => false,
            ]);
        }

        return $settings;
    }

    public static function getForCompany(int $companyId): self
    {
        $settings = static::where('company_id', $companyId)->first();

        if (! $settings) {
            $settings = static::create([
                'company_id' => $companyId,
                'is_enabled' => false,
                'sync_customers' => false,
                'sync_products' => false,
                'sync_invoices' => false,
            ]);
        }

        return $settings;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function getAllCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        return static::with('company')->get();
    }

    public function isConfigured(): bool
    {
        return $this->is_enabled &&
            $this->client_id &&
            $this->client_secret &&
            $this->access_token &&
            $this->realm_id;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function needsReauthorization(): bool
    {
        if ($this->refresh_token_expires_at && $this->refresh_token_expires_at->isPast()) {
            return true;
        }

        return ! $this->access_token ||
            (! $this->refresh_token && $this->isTokenExpired()) ||
            (! $this->access_token && ! $this->refresh_token);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
