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
        ]);

        $license = License::with('customer:id,name')
            ->where('license_key', $request->input('license_key'))
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

        return response()->json([
            'valid' => true,
            'message' => 'License is valid.',
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
}
