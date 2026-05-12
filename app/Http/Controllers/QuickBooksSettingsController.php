<?php

namespace App\Http\Controllers;

use App\Models\QuickBooksSettings;
use App\Services\QuickBooksService;
use App\Support\SafeLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QuickBooksSettingsController extends Controller
{
    private const SESSION_QUICKBOOKS_OAUTH_STATE = 'quickbooks_oauth_state';

    public function index()
    {
        $settings = QuickBooksSettings::getCurrent();
        $currentCompany = auth()->user()->getCurrentCompany();

        $user = auth()->user();
        if ($user->companies()->count() === 0) {
            $availableCompanies = \App\Models\Company::where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']);
        } else {
            $availableCompanies = $user->companies()
                ->where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get(['companies.id', 'companies.name', 'companies.is_default']);
        }

        SafeLog::integration('info', 'quickbooks', 'Settings loaded', [
            'settings_id' => $settings->id,
            'company_id' => $settings->company_id,
            'company_name' => $currentCompany->name,
            'is_enabled' => $settings->is_enabled,
            'realm_name' => $settings->realm_name,
            'has_access_token' => ! empty($settings->access_token),
            'has_refresh_token' => ! empty($settings->refresh_token),
            'has_realm_id' => ! empty($settings->realm_id),
            'needs_reauthorization' => $settings->needsReauthorization(),
        ]);

        return Inertia::render('administration/QuickBooksSettings', [
            'settings' => array_merge($settings->toArray(), [
                'has_client_secret' => filled($settings->getAttribute('client_secret')),
                'has_access_token' => filled($settings->getAttribute('access_token')),
                'has_refresh_token' => filled($settings->getAttribute('refresh_token')),
                'is_connected' => filled($settings->realm_id) && filled($settings->getAttribute('access_token')),
                'needs_reauthorization' => $settings->needsReauthorization(),
            ]),
            'currentCompany' => $currentCompany,
            'availableCompanies' => $availableCompanies,
            'quickbooksUseSandbox' => (bool) config('services.quickbooks.use_sandbox', true),
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
            'sync_customers_to_quickbooks' => 'boolean',
            'sync_customers_from_quickbooks' => 'boolean',
            'sync_products_to_quickbooks' => 'boolean',
            'sync_products_from_quickbooks' => 'boolean',
            'sync_invoices_to_quickbooks' => 'boolean',
            'sync_invoices_from_quickbooks' => 'boolean',
            'sync_credit_notes_to_quickbooks' => 'boolean',
            'sync_credit_notes_from_quickbooks' => 'boolean',
            'sync_purchase_orders_to_quickbooks' => 'boolean',
            'sync_purchase_orders_from_quickbooks' => 'boolean',
            'sync_suppliers_to_quickbooks' => 'boolean',
            'sync_suppliers_from_quickbooks' => 'boolean',
            'sync_quotes_to_quickbooks' => 'boolean',
            'sync_quotes_from_quickbooks' => 'boolean',
            'sync_tax_rates_from_quickbooks' => 'boolean',
            'sync_bank_accounts_from_quickbooks' => 'boolean',
            'sync_chart_of_accounts_from_quickbooks' => 'boolean',
        ]);

        $settings = QuickBooksSettings::getCurrent();

        $payload = [
            'is_enabled' => $request->boolean('is_enabled'),
            'client_id' => $request->client_id,
            'sync_customers' => $request->boolean('sync_customers'),
            'sync_products' => $request->boolean('sync_products'),
            'sync_invoices' => $request->boolean('sync_invoices'),
            'sync_customers_to_quickbooks' => $request->boolean('sync_customers_to_quickbooks'),
            'sync_customers_from_quickbooks' => $request->boolean('sync_customers_from_quickbooks'),
            'sync_products_to_quickbooks' => $request->boolean('sync_products_to_quickbooks'),
            'sync_products_from_quickbooks' => $request->boolean('sync_products_from_quickbooks'),
            'sync_invoices_to_quickbooks' => $request->boolean('sync_invoices_to_quickbooks'),
            'sync_invoices_from_quickbooks' => $request->boolean('sync_invoices_from_quickbooks'),
            'sync_credit_notes_to_quickbooks' => $request->boolean('sync_credit_notes_to_quickbooks'),
            'sync_credit_notes_from_quickbooks' => $request->boolean('sync_credit_notes_from_quickbooks'),
            'sync_purchase_orders_to_quickbooks' => $request->boolean('sync_purchase_orders_to_quickbooks'),
            'sync_purchase_orders_from_quickbooks' => $request->boolean('sync_purchase_orders_from_quickbooks'),
            'sync_suppliers_to_quickbooks' => $request->boolean('sync_suppliers_to_quickbooks'),
            'sync_suppliers_from_quickbooks' => $request->boolean('sync_suppliers_from_quickbooks'),
            'sync_quotes_to_quickbooks' => $request->boolean('sync_quotes_to_quickbooks'),
            'sync_quotes_from_quickbooks' => $request->boolean('sync_quotes_from_quickbooks'),
            'sync_tax_rates_from_quickbooks' => $request->boolean('sync_tax_rates_from_quickbooks'),
            'sync_bank_accounts_from_quickbooks' => $request->boolean('sync_bank_accounts_from_quickbooks'),
            'sync_chart_of_accounts_from_quickbooks' => $request->boolean('sync_chart_of_accounts_from_quickbooks'),
        ];

        if ($request->filled('client_secret')) {
            $payload['client_secret'] = $request->string('client_secret')->toString();
        }

        $settings->update($payload);

        return redirect()->back()->with('success', 'QuickBooks settings updated successfully.');
    }

    public function redirectToQuickBooks()
    {
        $settings = QuickBooksSettings::getCurrent();

        if (! $settings->client_id || ! $settings->client_secret) {
            return redirect()->back()->withErrors(['message' => 'Please configure Client ID and Client Secret first.']);
        }

        $state = bin2hex(random_bytes(32));
        session([self::SESSION_QUICKBOOKS_OAUTH_STATE => $state]);

        return redirect($this->generateAuthUrl($settings->client_id, $state));
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            session()->forget(self::SESSION_QUICKBOOKS_OAUTH_STATE);

            $description = $request->string('error_description')->toString();

            return redirect('/administration/quickbooks-settings')
                ->withErrors([
                    'message' => $description !== ''
                        ? 'QuickBooks authorization failed: '.$description
                        : 'QuickBooks authorization was cancelled or denied.',
                ]);
        }

        if (! $request->filled('state')) {
            session()->forget(self::SESSION_QUICKBOOKS_OAUTH_STATE);

            return redirect('/administration/quickbooks-settings')
                ->withErrors(['message' => 'Missing OAuth state. Please try authorizing again.']);
        }

        $sessionState = session(self::SESSION_QUICKBOOKS_OAUTH_STATE);
        $requestState = $request->string('state')->toString();

        if (! is_string($sessionState) || $sessionState === '' || ! hash_equals($sessionState, $requestState)) {
            session()->forget(self::SESSION_QUICKBOOKS_OAUTH_STATE);

            return redirect('/administration/quickbooks-settings')
                ->withErrors(['message' => 'Invalid or expired OAuth state. Please try authorizing again.']);
        }

        session()->forget(self::SESSION_QUICKBOOKS_OAUTH_STATE);

        $request->validate([
            'code' => 'required|string',
        ]);

        $realmId = $request->string('realmId')->toString();
        if ($realmId === '') {
            return redirect('/administration/quickbooks-settings')
                ->withErrors(['message' => 'Intuit did not return a QuickBooks company (realmId). Check your app redirect URI and scopes, then try again.']);
        }

        $settings = QuickBooksSettings::getCurrent();

        try {
            $tokens = $this->exchangeCodeForTokens($request->string('code')->toString(), $settings);

            $updateData = [
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                'realm_id' => $realmId,
            ];

            if (isset($tokens['refresh_token'])) {
                $updateData['refresh_token'] = $tokens['refresh_token'];
            }

            if (isset($tokens['x_refresh_token_expires_in']) && is_numeric($tokens['x_refresh_token_expires_in'])) {
                $updateData['refresh_token_expires_at'] = now()->addSeconds((int) $tokens['x_refresh_token_expires_in']);
            }

            $settings->update($updateData);
            $settings->refresh();

            $displayName = null;
            try {
                $company = $settings->company ?? \App\Models\Company::query()->find($settings->company_id);
                if ($company) {
                    $qb = new QuickBooksService($company);
                    $displayName = $qb->fetchRealmDisplayName();
                }
            } catch (\Throwable $e) {
                SafeLog::integration('warning', 'quickbooks', 'Company name lookup skipped', [
                    'error' => $e->getMessage(),
                ]);
            }

            if ($displayName) {
                $settings->update(['realm_name' => $displayName]);
            } elseif (! $settings->realm_name) {
                $settings->update(['realm_name' => 'QuickBooks company '.$realmId]);
            }

            SafeLog::integration('info', 'quickbooks', 'Authorization successful', [
                'realm_id' => $realmId,
            ]);

            return redirect('/administration/quickbooks-settings')
                ->with('success', 'QuickBooks integration authorized successfully!');
        } catch (\Exception $e) {
            SafeLog::integration('error', 'quickbooks', 'Authorization failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/administration/quickbooks-settings')
                ->withErrors(['message' => 'Failed to authorize with QuickBooks: '.$e->getMessage()]);
        }
    }

    public function disconnect()
    {
        $settings = QuickBooksSettings::getCurrent();

        QuickBooksService::revokeIntuitTokens($settings);

        $settings->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'refresh_token_expires_at' => null,
            'realm_id' => null,
            'realm_name' => null,
        ]);

        return redirect()->back()->with('success', 'QuickBooks integration disconnected successfully.');
    }

    public function switchCompany(Request $request)
    {
        $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! auth()->user()->hasAccessToCompany((int) $value)) {
                        $fail('You do not have access to this company.');
                    }
                },
            ],
        ]);

        $user = auth()->user();
        $companyId = $request->company_id;

        $user->update(['current_company_id' => $companyId]);

        $company = \App\Models\Company::find($companyId);

        return redirect()->back()->with('success', "Switched to {$company->name} for QuickBooks settings.");
    }

    private function generateAuthUrl(string $clientId, string $state): string
    {
        // Intuit OAuth 2.0: https://developer.intuit.com/app/developer/qbo/docs/develop/authentication-and-authorization/oauth-2-0
        $scope = implode(' ', [
            'com.intuit.quickbooks.accounting',
            'openid',
            'profile',
            'email',
            'offline_access',
        ]);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => url('/quickbooks/callback'),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ];

        return 'https://appcenter.intuit.com/connect/oauth2?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForTokens(string $code, QuickBooksSettings $settings): array
    {
        $response = \Http::timeout(30)
            ->withBasicAuth($settings->client_id, $settings->client_secret)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => (string) config('services.quickbooks.user_agent', 'JobCardOnline Laravel QuickBooks Client'),
            ])
            ->asForm()
            ->post(QuickBooksService::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => url('/quickbooks/callback'),
            ]);

        if (! $response->successful()) {
            SafeLog::integration(
                'error',
                'quickbooks',
                'Token exchange failed',
                SafeLog::httpResponseContext($response->status(), $response->body())
            );
            throw new \Exception('Failed to exchange code for tokens (HTTP '.$response->status().').');
        }

        $tokens = $response->json();
        if (! is_array($tokens)) {
            throw new \Exception('Invalid token response from Intuit.');
        }

        SafeLog::integration('info', 'quickbooks', 'Token exchange response metadata', [
            'response_keys' => array_keys($tokens),
            'has_access_token' => isset($tokens['access_token']),
            'has_refresh_token' => isset($tokens['refresh_token']),
            'expires_in' => $tokens['expires_in'] ?? 'not_set',
        ]);

        return $tokens;
    }
}
