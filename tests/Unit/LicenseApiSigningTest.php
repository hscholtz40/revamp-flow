<?php

namespace Tests\Unit;

use App\Support\LicenseApiSigning;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class LicenseApiSigningTest extends TestCase
{
    public function test_path_matches_laravel_request_path_for_typical_api_url(): void
    {
        $url = 'https://license.example.com/api/licenses/validate';
        $this->assertSame('api/licenses/validate', LicenseApiSigning::pathForSignatureFromUrl($url));
    }

    public function test_trailing_slash_on_path_is_normalized_like_laravel_path(): void
    {
        $url = 'https://license.example.com/api/licenses/validate/';
        $this->assertSame('api/licenses/validate', LicenseApiSigning::pathForSignatureFromUrl($url));
    }

    public function test_root_path_is_slash(): void
    {
        $this->assertSame('/', LicenseApiSigning::pathForSignatureFromUrl('https://license.example.com/'));
    }

    public function test_path_candidates_cover_request_path_and_url_derived_paths(): void
    {
        $request = Request::create(
            'https://license.example.com/api/licenses/validate',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"license_key":"TEST","url":"https://app.test"}'
        );
        $candidates = LicenseApiSigning::pathCandidatesForIncomingRequest($request);
        $this->assertContains('api/licenses/validate', $candidates);
    }

    public function test_payload_hash_candidates_include_raw_and_reencoded_json(): void
    {
        $raw = '{"license_key":"ABC","url":"https:\/\/app.example.com"}';
        $hashes = LicenseApiSigning::payloadHashCandidatesForRawBody($raw);
        $this->assertContains(hash('sha256', $raw), $hashes);
        $canonical = json_encode(json_decode($raw, true), JSON_UNESCAPED_SLASHES);
        $this->assertIsString($canonical);
        $this->assertContains(hash('sha256', $canonical), $hashes);
        $this->assertNotSame($raw, $canonical);
    }

    public function test_payload_hash_candidates_deduplicate_identical_forms(): void
    {
        $raw = '{"license_key":"X","url":"https://a.com"}';
        $hashes = LicenseApiSigning::payloadHashCandidatesForRawBody($raw);
        $this->assertSame(count($hashes), count(array_unique($hashes)));
    }

    public function test_method_candidates_always_include_post_for_legacy_clients(): void
    {
        $request = Request::create(
            'https://license.example.com/api/licenses/validate',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"license_key":"TEST","url":"https://app.test"}'
        );
        $methods = LicenseApiSigning::methodCandidatesForIncomingRequest($request);
        $this->assertContains('POST', $methods);
    }

    public function test_path_candidates_include_decoded_ltrim_when_path_has_percent_encoding(): void
    {
        $request = Request::create(
            'https://license.example.com/api%2Flicenses%2Fvalidate',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"license_key":"TEST","url":"https://app.test"}'
        );
        $candidates = LicenseApiSigning::pathCandidatesForIncomingRequest($request);
        $this->assertContains('api/licenses/validate', $candidates);
    }
}
