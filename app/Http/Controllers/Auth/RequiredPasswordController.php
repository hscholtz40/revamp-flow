<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RequiredPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->must_reset_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/RequiredPassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user?->must_reset_password) {
            return redirect()->route('dashboard');
        }

        $defaultDeployPassword = (string) config('services.cpanel.deploy_default_admin_password', 'P@ssw0rd');

        $validated = $request->validate([
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',
                Rule::notIn([$defaultDeployPassword]),
            ],
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_reset_password' => false,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Your password has been updated.');
    }
}
