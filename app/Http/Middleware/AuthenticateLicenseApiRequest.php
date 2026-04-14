<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Support\LicenseApiSigning;
use App\Support\SafeLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $legacyNonceless = ($nonce === '');

        if ($timestamp === '' || $signature === '') {
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

        if (! ctype_digit($timestamp)) {
            return $this->unauthorized('Invalid timestamp header.');
        }

        $requestTs = (int) $timestamp;
        $nowTs = now()->timestamp;
        $maxSkew = (int) config('app.license_api_max_clock_skew', 300);
        if (abs($nowTs - $requestTs) > $maxSkew) {
            return $this->unauthorized('Expired request signature.');
        }

        if (! $legacyNonceless && ! preg_match('/^[a-zA-Z0-9_-]{16,128}$/', $nonce)) {
            return $this->unauthorized('Invalid nonce header.');
        }

        $signingSecrets = LicenseApiSigning::signingSecretCandidates($licenseKey);
        $pathCandidates = LicenseApiSigning::pathCandidatesForIncomingRequest($request);
        $signatureValid = false;

        if ($legacyNonceless) {
            // Older InstanceLicenseService: hash_hmac(sha256, timestamp|POST|ltrim(path)|payloadHash, key) — no nonce.
            foreach ($signingSecrets as $signingSecret) {
                foreach ($pathCandidates as $pathForSigning) {
                    foreach ($payloadHashCandidates as $payloadHash) {
                        $toSign = LicenseApiSigning::legacySignaturePayload($timestamp, $pathForSigning, $payloadHash);
                        $expectedSignature = hash_hmac('sha256', $toSign, $signingSecret);
                        if (hash_equals($expectedSignature, $signature)) {
                            $signatureValid = true;
                            break 3;
                        }
                    }
                }
            }
        } else {
            $methodCandidates = LicenseApiSigning::methodCandidatesForIncomingRequest($request);
            foreach ($signingSecrets as $signingSecret) {
                foreach ($methodCandidates as $methodForSigning) {
                    foreach ($pathCandidates as $pathForSigning) {
                        foreach ($payloadHashCandidates as $payloadHash) {
                            $toSign = $timestamp . '|' . $nonce . '|' . $methodForSigning . '|' . $pathForSigning . '|' . $payloadHash;
                            $expectedSignature = hash_hmac('sha256', $toSign, $signingSecret);
                            if (hash_equals($expectedSignature, $signature)) {
                                $signatureValid = true;
                                break 4;
                            }
                        }
                    }
                }
            }
        }

        if (! $signatureValid) {
            $primaryPayloadHash = $payloadHashCandidates[0] ?? hash('sha256', $rawBody);
            Log::channel('license')->warning('License API signature mismatch (no candidate matched)', SafeLog::redactContext(array_merge(
                $this->diagnosticContext($request, $rawBody),
                [
                    'legacy_nonceless_client' => $legacyNonceless,
                    'path_from_full_url' => LicenseApiSigning::pathForSignatureFromUrl($request->fullUrl()),
                    'signing_secret_candidates' => count($signingSecrets),
                    'path_candidates' => $pathCandidates,
                    'payload_hash_candidates' => count($payloadHashCandidates),
                    'payload_hmac_primary_prefix' => substr($primaryPayloadHash, 0, 16),
                ]
            )));

            return $this->unauthorized('Invalid request signature.');
        }

        // Ensure the license key exists so random signed requests cannot be used.
        // Match case-insensitively: HMAC may have been computed with a different casing than stored in DB.
        $licenseExists = License::query()
            ->whereRaw('LOWER(license_key) = ?', [strtolower($licenseKey)])
            ->exists();
        if (! $licenseExists) {
            Log::channel('license')->warning('License API HMAC valid but license_key not found in database', SafeLog::redactContext([
                'license_key_length' => strlen($licenseKey),
                'client_ip' => $request->ip(),
            ]));

            return $this->unauthorized('License authentication failed.');
        }

        $nonceTtlSeconds = max((int) config('app.license_api_nonce_ttl', 300), 60);

        if ($legacyNonceless) {
            $replayKey = 'license-api-legacy-replay:' . sha1(strtolower($licenseKey) . '|' . $timestamp . '|' . hash('sha256', $rawBody));
            if (! Cache::add($replayKey, true, now()->addSeconds($nonceTtlSeconds))) {
                Log::channel('license')->notice('License API legacy replay rejected', [
                    'client_ip' => $request->ip(),
                ]);

                return $this->unauthorized('Replay request detected.');
            }
        } else {
            $nonceCacheKey = 'license-api-nonce:' . sha1(strtolower($licenseKey) . '|' . $timestamp . '|' . $nonce);
            if (! Cache::add($nonceCacheKey, true, now()->addSeconds($nonceTtlSeconds))) {
                Log::channel('license')->notice('License API replay nonce rejected', [
                    'client_ip' => $request->ip(),
                ]);

                return $this->unauthorized('Replay request detected.');
            }
        }

        if (config('app.license_api_debug_log')) {
            Log::channel('license')->info('License API request authenticated', SafeLog::redactContext(array_merge(
                $this->diagnosticContext($request, $rawBody),
                ['legacy_nonceless_client' => $legacyNonceless]
            )));
        }

        return $next($request);
    }

    /**
     * Safe, high-signal fields for comparing old vs new client behaviour (license_key value is redacted).
     *
     * @return array<string, mixed>
     */
    private function diagnosticContext(Request $request, string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        $keyOrder = is_array($decoded) ? array_keys($decoded) : null;
        $licenseKeyLen = is_array($decoded) && isset($decoded['license_key'])
            ? strlen((string) $decoded['license_key'])
            : null;

        $redactedBody = preg_replace('/"license_key"\s*:\s*"[^"]*"/i', '"license_key":"[REDACTED]"', $rawBody);
        if (! is_string($redactedBody)) {
            $redactedBody = $rawBody;
        }
        $redactedBody = preg_replace('/"url"\s*:\s*"[^"]*"/i', '"url":"[REDACTED]"', $redactedBody);
        if (! is_string($redactedBody)) {
            $redactedBody = $rawBody;
        }

        return [
            'client_ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 240),
            'http_method' => $request->method(),
            'request_uri' => $request->server('REQUEST_URI'),
            'path_info' => $request->server('PATH_INFO'),
            'script_name' => $request->server('SCRIPT_NAME'),
            'laravel_path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'query_string' => $request->getQueryString(),
            'raw_body_length_bytes' => strlen($rawBody),
            'raw_body_sha256_hex' => hash('sha256', $rawBody),
            'json_key_order' => $keyOrder,
            'license_key_length' => $licenseKeyLen,
            'content_type' => $request->header('Content-Type'),
            'body_redacted_excerpt' => Str::limit($redactedBody, 2000),
            'x_license_timestamp' => $request->header('X-License-Timestamp'),
            'x_license_nonce_length' => strlen((string) $request->header('X-License-Nonce', '')),
            'x_license_signature_prefix' => Str::limit((string) $request->header('X-License-Signature', ''), 32),
        ];
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'valid' => false,
            'message' => $message,
        ], 401);
    }
}
