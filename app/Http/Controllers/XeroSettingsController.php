<?php

namespace App\Http\Controllers;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class XeroSettingsController extends Controller
{
    public function index()
    {
        $settings = XeroSettings::getCurrent();
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Get companies the user has access to
        $user = auth()->user();
        if ($user->companies()->count() === 0) {
            // User has access to all companies - show all active companies
            $availableCompanies = \App\Models\Company::where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']);
        } else {
            // User has access to specific companies - only show those
            $availableCompanies = $user->companies()
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get(['companies.id', 'companies.name', 'companies.is_default']);
        }
        
        \Log::info('Xero settings loaded', [
            'settings_id' => $settings->id,
            'company_id' => $settings->company_id,
            'company_name' => $currentCompany->name,
            'is_enabled' => $settings->is_enabled,
            'tenant_name' => $settings->tenant_name,
            'has_access_token' => !empty($settings->access_token),
            'has_refresh_token' => !empty($settings->refresh_token),
            'has_tenant_id' => !empty($settings->tenant_id),
            'needs_reauthorization' => $settings->needsReauthorization(),
        ]);
        
        return Inertia::render('administration/XeroSettings', [
            'settings' => $settings,
            'currentCompany' => $currentCompany,
            'availableCompanies' => $availableCompanies,
            'xeroTenants' => session('xero_tenants', []),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'is_enabled' => 'boolean',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'sync_customers' => 'boolean',
            'sync_products' => 'boolean',
            'sync_invoices' => 'boolean',
            'sync_customers_to_xero' => 'boolean',
            'sync_customers_from_xero' => 'boolean',
            'sync_products_to_xero' => 'boolean',
            'sync_products_from_xero' => 'boolean',
            'sync_invoices_to_xero' => 'boolean',
            'sync_invoices_from_xero' => 'boolean',
            'sync_credit_notes_to_xero' => 'boolean',
            'sync_credit_notes_from_xero' => 'boolean',
            'sync_purchase_orders_to_xero' => 'boolean',
            'sync_purchase_orders_from_xero' => 'boolean',
            'sync_suppliers_to_xero' => 'boolean',
            'sync_suppliers_from_xero' => 'boolean',
            'sync_quotes_to_xero' => 'boolean',
            'sync_quotes_from_xero' => 'boolean',
            'sync_tax_rates_from_xero' => 'boolean',
            'sync_bank_accounts_from_xero' => 'boolean',
            'sync_chart_of_accounts_from_xero' => 'boolean',
        ]);

        $settings = XeroSettings::getCurrent();
        
        $settings->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'sync_customers' => $request->boolean('sync_customers'),
            'sync_products' => $request->boolean('sync_products'),
            'sync_invoices' => $request->boolean('sync_invoices'),
            'sync_customers_to_xero' => $request->boolean('sync_customers_to_xero'),
            'sync_customers_from_xero' => $request->boolean('sync_customers_from_xero'),
            'sync_products_to_xero' => $request->boolean('sync_products_to_xero'),
            'sync_products_from_xero' => $request->boolean('sync_products_from_xero'),
            'sync_invoices_to_xero' => $request->boolean('sync_invoices_to_xero'),
            'sync_invoices_from_xero' => $request->boolean('sync_invoices_from_xero'),
            'sync_credit_notes_to_xero' => $request->boolean('sync_credit_notes_to_xero'),
            'sync_credit_notes_from_xero' => $request->boolean('sync_credit_notes_from_xero'),
            'sync_purchase_orders_to_xero' => $request->boolean('sync_purchase_orders_to_xero'),
            'sync_purchase_orders_from_xero' => $request->boolean('sync_purchase_orders_from_xero'),
            'sync_suppliers_to_xero' => $request->boolean('sync_suppliers_to_xero'),
            'sync_suppliers_from_xero' => $request->boolean('sync_suppliers_from_xero'),
            'sync_quotes_to_xero' => $request->boolean('sync_quotes_to_xero'),
            'sync_quotes_from_xero' => $request->boolean('sync_quotes_from_xero'),
            'sync_tax_rates_from_xero' => $request->boolean('sync_tax_rates_from_xero'),
            'sync_bank_accounts_from_xero' => $request->boolean('sync_bank_accounts_from_xero'),
            'sync_chart_of_accounts_from_xero' => $request->boolean('sync_chart_of_accounts_from_xero'),
        ]);

        return redirect()->back()->with('success', 'Xero settings updated successfully.');
    }

    public function authorize()
    {
        $settings = XeroSettings::getCurrent();
        
        if (!$settings->client_id || !$settings->client_secret) {
            return redirect()->back()->withErrors(['message' => 'Please configure Client ID and Client Secret first.']);
        }

        // Generate authorization URL
        $authUrl = $this->generateAuthUrl($settings->client_id);
        
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $settings = XeroSettings::getCurrent();
        
        try {
            $tokens = $this->exchangeCodeForTokens($request->code, $settings);
            $tenants = $this->getAvailableTenants($tokens['access_token']);

            $updateData = [
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            ];

            if (isset($tokens['refresh_token'])) {
                $updateData['refresh_token'] = $tokens['refresh_token'];
            }

            if (count($tenants) === 1) {
                $updateData['tenant_id'] = $tenants[0]['tenantId'];
                $updateData['tenant_name'] = $tenants[0]['tenantName'];
                $settings->update($updateData);

                \Log::info('Xero authorization successful (single tenant)', [
                    'tenant_id' => $tenants[0]['tenantId'],
                    'tenant_name' => $tenants[0]['tenantName'],
                ]);

                return redirect('/administration/xero-settings')
                    ->with('success', 'Xero integration authorized successfully!');
            }

            $settings->update($updateData);

            \Log::info('Xero authorization successful - awaiting tenant selection', [
                'available_tenants' => count($tenants),
            ]);

            return redirect('/administration/xero-settings')
                ->with('success', 'Xero authorized! Please select an organisation below.')
                ->with('xero_tenants', $tenants);
                
        } catch (\Exception $e) {
            \Log::error('Xero authorization failed', [
                'error' => $e->getMessage(),
                'code' => $request->code
            ]);
            
            return redirect('/administration/xero-settings')
                ->withErrors(['message' => 'Failed to authorize with Xero: ' . $e->getMessage()]);
        }
    }

    public function selectTenant(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|string',
            'tenant_name' => 'required|string',
        ]);

        $settings = XeroSettings::getCurrent();

        if (!$settings->access_token) {
            return redirect()->back()->withErrors(['message' => 'Please authorize with Xero first.']);
        }

        $settings->update([
            'tenant_id' => $request->tenant_id,
            'tenant_name' => $request->tenant_name,
        ]);

        \Log::info('Xero tenant selected', [
            'tenant_id' => $request->tenant_id,
            'tenant_name' => $request->tenant_name,
        ]);

        return redirect()->back()->with('success', "Connected to {$request->tenant_name}.");
    }

    public function fetchTenants()
    {
        $settings = XeroSettings::getCurrent();

        if (!$settings->access_token) {
            return redirect()->back()->withErrors(['message' => 'Please authorize with Xero first.']);
        }

        try {
            if ($settings->isTokenExpired() && $settings->refresh_token) {
                $xeroService = new \App\Services\XeroService(auth()->user()->getCurrentCompany());
                $xeroService->refreshTokenIfNeeded();
                $settings->refresh();
            }

            $tenants = $this->getAvailableTenants($settings->access_token);
            return redirect()->back()->with('xero_tenants', $tenants);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'Failed to fetch tenants: ' . $e->getMessage()]);
        }
    }

    public function disconnect()
    {
        $settings = XeroSettings::getCurrent();
        
        $settings->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'tenant_id' => null,
            'tenant_name' => null,
        ]);

        return redirect()->back()->with('success', 'Xero integration disconnected successfully.');
    }

    public function switchCompany(Request $request)
    {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id'
        ]);

        $user = auth()->user();
        $companyId = $request->company_id;
        
        // Check if user has access to this company
        if (!$user->hasAccessToCompany($companyId)) {
            return redirect()->back()->with('error', 'You do not have access to this company.');
        }

        // Set the current company for the authenticated user
        $user->update(['current_company_id' => $companyId]);

        $company = \App\Models\Company::find($companyId);
        return redirect()->back()->with('success', "Switched to {$company->name} for Xero settings.");
    }

    public function resetInitialSyncStatus(Request $request)
    {
        $request->validate([
            'module' => 'required|string|in:customer,product,supplier,quote,invoice,credit_note,purchase_order,tax_rate,bank_account,chart_of_account',
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();
        XeroService::resetInitialSyncStatus($currentCompany->id, $request->string('module')->toString());

        return redirect()->back()->with('success', 'Initial sync status reset successfully.');
    }

    private function generateAuthUrl($clientId)
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => url('/xero/callback'),
            'scope' => 'openid profile email offline_access accounting.transactions accounting.settings accounting.contacts',
            'state' => csrf_token(),
        ];

        return 'https://login.xero.com/identity/connect/authorize?' . http_build_query($params);
    }

    private function exchangeCodeForTokens($code, $settings)
    {
        $response = \Http::asForm()->post('https://identity.xero.com/connect/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $settings->client_id,
            'client_secret' => $settings->client_secret,
            'code' => $code,
            'redirect_uri' => url('/xero/callback'),
        ]);

        if (!$response->successful()) {
            \Log::error('Xero token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);
            throw new \Exception('Failed to exchange code for tokens: ' . $response->body());
        }

        $tokens = $response->json();
        
        \Log::info('Xero token exchange response', [
            'response_keys' => array_keys($tokens),
            'has_access_token' => isset($tokens['access_token']),
            'has_refresh_token' => isset($tokens['refresh_token']),
            'expires_in' => $tokens['expires_in'] ?? 'not_set'
        ]);

        return $tokens;
    }

    private function getAvailableTenants($accessToken): array
    {
        $response = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/connections');

        if (!$response->successful()) {
            throw new \Exception('Failed to get tenant information: ' . $response->body());
        }

        $connections = $response->json();

        if (empty($connections)) {
            throw new \Exception('No Xero organisations found for this account.');
        }

        return $connections;
    }
}