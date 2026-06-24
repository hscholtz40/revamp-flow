<?php

use App\Channels\PushChannel;
use App\Models\Device;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Services\FcmMessagingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function generateTestFcmServiceAccountCredentials(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $pem);

    return [
        'project_id' => 'test-firebase-project',
        'client_email' => 'firebase-adminsdk@test-firebase-project.iam.gserviceaccount.com',
        'private_key' => $pem,
    ];
}

test('fcm push uses http v1 api with oauth access token', function () {
    Cache::flush();

    $credentials = generateTestFcmServiceAccountCredentials();
    config([
        'services.push.fcm_project_id' => $credentials['project_id'],
        'services.push.fcm_client_email' => $credentials['client_email'],
        'services.push.fcm_private_key' => $credentials['private_key'],
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'test-fcm-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'fcm.googleapis.com/v1/projects/*/messages:send' => Http::response([
            'name' => 'projects/test-firebase-project/messages/0:123',
        ], 200),
    ]);

    $user = User::factory()->create();
    Device::create([
        'user_id' => $user->id,
        'token' => 'android-fcm-device-token',
        'platform' => 'android',
        'device_name' => 'Test Android',
        'last_active_at' => now(),
    ]);

    app(PushChannel::class)->send(
        $user,
        new AssignmentNotification('jobcard', 42, 'Test jobcard')
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && is_string($request['assertion'] ?? null)
            && $request['assertion'] !== '';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://fcm.googleapis.com/v1/projects/test-firebase-project/messages:send')
            && $request->hasHeader('Authorization', 'Bearer test-fcm-access-token')
            && ($request['message']['token'] ?? null) === 'android-fcm-device-token'
            && ($request['message']['notification']['title'] ?? null) === 'Jobcard assignment: Test jobcard'
            && ($request['message']['data']['type'] ?? null) === 'jobcard'
            && ($request['message']['data']['entity_id'] ?? null) === '42';
    });
});

test('fcm messaging service can load credentials from json file path', function () {
    $credentials = generateTestFcmServiceAccountCredentials();
    $path = storage_path('framework/testing-fcm-credentials.json');
    file_put_contents($path, json_encode($credentials, JSON_THROW_ON_ERROR));

    config([
        'services.push.fcm_credentials_path' => $path,
        'services.push.fcm_project_id' => null,
        'services.push.fcm_client_email' => null,
        'services.push.fcm_private_key' => null,
    ]);

    $service = app(FcmMessagingService::class);

    expect($service->isConfigured())->toBeTrue()
        ->and($service->credentials())->toMatchArray([
            'project_id' => 'test-firebase-project',
            'client_email' => 'firebase-adminsdk@test-firebase-project.iam.gserviceaccount.com',
        ]);

    @unlink($path);
});

test('fcm push is skipped when credentials are not configured', function () {
    config([
        'services.push.fcm_project_id' => null,
        'services.push.fcm_client_email' => null,
        'services.push.fcm_private_key' => null,
        'services.push.fcm_credentials_path' => null,
    ]);

    Http::fake();

    $user = User::factory()->create();
    Device::create([
        'user_id' => $user->id,
        'token' => 'android-fcm-device-token',
        'platform' => 'android',
        'device_name' => 'Test Android',
        'last_active_at' => now(),
    ]);

    app(PushChannel::class)->send(
        $user,
        new AssignmentNotification('jobcard', 42, 'Test jobcard')
    );

    Http::assertNothingSent();
});
