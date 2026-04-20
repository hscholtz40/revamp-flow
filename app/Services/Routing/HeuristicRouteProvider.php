<?php

namespace App\Services\Routing;

class HeuristicRouteProvider implements RouteProviderInterface
{
    public function optimize(array $stops, ?string $googleMapsApiKey = null): array
    {
        $ordered = array_values($stops);

        return [
            'stops' => $ordered,
            'total_distance_m' => count($ordered) * 3000,
            'total_duration_s' => count($ordered) * 900,
        ];
    }
}
