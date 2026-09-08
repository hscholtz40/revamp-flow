<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GeoLocation
{
    /**
     * Copy common alternate location keys onto the request payload.
     */
    public static function normalizeRequest(Request $request): void
    {
        $latitude = $request->input('latitude', $request->input('lat'));
        $longitude = $request->input('longitude', $request->input('lng', $request->input('lon')));
        $accuracy = $request->input('location_accuracy', $request->input('accuracy'));

        if ($request->filled('location') && is_array($request->input('location'))) {
            $nested = $request->input('location');
            $latitude = $latitude ?? ($nested['latitude'] ?? $nested['lat'] ?? null);
            $longitude = $longitude ?? ($nested['longitude'] ?? $nested['lng'] ?? $nested['lon'] ?? null);
            $accuracy = $accuracy ?? ($nested['location_accuracy'] ?? $nested['accuracy'] ?? null);
        }

        if ($latitude !== null && ! $request->exists('latitude')) {
            $request->merge(['latitude' => $latitude]);
        }
        if ($longitude !== null && ! $request->exists('longitude')) {
            $request->merge(['longitude' => $longitude]);
        }
        if ($accuracy !== null && ! $request->exists('location_accuracy')) {
            $request->merge(['location_accuracy' => $accuracy]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validationRules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude?: float, longitude?: float, location_accuracy?: float}
     */
    public static function extract(array $payload): array
    {
        $location = [];

        if (array_key_exists('latitude', $payload) && $payload['latitude'] !== null && $payload['latitude'] !== '') {
            $location['latitude'] = (float) $payload['latitude'];
        }

        if (array_key_exists('longitude', $payload) && $payload['longitude'] !== null && $payload['longitude'] !== '') {
            $location['longitude'] = (float) $payload['longitude'];
        }

        if (array_key_exists('location_accuracy', $payload) && $payload['location_accuracy'] !== null && $payload['location_accuracy'] !== '') {
            $location['location_accuracy'] = (float) $payload['location_accuracy'];
        }

        if (isset($location['latitude']) xor isset($location['longitude'])) {
            throw ValidationException::withMessages([
                'latitude' => ['Both latitude and longitude are required when sending a location.'],
                'longitude' => ['Both latitude and longitude are required when sending a location.'],
            ]);
        }

        return $location;
    }
}
