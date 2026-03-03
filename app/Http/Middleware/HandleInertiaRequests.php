<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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

        // Skip ALL database operations if app is not installed yet OR if we're on installer routes
        $isInstalled = file_exists(base_path('.installed'));
        $isInstallerRoute = $request->routeIs('install.*');
        
        // If .installed file doesn't exist but user is authenticated, treat as installed
        // This handles cases where the file was deleted but the app is actually installed
        if (!$isInstalled) {
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
        if (!$isInstalled || $isInstallerRoute) {
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'app' => [
                    'version' => \App\Helpers\Version::get(),
                ],
                'quote' => ['message' => trim($message), 'author' => trim($author)],
                'currentCompany' => null,
                'companies' => collect(),
                'auth' => [
                    'user' => null,
                    'abilities' => null,
                ],
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'isLicensingInstance' => config('app.is_licensing_instance'),
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
                    'user_type' => $user->user_type ?? 'standard',
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
                
                // Get companies the user has access to
                if ($user->companies()->count() === 0) {
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

        // Check if parent share already has auth data
        $parentAuth = $parentShare['auth'] ?? null;
        
        return [
            ...$parentShare,
            'name' => config('app.name'),
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
                'visible_modules' => $currentCompany->visible_modules,
            ] : null,
            'companies' => $companies,
            'auth' => [
                'user' => $userData ?? $parentAuth['user'] ?? null,
                'abilities' => $this->getUserAbilities($isInstalled, $user, $isInstallerRoute) ?? $parentAuth['abilities'] ?? null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'isLicensingInstance' => config('app.is_licensing_instance'),
        ];
    }

    /**
     * Get user abilities, handling database connection errors gracefully
     */
    private function getUserAbilities(bool $isInstalled, $user, bool $isInstallerRoute = false): ?array
    {
        if (!$isInstalled || !$user || $isInstallerRoute) {
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
            ];
        } catch (\Exception $e) {
            // If database connection fails, return null
            return null;
        }
    }
}
