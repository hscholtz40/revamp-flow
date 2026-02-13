<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictLimitedUser
{
    /**
     * Restrict limited users to only their allowed routes.
     *
     * Limited users can:
     * - View the dashboard
     * - View jobcards (list + detail)
     * - Change jobcard status
     * - View/create/manage time entries
     * - Access settings routes (profile, password, appearance)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isLimitedUser()) {
            $path = ltrim($request->path(), '/');
            $method = strtoupper($request->method());

            // Always allow root/home
            if ($path === '' || $path === '/') {
                return $next($request);
            }

            // Always allow logout and auth-related routes
            if ($path === 'logout' || str_starts_with($path, 'user/') || str_starts_with($path, 'email/')) {
                return $next($request);
            }

            // Allow dashboard
            if (str_starts_with($path, 'dashboard')) {
                return $next($request);
            }

            // Allow settings (profile, password, appearance)
            if (str_starts_with($path, 'settings')) {
                return $next($request);
            }

            // Allow company switching (POST only)
            if (preg_match('#^company-settings/\d+/switch$#', $path) && $method === 'POST') {
                return $next($request);
            }

            // Allow time entries (view, create, update) but NOT delete
            if (str_starts_with($path, 'time-entries')) {
                // Block DELETE requests on time entries
                if ($method === 'DELETE') {
                    abort(403, 'Your account does not have permission to delete time entries.');
                }
                return $next($request);
            }

            // Jobcards: allow only specific actions
            if (str_starts_with($path, 'jobcards')) {
                // Block create/store routes
                if (preg_match('#^jobcards/create$#', $path)) {
                    abort(403, 'Your account does not have access to this area.');
                }
                // Block POST to /jobcards (store new jobcard) but NOT to sub-routes like /jobcards/{id}/time-entries/convert
                if ($path === 'jobcards' && $method === 'POST') {
                    abort(403, 'Your account does not have access to this area.');
                }
                // Block edit routes
                if (preg_match('#^jobcards/\d+/edit$#', $path)) {
                    abort(403, 'Your account does not have access to this area.');
                }
                // Block PUT (update full jobcard) but allow PATCH (status update)
                if (preg_match('#^jobcards/\d+$#', $path) && ($method === 'PUT' || $method === 'DELETE')) {
                    abort(403, 'Your account does not have access to this area.');
                }
                // Block convert-to-invoice
                if (preg_match('#^jobcards/\d+/convert-to-invoice$#', $path)) {
                    abort(403, 'Your account does not have access to this area.');
                }

                // Allow everything else (index, show, status update, print, time entries)
                return $next($request);
            }

            // Block everything else
            abort(403, 'Your account does not have access to this area.');
        }

        return $next($request);
    }
}
