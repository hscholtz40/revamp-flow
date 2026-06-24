<?php

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

test('company getLogoPathForPdf returns base64 data uri from logo_path file', function () {
    Storage::fake('public');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Storage::disk('public')->put('company-logos/test.pdf-logo.png', $png);

    $company = new Company([
        'name' => 'PDF Logo Co',
        'logo_path' => 'company-logos/test.pdf-logo.png',
    ]);

    $logoForPdf = $company->getLogoPathForPdf();

    expect($logoForPdf)->toStartWith('data:image/png;base64,');
});

test('company getLogoPathForPdf returns null when logo file is missing', function () {
    Storage::fake('public');

    $company = new Company([
        'name' => 'PDF Logo Co',
        'logo_path' => 'company-logos/missing.png',
    ]);

    expect($company->getLogoPathForPdf())->toBeNull();
});

test('company getLogoPathForPdf returns null when logo_path is unset', function () {
    $company = new Company([
        'name' => 'PDF Logo Co',
        'logo_path' => null,
    ]);

    expect($company->getLogoPathForPdf())->toBeNull();
});
