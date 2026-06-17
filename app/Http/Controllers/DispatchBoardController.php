<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\GoogleIntegrationSettings;
use App\Models\Jobcard;
use App\Models\RoutePlan;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Dispatch\UserLocationProvider;
use App\Support\DispatchCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DispatchBoardController extends Controller
{
    private function companyTimezone(): string
    {
        $companyId = DispatchCompanyResolver::companyIdForUser(auth()->user());
        if ($companyId > 0) {
            $timezone = Company::query()->whereKey($companyId)->value('locale_timezone');
            if (is_string($timezone) && $timezone !== '') {
                return $timezone;
            }
        }

        return (string) config('app.timezone', 'UTC');
    }

    /**
     * @return Collection<int, object{id: int, name: string}>
     */
    private function dispatchUsersForCompany(int $companyId): Collection
    {
        return User::query()
            ->staffSelectableForCompany($companyId)
            ->where(function ($query) {
                $query->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'info');
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function userLocations(UserLocationProvider $userLocationProvider): JsonResponse
    {
        $companyId = DispatchCompanyResolver::companyIdForUser(auth()->user());
        $dispatchUsers = $this->dispatchUsersForCompany($companyId);

        return response()
            ->json([
                'user_locations' => $userLocationProvider->getUserLocations($dispatchUsers, $companyId, $this->companyTimezone()),
                'timezone' => $this->companyTimezone(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function index(): Response
    {
        $companyId = DispatchCompanyResolver::companyIdForUser(auth()->user());
        $dispatchUsers = $this->dispatchUsersForCompany($companyId);

        $userLocations = app(UserLocationProvider::class)->getUserLocations($dispatchUsers, $companyId, $this->companyTimezone());

        return Inertia::render('dispatch/Index', [
            'jobcards' => Jobcard::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['new', 'needs_scheduling', 'scheduled', 'dispatched', 'accepted', 'en_route', 'on_site', 'paused', 'waiting_for_parts', 'needs_follow_up', 'emergency', 'completed', 'cancelled'])
                ->with(['assignedUser:id,name', 'assignedTeam:id,name', 'customer:id,name,address,city,country', 'contact:id,name,phone,email'])
                ->orderBy('scheduled_start_at')
                ->limit(200)
                ->get(),
            'tasks' => Task::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['new', 'scheduled', 'accepted', 'completed', 'cancelled'])
                ->with(['assignedUser:id,name', 'assignedTeam:id,name'])
                ->orderBy('scheduled_start_at')
                ->limit(200)
                ->get(),
            'routes' => RoutePlan::query()
                ->where('company_id', $companyId)
                ->orderByDesc('route_date')
                ->limit(30)
                ->get(),
            'users' => $dispatchUsers,
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'google_maps_api_key' => GoogleIntegrationSettings::mapsApiKey(),
            'google_maps_map_id' => GoogleIntegrationSettings::record()->resolvedMapId(),
            'user_locations' => $userLocations,
            'company_timezone' => $this->companyTimezone(),
        ]);
    }
}
