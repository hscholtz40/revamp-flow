<?php

namespace App\Http\Controllers;

use App\Models\Task;
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
        ]);
    }
}
