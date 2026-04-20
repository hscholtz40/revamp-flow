<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DispatchUpdated;
use App\Models\Jobcard;
use App\Models\RoutePlan;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Services\Routing\HeuristicRouteProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(private readonly HeuristicRouteProvider $routeProvider) {}

    public function board(Request $request)
    {
        $companyId = $request->user()->getCurrentCompany()?->id;

        $jobcards = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('scheduled_start_at')
            ->get();

        $tasks = Task::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('scheduled_start_at')
            ->get();

        return response()->json([
            'jobcards' => $jobcards,
            'tasks' => $tasks,
        ]);
    }

    public function assignJobcard(Request $request, Jobcard $jobcard)
    {
        $payload = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
            'dispatch_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $jobcard->update($payload);
        if ($jobcard->assigned_to_user_id) {
            User::query()->whereKey($jobcard->assigned_to_user_id)->first()?->notify(
                new AssignmentNotification('jobcard', $jobcard->id, $jobcard->title)
            );
        }
        event(new DispatchUpdated($jobcard->fresh()));

        return response()->json($jobcard->fresh());
    }

    public function generateRoute(Request $request)
    {
        $payload = $request->validate([
            'route_date' => ['required', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        $companyId = $request->user()->getCurrentCompany()?->id;
        $jobs = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereDate('scheduled_start_at', $payload['route_date'])
            ->orderBy('dispatch_order')
            ->get(['id', 'job_number', 'scheduled_start_at', 'scheduled_end_at']);

        $stops = $jobs->map(fn ($job) => [
            'jobcard_id' => $job->id,
            'job_number' => $job->job_number,
            'scheduled_start_at' => $job->scheduled_start_at,
            'scheduled_end_at' => $job->scheduled_end_at,
        ])->values()->all();

        $optimizedRoute = $this->routeProvider->optimize($stops);

        $routePlan = RoutePlan::create([
            'company_id' => $companyId,
            'user_id' => $payload['user_id'] ?? null,
            'vehicle_id' => $payload['vehicle_id'] ?? null,
            'route_date' => $payload['route_date'],
            'stops' => $optimizedRoute['stops'],
            'provider' => 'heuristic',
            'total_distance_m' => $optimizedRoute['total_distance_m'],
            'total_duration_s' => $optimizedRoute['total_duration_s'],
        ]);

        return response()->json($routePlan, 201);
    }
}
