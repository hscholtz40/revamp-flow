<?php

use App\Http\Middleware\EnsureLicenseInfrastructureAccess;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Http\Middleware\CspMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Stateless API routes (no CSRF, no session)
            \Illuminate\Support\Facades\Route::middleware('throttle:60,1')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'xero/webhook',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            CspMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\RestrictLimitedUser::class,
            \App\Http\Middleware\EnsureLicenseIsValid::class,
        ]);

        $middleware->alias([
            'module.permission' => EnsureModulePermission::class,
            'timesheet.permission' => \App\Http\Middleware\EnsureTimesheetPermission::class,
            'admin' => EnsureUserIsAdministrator::class,
            'approved.client' => \App\Http\Middleware\EnsureApprovedClient::class,
            'licensing' => \App\Http\Middleware\EnsureLicensingInstance::class,
            'license.api.auth' => \App\Http\Middleware\AuthenticateLicenseApiRequest::class,
            'license.infrastructure' => EnsureLicenseInfrastructureAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
