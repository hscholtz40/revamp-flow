<?php

namespace App\Support;

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
        $pattern = trim($path, '/');

        return $pattern === '' ? '/' : $pattern;
    }
}
