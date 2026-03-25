<?php

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

test('xero initial sync cache keys remain stable for upgrade compatibility', function () {
    $companyId = 42;
    $module = 'invoice';

    expect(XeroService::getInitialSyncCompletedCacheKey($companyId, $module))
        ->toBe('xero_invoice_full_sync_completed_company_42');
    expect(XeroService::getInitialSyncCursorCacheKey($companyId, $module))
        ->toBe('xero_invoice_import_cursor_company_42');
    expect(XeroService::getInitialSyncPaginationCacheKey($companyId, $module))
        ->toBe('xero_invoice_import_pagination_company_42');
});

test('reset initial sync status clears all module cache markers', function () {
    $companyId = 7;
    $module = 'purchase_order';

    $completedKey = XeroService::getInitialSyncCompletedCacheKey($companyId, $module);
    $cursorKey = XeroService::getInitialSyncCursorCacheKey($companyId, $module);
    $paginationKey = XeroService::getInitialSyncPaginationCacheKey($companyId, $module);

    Cache::put($completedKey, true, now()->addMinute());
    Cache::put($cursorKey, 'cursor-value', now()->addMinute());
    Cache::put($paginationKey, ['next' => 'token'], now()->addMinute());

    XeroService::resetInitialSyncStatus($companyId, $module);

    expect(Cache::has($completedKey))->toBeFalse();
    expect(Cache::has($cursorKey))->toBeFalse();
    expect(Cache::has($paginationKey))->toBeFalse();
});

test('xero settings reads legacy plaintext oauth tokens without decrypt exception', function () {
    $jwt = 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature';

    $settings = new XeroSettings;
    $settings->setRawAttributes([
        'access_token' => $jwt,
        'refresh_token' => 'plain-refresh-token',
    ], true);

    expect($settings->access_token)->toBe($jwt);
    expect($settings->refresh_token)->toBe('plain-refresh-token');
});

test('xero settings still decrypts values stored with laravel encrypted cast', function () {
    $plain = 'secret-client-value';
    $encrypted = Crypt::encrypt($plain, false);

    $settings = new XeroSettings;
    $settings->setRawAttributes([
        'client_secret' => $encrypted,
    ], true);

    expect($settings->client_secret)->toBe($plain);
});
