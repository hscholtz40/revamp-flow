<?php

namespace App\Http\Middleware;

use App\Services\InstanceLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseIsValid
{
    public function __construct(
        private readonly InstanceLicenseService $licenseService
    ) {
    }

    /**
     * Ensure non-licensing instances have a valid license.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.is_licensing_instance')) {
            return $next($request);
        }

        if (!$request->user()) {
            return $next($request);
        }

        if ($request->routeIs(
            'logout',
            'administration.license',
            'administration.license.update',
            'install.*',
            'verification.*',
            'password.*',
            'login',
        )) {
            return $next($request);
        }

        $validation = $this->licenseService->validate();

        if ($validation['valid']) {
            $limitRestrictionMessage = $this->licenseService->getUserLimitRestrictionMessage();
            if (!$limitRestrictionMessage) {
                return $next($request);
            }

            if ($request->routeIs(
                'users.*',
                'logout',
                'administration.license',
                'administration.license.update',
                'install.*',
                'verification.*',
                'password.*',
                'login',
            )) {
                return $next($request);
            }

            if (!$request->user()?->isAdministrator()) {
                abort(403, $limitRestrictionMessage . ' Please contact your administrator.');
            }

            return redirect()
                ->route('users.index')
                ->with('error', $limitRestrictionMessage);
        }

        return redirect()
            ->route('administration.license')
            ->with('error', $validation['message']);
    }
}
