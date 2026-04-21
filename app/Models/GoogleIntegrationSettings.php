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
        'openai_api_key',
        'ai_enabled',
        'ai_admin_only',
        'ai_prompt_logging_enabled',
        'ai_daily_user_limit',
        'ai_daily_company_limit',
    ];

    protected $hidden = [
        'maps_api_key',
        'openai_api_key',
    ];

    protected function casts(): array
    {
        return [
            'maps_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
            'ai_enabled' => 'boolean',
            'ai_admin_only' => 'boolean',
            'ai_prompt_logging_enabled' => 'boolean',
            'ai_daily_user_limit' => 'integer',
            'ai_daily_company_limit' => 'integer',
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

    public static function openAiApiKey(): string
    {
        return trim((string) (static::record()->openai_api_key ?? ''));
    }

    public static function aiEnabled(): bool
    {
        return (bool) (static::record()->ai_enabled ?? false);
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
