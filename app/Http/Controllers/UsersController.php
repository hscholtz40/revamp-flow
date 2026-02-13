<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Group;
use App\Models\Company;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create', [
            'groups' => Group::query()->orderBy('name')->get(['id','name']),
            'companies' => Company::query()->orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isInfoUser = $request->input('user_type') === 'info';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'user_type' => ['required', 'string', 'in:standard,limited,info'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'password' => [$isInfoUser ? 'nullable' : 'required', 'string', 'min:8'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'companies' => ['array'],
            'companies.*' => ['integer', 'exists:companies,id'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->user_type = $validated['user_type'];
        $user->hourly_rate = $validated['hourly_rate'] ?? null;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        // Set SMTP settings
        $user->smtp_host = $validated['smtp_host'] ?? null;
        $user->smtp_port = $validated['smtp_port'] ?? null;
        $user->smtp_username = $validated['smtp_username'] ?? null;
        $user->smtp_password = $validated['smtp_password'] ?? null;
        $user->smtp_encryption = $validated['smtp_encryption'] ?? 'tls';
        $user->smtp_from_email = $validated['smtp_from_email'] ?? null;
        $user->smtp_from_name = $validated['smtp_from_name'] ?? null;
        
        $user->save();
        if ($request->filled('groups')) {
            $user->groups()->sync($validated['groups']);
        }
        if ($request->filled('companies')) {
            $user->companies()->sync($validated['companies']);
        }

        return redirect()->route('users.index')->with('success', 'User created');
    }

    public function show(User $user): Response
    {
        $user->load(['groups:id,name', 'companies:id,name']);
        // Ensure companies are unique (in case of duplicate pivot entries)
        if ($user->companies) {
            $user->setRelation('companies', $user->companies->unique('id')->values());
        }
        
        return Inertia::render('users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load(['groups:id,name', 'companies:id,name']);
        // Ensure companies are unique (in case of duplicate pivot entries)
        if ($user->companies) {
            $user->setRelation('companies', $user->companies->unique('id')->values());
        }
        
        return Inertia::render('users/Edit', [
            'user' => $user,
            'groups' => Group::query()->orderBy('name')->get(['id','name']),
            'companies' => Company::query()->orderBy('name')->get(['id','name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'user_type' => ['required', 'string', 'in:standard,limited,info'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'password' => ['nullable', 'string', 'min:8'],
            'groups' => ['sometimes','array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'companies' => ['sometimes','array'],
            'companies.*' => ['integer', 'exists:companies,id'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->user_type = $validated['user_type'];
        $user->hourly_rate = $validated['hourly_rate'] ?? null;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        // Update SMTP settings
        $user->smtp_host = $validated['smtp_host'] ?? null;
        $user->smtp_port = $validated['smtp_port'] ?? null;
        $user->smtp_username = $validated['smtp_username'] ?? null;
        if (!empty($validated['smtp_password'])) {
            $user->smtp_password = $validated['smtp_password'];
        }
        $user->smtp_encryption = $validated['smtp_encryption'] ?? 'tls';
        $user->smtp_from_email = $validated['smtp_from_email'] ?? null;
        $user->smtp_from_name = $validated['smtp_from_name'] ?? null;
        
        $user->save();
        if ($request->has('groups')) {
            $user->groups()->sync($validated['groups'] ?? []);
        }
        if ($request->has('companies')) {
            $user->companies()->sync($validated['companies'] ?? []);
        }

        return redirect()->route('users.index')->with('success', 'User updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted');
    }
}


