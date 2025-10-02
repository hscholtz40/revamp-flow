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

        // Get user-specific company data
        $user = $request->user();
        $currentCompany = $user ? $user->getCurrentCompany() : null;
        
        if ($user) {
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
        } else {
            // No user logged in, show no companies
            $companies = collect();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'currentCompany' => $currentCompany ? [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
                'logo_path' => $currentCompany->logo_path,
                'is_default' => $currentCompany->is_default,
            ] : null,
            'companies' => $companies,
            'auth' => [
                'user' => $request->user(),
                'abilities' => $request->user() ? [
                    'customers' => [
                        'list' => $request->user()->hasModulePermission('customers', 'list'),
                        'view' => $request->user()->hasModulePermission('customers', 'view'),
                        'create' => $request->user()->hasModulePermission('customers', 'create'),
                        'edit' => $request->user()->hasModulePermission('customers', 'edit'),
                        'delete' => $request->user()->hasModulePermission('customers', 'delete'),
                    ],
                    'groups' => [
                        'list' => $request->user()->hasModulePermission('groups', 'list'),
                        'view' => $request->user()->hasModulePermission('groups', 'view'),
                        'create' => $request->user()->hasModulePermission('groups', 'create'),
                        'edit' => $request->user()->hasModulePermission('groups', 'edit'),
                        'delete' => $request->user()->hasModulePermission('groups', 'delete'),
                    ],
                    'users' => [
                        'list' => $request->user()->hasModulePermission('users', 'list'),
                        'view' => $request->user()->hasModulePermission('users', 'view'),
                        'create' => $request->user()->hasModulePermission('users', 'create'),
                        'edit' => $request->user()->hasModulePermission('users', 'edit'),
                        'delete' => $request->user()->hasModulePermission('users', 'delete'),
                    ],
                    'contacts' => [
                        'list' => $request->user()->hasModulePermission('contacts', 'list'),
                        'view' => $request->user()->hasModulePermission('contacts', 'view'),
                        'create' => $request->user()->hasModulePermission('contacts', 'create'),
                        'edit' => $request->user()->hasModulePermission('contacts', 'edit'),
                        'delete' => $request->user()->hasModulePermission('contacts', 'delete'),
                    ],
                    'products' => [
                        'list' => $request->user()->hasModulePermission('products', 'list'),
                        'view' => $request->user()->hasModulePermission('products', 'view'),
                        'create' => $request->user()->hasModulePermission('products', 'create'),
                        'edit' => $request->user()->hasModulePermission('products', 'edit'),
                        'delete' => $request->user()->hasModulePermission('products', 'delete'),
                    ],
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
