<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Timesheet routes: enforces {@see User::hasModulePermission()} for module "timesheet" only.
 */
class EnsureTimesheetPermission
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        return app(EnsureModulePermission::class)->handle($request, $next, 'timesheet', $ability);
    }
}
