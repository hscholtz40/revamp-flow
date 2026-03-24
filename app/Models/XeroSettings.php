<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XeroSettings extends Model
{
    use ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'is_enabled',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'tenant_id',
        'tenant_name',
        'sync_customers',
        'sync_products',
        'sync_invoices',
        'sync_customers_to_xero',
        'sync_customers_from_xero',
        'sync_products_to_xero',
        'sync_products_from_xero',
        'sync_invoices_to_xero',
        'sync_invoices_from_xero',
        'sync_suppliers_to_xero',
        'sync_suppliers_from_xero',
        'sync_quotes_to_xero',
        'sync_quotes_from_xero',
        'sync_tax_rates_from_xero',
        'sync_bank_accounts_from_xero',
        'sync_chart_of_accounts_from_xero',
        'sync_credit_notes_to_xero',
        'sync_credit_notes_from_xero',
        'sync_purchase_orders_to_xero',
        'sync_purchase_orders_from_xero',
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
        'client_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'is_enabled' => 'boolean',
        'token_expires_at' => 'datetime',
        'sync_customers' => 'boolean',
        'sync_products' => 'boolean',
        'sync_invoices' => 'boolean',
        'sync_customers_to_xero' => 'boolean',
        'sync_customers_from_xero' => 'boolean',
        'sync_products_to_xero' => 'boolean',
        'sync_products_from_xero' => 'boolean',
        'sync_invoices_to_xero' => 'boolean',
        'sync_invoices_from_xero' => 'boolean',
        'sync_suppliers_to_xero' => 'boolean',
        'sync_suppliers_from_xero' => 'boolean',
        'sync_quotes_to_xero' => 'boolean',
        'sync_quotes_from_xero' => 'boolean',
        'sync_tax_rates_from_xero' => 'boolean',
        'sync_bank_accounts_from_xero' => 'boolean',
        'sync_chart_of_accounts_from_xero' => 'boolean',
        'sync_credit_notes_to_xero' => 'boolean',
        'sync_credit_notes_from_xero' => 'boolean',
        'sync_purchase_orders_to_xero' => 'boolean',
        'sync_purchase_orders_from_xero' => 'boolean',
    ];

    /**
     * Get the current Xero settings instance for the authenticated user's company
     */
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

    /**
     * Get Xero settings for a specific company
     */
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
     * Get all companies with Xero settings
     */
    public static function getAllCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        return static::with('company')->get();
    }

    /**
     * Check if Xero integration is enabled and configured
     */
    public function isConfigured(): bool
    {
        return $this->is_enabled &&
               $this->client_id &&
               $this->client_secret &&
               $this->access_token &&
               $this->tenant_id;
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Check if Xero connection needs re-authorization
     */
    public function needsReauthorization(): bool
    {
        // Needs re-authorization if:
        // 1. No access token
        // 2. No refresh token (can't refresh expired tokens)
        // 3. Token is expired and no refresh token
        return ! $this->access_token ||
               (! $this->refresh_token && $this->isTokenExpired()) ||
               (! $this->access_token && ! $this->refresh_token);
    }

    /**
     * Get the company that owns the Xero settings
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
