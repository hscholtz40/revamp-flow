<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('client.login');
        }

        if (! $user->isClientUser()) {
            return redirect()->route('dashboard')->with('error', 'This area is available to client accounts only.');
        }

        if (! $user->isClientApproved()) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($user->approval_status) {
                'pending' => 'Your client account is pending approval. Please contact the company if this takes too long.',
                'deactivated' => 'Your client account has been deactivated. Please contact the company if you need access restored.',
                'rejected' => 'Your client registration was not approved.',
                default => 'Your client account cannot access Client Zone at this time.',
            };

            return redirect()->route('client.login')->withErrors([
                'email' => $message,
            ]);
        }

        return $next($request);
    }
}
