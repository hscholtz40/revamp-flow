<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(): Response
    {
        $companyId = auth()->user()?->getCurrentCompany()?->id;

        return Inertia::render('tasks/Index', [
            'tasks' => Task::query()
                ->where('company_id', $companyId)
                ->with(['assignedUser:id,name', 'assignedTeam:id,name', 'notes.user:id,name'])
                ->orderByDesc('id')
                ->paginate(30),
            'assignableUsers' => User::query()
                ->staffSelectableForCompany((int) $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'assignableTeams' => Team::query()
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()?->getCurrentCompany()?->id;

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser((int) $companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team((int) $companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
        ]);

        Task::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task created successfully.');
    }
}
