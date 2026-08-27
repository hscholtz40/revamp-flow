<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\GoogleIntegrationSettings;
use App\Services\AI\AiAccessService;
use App\Services\InstanceLicenseService;
use App\Support\CompanyTheme;
use DateTimeZone;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $sharedFlash = fn (): array => [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'warning' => $request->session()->get('warning'),
            'info' => $request->session()->get('info'),
            'status' => $request->session()->get('status'),
        ];

        // Skip ALL database operations if app is not installed yet OR if we're on installer routes
        $isInstalled = file_exists(base_path('.installed'));
        $isInstallerRoute = $request->routeIs('install.*');

        // If .installed file doesn't exist but user is authenticated, treat as installed
        // This handles cases where the file was deleted but the app is actually installed
        if (! $isInstalled) {
            try {
                // Check if user is authenticated - if so, app is likely installed
                $hasAuthenticatedUser = $request->user() !== null || auth()->check();
                if ($hasAuthenticatedUser) {
                    $isInstalled = true;
                }
            } catch (\Exception $e) {
                // If we can't check auth, assume not installed
            }
        }

        // Early return if not installed or on installer route - skip all DB operations
        if (! $isInstalled || $isInstallerRoute) {
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'csrf_token' => $request->session()->token(),
                'app' => [
                    'version' => \App\Helpers\Version::get(),
                ],
                'quote' => ['message' => trim($message), 'author' => trim($author)],
                'currentCompany' => null,
                'companies' => collect(),
                'numberFormat' => [
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                ],
                'dateTimeFormat' => [
                    'timezone' => config('app.timezone', 'UTC'),
                    'date_format' => 'dd/mm/yyyy',
                    'time_format' => '24h',
                ],
                'theme' => CompanyTheme::resolved(null),
                'flash' => $sharedFlash,
                'auth' => [
                    'user' => null,
                    'abilities' => null,
                    'payment_methods' => null,
                ],
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
                'isLicensingInstance' => config('app.is_licensing_instance'),
                'licenseExpiry' => null,
                'unreadNotificationCount' => 0,
                'google_maps_api_key' => '',
            ];
        }

        // Get user directly from request (handles authentication)
        $parentShare = parent::share($request);

        // Try multiple ways to get the user
        $user = $request->user() ?? auth()->user() ?? auth()->guard('web')->user();

        // Serialize user to array if it exists (only include safe fields)
        $userData = null;
        if ($user) {
            try {
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_administrator' => $user->isAdministrator(),
                    'user_type' => $user->user_type ?? 'standard',
                    'approval_status' => $user->approval_status,
                    'customer_id' => $user->customer_id,
                    'hourly_rate' => $user->hourly_rate,
                    'avatar' => null, // Can be added later if needed
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ];
            } catch (\Exception $e) {
                // If serialization fails, continue without user data
                $userData = null;
            }
        }

        // Get user-specific company data (only if installed and user exists)
        $currentCompany = null;
        $companies = collect();

        if ($user) {
            try {
                $currentCompany = $user->getCurrentCompany();

                if ($user->isClientUser()) {
                    $companies = $currentCompany
                        ? collect([[
                            'id' => $currentCompany->id,
                            'name' => $currentCompany->name,
                            'logo_path' => $currentCompany->logo_path,
                            'is_default' => $currentCompany->is_default,
                        ]])
                        : collect();
                } elseif ($user->companies()->count() === 0) {
                    // Get companies the user has access to
                    // User has access to all companies - show all active companies
                    $companies = Company::where('is_active', true)
                        ->orderBy('is_default', 'desc')
                        ->orderBy('name')
                        ->get(['id', 'name', 'logo_path', 'is_default']);
                } else {
                    // User has access to specific companies - only show those
                    $companies = $user->companies()
                        ->where('is_active', true)
                        ->orderBy('is_default', 'desc')
                        ->orderBy('name')
                        ->get(['companies.id', 'companies.name', 'companies.logo_path', 'companies.is_default']);
                }
            } catch (\Exception $e) {
                // If database connection fails for company queries, use empty collections
                // But keep the user object since authentication doesn't require DB
                $companies = collect();
                $currentCompany = null;
            }
        }

        $resolvedTimezone = config('app.timezone', 'UTC');
        if ($currentCompany && is_string($currentCompany->locale_timezone) && $currentCompany->locale_timezone !== '') {
            try {
                new DateTimeZone($currentCompany->locale_timezone);
                $resolvedTimezone = $currentCompany->locale_timezone;
            } catch (\Throwable $e) {
                // Keep default timezone when company setting is invalid.
            }
        }
        config(['app.timezone' => $resolvedTimezone]);
        date_default_timezone_set($resolvedTimezone);

        $unreadNotificationCount = 0;
        if ($user) {
            try {
                if (Schema::hasTable('notifications')) {
                    $unreadNotificationCount = (int) $user->unreadNotifications()->count();
                }
            } catch (\Throwable) {
                $unreadNotificationCount = 0;
            }
        }

        // Check if parent share already has auth data
        $parentAuth = $parentShare['auth'] ?? null;

        $googleMapsApiKey = '';
        $aiCapabilities = [
            'enabled' => false,
            'available' => false,
            'allowed' => false,
            'admin_only' => true,
            'prompt_logging_enabled' => false,
            'daily_user_limit' => 0,
            'daily_company_limit' => 0,
        ];
        try {
            if (Schema::hasTable('google_integration_settings')) {
                $googleMapsApiKey = GoogleIntegrationSettings::mapsApiKey();
                $aiCapabilities = app(AiAccessService::class)->capabilitiesForUser($user);
            }
        } catch (\Throwable) {
            $googleMapsApiKey = '';
        }

        return [
            ...$parentShare,
            'name' => config('app.name'),
            'csrf_token' => $request->session()->token(),
            'app' => [
                'version' => \App\Helpers\Version::get(),
            ],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'currentCompany' => $currentCompany ? [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
                'logo_path' => $currentCompany->logo_path,
                'is_default' => $currentCompany->is_default,
                'enable_pos' => (bool) $currentCompany->enable_pos,
                'enable_dispatch' => (bool) $currentCompany->enable_dispatch,
                'visible_modules' => $currentCompany->visible_modules,
                'locale_timezone' => ($currentCompany->locale_timezone !== null && $currentCompany->locale_timezone !== '')
                    ? $currentCompany->locale_timezone
                    : $resolvedTimezone,
            ] : null,
            'numberFormat' => $currentCompany ? [
                'decimal_separator' => ($currentCompany->locale_decimal_separator !== null && $currentCompany->locale_decimal_separator !== '')
                    ? $currentCompany->locale_decimal_separator
                    : '.',
                'thousands_separator' => ($currentCompany->locale_thousands_separator !== null && $currentCompany->locale_thousands_separator !== '')
                    ? $currentCompany->locale_thousands_separator
                    : ',',
            ] : [
                'decimal_separator' => '.',
                'thousands_separator' => ',',
            ],
            'dateTimeFormat' => $currentCompany ? [
                'timezone' => ($currentCompany->locale_timezone !== null && $currentCompany->locale_timezone !== '')
                    ? $currentCompany->locale_timezone
                    : $resolvedTimezone,
                'date_format' => ($currentCompany->locale_date_format !== null && $currentCompany->locale_date_format !== '')
                    ? $currentCompany->locale_date_format
                    : 'dd/mm/yyyy',
                'time_format' => ($currentCompany->locale_time_format !== null && $currentCompany->locale_time_format !== '')
                    ? $currentCompany->locale_time_format
                    : '24h',
            ] : [
                'timezone' => $resolvedTimezone,
                'date_format' => 'dd/mm/yyyy',
                'time_format' => '24h',
            ],
            // Brand colours for Inertia navigations (login / company switch) without full reload.
            'theme' => CompanyTheme::resolved($currentCompany),
            'companies' => $companies,
            'flash' => $sharedFlash,
            'auth' => [
                'user' => $userData ?? $parentAuth['user'] ?? null,
                'abilities' => $this->getUserAbilities($isInstalled, $user, $isInstallerRoute) ?? $parentAuth['abilities'] ?? null,
                'payment_methods' => $user ? $user->getAllowedPaymentMethodsMap() : ($parentAuth['payment_methods'] ?? null),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'isLicensingInstance' => config('app.is_licensing_instance'),
            'licenseExpiry' => $this->licenseExpiryShare($isInstalled, $isInstallerRoute),
            'unreadNotificationCount' => $unreadNotificationCount,
            'google_maps_api_key' => $googleMapsApiKey,
            'ai' => $aiCapabilities,
        ];
    }

    /**
     * @return array{expires_at: string, days_remaining: int, message: string}|null
     */
    private function licenseExpiryShare(bool $isInstalled, bool $isInstallerRoute): ?array
    {
        if (! $isInstalled || $isInstallerRoute || config('app.is_licensing_instance')) {
            return null;
        }

        try {
            return app(InstanceLicenseService::class)->getExpiryWarning();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get user abilities, handling database connection errors gracefully
     */
    private function getUserAbilities(bool $isInstalled, $user, bool $isInstallerRoute = false): ?array
    {
        if (! $isInstalled || ! $user || $isInstallerRoute) {
            return null;
        }

        try {
            return [
                'customers' => [
                    'list' => $user->hasModulePermission('customers', 'list'),
                    'view' => $user->hasModulePermission('customers', 'view'),
                    'create' => $user->hasModulePermission('customers', 'create'),
                    'edit' => $user->hasModulePermission('customers', 'edit'),
                    'delete' => $user->hasModulePermission('customers', 'delete'),
                ],
                'groups' => [
                    'list' => $user->hasModulePermission('groups', 'list'),
                    'view' => $user->hasModulePermission('groups', 'view'),
                    'create' => $user->hasModulePermission('groups', 'create'),
                    'edit' => $user->hasModulePermission('groups', 'edit'),
                    'delete' => $user->hasModulePermission('groups', 'delete'),
                ],
                'users' => [
                    'list' => $user->hasModulePermission('users', 'list'),
                    'view' => $user->hasModulePermission('users', 'view'),
                    'create' => $user->hasModulePermission('users', 'create'),
                    'edit' => $user->hasModulePermission('users', 'edit'),
                    'delete' => $user->hasModulePermission('users', 'delete'),
                ],
                'contacts' => [
                    'list' => $user->hasModulePermission('contacts', 'list'),
                    'view' => $user->hasModulePermission('contacts', 'view'),
                    'create' => $user->hasModulePermission('contacts', 'create'),
                    'edit' => $user->hasModulePermission('contacts', 'edit'),
                    'delete' => $user->hasModulePermission('contacts', 'delete'),
                ],
                'products' => [
                    'list' => $user->hasModulePermission('products', 'list'),
                    'view' => $user->hasModulePermission('products', 'view'),
                    'create' => $user->hasModulePermission('products', 'create'),
                    'edit' => $user->hasModulePermission('products', 'edit'),
                    'delete' => $user->hasModulePermission('products', 'delete'),
                ],
                'suppliers' => [
                    'list' => $user->hasModulePermission('suppliers', 'list'),
                    'view' => $user->hasModulePermission('suppliers', 'view'),
                    'create' => $user->hasModulePermission('suppliers', 'create'),
                    'edit' => $user->hasModulePermission('suppliers', 'edit'),
                    'delete' => $user->hasModulePermission('suppliers', 'delete'),
                ],
                'stock-movements' => [
                    'list' => $user->hasModulePermission('stock-movements', 'list'),
                    'view' => $user->hasModulePermission('stock-movements', 'view'),
                    'create' => $user->hasModulePermission('stock-movements', 'create'),
                    'edit' => $user->hasModulePermission('stock-movements', 'edit'),
                    'delete' => $user->hasModulePermission('stock-movements', 'delete'),
                ],
                'purchase-orders' => [
                    'list' => $user->hasModulePermission('purchase-orders', 'list'),
                    'view' => $user->hasModulePermission('purchase-orders', 'view'),
                    'create' => $user->hasModulePermission('purchase-orders', 'create'),
                    'edit' => $user->hasModulePermission('purchase-orders', 'edit'),
                    'delete' => $user->hasModulePermission('purchase-orders', 'delete'),
                ],
                'delivery-notes' => [
                    'list' => $user->hasModulePermission('delivery-notes', 'list'),
                    'view' => $user->hasModulePermission('delivery-notes', 'view'),
                    'create' => $user->hasModulePermission('delivery-notes', 'create'),
                    'edit' => $user->hasModulePermission('delivery-notes', 'edit'),
                    'delete' => $user->hasModulePermission('delivery-notes', 'delete'),
                ],
                'jobcards' => [
                    'list' => $user->hasModulePermission('jobcards', 'list'),
                    'view' => $user->hasModulePermission('jobcards', 'view'),
                    'create' => $user->hasModulePermission('jobcards', 'create'),
                    'edit' => $user->hasModulePermission('jobcards', 'edit'),
                    'delete' => $user->hasModulePermission('jobcards', 'delete'),
                ],
                'quotes' => [
                    'list' => $user->hasModulePermission('quotes', 'list'),
                    'view' => $user->hasModulePermission('quotes', 'view'),
                    'create' => $user->hasModulePermission('quotes', 'create'),
                    'edit' => $user->hasModulePermission('quotes', 'edit'),
                    'delete' => $user->hasModulePermission('quotes', 'delete'),
                ],
                'invoices' => [
                    'list' => $user->hasModulePermission('invoices', 'list'),
                    'view' => $user->hasModulePermission('invoices', 'view'),
                    'create' => $user->hasModulePermission('invoices', 'create'),
                    'edit' => $user->hasModulePermission('invoices', 'edit'),
                    'delete' => $user->hasModulePermission('invoices', 'delete'),
                ],
                'credit-notes' => [
                    'list' => $user->hasModulePermission('credit-notes', 'list'),
                    'view' => $user->hasModulePermission('credit-notes', 'view'),
                    'create' => $user->hasModulePermission('credit-notes', 'create'),
                    'edit' => $user->hasModulePermission('credit-notes', 'edit'),
                    'delete' => $user->hasModulePermission('credit-notes', 'delete'),
                ],
                'reports' => [
                    'list' => $user->hasModulePermission('reports', 'list'),
                    'view' => $user->hasModulePermission('reports', 'view'),
                    'create' => $user->hasModulePermission('reports', 'create'),
                    'edit' => $user->hasModulePermission('reports', 'edit'),
                    'delete' => $user->hasModulePermission('reports', 'delete'),
                ],
                'timesheet' => [
                    'list' => $user->hasModulePermission('timesheet', 'list'),
                    'view' => $user->hasModulePermission('timesheet', 'view'),
                    'create' => $user->hasModulePermission('timesheet', 'create'),
                    'edit' => $user->hasModulePermission('timesheet', 'edit'),
                    'delete' => $user->hasModulePermission('timesheet', 'delete'),
                ],
                'messages' => [
                    'list' => $user->hasModulePermission('messages', 'list'),
                    'view' => $user->hasModulePermission('messages', 'view'),
                    'create' => $user->hasModulePermission('messages', 'create'),
                    'edit' => $user->hasModulePermission('messages', 'edit'),
                    'delete' => $user->hasModulePermission('messages', 'delete'),
                ],
                'dispatch' => [
                    'list' => $user->hasModulePermission('dispatch', 'list'),
                    'view' => $user->hasModulePermission('dispatch', 'view'),
                    'create' => $user->hasModulePermission('dispatch', 'create'),
                    'edit' => $user->hasModulePermission('dispatch', 'edit'),
                    'delete' => $user->hasModulePermission('dispatch', 'delete'),
                ],
                'tasks' => [
                    'list' => $user->hasModulePermission('tasks', 'list'),
                    'view' => $user->hasModulePermission('tasks', 'view'),
                    'create' => $user->hasModulePermission('tasks', 'create'),
                    'edit' => $user->hasModulePermission('tasks', 'edit'),
                    'delete' => $user->hasModulePermission('tasks', 'delete'),
                ],
                'queries' => [
                    'list' => $user->hasModulePermission('queries', 'list'),
                    'view' => $user->hasModulePermission('queries', 'view'),
                    'create' => $user->hasModulePermission('queries', 'create'),
                    'edit' => $user->hasModulePermission('queries', 'edit'),
                    'delete' => $user->hasModulePermission('queries', 'delete'),
                ],
                'registered-users' => [
                    'list' => $user->hasModulePermission('registered-users', 'list'),
                    'view' => $user->hasModulePermission('registered-users', 'view'),
                    'approve' => $user->hasModulePermission('registered-users', 'approve'),
                ],
                'customer-update-requests' => [
                    'list' => $user->hasModulePermission('customer-update-requests', 'list'),
                    'view' => $user->hasModulePermission('customer-update-requests', 'view'),
                    'approve' => $user->hasModulePermission('customer-update-requests', 'approve'),
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
