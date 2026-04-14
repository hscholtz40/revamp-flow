<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $nonce = Str::random(40);
        $request->attributes->set('csp_nonce', $nonce);
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (app()->environment('production') || config('app.csp_enabled', false)) {
            $csp = "default-src 'self'; " .
                   "base-uri 'self'; " .
                   "object-src 'none'; " .
                   "frame-ancestors 'self'; " .
                   "script-src 'self' 'nonce-{$nonce}' https://login.xero.com https://identity.xero.com; " .
                   "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://fonts.bunny.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https://fonts.bunny.net; " .
                   "connect-src 'self' https://api.xero.com https://identity.xero.com; " .
                   "frame-src 'self' https://login.xero.com;";

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}