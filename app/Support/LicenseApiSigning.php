<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * HMAC signing helpers for license API calls.
 *
 * The signing path must match {@see \Illuminate\Http\Request::path()} on the server
 * (trim slashes, root becomes "/").
 */
final class LicenseApiSigning
{
    /**
     * Legacy HMAC string (no nonce, no X-License-Nonce header) used by older InstanceLicenseService:
     * timestamp|POST|ltrim(parse_url path)|payloadSha256Hex
     */
    public static function legacySignaturePayload(string $timestamp, string $pathForSigning, string $payloadHashHex): string
    {
        return $timestamp . '|POST|' . $pathForSigning . '|' . $payloadHashHex;
    }

    /**
     * Path segment used in the HMAC payload, derived from the full request URL.
     *
     * Mirrors Laravel's Request::path() for the same URI path.
     */
    public static function pathForSignatureFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            $path = '/';
        }
        // Match Symfony Request::getPathInfo() (decoded path segments).
        $path = rawurldecode($path);
        $pattern = trim($path, '/');

        return $pattern === '' ? '/' : $pattern;
    }

    /**
     * Possible path strings to verify for an incoming license API request.
     *
     * Outbound clients sign using the URL they POST to; proxies, subdirectory mounts, or
     * legacy left-trim-only path logic can make that differ slightly from {@see Request::path()}.
     * We accept a signature that matches any one candidate (same payload hash and secret).
     *
     * @return list<string>
     */
    public static function pathCandidatesForIncomingRequest(Request $request): array
    {
        $fullUrl = $request->fullUrl();
        $candidates = [];

        $push = function (string $p) use (&$candidates): void {
            if (! in_array($p, $candidates, true)) {
                $candidates[] = $p;
            }
        };

        $push($request->path());
        $push(self::pathForSignatureFromUrl($fullUrl));

        $rawPath = parse_url($fullUrl, PHP_URL_PATH);
        if ($rawPath === null || $rawPath === '') {
            $rawPath = '/';
        }
        // Legacy outbound client used ltrim(path, '/') only (no trailing-slash trim, no decode).
        $legacyLtrim = ltrim($rawPath, '/');
        $push($legacyLtrim === '' ? '/' : $legacyLtrim);
        if ($legacyLtrim === '') {
            $push('');
        }

        // Legacy: ltrim after rawurldecode(parse_url path) when %XX segments differ from raw ltrim.
        $decodedPath = rawurldecode($rawPath);
        if ($decodedPath !== $rawPath) {
            $legacyLtrimDecoded = ltrim($decodedPath, '/');
            $push($legacyLtrimDecoded === '' ? '/' : $legacyLtrimDecoded);
            if ($legacyLtrimDecoded === '') {
                $push('');
            }
        }

        // REQUEST_URI / PATH_INFO can differ from fullUrl() behind proxies, rewrites, or index.php routing.
        $uriPath = parse_url((string) $request->server('REQUEST_URI', ''), PHP_URL_PATH);
        if (is_string($uriPath) && $uriPath !== '' && $uriPath !== $rawPath) {
            self::pushPathVariantsForRawRequestPath($uriPath, $push);
        }
        $pathInfo = $request->server('PATH_INFO');
        if (is_string($pathInfo) && $pathInfo !== '' && $pathInfo !== $uriPath && $pathInfo !== $rawPath) {
            self::pushPathVariantsForRawRequestPath($pathInfo, $push);
        }

        return $candidates;
    }

    /**
     * @param  callable(string): void  $push
     */
    private static function pushPathVariantsForRawRequestPath(string $path, callable $push): void
    {
        $path = $path === '' ? '/' : $path;
        $fakeUrl = 'https://license.invalid'.(str_starts_with($path, '/') ? $path : '/'.$path);
        $push(self::pathForSignatureFromUrl($fakeUrl));

        $legacyLtrim = ltrim($path, '/');
        $push($legacyLtrim === '' ? '/' : $legacyLtrim);
        if ($legacyLtrim === '') {
            $push('');
        }

        $decodedPath = rawurldecode($path);
        if ($decodedPath !== $path) {
            $legacyLtrimDecoded = ltrim($decodedPath, '/');
            $push($legacyLtrimDecoded === '' ? '/' : $legacyLtrimDecoded);
            if ($legacyLtrimDecoded === '') {
                $push('');
            }
        }
    }

    /**
     * HTTP methods to try when verifying the HMAC (legacy clients always used "POST" in the string).
     *
     * @return list<string>
     */
    public static function methodCandidatesForIncomingRequest(Request $request): array
    {
        $candidates = [];
        $push = function (string $m) use (&$candidates): void {
            $m = strtoupper($m);
            if ($m !== '' && ! in_array($m, $candidates, true)) {
                $candidates[] = $m;
            }
        };

        $push('POST');
        $push($request->method());
        // Extremely old or mistaken clients (unlikely but cheap to try).
        $push('post');

        return $candidates;
    }

    /**
     * HMAC secret variants: some instances hashed with a normalized license key string.
     *
     * @return list<string>
     */
    public static function signingSecretCandidates(string $licenseKeyFromJsonBody): array
    {
        $candidates = [];
        $push = function (string $s) use (&$candidates): void {
            if ($s !== '' && ! in_array($s, $candidates, true)) {
                $candidates[] = $s;
            }
        };

        $push($licenseKeyFromJsonBody);
        $push(trim($licenseKeyFromJsonBody));
        $push(strtoupper($licenseKeyFromJsonBody));
        $push(strtolower($licenseKeyFromJsonBody));

        return $candidates;
    }

    /**
     * SHA-256 digests of the license JSON body that an outbound client may have used when signing.
     *
     * Older instances always hash the bytes they send, but some builds used different json_encode
     * flags than the bytes on the wire (or vice versa). We try the raw body plus stable re-encodings
     * of the decoded payload so both behaviours verify.
     *
     * @return list<string> 32-byte hex sha256 values (deduplicated)
     */
    public static function payloadHashCandidatesForRawBody(string $rawBody): array
    {
        $hashes = [];
        $pushBody = function (string $body) use (&$hashes): void {
            $h = hash('sha256', $body);
            if (! in_array($h, $hashes, true)) {
                $hashes[] = $h;
            }
        };

        $pushBody($rawBody);

        if (str_starts_with($rawBody, "\xEF\xBB\xBF")) {
            $pushBody(substr($rawBody, 3));
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== $rawBody) {
            $pushBody($trimmed);
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return $hashes;
        }

        $flagSets = [
            JSON_UNESCAPED_SLASHES,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            0,
        ];
        foreach ($flagSets as $flags) {
            $encoded = json_encode($decoded, $flags);
            if ($encoded !== false) {
                $pushBody($encoded);
            }
        }

        // Alternate key orders / sorted keys (older json_encode insertion order or canonicalizers).
        if (array_key_exists('license_key', $decoded) && array_key_exists('url', $decoded)) {
            $lk = $decoded['license_key'];
            $u = $decoded['url'];
            $orderVariants = [
                ['license_key' => $lk, 'url' => $u],
                ['url' => $u, 'license_key' => $lk],
            ];
            foreach ($orderVariants as $ordered) {
                foreach ($flagSets as $flags) {
                    $encoded = json_encode($ordered, $flags);
                    if ($encoded !== false) {
                        $pushBody($encoded);
                    }
                }
            }
            $sorted = $decoded;
            ksort($sorted);
            foreach ($flagSets as $flags) {
                $encoded = json_encode($sorted, $flags);
                if ($encoded !== false) {
                    $pushBody($encoded);
                }
            }
        }

        return $hashes;
    }
}
