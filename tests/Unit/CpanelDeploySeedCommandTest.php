<?php

use App\Services\CpanelService;

test('cpanel deploy seed command passes admin bootstrap env vars', function () {
    $service = new CpanelService;
    $method = new ReflectionMethod(CpanelService::class, 'buildSeedCommand');
    $method->setAccessible(true);

    $command = $method->invoke($service, [
        'email' => 'customer@example.com',
        'password' => 'P@ssw0rd',
        'name' => 'Acme Corp',
        'must_reset_password' => true,
    ]);

    expect($command)->toContain("ADMIN_EMAIL='customer@example.com'")
        ->and($command)->toContain("ADMIN_PASSWORD='P@ssw0rd'")
        ->and($command)->toContain("ADMIN_NAME='Acme Corp'")
        ->and($command)->toContain('ADMIN_MUST_RESET_PASSWORD=1')
        ->and($command)->toEndWith('php artisan db:seed --force');
});

test('cpanel deploy seed command falls back to plain db seed without bootstrap', function () {
    $service = new CpanelService;
    $method = new ReflectionMethod(CpanelService::class, 'buildSeedCommand');
    $method->setAccessible(true);

    expect($method->invoke($service, null))->toBe('php artisan db:seed --force');
});
