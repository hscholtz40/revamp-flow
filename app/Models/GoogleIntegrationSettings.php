<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row for instance-wide Google Maps / Places configuration (Dispatch maps, route optimization).
 */
class GoogleIntegrationSettings extends Model
{
    protected $table = 'google_integration_settings';

    protected $fillable = [
        'maps_api_key',
        'maps_map_id',
    ];

    protected $hidden = [
        'maps_api_key',
    ];

    protected function casts(): array
    {
        return [
            'maps_api_key' => 'encrypted',
        ];
    }

    /**
     * Single settings row (lazy-created).
     */
    public static function record(): self
    {
        $row = static::query()->first();
        if ($row !== null) {
            return $row;
        }

        return static::query()->create([]);
    }

    /**
     * Maps JavaScript API key for the browser and server-side Directions usage.
     */
    public static function mapsApiKey(): string
    {
        return trim((string) (static::record()->maps_api_key ?? ''));
    }

    /**
     * Map ID for Advanced Markers; falls back to config when unset in DB.
     */
    public function resolvedMapId(): string
    {
        $v = trim((string) ($this->maps_map_id ?? ''));
        if ($v !== '') {
            return $v;
        }

        return (string) config('services.google_maps.map_id', 'DEMO_MAP_ID');
    }
}
