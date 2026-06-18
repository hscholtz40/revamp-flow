<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Collection;

class ContractorMatchingService
{
    /**
     * Find the nearest active-licensed contractors to a job location using the
     * Haversine formula against each license's stored coordinates.
     *
     * @return Collection<int, License>  Licenses (with company loaded) ordered by distance,
     *                                    one per company, with a `distance_km` attribute.
     */
    public function nearest(float $latitude, float $longitude, int $limit = 3): Collection
    {
        $haversine = '(6371 * acos('
            .'cos(radians(?)) * cos(radians(licenses.latitude)) * '
            .'cos(radians(licenses.longitude) - radians(?)) + '
            .'sin(radians(?)) * sin(radians(licenses.latitude))'
            .'))';

        return License::query()
            ->select('licenses.*')
            ->selectRaw("{$haversine} AS distance_km", [$latitude, $longitude, $latitude])
            ->where('licenses.status', 'active')
            ->whereNotNull('licenses.latitude')
            ->whereNotNull('licenses.longitude')
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->with('company')
            ->orderBy('distance_km')
            ->get()
            // One contractor per company even if it holds multiple licenses.
            ->unique('company_id')
            ->take($limit)
            ->values();
    }
}
