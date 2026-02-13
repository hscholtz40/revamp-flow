<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicensingInstance
{
    /**
     * Ensure the application is configured as a licensing instance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.is_licensing_instance')) {
            abort(403, 'This feature is not available on this instance.');
        }

        return $next($request);
    }
}
