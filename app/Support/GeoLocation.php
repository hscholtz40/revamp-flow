<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class GeoLocation
{
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

        if (array_key_exists('latitude', $payload) && $payload['latitude'] !== null) {
            $location['latitude'] = (float) $payload['latitude'];
        }

        if (array_key_exists('longitude', $payload) && $payload['longitude'] !== null) {
            $location['longitude'] = (float) $payload['longitude'];
        }

        if (array_key_exists('location_accuracy', $payload) && $payload['location_accuracy'] !== null) {
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
