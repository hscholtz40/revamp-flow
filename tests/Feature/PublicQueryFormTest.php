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
        'company_email' => 'hello@doeplumbing.test',
        'company_contact_number' => '0211111111',
        'company_website' => 'https://doeplumbing.test',
    ])->assertRedirect();

    $query = Query::query()->where('company_id', $company->id)->latest('id')->first();
    expect($query)->not->toBeNull()
        ->and($query->kind)->toBe('contractor')
        ->and($query->company_name)->toBe('Doe Plumbing')
        ->and($query->response)->toBe(Query::RESPONSE_PENDING);
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
