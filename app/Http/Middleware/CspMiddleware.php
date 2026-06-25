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
        $isPublicQueryForm = $request->routeIs('queries.public.form');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $isPublicQueryForm) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (app()->environment('production') || config('app.csp_enabled', false)) {
            $frameAncestors = $isPublicQueryForm ? '*' : "'self'";
            $csp = "default-src 'self'; " .
                   "base-uri 'self'; " .
                   "object-src 'none'; " .
                   "frame-ancestors {$frameAncestors}; " .
                   "script-src 'self' 'nonce-{$nonce}' https://login.xero.com https://identity.xero.com https://*.googleapis.com https://*.gstatic.com; " .
                   // Browsers ignore 'unsafe-inline' when a nonce is present; Vue/third-party often sets style="..." without a nonce.
                   "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https://fonts.bunny.net; " .
                   "connect-src 'self' https://api.xero.com https://identity.xero.com https://*.googleapis.com https://*.gstatic.com blob:; " .
                   "worker-src blob:; " .
                   "frame-src 'self' https://login.xero.com *.google.com;";

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}