<?php

namespace App\Http\Controllers;

use App\Models\Jobcard;
use App\Models\RoutePlan;
use App\Models\Task;
use Inertia\Inertia;
use Inertia\Response;

class DispatchBoardController extends Controller
{
    public function index(): Response
    {
        $companyId = auth()->user()?->getCurrentCompany()?->id;

        return Inertia::render('dispatch/Index', [
            'jobcards' => Jobcard::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('scheduled_start_at')
                ->limit(200)
                ->get(),
            'tasks' => Task::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('scheduled_start_at')
                ->limit(200)
                ->get(),
            'routes' => RoutePlan::query()
                ->where('company_id', $companyId)
                ->orderByDesc('route_date')
                ->limit(30)
                ->get(),
        ]);
    }
}
