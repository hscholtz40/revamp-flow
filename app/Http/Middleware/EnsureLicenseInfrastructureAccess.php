<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseInfrastructureAccess
{
    /**
     * Restrict cPanel deploy, upgrade, and force-SSL to administrators.
     * Named middleware keeps infrastructure actions explicit for future permission splits.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isAdministrator()) {
            abort(403, 'License deployment and infrastructure actions require an administrator account.');
        }

        return $next($request);
    }
}
