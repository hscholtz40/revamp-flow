<?php

namespace App\Services\Dispatch;

use App\Models\LocationPing;
use Illuminate\Support\Collection;

class UserLocationProvider
{
    /**
     * Get user locations for the dispatch map.
     * Returns real GPS pings when `DISPATCH_USE_REAL_LOCATIONS=true`, otherwise falls back to test coordinates.
     *
     * @param  Collection<int, object{id: int, name: string}>  $users
     * @return array<int, array{user_id: int, name: string, lat: float, lng: float}>
     */
    public function getUserLocations(Collection $users, int $companyId): array
    {
        if (! config('services.dispatch.use_real_locations', true)) {
            return $this->getTestLocations($users, (string) config('services.dispatch.test_user_locations', ''));
        }

        $userIds = $users->pluck('id')->all();
        if ($userIds === []) {
            return [];
        }

        $latestPings = LocationPing::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $userIds)
            ->where('recorded_at', '>=', now()->subMinutes(30))
            ->select('user_id', 'latitude', 'longitude')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $pings) => $pings->sortByDesc('recorded_at')->first())
            ->filter();

        if ($latestPings->isEmpty()) {
            return $this->getTestLocations($users, (string) config('services.dispatch.test_user_locations', ''));
        }

        return $users
            ->map(function ($user) use ($latestPings) {
                $ping = $latestPings->get((int) $user->id);
                if (! $ping) {
                    return null;
                }

                return [
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'lat' => (float) $ping->latitude,
                    'lng' => (float) $ping->longitude,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build placeholder map markers for dispatch users from env config.
     * Format: "lat,lng|lat,lng|..."
     *
     * @param  Collection<int, object{id: int, name: string}>  $users
     * @return array<int, array{user_id: int, name: string, lat: float, lng: float}>
     */
    private function getTestLocations(Collection $users, string $raw): array
    {
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
