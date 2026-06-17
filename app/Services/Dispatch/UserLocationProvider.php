<?php

namespace App\Services\Dispatch;

use App\Models\Company;
use App\Models\LocationPing;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UserLocationProvider
{
    /**
     * Get user locations for the dispatch map.
     * Returns real GPS pings when `DISPATCH_USE_REAL_LOCATIONS=true`, otherwise falls back to test coordinates.
     *
     * @param  Collection<int, object{id: int, name: string}>  $users
     * @return array<int, array{user_id: int, name: string, lat: float, lng: float, recorded_at: string|null, recorded_at_local_display: string|null, recorded_at_ago: string|null, last_updated_label: string|null, is_test: bool}>
     */
    public function getUserLocations(Collection $users, int $companyId, ?string $displayTimezone = null): array
    {
        $displayTimezone = $this->resolveDisplayTimezone($companyId, $displayTimezone);
        if (! config('services.dispatch.use_real_locations', true)) {
            return $this->getTestLocations($users, (string) config('services.dispatch.test_user_locations', ''));
        }

        if ($companyId <= 0 || $users->isEmpty()) {
            return $this->getTestLocations($users, (string) config('services.dispatch.test_user_locations', ''));
        }

        $tz = $displayTimezone;

        $userIds = $users->pluck('id')->all();
        $windowMinutes = max(30, (int) config('services.dispatch.location_window_minutes', 480));

        $latestPings = LocationPing::query()
            ->where('company_id', $companyId)
            ->whereIn('user_id', $userIds)
            ->where('recorded_at', '>=', now()->subMinutes($windowMinutes))
            ->orderByDesc('recorded_at')
            ->get(['user_id', 'latitude', 'longitude', 'recorded_at'])
            ->unique('user_id')
            ->keyBy('user_id');

        if ($latestPings->isEmpty()) {
            return $this->getTestLocations($users, (string) config('services.dispatch.test_user_locations', ''));
        }

        return $users
            ->map(function ($user) use ($latestPings, $tz) {
                $ping = $latestPings->get((int) $user->id);
                if (! $ping) {
                    return null;
                }

                $display = $this->formatRecordedAtForDisplay($ping->recorded_at, $tz);

                return [
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'lat' => (float) $ping->latitude,
                    'lng' => (float) $ping->longitude,
                    'recorded_at' => $ping->recorded_at?->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'recorded_at_local_display' => $display['local'],
                    'recorded_at_ago' => $display['ago'],
                    'last_updated_label' => $this->buildLastUpdatedLabel($display['ago'], $display['local']),
                    'is_test' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveDisplayTimezone(int $companyId, ?string $displayTimezone): string
    {
        if ($companyId > 0) {
            $companyTimezone = Company::query()->whereKey($companyId)->value('locale_timezone');
            if (is_string($companyTimezone) && $companyTimezone !== '') {
                return $companyTimezone;
            }
        }

        if (is_string($displayTimezone) && $displayTimezone !== '') {
            return $displayTimezone;
        }

        return (string) config('app.timezone', 'UTC');
    }

    /**
     * @return array{local: string|null, ago: string|null}
     */
    private function formatRecordedAtForDisplay(?CarbonInterface $recordedAt, string $timezone): array
    {
        if (! $recordedAt) {
            return ['local' => null, 'ago' => null];
        }

        $utc = Carbon::parse($recordedAt)->utc();
        $local = $utc->copy()->timezone($timezone);

        return [
            'local' => $local->format('d/m/Y H:i'),
            'ago' => $this->formatRelativeAgo($utc, $timezone),
        ];
    }

    private function buildLastUpdatedLabel(?string $ago, ?string $local): ?string
    {
        if ($ago === null || $local === null || $ago === '' || $local === '') {
            return null;
        }

        return "Last updated {$ago} ({$local})";
    }

    private function formatRelativeAgo(CarbonInterface $utcInstant, string $timezone): string
    {
        $seconds = abs(now()->utc()->getTimestamp() - $utcInstant->getTimestamp());
        if ($seconds < 45) {
            return 'just now';
        }
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes} min ago";
        }
        $hours = (int) round($minutes / 60);
        if ($hours < 48) {
            return "{$hours} hr ago";
        }
        $days = (int) round($hours / 24);
        if ($days < 14) {
            return $days === 1 ? '1 day ago' : "{$days} days ago";
        }

        return $utcInstant->copy()->timezone($timezone)->format('d/m/Y H:i');
    }

    /**
     * Build placeholder map markers for dispatch users from env config.
     * Format: "lat,lng|lat,lng|..."
     *
     * @param  Collection<int, object{id: int, name: string}>  $users
     * @return array<int, array{user_id: int, name: string, lat: float, lng: float, recorded_at: string|null, recorded_at_local_display: string|null, recorded_at_ago: string|null, last_updated_label: string|null, is_test: bool}>
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
                'recorded_at' => null,
                'recorded_at_local_display' => null,
                'recorded_at_ago' => null,
                'last_updated_label' => null,
                'is_test' => true,
            ];
        })->all();
    }
}
