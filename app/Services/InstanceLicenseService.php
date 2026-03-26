<?php

namespace App\Services;

use App\Helpers\Version;
use App\Models\InstanceLicense;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response as HttpResponse;
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

        if (!$forceRefresh && !$this->shouldRefreshValidation($settings)) {
            return $this->buildResultFromStoredSettings($settings);
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
                ->timeout((int) config('app.license_validation_timeout'));

            $payload = [
                'license_key' => $settings->license_key,
                'url' => $localUrl,
            ];

            $response = $this->signedLicenseApiPost($response, $serverUrl . '/api/licenses/validate', $payload, $settings->license_key);

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

            if ($valid) {
                $this->reportCurrentVersion($settings->license_key, $localUrl);
            }

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
        $settings->licensed_url = null;
        $settings->limited_users = null;
        $settings->standard_users = null;
        $settings->last_validated_at = null;
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

    /**
     * @return array<string,array{active:int,licensed:int}>
     */
    public function getUserLimitOverages(bool $forceRefresh = false): array
    {
        if (config('app.is_licensing_instance')) {
            return [];
        }

        $limits = $this->getUserLimits($forceRefresh);
        if (!$limits) {
            return [];
        }

        $activeStandardUsers = User::query()
            ->where(function ($query) {
                $query->where('user_type', 'standard')->orWhereNull('user_type');
            })
            ->count();

        $activeLimitedUsers = User::query()
            ->where('user_type', 'limited')
            ->count();

        $overages = [];

        if ($activeStandardUsers > $limits['standard_users']) {
            $overages['standard'] = [
                'active' => $activeStandardUsers,
                'licensed' => $limits['standard_users'],
            ];
        }

        if ($activeLimitedUsers > $limits['limited_users']) {
            $overages['limited'] = [
                'active' => $activeLimitedUsers,
                'licensed' => $limits['limited_users'],
            ];
        }

        return $overages;
    }

    public function getUserLimitRestrictionMessage(bool $forceRefresh = false): ?string
    {
        $overages = $this->getUserLimitOverages($forceRefresh);
        if (empty($overages)) {
            return null;
        }

        $parts = [];
        if (isset($overages['standard'])) {
            $parts[] = "Standard users: {$overages['standard']['active']} active, {$overages['standard']['licensed']} licensed";
        }

        if (isset($overages['limited'])) {
            $parts[] = "Limited users: {$overages['limited']['active']} active, {$overages['limited']['licensed']} licensed";
        }

        return 'User allocation exceeds the licensed limits (' . implode('; ', $parts) . '). '
            . 'Access is restricted to User Management until user types are updated to comply with your license.';
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

    private function shouldRefreshValidation(InstanceLicense $settings): bool
    {
        if ($settings->status === 'unvalidated' || !$settings->last_validated_at) {
            return true;
        }

        return $settings->last_validated_at->lte(now()->subDay());
    }

    /**
     * @return array{
     *   valid: bool,
     *   message: string,
     *   license: array<string,mixed>|null
     * }
     */
    private function buildResultFromStoredSettings(InstanceLicense $settings): array
    {
        $license = null;
        if ($settings->status === 'valid') {
            $license = [
                'url' => $settings->licensed_url,
                'limited_users' => $settings->limited_users,
                'standard_users' => $settings->standard_users,
            ];
        }

        return [
            'valid' => $settings->status === 'valid',
            'message' => $settings->message ?: 'License validation failed.',
            'license' => $license,
        ];
    }

    private function reportCurrentVersion(string $licenseKey, string $localUrl): void
    {
        $appVersion = Version::get();
        $cacheKey = 'instance-license-version-reported:' . sha1($licenseKey . '|' . $appVersion . '|' . $localUrl);

        if (Cache::get($cacheKey)) {
            return;
        }

        $serverUrl = rtrim((string) config('app.license_server_url'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('app.license_validation_timeout'));

            $payload = [
                'license_key' => $licenseKey,
                'url' => $localUrl,
                'version' => $appVersion,
            ];

            $reportResponse = $this->signedLicenseApiPost($response, $serverUrl . '/api/licenses/report-version', $payload, $licenseKey);

            if ($reportResponse->ok()) {
                Cache::put($cacheKey, true, now()->addHours(12));
            }
        } catch (\Throwable $e) {
            // Best effort only: validation should not fail because version reporting failed.
        }
    }

    private function cacheKey(?string $licenseKey): string
    {
        return 'instance-license-validation:' . sha1(($licenseKey ?? '') . '|' . (string) config('app.url'));
    }

    private function signedLicenseApiPost($client, string $url, array $payload, string $licenseKey): HttpResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{}';
        }
        $timestamp = (string) now()->timestamp;
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $payloadHash = hash('sha256', $body);
        $toSign = $timestamp . '|POST|' . ltrim($path, '/') . '|' . $payloadHash;
        $signature = hash_hmac('sha256', $toSign, $licenseKey);

        return $client
            ->withHeaders([
                'X-License-Timestamp' => $timestamp,
                'X-License-Signature' => $signature,
                'Content-Type' => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->post($url);
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
