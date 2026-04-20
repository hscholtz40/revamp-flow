<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DispatchUpdated;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Support\CompanyScopedRules;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->getCurrentCompany()?->id;

        return response()->json(
            Task::query()
                ->where('company_id', $companyId)
                ->with(['assignedUser:id,name', 'assignedTeam:id,name', 'notes.user:id,name'])
                ->orderByDesc('id')
                ->paginate(25)
        );
    }

    public function store(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'required_without:assigned_to_team_id', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', 'required_without:assigned_to_user_id', CompanyScopedRules::team($companyId)],
            'jobcard_id' => ['nullable', 'integer', CompanyScopedRules::jobcard($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
        ]);

        $task = Task::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        if ($task->assigned_to_user_id) {
            User::query()->whereKey($task->assigned_to_user_id)->first()?->notify(
                new AssignmentNotification('task', $task->id, $task->title)
            );
        }

        event(new DispatchUpdated($task));

        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        $payload = $request->validate([
            'status' => ['nullable', 'in:new,scheduled,accepted,completed,cancelled'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
        ]);

        $effectiveAssignedUserId = array_key_exists('assigned_to_user_id', $payload)
            ? $payload['assigned_to_user_id']
            : $task->assigned_to_user_id;
        $effectiveAssignedTeamId = array_key_exists('assigned_to_team_id', $payload)
            ? $payload['assigned_to_team_id']
            : $task->assigned_to_team_id;

        if (! $effectiveAssignedUserId && ! $effectiveAssignedTeamId) {
            return response()->json([
                'message' => 'A task must be assigned to either a user or a team.',
                'errors' => [
                    'assigned_to_user_id' => ['A task must be assigned to either a user or a team.'],
                    'assigned_to_team_id' => ['A task must be assigned to either a user or a team.'],
                ],
            ], 422);
        }

        if (($payload['status'] ?? null) === 'completed' && ! $task->completed_at) {
            $payload['completed_at'] = now();
        }

        $task->update($payload);

        if ($task->assigned_to_user_id) {
            User::query()->whereKey($task->assigned_to_user_id)->first()?->notify(
                new AssignmentNotification('task', $task->id, $task->title)
            );
        }

        event(new DispatchUpdated($task->fresh()));

        return response()->json($task->fresh());
    }

    public function show(Request $request, Task $task)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        return response()->json($task->load(['assignedUser:id,name', 'assignedTeam:id,name', 'notes.user:id,name']));
    }

    public function destroy(Request $request, Task $task)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);
        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    public function addNote(Request $request, Task $task)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        $payload = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $note = TaskNote::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'note' => $payload['note'],
        ]);

        return response()->json($note->load('user:id,name'), 201);
    }
}
