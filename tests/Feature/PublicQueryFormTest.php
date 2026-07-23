<?php

use App\Models\Query;

test('public hosted query form is accessible with valid token', function () {
    $company = coverageCreateCompany();
    config(['services.query_api.public_form_key' => 'test-public-form-key']);

    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $this->get(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
    ]))->assertOk()->assertSee('Submit your enquiry');
});

test('public hosted query form shows the company logo when configured', function () {
    $company = coverageCreateCompany([
        'name' => 'Logo Co',
        'logo_path' => 'company-logos/public-form-logo.png',
    ]);
    config(['services.query_api.public_form_key' => 'test-public-form-key']);

    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $this->get(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertSee('src="/storage/company-logos/public-form-logo.png"', false)
        ->assertSee('alt="Logo Co logo"', false);
});

test('public hosted query form rejects invalid token', function () {
    $company = coverageCreateCompany();
    config(['services.query_api.public_form_key' => 'test-public-form-key']);

    $this->get(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => 'invalid',
    ]))->assertForbidden();
});

test('public hosted query form can submit enquiry', function () {
    $company = coverageCreateCompany();
    config(['services.query_api.public_form_key' => 'test-public-form-key']);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $this->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), [
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Need help with a repair',
    ])->assertRedirect();

    $query = Query::query()->where('company_id', $company->id)->first();
    expect($query)->not->toBeNull()
        ->and($query->name)->toBe('Jane')
        ->and($query->status)->toBe(Query::STATUS_OPEN);
});

test('public hosted contractor form is only available on licensing instances', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => false,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $this->get(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
        'kind' => 'contractor',
    ]))->assertForbidden();
});

test('public hosted contractor form stores contractor-specific fields', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $ck = Illuminate\Http\UploadedFile::fake()->create('company-ck.pdf', 100, 'application/pdf');
    $por = Illuminate\Http\UploadedFile::fake()->create('proof-of-residence.pdf', 100, 'application/pdf');

    $this->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), [
        'kind' => 'contractor',
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding',
        'company_name' => 'Doe Plumbing',
        'company_registration_no' => '2024/123456/07',
        'company_address' => '12 High Street',
        'company_city' => 'Cape Town',
        'company_province' => 'Western Cape',
        'company_email' => 'hello@doeplumbing.test',
        'company_contact_number' => '0211111111',
        'website_status' => 'have_website',
        'company_website' => 'https://doeplumbing.test',
        'selected_package' => 'option_1',
        'document_company_ck' => $ck,
        'document_proof_of_residence' => $por,
    ])->assertRedirect();

    $query = Query::query()->where('company_id', $company->id)->latest('id')->first();
    expect($query)->not->toBeNull()
        ->and($query->kind)->toBe('contractor')
        ->and($query->company_name)->toBe('Doe Plumbing')
        ->and($query->company_city)->toBe('Cape Town')
        ->and($query->company_province)->toBe('Western Cape')
        ->and($query->website_status)->toBe('have_website')
        ->and($query->selected_package)->toBe('option_1')
        ->and($query->response)->toBe(Query::RESPONSE_PENDING)
        ->and($query->attachments)->toHaveCount(2);
});

test('public hosted contractor form prepends http to website without scheme', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $ck = Illuminate\Http\UploadedFile::fake()->create('company-ck.pdf', 100, 'application/pdf');
    $por = Illuminate\Http\UploadedFile::fake()->create('proof-of-residence.pdf', 100, 'application/pdf');

    $this->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), [
        'kind' => 'contractor',
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding',
        'company_name' => 'Doe Plumbing',
        'company_registration_no' => '2024/123456/07',
        'company_address' => '12 High Street',
        'company_city' => 'Cape Town',
        'company_province' => 'Western Cape',
        'company_email' => 'hello@doeplumbing.test',
        'company_contact_number' => '0211111111',
        'website_status' => 'have_website',
        'company_website' => 'www.test.com',
        'selected_package' => 'option_2',
        'document_company_ck' => $ck,
        'document_proof_of_residence' => $por,
    ])->assertRedirect();

    $query = Query::query()->where('company_id', $company->id)->latest('id')->first();
    expect($query)->not->toBeNull()
        ->and($query->company_website)->toBe('http://www.test.com');
});

