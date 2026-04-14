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
            0,
        ];
        foreach ($flagSets as $flags) {
            $encoded = json_encode($decoded, $flags);
            if ($encoded !== false) {
                $pushBody($encoded);
            }
        }

        return $hashes;
    }
}
