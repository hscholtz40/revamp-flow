<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound public query submissions from external sites
 * (e.g. the Revamp marketing landing page) using a shared API key.
 */
class VerifyQueryApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.query_api.key');
        $provided = (string) ($request->header('X-Api-Key') ?? $request->bearerToken() ?? '');

        abort_if($expected === '', 503, 'Query API is not configured.');
        abort_unless($provided !== '' && hash_equals($expected, $provided), 401, 'Invalid API key.');

        return $next($request);
    }
}
