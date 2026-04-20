<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DispatchUpdated;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Http\Controllers\Controller;
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
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'jobcard_id' => ['nullable', 'integer', 'exists:jobcards,id'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
        ]);

        $task = Task::create([
            ...$payload,
            'company_id' => $request->user()->getCurrentCompany()?->id,
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
        $payload = $request->validate([
            'status' => ['nullable', 'in:pending,in_progress,completed,cancelled'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
        ]);

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

    public function addNote(Request $request, Task $task)
    {
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
