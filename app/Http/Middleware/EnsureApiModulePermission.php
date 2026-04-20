<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $ability): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasModulePermission($module, $ability)) {
            return response()->json([
                'message' => "Missing permission: {$module}.{$ability}",
            ], 403);
        }

        return $next($request);
    }
}
