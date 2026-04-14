<?php

use App\Models\Quote;
use App\Services\QuoteUpsertService;

it('creates a quote with grouped line items and default sales account', function () {
    $company = coverageCreateCompany();
    $customer = coverageSeedCustomer($company);
    $account = coverageSeedChartOfAccount($company, [
        'account_code' => '4100',
        'account_name' => 'Default Sales',
    ]);
    $salesperson = coverageCreateUserWithPermissions($company, []);

    $service = app(QuoteUpsertService::class);

    $quote = $service->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Coverage Quote',
        'status' => 'draft',
        'line_groups' => [
            ['id' => 1, 'name' => 'Services'],
            ['id' => 2, 'name' => 'Parts'],
        ],
        'line_items' => [
            [
                'description' => 'Inspection',
                'quantity' => 2,
                'unit_price' => 150,
                'line_group_id' => 1,
            ],
            [
                'description' => 'Replacement part',
                'quantity' => 1,
                'unit_price' => 80,
                'line_group_id' => 2,
            ],
        ],
    ], $company->id, $salesperson->id);

    expect($quote)
        ->toBeInstanceOf(Quote::class)
        ->customer_id->toBe($customer->id)
        ->salesperson_id->toBe($salesperson->id);

    $quote->load(['lineGroups', 'lineItems']);

    expect($quote->lineGroups)->toHaveCount(2)
        ->and($quote->lineItems)->toHaveCount(2)
        ->and($quote->lineItems->pluck('account_id')->unique()->all())->toBe([$account->id])
        ->and((float) $quote->subtotal)->toBe(380.0)
        ->and((float) $quote->total)->toBe(380.0);
});

it('replaces quote line groups and items during update', function () {
    $company = coverageCreateCompany();
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);
    $salesperson = coverageCreateUserWithPermissions($company, []);

    $service = app(QuoteUpsertService::class);

    $quote = $service->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Original Quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Original line',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $salesperson->id);

    $updated = $service->update($quote, [
        'customer_id' => $customer->id,
        'title' => 'Updated Quote',
        'status' => 'sent',
        'line_groups' => [
            ['id' => 10, 'name' => 'Updated Items'],
        ],
        'line_items' => [
            [
                'description' => 'Updated line',
                'quantity' => 3,
                'unit_price' => 200,
                'line_group_id' => 10,
            ],
        ],
    ]);

    $updated->load(['lineGroups', 'lineItems']);

    expect($updated->title)->toBe('Updated Quote')
        ->and($updated->status)->toBe('sent')
        ->and($updated->lineGroups)->toHaveCount(1)
        ->and($updated->lineItems)->toHaveCount(1)
        ->and($updated->lineItems->first()->description)->toBe('Updated line')
        ->and((float) $updated->subtotal)->toBe(600.0)
        ->and((float) $updated->total)->toBe(600.0);
});
