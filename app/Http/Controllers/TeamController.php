<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $teams = Team::withCount('users', 'jobcards')
            ->where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('administration/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $users = User::query()
            ->staffSelectableForCompany($currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('administration/Teams/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [\App\Support\CompanyScopedRules::staffUser($currentCompany->id)],
        ]);

        $team = Team::create([
            'company_id' => $currentCompany->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['user_ids'])) {
            $team->users()->sync($validated['user_ids']);
        }

        return redirect()->route('administration.teams.show', $team)
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): Response
    {
        $team->load(['users', 'jobcards.customer']);
        $team->loadCount('users', 'jobcards');

        return Inertia::render('administration/Teams/Show', [
            'team' => $team,
        ]);
    }

    public function edit(Team $team): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $team->load('users');

        $users = User::query()
            ->staffSelectableForCompany($currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('administration/Teams/Edit', [
            'team' => $team,
            'users' => $users,
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [\App\Support\CompanyScopedRules::staffUser($currentCompany->id)],
        ]);

        $team->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);

        return redirect()->route('administration.teams.show', $team)
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()->route('administration.teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}
