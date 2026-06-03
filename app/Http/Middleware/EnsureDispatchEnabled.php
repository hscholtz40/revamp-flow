<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDispatchEnabled
{
    /**
     * Ensure the current company has the jobcard dispatch board enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->getCurrentCompany();

        if (! $company || ! $company->enable_dispatch) {
            abort(403, 'Dispatch is not enabled for this company.');
        }

        return $next($request);
    }
}
