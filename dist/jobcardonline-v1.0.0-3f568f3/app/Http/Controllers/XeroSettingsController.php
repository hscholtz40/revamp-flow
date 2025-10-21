<?php

namespace App\Http\Controllers;

use App\Models\XeroSettings;
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
            'availableCompanies' => $availableCompanies
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
            'payment_account_cash' => 'nullable|string|max:10',
            'payment_account_card' => 'nullable|string|max:10',
            'payment_account_eft' => 'nullable|string|max:10',
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
            // Automatically enable payment sync when invoice sync is enabled
            'sync_invoices_from_xero' => $request->boolean('sync_invoices') && $request->boolean('sync_invoices_to_xero'),
            'payment_account_cash' => $request->payment_account_cash,
            'payment_account_card' => $request->payment_account_card,
            'payment_account_eft' => $request->payment_account_eft,
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
            // Exchange authorization code for tokens
            $tokens = $this->exchangeCodeForTokens($request->code, $settings);
            
            // Get tenant information
            $tenant = $this->getTenantInfo($tokens['access_token']);
            
            $updateData = [
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'tenant_id' => $tenant['tenantId'],
                'tenant_name' => $tenant['tenantName'],
            ];
            
            // Only update refresh_token if it exists in the response
            if (isset($tokens['refresh_token'])) {
                $updateData['refresh_token'] = $tokens['refresh_token'];
                \Log::info('Refresh token received from Xero');
            } else {
                \Log::warning('No refresh token received from Xero - this may cause issues when access token expires');
            }
            
            $settings->update($updateData);

            \Log::info('Xero authorization successful', [
                'tenant_id' => $tenant['tenantId'],
                'tenant_name' => $tenant['tenantName'],
                'settings_id' => $settings->id,
                'has_refresh_token' => isset($tokens['refresh_token']),
                'token_expires_at' => $updateData['token_expires_at']
            ]);

            return redirect('/administration/xero-settings')
                ->with('success', 'Xero integration authorized successfully!');
                
        } catch (\Exception $e) {
            \Log::error('Xero authorization failed', [
                'error' => $e->getMessage(),
                'code' => $request->code
            ]);
            
            return redirect('/administration/xero-settings')
                ->withErrors(['message' => 'Failed to authorize with Xero: ' . $e->getMessage()]);
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

    private function getTenantInfo($accessToken)
    {
        $response = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/connections');

        if (!$response->successful()) {
            throw new \Exception('Failed to get tenant information: ' . $response->body());
        }

        $connections = $response->json();
        return $connections[0]; // Return first connection
    }
}