<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCurrent
{
    /**
     * Redirect users who must change their password before using the app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_reset_password) {
            return $next($request);
        }

        if ($request->routeIs('password.required', 'password.required.update', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.required');
    }
}