test('public hosted contractor form rejects invalid company contact numbers', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $ck = Illuminate\Http\UploadedFile::fake()->create('company-ck.pdf', 100, 'application/pdf');
    $por = Illuminate\Http\UploadedFile::fake()->create('proof-of-residence.pdf', 100, 'application/pdf');

    $payload = [
        'kind' => 'contractor',
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding',
        'company_name' => 'Doe Plumbing',
        'company_registration_no' => '2024/123456/07',
        'company_address' => '12 High Street',
        'company_city' => 'Cape Town',
        'company_province' => 'Western Cape',
        'company_email' => 'hello@doeplumbing.test',
        'website_status' => 'need_website',
        'selected_package' => 'custom',
        'document_company_ck' => $ck,
        'document_proof_of_residence' => $por,
    ];

    $this->from(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
        'kind' => 'contractor',
    ]))->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), $payload + ['company_contact_number' => '12345'])
        ->assertRedirect()
        ->assertSessionHasErrors('company_contact_number');

    $this->from(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
        'kind' => 'contractor',
    ]))->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), $payload + ['company_contact_number' => '0012345678'])
        ->assertRedirect()
        ->assertSessionHasErrors('company_contact_number');
});

test('public hosted contractor form normalizes +27 company contact numbers', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $ck = Illuminate\Http\UploadedFile::fake()->create('company-ck.pdf', 100, 'application/pdf');
    $por = Illuminate\Http\UploadedFile::fake()->create('proof-of-residence.pdf', 100, 'application/pdf');

    $this->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), [
        'kind' => 'contractor',
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding',
        'company_name' => 'Doe Plumbing',
        'company_registration_no' => '2024/123456/07',
        'company_address' => '12 High Street',
        'company_city' => 'Cape Town',
        'company_province' => 'Western Cape',
        'company_email' => 'hello@doeplumbing.test',
        'company_contact_number' => '+27 82 123 4567',
        'website_status' => 'need_website',
        'selected_package' => 'custom',
        'document_company_ck' => $ck,
        'document_proof_of_residence' => $por,
    ])->assertRedirect();

    $query = Query::query()->where('company_id', $company->id)->latest('id')->first();
    expect($query)->not->toBeNull()
        ->and($query->company_contact_number)->toBe('0821234567');
});

test('public hosted contractor form validation omits when kind is contractor wording', function () {
    $company = coverageCreateCompany();
    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $response = $this->from(route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $token,
        'kind' => 'contractor',
    ]))->post(route('queries.public.store', [
        'companyId' => $company->id,
        'token' => $token,
    ]), [
        'kind' => 'contractor',
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors([
            'company_registration_no' => 'The company registration no field is required.',
            'company_contact_number' => 'The company contact number field is required.',
            'website_status' => 'Please select a website option.',
            'selected_package' => 'Please select a package.',
            'document_company_ck' => 'The Company CK document is required.',
            'document_proof_of_residence' => 'The proof of residence for company document is required.',
        ]);
});

test('embed script returns iframe loader javascript with valid token', function () {
    $company = coverageCreateCompany();
    config(['services.query_api.public_form_key' => 'test-public-form-key']);
    $token = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');

    $this->get(route('queries.public.embed', [
        'company' => $company->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertSee('createElement(\'iframe\')', false);
});

test('queries index shows hosted url without query keys using app key fallback on normal instances', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'queries' => ['list', 'view'],
    ]);

    config([
        'services.query_api.public_form_key' => '',
        'services.query_api.key' => '',
        'app.is_licensing_instance' => false,
    ]);

    $this->mock(\App\Services\InstanceLicenseService::class, function ($mock) {
        $mock->shouldReceive('validate')->andReturn(['valid' => true, 'message' => null]);
        $mock->shouldReceive('getUserLimitRestrictionMessage')->andReturn(null);
    });

    $appKey = (string) config('app.key');
    expect($appKey)->not->toBe('');

    $expectedToken = hash_hmac('sha256', 'company:'.$company->id, $appKey);
    $expectedUrl = route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $expectedToken,
    ]);

    $this->actingAs($user)
        ->get(route('queries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('queries/Index')
            ->where('integration.public_url', $expectedUrl)
            ->where('integration.contractor_public_url', null)
        );
});

test('queries index includes contractor form url only on licensing instances', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'queries' => ['list', 'view'],
    ]);

    config([
        'services.query_api.public_form_key' => 'test-public-form-key',
        'app.is_licensing_instance' => true,
    ]);

    $expectedToken = hash_hmac('sha256', 'company:'.$company->id, 'test-public-form-key');
    $expectedUrl = route('queries.public.form', [
        'companyId' => $company->id,
        'token' => $expectedToken,
    ]);

    $this->actingAs($user)
        ->get(route('queries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('queries/Index')
            ->where('integration.public_url', $expectedUrl)
            ->where('integration.contractor_public_url', $expectedUrl.'?kind=contractor')
        );
});
