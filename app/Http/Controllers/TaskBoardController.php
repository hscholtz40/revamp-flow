<?php

namespace App\Http\Controllers;

use App\Models\Jobcard;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Support\CompanyScopedRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        $search = trim((string) $request->string('search', ''));
        $status = (string) $request->string('status', '');
        $view = (string) $request->string('view', 'list');
        if (! in_array($view, ['list', 'kanban'], true)) {
            $view = 'list';
        }
        $canListOrViewAll = $this->canListOrViewAllTasks($request);

        $tasksQuery = Task::query()
            ->where('company_id', $companyId)
            ->with(['assignedUser:id,name', 'assignedTeam:id,name', 'jobcard:id,job_number', 'createdBy:id,name'])
            ->withCount(['recordNotes as notes_count']);

        if (! $canListOrViewAll) {
            $this->applyOwnAssignmentScope($tasksQuery, (int) ($user?->id ?? 0), $this->currentUserTeamIds($request, $companyId));
        }

        if ($search !== '') {
            $tasksQuery->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $tasksQuery->where('status', $status);
        }

        $tasks = $tasksQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $tasks->getCollection()->transform(function (Task $task) use ($request, $companyId) {
            $task->setAttribute('can_update_status', $this->canUpdateTaskStatus($request, $task, $companyId));

            return $task;
        });

        return Inertia::render('tasks/Index', array_merge([
            'tasks' => $tasks,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'view' => $view,
            ],
            'statusOptions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ], $this->assignmentPayload($companyId)));
    }

    public function create(Request $request): Response
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);

        return Inertia::render('tasks/Create', array_merge([
            'statusOptions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ], $this->assignmentPayload($companyId)));
    }

    public function edit(Request $request, Task $task): Response
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);
        abort_unless($this->canEditTask($request, $task, $companyId), 403);

        return Inertia::render('tasks/Edit', array_merge([
            'task' => $task->only([
                'id',
                'title',
                'description',
                'status',
                'assigned_to_user_id',
                'assigned_to_team_id',
                'jobcard_id',
                'scheduled_start_at',
                'scheduled_end_at',
            ]),
            'statusOptions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ], $this->assignmentPayload($companyId)));
    }

    public function show(Request $request, Task $task): Response
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);
        abort_unless($this->canViewTask($request, $task, $companyId), 403);

        $task->load([
            'assignedUser:id,name',
            'assignedTeam:id,name',
            'jobcard:id,job_number,title',
            'createdBy:id,name',
        ]);

        return Inertia::render('tasks/Show', [
            'task' => $task,
            'canUpdateStatus' => $this->canUpdateTaskStatus($request, $task, $companyId),
            'statusOptions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);

        $payload = $this->validatePayload($request, $companyId, false);

        $task = Task::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'status' => 'new',
        ]);
        $this->notifyTaskAssignmentTargets($companyId, $task);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task created successfully.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);
        abort_unless($this->canViewTask($request, $task, $companyId), 403);

        $canEditTask = $this->canEditTask($request, $task, $companyId);
        $canUpdateStatusOnly = $this->canUpdateTaskStatus($request, $task, $companyId);
        abort_unless($canEditTask || $canUpdateStatusOnly, 403);

        $statusOnlyUpdate = $request->has('status')
            && ! $request->hasAny([
                'title',
                'description',
                'assigned_to_user_id',
                'assigned_to_team_id',
                'jobcard_id',
                'scheduled_start_at',
                'scheduled_end_at',
            ]);

        $payload = (! $canEditTask || $statusOnlyUpdate)
            ? $request->validate([
                'status' => ['required', Rule::in(['new', 'scheduled', 'accepted', 'completed', 'cancelled'])],
            ])
            : $this->validatePayload($request, $companyId, true);

        if ($canEditTask && ! $statusOnlyUpdate) {
            $effectiveAssignedUserId = array_key_exists('assigned_to_user_id', $payload)
                ? $payload['assigned_to_user_id']
                : $task->assigned_to_user_id;
            $effectiveAssignedTeamId = array_key_exists('assigned_to_team_id', $payload)
                ? $payload['assigned_to_team_id']
                : $task->assigned_to_team_id;

            if (! $effectiveAssignedUserId && ! $effectiveAssignedTeamId) {
                return back()->withErrors([
                    'assigned_to_user_id' => 'A task must be assigned to either a user or a team.',
                    'assigned_to_team_id' => 'A task must be assigned to either a user or a team.',
                ]);
            }
        }

        if (($payload['status'] ?? null) === 'completed' && ! $task->completed_at) {
            $payload['completed_at'] = now();
        }

        if (($payload['status'] ?? null) !== 'completed') {
            $payload['completed_at'] = null;
        }

        $task->update($payload);
        $this->notifyTaskAssignmentTargets($companyId, $task->fresh());

        return back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task deleted successfully.');
    }

    private function validatePayload(Request $request, int $companyId, bool $isUpdate): array
    {
        return $request->validate([
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Match jobcard-style assignment intent: every task must have a user OR a team.
            'assigned_to_user_id' => [
                $isUpdate ? 'sometimes' : 'nullable',
                'nullable',
                'integer',
                CompanyScopedRules::staffUser($companyId),
            ],
            'assigned_to_team_id' => [
                $isUpdate ? 'sometimes' : 'nullable',
                'nullable',
                'integer',
                CompanyScopedRules::team($companyId),
            ],
            'status' => ['nullable', Rule::in(['new', 'scheduled', 'accepted', 'completed', 'cancelled'])],
            'jobcard_id' => ['nullable', 'integer', CompanyScopedRules::jobcard($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
        ]);
    }

    private function assignmentPayload(int $companyId): array
    {
        return [
            'assignableUsers' => User::query()
                ->staffSelectableForCompany($companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'assignableTeams' => Team::query()
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'jobcards' => Jobcard::query()
                ->where('company_id', $companyId)
                ->select('id', 'job_number', 'title')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
        ];
    }

    private function notifyTaskAssignmentTargets(int $companyId, Task $task): void
    {
        $notifiableUsers = collect();

        if ($task->assigned_to_user_id) {
            $user = User::query()
                ->staffSelectableForCompany($companyId)
                ->whereKey((int) $task->assigned_to_user_id)
                ->first();
            if ($user) {
                $notifiableUsers->push($user);
            }
        }

        if ($task->assigned_to_team_id) {
            $teamUsers = Team::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $task->assigned_to_team_id)
                ->with(['users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email')])
                ->first()
                ?->users ?? collect();
            $notifiableUsers = $notifiableUsers->merge($teamUsers);
        }

        $notifiableUsers
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new AssignmentNotification('task', $task->id, $task->title ?: 'Task #'.$task->id)));
    }

    private function canListOrViewAllTasks(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->hasModulePermission('tasks', 'list') || $user?->hasModulePermission('tasks', 'view'));
    }

    private function currentUserTeamIds(Request $request, int $companyId): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        return $user->teams()
            ->where('teams.company_id', $companyId)
            ->pluck('teams.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isTaskAssignedToUser(Request $request, Task $task): bool
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if ((int) $task->assigned_to_user_id === $userId) {
            return true;
        }

        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        $teamIds = $this->currentUserTeamIds($request, $companyId);

        return (int) $task->assigned_to_user_id === 0
            && ! empty($teamIds)
            && in_array((int) $task->assigned_to_team_id, $teamIds, true);
    }

    private function canViewTask(Request $request, Task $task, int $companyId): bool
    {
        return $this->canListOrViewAllTasks($request) || $this->isTaskAssignedToUser($request, $task);
    }

    private function canEditTask(Request $request, Task $task, int $companyId): bool
    {
        $canEdit = (bool) $request->user()?->hasModulePermission('tasks', 'edit');
        if (! $canEdit) {
            return false;
        }

        if ($this->canListOrViewAllTasks($request)) {
            return true;
        }

        return $this->isTaskAssignedToUser($request, $task);
    }

    private function canUpdateTaskStatus(Request $request, Task $task, int $companyId): bool
    {
        if ($this->canEditTask($request, $task, $companyId)) {
            return true;
        }

        return $this->isTaskAssignedToUser($request, $task);
    }

    private function applyOwnAssignmentScope(Builder $query, int $currentUserId, array $teamIds): void
    {
        $query->where(function (Builder $taskScope) use ($currentUserId, $teamIds): void {
            $taskScope->where('assigned_to_user_id', $currentUserId);
            if ($teamIds !== []) {
                $taskScope->orWhere(function (Builder $teamScope) use ($teamIds): void {
                    $teamScope
                        ->whereNull('assigned_to_user_id')
                        ->whereIn('assigned_to_team_id', $teamIds);
                });
            }
        });
    }
}
