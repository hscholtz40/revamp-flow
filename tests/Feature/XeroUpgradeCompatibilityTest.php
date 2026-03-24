<?php

use App\Services\XeroService;
use Illuminate\Support\Facades\Cache;

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
