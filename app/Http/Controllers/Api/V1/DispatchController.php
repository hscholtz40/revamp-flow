<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DispatchUpdated;
use App\Http\Controllers\Controller;
use App\Models\GoogleIntegrationSettings;
use App\Models\Jobcard;
use App\Models\RoutePlan;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\AssignmentNotificationService;
use App\Services\Routing\GoogleRouteProvider;
use App\Services\Routing\HeuristicRouteProvider;
use App\Support\CompanyScopedRules;
use App\Support\JobcardStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DispatchController extends Controller
{
    public function __construct(
        private readonly HeuristicRouteProvider $heuristicRouteProvider,
        private readonly GoogleRouteProvider $googleRouteProvider
    ) {}

    public function board(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($user?->getCurrentCompany()?->id ?? 0);
        $canListOrViewTasks = (bool) ($user?->hasModulePermission('tasks', 'list') || $user?->hasModulePermission('tasks', 'view'));
        $currentUserId = (int) ($user?->id ?? 0);
        $currentUserTeamIds = $user
            ? $user->teams()->where('teams.company_id', $companyId)->pluck('teams.id')
            : collect();

        $input = $request->all();
        if (isset($input['status']) && is_string($input['status'])) {
            $input['status'] = [$input['status']];
            $request->merge($input);
        }

        $payload = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(JobcardStatuses::ALL)],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'tab' => ['nullable', 'string', Rule::in(['unscheduled', 'needs_follow_up', 'waiting_for_parts', 'emergency', 'all'])],
        ]);

        $from = $payload['from'] ?? null;
        $to = $payload['to'] ?? null;
        $day = $payload['date'] ?? null;
        $userId = $payload['assigned_to_user_id'] ?? null;
        $teamId = $payload['assigned_to_team_id'] ?? null;
        $search = isset($payload['search']) ? trim((string) $payload['search']) : '';
        $statusFilter = ! empty($payload['status']) ? array_values(array_unique($payload['status'])) : null;
        $priorityFilter = $payload['priority'] ?? null;
        $tab = $payload['tab'] ?? 'unscheduled';

        $jobcardWith = [
            'assignedUser:id,name',
            'assignedTeam:id,name',
            'customer:id,name,address,city,country',
            'contact:id,name,phone,email',
        ];

        $applyJobcardFilters = function (Builder $query) use ($userId, $teamId, $search, $statusFilter, $priorityFilter): void {
            if ($userId) {
                $query->where('assigned_to_user_id', $userId);
            }
            if ($teamId) {
                $query->where('assigned_to_team_id', $teamId);
            }
            if ($statusFilter) {
                $query->whereIn('status', $statusFilter);
            }
            if ($priorityFilter) {
                $query->where('priority', $priorityFilter);
            }
            if ($search !== '') {
                $needle = '%'.$search.'%';
                $query->where(function (Builder $q) use ($needle) {
                    $q->where('jobcards.job_number', 'like', $needle)
                        ->orWhere('jobcards.order_number', 'like', $needle)
                        ->orWhere('jobcards.title', 'like', $needle)
                        ->orWhere('jobcards.phone', 'like', $needle)
                        ->orWhere('jobcards.email', 'like', $needle)
                        ->orWhere('jobcards.service_address', 'like', $needle)
                        ->orWhereHas('customer', function (Builder $cq) use ($needle) {
                            $cq->where('customers.name', 'like', $needle)
                                ->orWhere('customers.address', 'like', $needle)
                                ->orWhere('customers.city', 'like', $needle)
                                ->orWhere('customers.phone', 'like', $needle);
                        });
                });
            }
        };

        $scheduledJobcards = collect();
        $unscheduledJobcards = collect();

        if ($day) {
            $scheduledQuery = Jobcard::query()
                ->where('company_id', $companyId)
                ->whereIn('status', JobcardStatuses::ALL)
                ->whereDate('scheduled_start_at', $day)
                ->whereNotNull('scheduled_start_at');
            $applyJobcardFilters($scheduledQuery);
            $scheduledJobcards = $scheduledQuery
                ->with($jobcardWith)
                ->orderByRaw('COALESCE(dispatch_order, 999999), scheduled_start_at')
                ->get();

            $queueQuery = Jobcard::query()
                ->where('company_id', $companyId)
                ->whereIn('status', JobcardStatuses::ALL);
            $applyJobcardFilters($queueQuery);

            if ($tab === 'unscheduled' || $tab === 'all') {
                $queueQuery->whereNull('scheduled_start_at')
                    ->whereNotIn('status', ['completed', 'cancelled']);
            } elseif ($tab === 'needs_follow_up') {
                $queueQuery->where('status', 'needs_follow_up');
            } elseif ($tab === 'waiting_for_parts') {
                $queueQuery->where('status', 'waiting_for_parts');
            } elseif ($tab === 'emergency') {
                $queueQuery->where('status', 'emergency');
            }

            $unscheduledJobcards = $queueQuery
                ->with($jobcardWith)
                ->orderByRaw('COALESCE(due_date, start_date) DESC')
                ->orderBy('job_number')
                ->get();

            $baseUsers = User::query()
                ->staffSelectableForCompany($companyId)
                ->where(function (Builder $query) {
                    $query->whereNull('user_type')
                        ->orWhere('user_type', '!=', 'info');
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $extraUserIds = $scheduledJobcards->pluck('assigned_to_user_id')->filter()->unique()->values();
            $extraUsers = $extraUserIds->isEmpty()
                ? collect()
                : User::query()
                    ->whereIn('id', $extraUserIds->all())
                    ->whereNotIn('id', $baseUsers->pluck('id')->all())
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();

            $users = $baseUsers->concat($extraUsers)->unique('id')->sortBy('name')->values();

            $jobcardsMerged = $scheduledJobcards->concat($unscheduledJobcards)->unique('id')->values();

            return response()->json([
                'date' => $day,
                'tab' => $tab,
                'scheduled_jobcards' => $scheduledJobcards,
                'unscheduled_jobcards' => $unscheduledJobcards,
                'jobcards' => $jobcardsMerged,
                'tasks' => collect(),
                'users' => $users,
                'teams' => Team::query()
                    ->where('company_id', $companyId)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'conflicts' => $this->collectConflicts($scheduledJobcards, collect()),
            ]);
        }

        $legacyJobcardsQuery = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereIn('status', JobcardStatuses::ALL)
            ->when($from && $to, fn (Builder $query) => $query->whereBetween('scheduled_start_at', [$from, $to]));
        $applyJobcardFilters($legacyJobcardsQuery);
        $jobcards = $legacyJobcardsQuery
            ->with($jobcardWith)
            ->orderByRaw('COALESCE(dispatch_order, 999999), scheduled_start_at')
            ->get();

        $tasks = Task::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['new', 'scheduled', 'accepted', 'completed', 'cancelled'])
            ->when($from && $to, fn (Builder $query) => $query->whereBetween('scheduled_start_at', [$from, $to]))
            ->when($userId, fn (Builder $query) => $query->where('assigned_to_user_id', $userId))
            ->when($teamId, fn (Builder $query) => $query->where('assigned_to_team_id', $teamId))
            ->when(! $canListOrViewTasks, function (Builder $query) use ($currentUserId, $currentUserTeamIds) {
                $query->where(function (Builder $taskScope) use ($currentUserId, $currentUserTeamIds) {
                    $taskScope->where('assigned_to_user_id', $currentUserId);
                    if ($currentUserTeamIds->isNotEmpty()) {
                        $taskScope->orWhere(function (Builder $teamScope) use ($currentUserTeamIds) {
                            $teamScope
                                ->whereNull('assigned_to_user_id')
                                ->whereIn('assigned_to_team_id', $currentUserTeamIds->all());
                        });
                    }
                });
            })
            ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
            ->orderBy('scheduled_start_at')
            ->get();

        return response()->json([
            'date' => null,
            'tab' => null,
            'scheduled_jobcards' => [],
            'unscheduled_jobcards' => [],
            'jobcards' => $jobcards,
            'tasks' => $tasks,
            'users' => User::query()
                ->staffSelectableForCompany($companyId)
                ->where(function (Builder $query) {
                    $query->whereNull('user_type')
                        ->orWhere('user_type', '!=', 'info');
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'conflicts' => $this->collectConflicts($jobcards, $tasks),
        ]);
    }

    public function routes(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);

        return response()->json(
            RoutePlan::query()
                ->where('company_id', $companyId)
                ->orderByDesc('route_date')
                ->paginate(25)
        );
    }

    public function showRoute(Request $request, RoutePlan $routePlan)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $routePlan->company_id === $companyId, 404);

        return response()->json($routePlan);
    }

    public function assignJobcard(Request $request, Jobcard $jobcard)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $jobcard->company_id === $companyId, 404);

        $this->authorize('update', $jobcard);

        $payload = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'dispatch_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(JobcardStatuses::ALL)],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $previousAssignedUserId = (int) ($jobcard->assigned_to_user_id ?? 0);
        $previousAssignedTeamId = (int) ($jobcard->assigned_to_team_id ?? 0);
        $hadSchedule = $jobcard->scheduled_start_at !== null;
        $changes = collect($payload)->only([
            'assigned_to_user_id',
            'assigned_to_team_id',
            'scheduled_start_at',
            'scheduled_end_at',
            'dispatch_order',
            'status',
            'estimated_duration_minutes',
            'priority',
        ])->all();

        if (array_key_exists('scheduled_start_at', $changes) && $changes['scheduled_start_at'] === null && $hadSchedule) {
            $changes['dispatch_order'] = null;
            if (! array_key_exists('status', $changes)) {
                $changes['status'] = 'needs_scheduling';
            }
            $changes['scheduled_end_at'] = $changes['scheduled_end_at'] ?? null;
        }

        $jobcard->update($changes);
        $freshJobcard = $jobcard->fresh();

        if ($freshJobcard && app(AssignmentNotificationService::class)->assignmentChanged(
            $previousAssignedUserId,
            $previousAssignedTeamId,
            $freshJobcard->assigned_to_user_id,
            $freshJobcard->assigned_to_team_id
        )) {
            app(AssignmentNotificationService::class)->notifyJobcardAssignment($companyId, $freshJobcard);
        }

        event(new DispatchUpdated($freshJobcard));

        return response()->json($freshJobcard->load(['assignedUser:id,name', 'assignedTeam:id,name', 'customer:id,name,address,city,country', 'contact:id,name,phone,email']));
    }

    public function assignTask(Request $request, Task $task)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $task->company_id === $companyId, 404);

        $payload = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:new,scheduled,accepted,completed,cancelled'],
        ]);

        if (($payload['status'] ?? null) === 'completed' && ! $task->completed_at) {
            $payload['completed_at'] = now();
        }

        $previousAssignedUserId = (int) ($task->assigned_to_user_id ?? 0);
        $previousAssignedTeamId = (int) ($task->assigned_to_team_id ?? 0);
        $task->update($payload);
        $freshTask = $task->fresh();
        if ($freshTask && app(AssignmentNotificationService::class)->assignmentChanged(
            $previousAssignedUserId,
            $previousAssignedTeamId,
            $freshTask->assigned_to_user_id,
            $freshTask->assigned_to_team_id
        )) {
            app(AssignmentNotificationService::class)->notifyTaskAssignment($companyId, $freshTask);
        }
        event(new DispatchUpdated($freshTask));

        return response()->json($freshTask);
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
            ->when(isset($payload['user_id']), fn (Builder $query) => $query->where('assigned_to_user_id', $payload['user_id']))
            ->orderBy('dispatch_order')
            ->with(['customer:id,address,city,country'])
            ->get(['id', 'job_number', 'title', 'customer_id', 'scheduled_start_at', 'scheduled_end_at']);

        $stops = $jobs->map(fn ($job) => [
            'jobcard_id' => $job->id,
            'job_number' => $job->job_number,
            'title' => $job->title,
            'address' => $this->resolveStopAddress($job),
            'scheduled_start_at' => $job->scheduled_start_at,
            'scheduled_end_at' => $job->scheduled_end_at,
        ])->filter(fn (array $stop) => ! empty($stop['address']))->values()->all();

        if (count($stops) === 0) {
            return response()->json([
                'message' => 'No routable jobcards found for the selected day. Add customer addresses first.',
            ], 422);
        }

        $optimizedRoute = $this->optimizeRouteWithProvider($stops, GoogleIntegrationSettings::mapsApiKey());

        $routePlan = RoutePlan::create([
            'company_id' => $companyId,
            'user_id' => $payload['user_id'] ?? null,
            'vehicle_id' => $payload['vehicle_id'] ?? null,
            'route_date' => $payload['route_date'],
            'stops' => $optimizedRoute['stops'],
            'provider' => $optimizedRoute['provider'],
            'total_distance_m' => $optimizedRoute['total_distance_m'],
            'total_duration_s' => $optimizedRoute['total_duration_s'],
        ]);

        return response()->json($routePlan, 201);
    }

    public function moveCard(Request $request, string $type, int $id)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'status' => ['required', 'string'],
        ]);

        if ($type === 'jobcard') {
            abort_unless(in_array($payload['status'], JobcardStatuses::ALL, true), 422);
            $jobcard = Jobcard::query()
                ->where('company_id', $companyId)
                ->whereKey($id)
                ->firstOrFail();
            $this->authorize('update', $jobcard);
            $jobcard->update([
                'status' => $payload['status'],
            ]);
            event(new DispatchUpdated($jobcard->fresh()));

            return response()->json($jobcard->fresh());
        }

        if ($type === 'task') {
            abort_unless(in_array($payload['status'], ['new', 'scheduled', 'accepted', 'completed', 'cancelled'], true), 422);
            $task = Task::query()
                ->where('company_id', $companyId)
                ->whereKey($id)
                ->firstOrFail();

            $changes = [
                'status' => $payload['status'],
            ];
            if ($payload['status'] === 'completed') {
                $changes['completed_at'] = $task->completed_at ?? now();
            } else {
                $changes['completed_at'] = null;
            }

            $task->update($changes);
            event(new DispatchUpdated($task->fresh()));

            return response()->json($task->fresh());
        }

        return response()->json(['message' => 'Invalid card type.'], 422);
    }

    public function rescheduleCard(Request $request, string $type, int $id)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        if ($type === 'jobcard') {
            $jobcard = Jobcard::query()
                ->where('company_id', $companyId)
                ->whereKey($id)
                ->firstOrFail();
            $this->authorize('update', $jobcard);

            $updates = [
                'scheduled_start_at' => $payload['scheduled_start_at'],
                'scheduled_end_at' => $payload['scheduled_end_at'] ?? null,
            ];
            if (array_key_exists('estimated_duration_minutes', $payload)) {
                $updates['estimated_duration_minutes'] = $payload['estimated_duration_minutes'];
            }
            $jobcard->update($updates);
            event(new DispatchUpdated($jobcard->fresh()));

            return response()->json($jobcard->fresh()->load(['assignedUser:id,name', 'assignedTeam:id,name', 'customer:id,name,address,city,country', 'contact:id,name,phone,email']));
        }

        if ($type === 'task') {
            $task = Task::query()
                ->where('company_id', $companyId)
                ->whereKey($id)
                ->firstOrFail();
            $task->update([
                'scheduled_start_at' => $payload['scheduled_start_at'],
                'scheduled_end_at' => $payload['scheduled_end_at'] ?? null,
            ]);
            event(new DispatchUpdated($task->fresh()));

            return response()->json($task->fresh());
        }

        return response()->json(['message' => 'Invalid card type.'], 422);
    }

    public function createTask(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:new,scheduled,accepted,completed,cancelled'],
            'assigned_to_user_id' => ['nullable', 'integer', 'required_without:assigned_to_team_id', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', 'required_without:assigned_to_user_id', CompanyScopedRules::team($companyId)],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
        ]);

        $task = Task::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'status' => $payload['status'] ?? 'new',
        ]);

        if ($task->status === 'completed') {
            $task->update(['completed_at' => now()]);
        }

        app(AssignmentNotificationService::class)->notifyTaskAssignment($companyId, $task);
        event(new DispatchUpdated($task->fresh()));

        return response()->json($task->fresh(), 201);
    }

    public function reorder(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'jobcard_ids' => ['required', 'array', 'min:1'],
            'jobcard_ids.*' => ['integer'],
            'assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
        ]);

        $jobcards = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $payload['jobcard_ids'])
            ->get()
            ->keyBy('id');

        abort_unless($jobcards->count() === count($payload['jobcard_ids']), 404);

        DB::transaction(function () use ($payload, $jobcards, $companyId) {
            foreach (array_values($payload['jobcard_ids']) as $index => $id) {
                $jobcard = $jobcards->get((int) $id);
                if (! $jobcard) {
                    continue;
                }

                $previousAssignedUserId = (int) ($jobcard->assigned_to_user_id ?? 0);
                $previousAssignedTeamId = (int) ($jobcard->assigned_to_team_id ?? 0);
                $jobcard->update([
                    'dispatch_order' => $index,
                    'assigned_to_user_id' => $payload['assigned_to_user_id'] ?? $jobcard->assigned_to_user_id,
                    'assigned_to_team_id' => $payload['assigned_to_team_id'] ?? $jobcard->assigned_to_team_id,
                ]);
                $freshJobcard = $jobcard->fresh();
                app(AssignmentNotificationService::class)->notifyJobcardAssignmentIfChanged(
                    $companyId,
                    $freshJobcard,
                    $previousAssignedUserId,
                    $previousAssignedTeamId
                );
                event(new DispatchUpdated($freshJobcard));
            }
        });

        return response()->json(['message' => 'Dispatch order updated']);
    }

    public function bulkUpdate(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:jobcard,task'],
            'items.*.id' => ['required', 'integer'],
            'items.*.assigned_to_user_id' => ['nullable', 'integer', CompanyScopedRules::staffUser($companyId)],
            'items.*.assigned_to_team_id' => ['nullable', 'integer', CompanyScopedRules::team($companyId)],
            'items.*.scheduled_start_at' => ['nullable', 'date'],
            'items.*.scheduled_end_at' => ['nullable', 'date'],
            'items.*.dispatch_order' => ['nullable', 'integer', 'min:0'],
            'items.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'items.*.status' => ['nullable', 'in:new,scheduled,accepted,completed,cancelled'],
        ]);

        $updated = DB::transaction(function () use ($payload, $companyId) {
            $updated = [];

            foreach ($payload['items'] as $item) {
                if ($item['type'] === 'jobcard') {
                    $record = Jobcard::query()->where('company_id', $companyId)->whereKey((int) $item['id'])->firstOrFail();
                    $previousAssignedUserId = (int) ($record->assigned_to_user_id ?? 0);
                    $previousAssignedTeamId = (int) ($record->assigned_to_team_id ?? 0);
                    $changes = collect($item)->only([
                        'assigned_to_user_id',
                        'assigned_to_team_id',
                        'scheduled_start_at',
                        'scheduled_end_at',
                        'dispatch_order',
                        'estimated_duration_minutes',
                    ])->all();
                    $record->update($changes);
                    $freshRecord = $record->fresh();
                    app(AssignmentNotificationService::class)->notifyJobcardAssignmentIfChanged(
                        $companyId,
                        $freshRecord,
                        $previousAssignedUserId,
                        $previousAssignedTeamId
                    );
                    event(new DispatchUpdated($freshRecord));
                    $updated[] = ['type' => 'jobcard', 'id' => $record->id];

                    continue;
                }

                $record = Task::query()->where('company_id', $companyId)->whereKey((int) $item['id'])->firstOrFail();
                $changes = collect($item)->only([
                    'assigned_to_user_id',
                    'assigned_to_team_id',
                    'scheduled_start_at',
                    'scheduled_end_at',
                    'status',
                ])->all();
                if (($changes['status'] ?? null) === 'completed' && ! $record->completed_at) {
                    $changes['completed_at'] = now();
                }
                $previousAssignedUserId = (int) ($record->assigned_to_user_id ?? 0);
                $previousAssignedTeamId = (int) ($record->assigned_to_team_id ?? 0);
                $record->update($changes);
                $freshRecord = $record->fresh();
                if ($freshRecord && app(AssignmentNotificationService::class)->assignmentChanged(
                    $previousAssignedUserId,
                    $previousAssignedTeamId,
                    $freshRecord->assigned_to_user_id,
                    $freshRecord->assigned_to_team_id
                )) {
                    app(AssignmentNotificationService::class)->notifyTaskAssignment($companyId, $freshRecord);
                }
                event(new DispatchUpdated($freshRecord));
                $updated[] = ['type' => 'task', 'id' => $record->id];
            }

            return $updated;
        });

        return response()->json([
            'message' => 'Dispatch items updated',
            'updated' => $updated,
        ]);
    }

    public function conflicts(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $payload['from'] ?? now()->startOfDay()->toDateTimeString();
        $to = $payload['to'] ?? now()->addDays(7)->endOfDay()->toDateTimeString();

        $jobcards = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->whereNotNull('scheduled_end_at')
            ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
            ->get();
        $tasks = Task::query()
            ->where('company_id', $companyId)
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->whereNotNull('scheduled_end_at')
            ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
            ->get();

        return response()->json([
            'conflicts' => $this->collectConflicts($jobcards, $tasks),
        ]);
    }

    public function mySchedule(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $user = $request->user();
        $payload = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $payload['from'] ?? now()->startOfDay()->toDateTimeString();
        $to = $payload['to'] ?? now()->addDays(14)->endOfDay()->toDateTimeString();
        $teamIds = $user->teams()->where('teams.company_id', $companyId)->pluck('teams.id');

        $jobcards = Jobcard::query()
            ->where('company_id', $companyId)
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->where(function (Builder $query) use ($user, $teamIds) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_team_id', $teamIds);
            })
            ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
            ->orderBy('scheduled_start_at')
            ->get();

        $tasks = Task::query()
            ->where('company_id', $companyId)
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->where(function (Builder $query) use ($user, $teamIds) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_team_id', $teamIds);
            })
            ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
            ->orderBy('scheduled_start_at')
            ->get();

        return response()->json([
            'jobcards' => $jobcards,
            'tasks' => $tasks,
            'conflicts' => $this->collectConflicts($jobcards, $tasks),
        ]);
    }

    private function collectConflicts(Collection $jobcards, Collection $tasks): array
    {
        $items = $jobcards->map(fn (Jobcard $jobcard) => [
            'type' => 'jobcard',
            'id' => $jobcard->id,
            'title' => $jobcard->title ?: ($jobcard->job_number ?: 'Jobcard #'.$jobcard->id),
            'assigned_to_user_id' => $jobcard->assigned_to_user_id,
            'assigned_to_team_id' => $jobcard->assigned_to_team_id,
            'scheduled_start_at' => optional($jobcard->scheduled_start_at)->toDateTimeString(),
            'scheduled_end_at' => optional($jobcard->scheduled_end_at)->toDateTimeString(),
        ])->merge(
            $tasks->map(fn (Task $task) => [
                'type' => 'task',
                'id' => $task->id,
                'title' => $task->title ?: 'Task #'.$task->id,
                'assigned_to_user_id' => $task->assigned_to_user_id,
                'assigned_to_team_id' => $task->assigned_to_team_id,
                'scheduled_start_at' => optional($task->scheduled_start_at)->toDateTimeString(),
                'scheduled_end_at' => optional($task->scheduled_end_at)->toDateTimeString(),
            ])
        )->filter(fn (array $item) => $item['scheduled_start_at'] && $item['scheduled_end_at'])->values();

        $conflicts = [];
        $seen = [];
        $count = $items->count();
        for ($left = 0; $left < $count; $left++) {
            $first = $items[$left];
            for ($right = $left + 1; $right < $count; $right++) {
                $second = $items[$right];
                $sharesUser = $first['assigned_to_user_id'] && $first['assigned_to_user_id'] === $second['assigned_to_user_id'];
                $sharesTeam = $first['assigned_to_team_id'] && $first['assigned_to_team_id'] === $second['assigned_to_team_id'];
                if (! $sharesUser && ! $sharesTeam) {
                    continue;
                }

                if (! ($first['scheduled_start_at'] < $second['scheduled_end_at'] && $first['scheduled_end_at'] > $second['scheduled_start_at'])) {
                    continue;
                }

                $key = $first['type'].':'.$first['id'].'|'.$second['type'].':'.$second['id'];
                if (isset($seen[$key])) {
                    continue;
                }

                $conflicts[] = [
                    'first' => $first,
                    'second' => $second,
                    'reason' => $sharesUser ? 'user_overlap' : 'team_overlap',
                ];
                $seen[$key] = true;
            }
        }

        return $conflicts;
    }

    private function resolveStopAddress(Jobcard $jobcard): ?string
    {
        $parts = array_filter([
            $jobcard->customer?->address,
            $jobcard->customer?->city,
            $jobcard->customer?->country,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stops
     * @return array{stops: array<int, array<string, mixed>>, total_distance_m: int, total_duration_s: int, provider: string}
     */
    private function optimizeRouteWithProvider(array $stops, string $googleMapsApiKey = ''): array
    {
        try {
            $optimized = $this->googleRouteProvider->optimize($stops, $googleMapsApiKey);

            return [
                ...$optimized,
                'provider' => 'google',
            ];
        } catch (\Throwable $exception) {
            report($exception);

            $fallback = $this->heuristicRouteProvider->optimize($stops);

            return [
                ...$fallback,
                'provider' => 'heuristic',
            ];
        }
    }
}
