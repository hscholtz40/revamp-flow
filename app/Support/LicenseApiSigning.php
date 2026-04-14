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
}
