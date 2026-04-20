<?php

namespace App\Http\Controllers;

use App\Models\Jobcard;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        $search = trim((string) $request->string('search', ''));
        $status = (string) $request->string('status', '');

        $tasksQuery = Task::query()
            ->where('company_id', $companyId)
            ->with(['assignedUser:id,name', 'assignedTeam:id,name', 'jobcard:id,job_number', 'createdBy:id,name'])
            ->withCount('notes');

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

        return Inertia::render('tasks/Index', array_merge([
            'tasks' => $tasksQuery
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
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

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);

        $payload = $this->validatePayload($request, $companyId, false);

        Task::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'status' => 'new',
        ]);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task created successfully.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        $payload = $this->validatePayload($request, $companyId, true);

        if (($payload['status'] ?? null) === 'completed' && ! $task->completed_at) {
            $payload['completed_at'] = now();
        }

        if (($payload['status'] ?? null) !== 'completed') {
            $payload['completed_at'] = null;
        }

        $task->update($payload);

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Task updated successfully.');
    }

    private function validatePayload(Request $request, int $companyId, bool $isUpdate): array
    {
        return $request->validate([
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Match jobcard-style assignment intent: every task must have a user OR a team.
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                'required_without:assigned_to_team_id',
                CompanyScopedRules::staffUser($companyId),
            ],
            'assigned_to_team_id' => [
                'nullable',
                'integer',
                'required_without:assigned_to_user_id',
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
}
