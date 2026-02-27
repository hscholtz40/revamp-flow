<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLicenseApiRequest
{
    /**
     * Authenticate machine-to-machine license API requests using HMAC signatures.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $licenseKey = (string) $request->input('license_key', '');
        $timestamp = (string) $request->header('X-License-Timestamp', '');
        $signature = (string) $request->header('X-License-Signature', '');

        if ($licenseKey === '' || $timestamp === '' || $signature === '') {
            return $this->unauthorized('Missing license API authentication headers.');
        }

        if (!ctype_digit($timestamp)) {
            return $this->unauthorized('Invalid timestamp header.');
        }

        $requestTs = (int) $timestamp;
        $nowTs = now()->timestamp;
        $maxSkew = (int) config('app.license_api_max_clock_skew', 300);
        if (abs($nowTs - $requestTs) > $maxSkew) {
            return $this->unauthorized('Expired request signature.');
        }

        $payloadHash = hash('sha256', (string) $request->getContent());
        $toSign = $timestamp . '|' . strtoupper($request->method()) . '|' . $request->path() . '|' . $payloadHash;
        $expectedSignature = hash_hmac('sha256', $toSign, $licenseKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return $this->unauthorized('Invalid request signature.');
        }

        // Ensure the license key exists so random signed requests cannot be used.
        $licenseExists = License::query()->where('license_key', $licenseKey)->exists();
        if (!$licenseExists) {
            return $this->unauthorized('License authentication failed.');
        }

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'valid' => false,
            'message' => $message,
        ], 401);
    }
}
