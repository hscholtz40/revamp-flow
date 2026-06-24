<?php

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

test('company getLogoPathForPdf returns storage path when logo exists', function () {
    Storage::fake('public');
    Storage::disk('public')->put('company-logos/test.pdf-logo.png', 'png');

    $company = new Company([
        'name' => 'PDF Logo Co',
        'logo_path' => 'company-logos/test.pdf-logo.png',
    ]);

    expect($company->getLogoPathForPdf())->toBe('/storage/company-logos/test.pdf-logo.png');
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
