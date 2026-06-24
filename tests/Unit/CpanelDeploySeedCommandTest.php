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

test('cpanel deploy writes push env vars for child instances', function () {
    config([
        'services.deploy.fcm_credentials_filename' => 'revampjobcard-8f5fb37b5d39.json',
        'services.push.apns_key_id' => 'XVYQRQF493',
        'services.push.apns_team_id' => '7N9338QH25',
        'services.push.apns_app_bundle_id' => 'com.revampjobcard.app',
        'services.push.apns_private_key' => 'test-apns-private-key',
        'services.push.apns_use_sandbox' => false,
    ]);

    $service = new CpanelService;
    $method = new ReflectionMethod(CpanelService::class, 'buildDeployedPushEnvVariables');
    $method->setAccessible(true);

    $subdomainRoot = '/home/jcrevamp/public_html/client1';
    $variables = $method->invoke($service, $subdomainRoot);

    expect($variables)->toMatchArray([
        'FCM_CREDENTIALS_PATH' => '/home/jcrevamp/public_html/client1/storage/app/revampjobcard-8f5fb37b5d39.json',
        'APNS_KEY_ID' => 'XVYQRQF493',
        'APNS_TEAM_ID' => '7N9338QH25',
        'APNS_APP_BUNDLE_ID' => 'com.revampjobcard.app',
        'APNS_PRIVATE_KEY' => 'test-apns-private-key',
        'APNS_USE_SANDBOX' => 'false',
    ]);
});
