<?php

use App\Models\InstanceLicense;
use App\Services\InstanceLicenseService;
use Inertia\Testing\AssertableInertia as Assert;

test('client instance shares license expiry warning when expires_at is set', function () {
    config(['app.is_licensing_instance' => false]);

    InstanceLicense::query()->create([
        'license_key' => 'TEST-KEY-EXPIRY',
        'status' => 'valid',
        'message' => 'License is valid.',
        'licensed_url' => config('app.url'),
        'limited_users' => 1,
        'standard_users' => 5,
        'expires_at' => now()->addDays(12)->startOfDay(),
        'last_validated_at' => now(),
    ]);

    $this->mock(InstanceLicenseService::class, function ($mock) {
        $mock->shouldReceive('validate')->andReturn([
            'valid' => true,
            'message' => 'License is valid.',
            'license' => [
                'url' => config('app.url'),
                'limited_users' => 1,
                'standard_users' => 5,
                'expires_at' => now()->addDays(12)->toIso8601String(),
            ],
        ]);
        $mock->shouldReceive('getUserLimitRestrictionMessage')->andReturn(null);
        $mock->shouldReceive('getExpiryWarning')->andReturn([
            'expires_at' => now()->addDays(12)->toIso8601String(),
            'days_remaining' => 12,
            'message' => '12 days before your license expires',
        ]);
        $mock->shouldReceive('getSettings')->andReturn(InstanceLicense::query()->first());
    });

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'dashboard' => ['list', 'view'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('licenseExpiry.days_remaining', 12)
            ->where('licenseExpiry.message', '12 days before your license expires')
        );
});

test('client instance shares no license expiry warning when expires_at is null', function () {
    config(['app.is_licensing_instance' => false]);

    InstanceLicense::query()->create([
        'license_key' => 'TEST-KEY-NEVER',
        'status' => 'valid',
        'message' => 'License is valid.',
        'licensed_url' => config('app.url'),
        'limited_users' => 1,
        'standard_users' => 5,
        'expires_at' => null,
        'last_validated_at' => now(),
    ]);

    $this->mock(InstanceLicenseService::class, function ($mock) {
        $mock->shouldReceive('validate')->andReturn([
            'valid' => true,
            'message' => 'License is valid.',
            'license' => [
                'url' => config('app.url'),
                'limited_users' => 1,
                'standard_users' => 5,
                'expires_at' => null,
            ],
        ]);
        $mock->shouldReceive('getUserLimitRestrictionMessage')->andReturn(null);
        $mock->shouldReceive('getExpiryWarning')->andReturn(null);
        $mock->shouldReceive('getSettings')->andReturn(InstanceLicense::query()->first());
    });

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'dashboard' => ['list', 'view'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('licenseExpiry', null)
        );
});

test('licensing instance never shares license expiry warning', function () {
    config(['app.is_licensing_instance' => true]);

    InstanceLicense::query()->create([
        'license_key' => 'TEST-KEY-LICENSING',
        'status' => 'valid',
        'message' => 'License is valid.',
        'expires_at' => now()->addDays(5),
        'last_validated_at' => now(),
    ]);

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'dashboard' => ['list', 'view'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('licenseExpiry', null)
        );
});

test('instance license service builds expiry warning message from stored expires_at', function () {
    config(['app.is_licensing_instance' => false]);

    InstanceLicense::query()->create([
        'license_key' => 'TEST-KEY',
        'status' => 'valid',
        'message' => 'ok',
        'expires_at' => now()->addDays(3)->startOfDay(),
        'last_validated_at' => now(),
    ]);

    $warning = app(InstanceLicenseService::class)->getExpiryWarning();

    expect($warning)->not->toBeNull()
        ->and($warning['days_remaining'])->toBe(3)
        ->and($warning['message'])->toBe('3 days before your license expires');
});

test('instance license service returns null expiry warning when no expiry date', function () {
    config(['app.is_licensing_instance' => false]);

    InstanceLicense::query()->create([
        'license_key' => 'TEST-KEY',
        'status' => 'valid',
        'message' => 'ok',
        'expires_at' => null,
        'last_validated_at' => now(),
    ]);

    expect(app(InstanceLicenseService::class)->getExpiryWarning())->toBeNull();
});
