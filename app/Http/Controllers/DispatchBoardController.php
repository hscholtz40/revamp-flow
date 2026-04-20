<?php

namespace App\Http\Controllers;

use App\Models\GoogleIntegrationSettings;
use App\Models\Jobcard;
use App\Models\RoutePlan;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DispatchBoardController extends Controller
{
    public function index(): Response
    {
        $companyId = auth()->user()?->getCurrentCompany()?->id;
        $dispatchUsers = User::query()
            ->staffSelectableForCompany((int) $companyId)
            ->where(function ($query) {
                $query->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'info');
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

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
            'user_locations' => $this->buildDispatchUserLocations($dispatchUsers),
        ]);
    }

    /**
     * Build placeholder map markers for dispatch users from env config.
     *
     * Format: "lat,lng|lat,lng|..."
     */
    private function buildDispatchUserLocations(Collection $users): array
    {
        $raw = (string) config('services.dispatch.test_user_locations', '');
        if ($raw === '') {
            return [];
        }

        $coordinates = collect(explode('|', $raw))
            ->map(fn (string $entry) => array_map('trim', explode(',', $entry)))
            ->filter(fn (array $parts) => count($parts) === 2)
            ->map(function (array $parts) {
                $lat = is_numeric($parts[0]) ? (float) $parts[0] : null;
                $lng = is_numeric($parts[1]) ? (float) $parts[1] : null;
                if ($lat === null || $lng === null) {
                    return null;
                }
                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    return null;
                }

                return ['lat' => $lat, 'lng' => $lng];
            })
            ->filter()
            ->values();

        if ($coordinates->isEmpty()) {
            return [];
        }

        return $users->values()->map(function ($user, int $index) use ($coordinates) {
            $point = $coordinates->get($index % $coordinates->count());

            return [
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'lat' => $point['lat'],
                'lng' => $point['lng'],
            ];
        })->all();
    }
}
