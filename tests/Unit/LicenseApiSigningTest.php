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
}
