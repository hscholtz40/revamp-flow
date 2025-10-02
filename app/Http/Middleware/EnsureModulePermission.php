<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $ability): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasModulePermission($module, $ability)) {
            // For Inertia requests, return a permission error page instead of 403
            if ($request->header('X-Inertia')) {
                return Inertia::render('Errors/PermissionDenied', [
                    'module' => $module,
                    'ability' => $ability,
                    'message' => "You don't have permission to {$ability} {$module}.",
                    'user' => $user,
                    'requestedUrl' => $request->fullUrl(),
                ])->toResponse($request)->setStatusCode(403);
            }
            
            // For non-Inertia requests, return 403 as usual
            abort(403, "You don't have permission to {$ability} {$module}.");
        }

        return $next($request);
    }
}


