<?php

namespace App\Services;

use App\Models\InstanceLicense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstanceLicenseService
{
    public function getSettings(): InstanceLicense
    {
        return InstanceLicense::query()->firstOrCreate([], [
            'status' => 'unvalidated',
            'message' => 'License key has not been configured.',
        ]);
    }

    /**
     * @return array{
     *   valid: bool,
     *   message: string,
     *   license: array<string,mixed>|null
     * }
     */
    public function validate(bool $forceRefresh = false): array
    {
        if (config('app.is_licensing_instance')) {
            return [
                'valid' => true,
                'message' => 'Licensing instance bypasses local license validation.',
                'license' => null,
            ];
        }

        $settings = $this->getSettings();

        if (blank($settings->license_key)) {
            return [
                'valid' => false,
                'message' => 'License key is required for this instance.',
                'license' => null,
            ];
        }

        $cacheKey = $this->cacheKey($settings->license_key);
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $serverUrl = rtrim((string) config('app.license_server_url'), '/');
        $localUrl = (string) config('app.url');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('app.license_validation_timeout'))
                ->post($serverUrl . '/api/licenses/validate', [
                    'license_key' => $settings->license_key,
                    'url' => $localUrl,
                ]);

            if (!$response->ok()) {
                $result = [
                    'valid' => false,
                    'message' => 'Could not validate license with the license server.',
                    'license' => null,
                ];

                $this->persistValidationResult($settings, $result);
                Cache::put($cacheKey, $result, now()->addMinutes(5));

                return $result;
            }

            $payload = $response->json();
            $valid = (bool) data_get($payload, 'valid', false);
            $message = (string) data_get($payload, 'message', 'License validation failed.');
            $licenseData = data_get($payload, 'license');

            if (!is_array($licenseData)) {
                $licenseData = null;
            }

            // Hard safety check if server did not enforce URL matching.
            if ($valid && !$this->urlsMatch($localUrl, (string) data_get($licenseData, 'url', ''))) {
                $valid = false;
                $message = 'License URL does not match this instance URL.';
            }

            $result = [
                'valid' => $valid,
                'message' => $message,
                'license' => $licenseData,
            ];

            $this->persistValidationResult($settings, $result);
            Cache::put($cacheKey, $result, now()->addMinutes(5));

            return $result;
        } catch (\Throwable $e) {
            $result = [
                'valid' => false,
                'message' => 'Could not connect to the license server.',
                'license' => null,
            ];

            $this->persistValidationResult($settings, $result);
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;
        }
    }

    public function saveLicenseKey(string $licenseKey): InstanceLicense
    {
        $settings = $this->getSettings();
        $settings->license_key = Str::upper(trim($licenseKey));
        $settings->status = 'unvalidated';
        $settings->message = 'License key saved. Validation pending.';
        $settings->save();

        Cache::forget($this->cacheKey($settings->license_key));

        return $settings;
    }

    /**
     * @return array{standard_users:int,limited_users:int}|null
     */
    public function getUserLimits(bool $forceRefresh = false): ?array
    {
        $validation = $this->validate($forceRefresh);

        if (!$validation['valid']) {
            return null;
        }

        $license = $validation['license'];
        if (!is_array($license)) {
            return null;
        }

        return [
            'standard_users' => (int) data_get($license, 'standard_users', 0),
            'limited_users' => (int) data_get($license, 'limited_users', 0),
        ];
    }

    private function persistValidationResult(InstanceLicense $settings, array $result): void
    {
        $licenseData = $result['license'];
        $settings->status = $result['valid'] ? 'valid' : 'invalid';
        $settings->message = $result['message'];
        $settings->licensed_url = is_array($licenseData) ? (string) data_get($licenseData, 'url') : null;
        $settings->limited_users = is_array($licenseData) ? (int) data_get($licenseData, 'limited_users') : null;
        $settings->standard_users = is_array($licenseData) ? (int) data_get($licenseData, 'standard_users') : null;
        $settings->last_validated_at = now();
        $settings->save();
    }

    private function cacheKey(?string $licenseKey): string
    {
        return 'instance-license-validation:' . sha1(($licenseKey ?? '') . '|' . (string) config('app.url'));
    }

    private function urlsMatch(string $localUrl, string $licenseUrl): bool
    {
        return $this->normalizeUrl($localUrl) === $this->normalizeUrl($licenseUrl);
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $trimmed = rtrim((string) $url, '/');
        if (!str_contains($trimmed, '://')) {
            $trimmed = 'https://' . $trimmed;
        }

        $parts = parse_url($trimmed);
        if (!$parts || empty($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = isset($parts['path']) && $parts['path'] !== '/' ? rtrim((string) $parts['path'], '/') : '';

        return $host . $path;
    }
}
