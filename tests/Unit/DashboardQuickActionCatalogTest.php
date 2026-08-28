<?php

use App\Support\DashboardQuickActionCatalog;

test('dashboard quick action catalog resolves stored preferences with defaults', function () {
    $resolved = DashboardQuickActionCatalog::resolve([
        'quote' => false,
        'product' => false,
    ]);

    expect($resolved['quote'])->toBeFalse()
        ->and($resolved['invoice'])->toBeTrue()
        ->and($resolved['product'])->toBeFalse();
});

test('dashboard quick action catalog normalizes all-default preferences to null', function () {
    $normalized = DashboardQuickActionCatalog::normalizeForStorage(DashboardQuickActionCatalog::defaults());

    expect($normalized)->toBeNull();
});

test('dashboard quick action catalog keeps non-default preferences', function () {
    $normalized = DashboardQuickActionCatalog::normalizeForStorage([
        'quote' => false,
    ]);

    expect($normalized)->toBe([
        'quote' => false,
        'invoice' => true,
        'pos' => true,
        'jobcard' => true,
        'customer' => true,
        'supplier' => true,
        'category' => true,
        'product' => true,
    ]);
});
