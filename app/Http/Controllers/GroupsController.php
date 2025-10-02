<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('groups/Index', [
            'groups' => Group::withCount('users')->with('permissions')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('groups/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:groups,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_administrator' => ['boolean'],
        ]);
        Group::create($validated);
        return to_route('groups.index')->with('success', 'Group created');
    }

    public function edit(Group $group): Response
    {
        $group->load('permissions');
        return Inertia::render('groups/Edit', [
            'group' => $group,
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:groups,name,' . $group->id],
            'description' => ['nullable', 'string', 'max:255'],
            'is_administrator' => ['boolean'],
        ]);
        $group->update($validated);
        return to_route('groups.index')->with('success', 'Group updated');
    }

    public function updatePermissions(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.module' => ['required', 'string'],
            'permissions.*.can_view' => ['boolean'],
            'permissions.*.can_list' => ['boolean'],
            'permissions.*.can_create' => ['boolean'],
            'permissions.*.can_edit' => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
            'permissions.*.can_edit_completed' => ['boolean'],
            'permissions.*.can_edit_salesperson' => ['boolean'],
        ]);

        foreach ($data['permissions'] as $perm) {
            GroupPermission::updateOrCreate(
                ['group_id' => $group->id, 'module' => $perm['module']],
                [
                    'can_view' => $perm['can_view'] ?? false,
                    'can_list' => $perm['can_list'] ?? false,
                    'can_create' => $perm['can_create'] ?? false,
                    'can_edit' => $perm['can_edit'] ?? false,
                    'can_delete' => $perm['can_delete'] ?? false,
                    'can_edit_completed' => $perm['can_edit_completed'] ?? false,
                    'can_edit_salesperson' => $perm['can_edit_salesperson'] ?? false,
                ]
            );
        }

        return back()->with('success', 'Permissions updated');
    }
}


