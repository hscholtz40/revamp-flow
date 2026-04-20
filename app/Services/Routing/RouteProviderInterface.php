<?php

namespace App\Services\Routing;

interface RouteProviderInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $stops
     * @return array{stops: array<int, array<string, mixed>>, total_distance_m: int, total_duration_s: int}
     */
    public function optimize(array $stops, ?string $googleMapsApiKey = null): array;
}
