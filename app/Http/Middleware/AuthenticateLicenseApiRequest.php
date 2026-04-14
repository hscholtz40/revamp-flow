<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Support\LicenseApiSigning;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLicenseApiRequest
{
    /**
     * Authenticate machine-to-machine license API requests using HMAC signatures.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = (string) $request->header('X-License-Timestamp', '');
        $signature = (string) $request->header('X-License-Signature', '');
        $nonce = (string) $request->header('X-License-Nonce', '');

        if ($timestamp === '' || $signature === '' || $nonce === '') {
            return $this->unauthorized('Missing license API authentication headers.');
        }

        // Hash raw body before any parsed input access so the digest matches outbound json_encode bytes.
        $rawBody = (string) $request->getContent();
        $payloadHashCandidates = LicenseApiSigning::payloadHashCandidatesForRawBody($rawBody);

        $decoded = json_decode($rawBody, true);
        $licenseKey = is_array($decoded) ? (string) ($decoded['license_key'] ?? '') : '';

        if ($licenseKey === '') {
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

        if (!preg_match('/^[a-zA-Z0-9_-]{16,128}$/', $nonce)) {
            return $this->unauthorized('Invalid nonce header.');
        }

        $method = strtoupper($request->method());
        $pathCandidates = LicenseApiSigning::pathCandidatesForIncomingRequest($request);
        $signatureValid = false;
        foreach ($pathCandidates as $pathForSigning) {
            foreach ($payloadHashCandidates as $payloadHash) {
                $toSign = $timestamp . '|' . $nonce . '|' . $method . '|' . $pathForSigning . '|' . $payloadHash;
                $expectedSignature = hash_hmac('sha256', $toSign, $licenseKey);
                if (hash_equals($expectedSignature, $signature)) {
                    $signatureValid = true;
                    break 2;
                }
            }
        }

        if (! $signatureValid) {
            $primaryPayloadHash = $payloadHashCandidates[0] ?? hash('sha256', $rawBody);
            Log::warning('License API signature mismatch (no candidate path matched)', [
                'path' => $request->path(),
                'path_from_full_url' => LicenseApiSigning::pathForSignatureFromUrl($request->fullUrl()),
                'path_candidates' => $pathCandidates,
                'payload_hash_candidates' => count($payloadHashCandidates),
                'payload_hash_prefix' => substr($primaryPayloadHash, 0, 16),
            ]);

            return $this->unauthorized('Invalid request signature.');
        }

        // Ensure the license key exists so random signed requests cannot be used.
        $licenseExists = License::query()->where('license_key', $licenseKey)->exists();
        if (!$licenseExists) {
            return $this->unauthorized('License authentication failed.');
        }

        $nonceTtlSeconds = max((int) config('app.license_api_nonce_ttl', 300), 60);
        $nonceCacheKey = 'license-api-nonce:' . sha1($licenseKey . '|' . $timestamp . '|' . $nonce);
        if (!Cache::add($nonceCacheKey, true, now()->addSeconds($nonceTtlSeconds))) {
            return $this->unauthorized('Replay request detected.');
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
