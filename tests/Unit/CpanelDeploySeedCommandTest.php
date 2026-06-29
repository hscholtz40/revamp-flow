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
        'FCM_PROJECT_ID' => '',
        'FCM_CLIENT_EMAIL' => '',
        'FCM_PRIVATE_KEY' => '',
        'APNS_KEY_ID' => 'XVYQRQF493',
        'APNS_TEAM_ID' => '7N9338QH25',
        'APNS_APP_BUNDLE_ID' => 'com.revampjobcard.app',
        'APNS_PRIVATE_KEY' => 'test-apns-private-key',
        'APNS_USE_SANDBOX' => 'false',
    ]);
});

test('cpanel upgrade env backfill replaces empty and package placeholder values', function () {
    $service = new CpanelService;
    $method = new ReflectionMethod(CpanelService::class, 'buildAppendMissingEnvCommands');
    $method->setAccessible(true);

    $commands = $method->invoke($service, '/home/jcrevamp/public_html/client1', [
        'MAIL_HOST' => 'mail.nexorasoftware.co.za',
        'FCM_CREDENTIALS_PATH' => '/home/jcrevamp/public_html/client1/storage/app/revampjobcard-8f5fb37b5d39.json',
    ]);

    expect($commands)->toHaveCount(2)
        ->and($commands[0])->toContain('SHOULD_SET=1')
        ->and($commands[0])->toContain("MAIL_HOST=mail.nexorasoftware.co.za")
        ->and($commands[0])->toContain("CURRENT=\$(grep -m1 '^MAIL_HOST='")
        ->and($commands[1])->toContain('FCM_CREDENTIALS_PATH=');
});

test('cpanel deploy mail env builder copies licensing server mail defaults', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'mail.nexorasoftware.co.za',
        'mail.mailers.smtp.port' => 465,
        'mail.mailers.smtp.username' => 'mailer@example.com',
        'mail.mailers.smtp.password' => 'secret',
        'mail.mailers.smtp.encryption' => 'tls',
        'mail.from.address' => 'no-reply@nexorasoftware.co.za',
        'mail.from.name' => 'JobCard Online',
    ]);

    $service = new CpanelService;
    $method = new ReflectionMethod(CpanelService::class, 'buildDeployedMailEnvVariables');
    $method->setAccessible(true);

    expect($method->invoke($service))->toMatchArray([
        'MAIL_MAILER' => 'smtp',
        'MAIL_HOST' => 'mail.nexorasoftware.co.za',
        'MAIL_PORT' => '465',
        'MAIL_USERNAME' => 'mailer@example.com',
        'MAIL_PASSWORD' => 'secret',
        'MAIL_ENCRYPTION' => 'tls',
        'MAIL_FROM_ADDRESS' => 'no-reply@nexorasoftware.co.za',
        'MAIL_FROM_NAME' => 'JobCard Online',
    ]);
});
