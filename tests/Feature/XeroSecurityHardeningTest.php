<?php

use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Models\User;

test('xero callback rejects missing oauth state before token exchange', function () {
    $user = User::factory()->create();

    $response = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->get(route('xero.callback', ['code' => 'test-code']));

    $response->assertRedirect('/administration/xero-settings');
    $response->assertSessionHasErrors(['message']);
});

test('xero callback rejects invalid oauth state', function () {
    $user = User::factory()->create();

    $response = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->withSession(['xero_oauth_state' => 'expected-state'])
        ->get(route('xero.callback', ['code' => 'test-code', 'state' => 'wrong-state']));

    $response->assertRedirect('/administration/xero-settings');
    $response->assertSessionHasErrors(['message']);
});

test('xero webhook rejects invalid signature', function () {
    config(['services.xero.webhook_key' => 'test-webhook-key']);

    $payload = json_encode([
        'events' => [],
        'tenantId' => 'tenant-123',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        route('xero.webhook'),
        [],
        [],
        [],
        ['HTTP_X_XERO_SIGNATURE' => 'invalid-signature', 'CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertStatus(401);
});

test('xero webhook accepts valid signature payload shape', function () {
    config(['services.xero.webhook_key' => 'test-webhook-key']);

    $payload = json_encode([
        'events' => [],
        'tenantId' => 'tenant-123',
    ], JSON_THROW_ON_ERROR);

    $signature = base64_encode(hash_hmac('sha256', $payload, 'test-webhook-key', true));

    $response = $this->call(
        'POST',
        route('xero.webhook'),
        [],
        [],
        [],
        ['HTTP_X_XERO_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertOk();
    $response->assertJson(['status' => 'ignored']);
});
