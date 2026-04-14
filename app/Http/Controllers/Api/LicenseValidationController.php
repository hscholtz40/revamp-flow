<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseValidationController extends Controller
{
    /**
     * Validate a license key.
     *
     * This endpoint is used by other instances of the app to verify
     * that a license key is valid and retrieve its entitlements.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => ['required', 'string'],
            // APP_URL / canonical site URL can exceed 255 characters (paths, ports, subpaths).
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $license = License::with('customer:id,name')
            ->whereRaw('LOWER(license_key) = ?', [strtolower((string) $request->input('license_key'))])
            ->first();

        if (!$license) {
            return response()->json([
                'valid' => false,
                'message' => 'License key not found.',
            ], 404);
        }

        // Check if expired
        if ($license->expires_at && $license->expires_at->isPast()) {
            if ($license->status === 'active') {
                $license->update(['status' => 'expired']);
            }

            return response()->json([
                'valid' => false,
                'message' => 'License has expired.',
                'license' => $this->formatLicense($license),
            ], 200);
        }

        if ($license->status !== 'active') {
            return response()->json([
                'valid' => false,
                'message' => 'License is ' . $license->status . '.',
                'license' => $this->formatLicense($license),
            ], 200);
        }

        $requestUrl = $request->input('url');
        if ($requestUrl && !$this->urlsMatch($requestUrl, (string) $license->url)) {
            return response()->json([
                'valid' => false,
                'message' => 'License URL does not match this instance URL.',
                'license' => $this->formatLicense($license),
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'License is valid.',
            'license' => $this->formatLicense($license),
        ], 200);
    }

    /**
     * Report the current instance version to the licensing server.
     */
    public function reportVersion(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => ['required', 'string'],
            'url' => ['required', 'string', 'max:2048'],
            'version' => ['required', 'string', 'max:64'],
        ]);

        $license = License::query()
            ->whereRaw('LOWER(license_key) = ?', [strtolower((string) $request->input('license_key'))])
            ->first();

        if (!$license) {
            return response()->json([
                'valid' => false,
                'message' => 'License key not found.',
            ], 404);
        }

        if (!$this->urlsMatch((string) $request->input('url'), (string) $license->url)) {
            return response()->json([
                'valid' => false,
                'message' => 'License URL does not match this instance URL.',
            ], 200);
        }

        $license->version = (string) $request->input('version');
        $license->save();

        return response()->json([
            'valid' => true,
            'message' => 'Version reported successfully.',
            'license' => $this->formatLicense($license),
        ], 200);
    }

    /**
     * Format license data for the API response.
     */
    private function formatLicense(License $license): array
    {
        return [
            'license_key' => $license->license_key,
            'status' => $license->status,
            'limited_users' => $license->limited_users,
            'standard_users' => $license->standard_users,
            'customer' => $license->customer ? $license->customer->name : null,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'url' => $license->url,
        ];
    }

    private function urlsMatch(string $requestUrl, string $licenseUrl): bool
    {
        return $this->normalizeUrl($requestUrl) === $this->normalizeUrl($licenseUrl);
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
