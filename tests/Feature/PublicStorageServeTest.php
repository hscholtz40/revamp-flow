<?php

use Illuminate\Support\Facades\Storage;

test('public storage route serves files from the public disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('company-logos/test-logo.png', 'logo-bytes');

    $this->get('/storage/company-logos/test-logo.png')
        ->assertOk()
        ->assertHeader('content-type');
});

test('public storage route rejects path traversal', function () {
    Storage::fake('public');

    $this->get('/storage/../.env')->assertNotFound();
});

test('public storage route returns 404 for missing files', function () {
    Storage::fake('public');

    $this->get('/storage/company-logos/missing.png')->assertNotFound();
});
