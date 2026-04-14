<?php

use App\Models\Quote;
use App\Services\QuoteUpsertService;

it('stores a quote through the controller', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);
    $account = coverageSeedChartOfAccount($company);

    $response = $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'title' => 'Coverage Quote',
        'status' => 'draft',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Coverage line item',
                'quantity' => 2,
                'unit_price' => 125,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $quote = Quote::query()->where('title', 'Coverage Quote')->firstOrFail();

    $response->assertRedirect(route('quotes.show', $quote));

    expect($quote->customer_id)->toBe($customer->id)
        ->and((float) $quote->total)->toBeGreaterThan(0);
});

it('updates and changes quote status', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);
    $account = coverageSeedChartOfAccount($company);
    $service = app(QuoteUpsertService::class);

    $quote = $service->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Editable Quote',
        'status' => 'draft',
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Original line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'title' => 'Updated Quote Title',
        'status' => 'sent',
        'line_groups' => [
            ['id' => 1, 'name' => 'Updated Items'],
        ],
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Updated line',
                'quantity' => 3,
                'unit_price' => 150,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
                'line_group_id' => 1,
            ],
        ],
    ])->assertRedirect(route('quotes.show', $quote));

    $this->from(route('quotes.show', $quote))
        ->actingAs($user)
        ->patch(route('quotes.update-status', $quote), [
            'status' => 'accepted',
        ])->assertRedirect(route('quotes.show', $quote));

    $quote->refresh();

    expect($quote->title)->toBe('Updated Quote Title')
        ->and($quote->status)->toBe('accepted');
});

it('deletes a quote', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);
    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Delete Me',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Delete line',
                'quantity' => 1,
                'unit_price' => 50,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->delete(route('quotes.destroy', $quote))
        ->assertRedirect(route('quotes.index'));

    expect(Quote::find($quote->id))->toBeNull();
});
