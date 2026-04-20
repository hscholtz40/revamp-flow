<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleRouteProvider implements RouteProviderInterface
{
    public function optimize(array $stops, ?string $googleMapsApiKey = null): array
    {
        $apiKey = $googleMapsApiKey !== null ? trim($googleMapsApiKey) : '';
        if ($apiKey === '') {
            throw new RuntimeException('Google Maps API key is not configured.');
        }

        $resolvedStops = array_values(array_filter($stops, function (array $stop): bool {
            $address = $stop['address'] ?? null;

            return is_string($address) && trim($address) !== '';
        }));

        if (count($resolvedStops) < 2) {
            throw new RuntimeException('At least two stops with addresses are required for Google route optimization.');
        }

        $origin = (string) $resolvedStops[0]['address'];
        $destination = (string) $resolvedStops[count($resolvedStops) - 1]['address'];
        $waypointStops = array_slice($resolvedStops, 1, max(0, count($resolvedStops) - 2));

        $waypoints = [];
        if ($waypointStops !== []) {
            $waypoints = array_map(
                fn (array $stop): string => (string) $stop['address'],
                $waypointStops
            );
        }

        $query = [
            'origin' => $origin,
            'destination' => $destination,
            'key' => $apiKey,
            'mode' => 'driving',
        ];
        if ($waypoints !== []) {
            $query['waypoints'] = 'optimize:true|'.implode('|', $waypoints);
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->get('https://maps.googleapis.com/maps/api/directions/json', $query);

        if (! $response->ok()) {
            throw new RuntimeException('Google Directions API request failed.');
        }

        $data = $response->json();
        if (($data['status'] ?? null) !== 'OK') {
            throw new RuntimeException('Google Directions API status: '.($data['status'] ?? 'unknown'));
        }

        $route = $data['routes'][0] ?? null;
        if (! is_array($route)) {
            throw new RuntimeException('Google Directions response did not include a route.');
        }

        $waypointOrder = $route['waypoint_order'] ?? [];
        $orderedWaypointStops = [];
        foreach ($waypointOrder as $index) {
            if (isset($waypointStops[$index])) {
                $orderedWaypointStops[] = $waypointStops[$index];
            }
        }

        $orderedStops = [$resolvedStops[0], ...$orderedWaypointStops, $resolvedStops[count($resolvedStops) - 1]];

        $totalDistance = 0;
        $totalDuration = 0;
        foreach (($route['legs'] ?? []) as $leg) {
            $totalDistance += (int) data_get($leg, 'distance.value', 0);
            $totalDuration += (int) data_get($leg, 'duration.value', 0);
        }

        return [
            'stops' => array_map(fn (array $stop) => [
                'jobcard_id' => $stop['jobcard_id'] ?? null,
                'job_number' => $stop['job_number'] ?? null,
                'title' => $stop['title'] ?? null,
                'address' => $stop['address'] ?? null,
                'scheduled_start_at' => $stop['scheduled_start_at'] ?? null,
                'scheduled_end_at' => $stop['scheduled_end_at'] ?? null,
            ], $orderedStops),
            'total_distance_m' => $totalDistance,
            'total_duration_s' => $totalDuration,
        ];
    }
}
