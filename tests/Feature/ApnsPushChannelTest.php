<?php

use App\Channels\PushChannel;
use App\Models\Device;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Support\Facades\Http;

function generateTestApnsPrivateKeyMaterial(): string
{
    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);

    openssl_pkey_export($resource, $pem);
    preg_match('/-----BEGIN PRIVATE KEY-----(.*?)-----END PRIVATE KEY-----/s', $pem, $matches);

    return preg_replace('/\s+/', '', $matches[1]);
}

test('apns push uses production host and http2 options', function () {
    config([
        'services.push.apns_key_id' => 'TESTKEYID',
        'services.push.apns_team_id' => 'TEAMID1234',
        'services.push.apns_app_bundle_id' => 'com.example.app',
        'services.push.apns_private_key' => generateTestApnsPrivateKeyMaterial(),
        'services.push.apns_use_sandbox' => false,
    ]);

    Http::fake([
        'api.push.apple.com/*' => Http::response('', 200),
    ]);

    $user = User::factory()->create();
    Device::create([
        'user_id' => $user->id,
        'token' => str_repeat('a', 64),
        'platform' => 'ios',
        'device_name' => 'Test iPhone',
        'last_active_at' => now(),
    ]);

    app(PushChannel::class)->send(
        $user,
        new AssignmentNotification('jobcard', 42, 'Test jobcard')
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://api.push.apple.com/3/device/')
            && $request->hasHeader('apns-topic', 'com.example.app')
            && $request->hasHeader('Authorization');
    });
});

test('apns push uses sandbox host when configured', function () {
    config([
        'services.push.apns_key_id' => 'TESTKEYID',
        'services.push.apns_team_id' => 'TEAMID1234',
        'services.push.apns_app_bundle_id' => 'com.example.app',
        'services.push.apns_private_key' => generateTestApnsPrivateKeyMaterial(),
        'services.push.apns_use_sandbox' => true,
    ]);

    Http::fake([
        'api.sandbox.push.apple.com/*' => Http::response('', 200),
    ]);

    $user = User::factory()->create();
    Device::create([
        'user_id' => $user->id,
        'token' => str_repeat('b', 64),
        'platform' => 'ios',
        'device_name' => 'Test iPhone',
        'last_active_at' => now(),
    ]);

    app(PushChannel::class)->send(
        $user,
        new AssignmentNotification('jobcard', 42, 'Test jobcard')
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://api.sandbox.push.apple.com/3/device/'));
});
