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
            ];
        }
        
        // Get user-specific company data (only if installed)
        $user = null;
        $currentCompany = null;
        $companies = collect();
        
        try {
            $user = $request->user();
            
            if ($user) {
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
            }
        } catch (\Exception $e) {
            // If database connection fails, use empty collections
            $companies = collect();
            $currentCompany = null;
            $user = null;
        }

        return [
            ...parent::share($request),
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
            ] : null,
            'companies' => $companies,
            'auth' => [
                'user' => $user,
                'abilities' => $this->getUserAbilities($isInstalled, $user, $isInstallerRoute),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
            ];
        } catch (\Exception $e) {
            // If database connection fails, return null
            return null;
        }
    }
}
