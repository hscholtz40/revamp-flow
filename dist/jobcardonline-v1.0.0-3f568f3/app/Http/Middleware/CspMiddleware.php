<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CspMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply CSP in production or when explicitly enabled
        if (app()->environment('production') || config('app.csp_enabled', false)) {
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://login.xero.com https://identity.xero.com; " .
                   "style-src 'self' 'unsafe-inline'; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data:; " .
                   "connect-src 'self' https://api.xero.com https://identity.xero.com; " .
                   "frame-src 'self' https://login.xero.com;";

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}